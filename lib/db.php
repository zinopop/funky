<?php

use pool\connectionPool;
use pool\coroutineMySQLConnector;

class db
{

    private static $pool          = [];
    public         $affected_rows = 0;
    private        $request;
    private        $key;

    public function __construct(request $request = null, $key = 'default')
    {
        $this->request = $request;
        $this->key     = $key;
    }

    public static function init()
    {
        $config = config::get('db');
        foreach ($config as $key => $row) {
            self::$pool[$key] = new connectionPool([
                'minActive'         => $row['min'],
                'maxActive'         => $row['max'],
                'maxWaitTime'       => 5,
                'maxIdleTime'       => 30,
                'idleCheckInterval' => 10,
            ], new coroutineMySQLConnector(), [
                'host'        => $row['host'],
                'port'        => 3306,
                'user'        => $row['user'],
                'password'    => $row['password'],
                'database'    => $row['database'],
                'timeout'     => 10,
                'charset'     => 'utf8mb4',
                'strict_type' => true,
                'fetch_mode'  => false,
            ]);
            self::$pool[$key]->init();
        }
    }

    public static function close()
    {
        foreach (self::$pool as $pool) {
            $pool->close();
        }
    }

    public function field($field, $sql, $params = [])
    {
        $row = $this->row($sql, $params);
        return $row[$field];
    }

    public function row($sql, $params = [])
    {
        $res = $this->query($sql, $params);
        return $res[0];
    }

    public function query($sql, $params = [])
    {
        if (!is_array($params)) {
            $params = [$params];
        }
        $pool  = db::$pool[$this->key];
        $conn  = $pool->borrow();
        $state = $conn->prepare($sql);
        if (!$state) {
            if ($conn->errno == 2006 || $conn->errno == 2013) {
                $conn  = $pool->gcConnection($conn);
                $state = $conn->prepare($sql);
            } else {
                throw new Exception($conn->error);
            }
        }
        $res = $state->execute($params);
        if ($res === false) {
            throw new Exception($conn->error);
        }
        $this->affected_rows = $conn->affected_rows;
        db::$pool[$this->key]->return($conn);
        return $res;
    }

    public function getConn()
    {
        return db::$pool[$this->key]->borrow();
    }

    public function returnConn($conn)
    {
        db::$pool[$this->key]->return($conn);
    }

    public function fieldArray($field, $sql, $params = [])
    {
        $list = $this->query($sql, $params);
        $res  = [];
        foreach ($list as $row) {
            $res[] = $row[$field];
        }
        return $res;
    }

    public function insert($table, $data, $build_sql = false)
    {
        if (!$data['id']) {
            $data['id'] = functions::uuid();
        }

        $columns = [];
        $values  = [];
        $params  = [];
        foreach ($data as $k => $v) {
            if ($v === '') {
                $v = null;
            }
            $columns[] = "`{$k}`";
            $values[]  = "?";
            $params[]  = $v;
        }
        $columns = implode(', ', $columns);
        $values  = implode(', ', $values);

        if ($build_sql) {
            return ["insert into `{$table}`({$columns}) values({$values})", $params];
        }

        if (!$this->execute("insert into `{$table}`({$columns}) values({$values})", $params)) {
            return null;
        }
        return $data['id'];
    }

    public function execute($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->affected_rows;
    }

    public function insertAll($table, $datas)
    {
        $columns = [];
        $values  = [];
        $params  = [];
        foreach ($datas as $i => $data) {
            $data['id'] = functions::uuid();
            $values_row = [];
            foreach ($data as $k => $v) {
                if ($v === '') {
                    $v = null;
                }
                if ($i == 0) {
                    $columns[] = "`{$k}`";
                }
                $values_row[] = "?";
                $params[]     = $v;
            }
            $values[] = '(' . implode(', ', $values_row) . ')';
        }
        $columns = implode(', ', $columns);
        $values  = implode(', ', $values);
        return $this->execute("insert into `{$table}`({$columns}) values {$values}", $params);
    }

    public function increase($table, $field, $where, $where_params = [], $step = 1)
    {
        return $this->update($table, [$field => '`' . $field . '`+' . intval($step)], $where, $where_params);
    }

    public function update($table, $data, $where, $where_params = [])
    {
        if (!$where) {
            throw new Exception('where required');
        }
        if ($where_params && !is_array($where_params)) {
            $where_params = [$where_params];
        }
        $updates = [];
        $params  = [];
        foreach ($data as $k => $v) {
            if ($v === '') {
                $v = null;
            }
            if (strpos($v, '`') !== false) {
                $updates[] = "`{$k}`={$v}";
                continue;
            }
            $updates[] = "`{$k}`=?";
            $params[]  = $v;
        }
        $updates = implode(', ', $updates);
        if ($where_params) {
            $params = array_merge($params, $where_params);
        }
        return $this->execute("update `{$table}` set {$updates} where {$where}", $params);
    }

    public function decrease($table, $field, $where, $where_params = [], $step = 1)
    {
        return $this->update($table, [$field => '`' . $field . '`-' . intval($step)], $where, $where_params);
    }

    public function delete($table, $where, $where_params = [])
    {
        if (!$where) {
            throw new Exception('where required');
        }
        if ($where_params && !is_array($where_params)) {
            $where_params = [$where_params];
        }
        return $this->execute("delete from `{$table}` where {$where}", $where_params);
    }

    public function search($sql, $params, $count)
    {
        $page     = $this->request->arg('page', ['type' => 'int']);
        $pagesize = $this->request->arg('pagesize', ['type' => 'int']);
        if (!$pagesize) {
            $pagesize = config::get('pagesize.default');
        }
        if ($pagesize < config::get('pagesize.min')) {
            $pagesize = config::get('pagesize.min');
        } else {
            if ($pagesize > config::get('pagesize.max')) {
                $pagesize = config::get('pagesize.max');
            }
        }
        $page  = max(1, $page);
        $count = intval($count);

        $pagemax = ceil($count / $pagesize);

        $sql  .= ' limit ' . $pagesize * ($page - 1) . ',' . $pagesize;
        $list = $this->query($sql, $params);
        return [
            'page'  => [
                'current' => $page,
                'max'     => $pagemax,
                'size'    => $pagesize,
            ],
            'count' => count($list),
            'total' => $count,
            'list'  => $list,
        ];
    }

    public function handleCondition($where, $flag = true)
    {
        $field['field'] = '';
        $field['value'] = [];
        if (!empty($where)) {
            if ($flag) {
                $field['field'] = 'where';
            }
            foreach ($where as $key => $val) {
                $field['field'] .= ' ' . $val[0] . ' ' . $val[1] . ' ? and';
                array_push($field['value'], $val[2]);
            }
            $field['field'] .= ' 1=1';
        }
        return $field;
    }

    public function in($field, $values)
    {
        $field  = $this->quote($field);
        $flag   = [];
        $params = [];
        foreach ($values as $value) {
            $flag[]   = '?';
            $params[] = $value;
        }
        $sql = "{$field} in (" . implode(",", $flag) . ")";
        return [$sql, $params];
    }

    public function not_in($field, $values)
    {
        $field  = $this->quote($field);
        $flag   = [];
        $params = [];
        foreach ($values as $value) {
            $flag[]   = '?';
            $params[] = $value;
        }
        $sql = "{$field} not in (" . implode(",", $flag) . ")";
        return [$sql, $params];
    }

    private function quote($field)
    {
        if (strpos($field, '.') !== false) {
            $fr    = explode('.', $field);
            $field = $fr[0] . '.`' . $fr[1] . '`';
        } else {
            $field = '`' . $field . '`';
        }
        return $field;
    }

    public function like($field, $value, $type = 'lr')
    {
        $field = $this->quote($field);
        $sql   = "{$field} like ? escape '/'";
        $value = preg_replace('/([\[\]%_\/])/Us', '/$1', $value);
        if (strpos($type, 'l') !== false) {
            $value = '%' . $value;
        }
        if (strpos($type, 'r') !== false) {
            $value .= '%';
        }
        return [$sql, $value];
    }

}