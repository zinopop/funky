<?php

use Symfony\Component\Yaml\Yaml as Yaml;

class syncdb
{

    private static $db;
    private static $result = '';

    public static function run()
    {
        self::$result = '';
        $keys         = scandir(ROOT . '/data');
        foreach ($keys as $key) {
            if (in_array($key, ['.', '..'])) {
                continue;
            }
            self::$db = new db(null, $key);
            $files    = scandir(ROOT . '/data/' . $key);
            foreach ($files as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }
                $data = Yaml::parseFile(ROOT . '/data/' . $key . '/' . $file);
                foreach ($data as $k => $v) {
                    self::syncTable($k, self::fix($v));
                }
            }
        }
        return self::$result;
    }

    private static function syncTable($table, $data)
    {
        if (self::tableExist($table)) {
            self::updateTable($table, $data['field']);
        } else {
            self::createTable($table, $data['field']);
            self::createData($table, $data['data']);
        }
        self::syncIndex($table, $data['index']);
    }

    private static function tableExist($table)
    {
        return !!self::$db->query("show tables like '$table'");
    }

    private static function updateTable($table, $data)
    {
        $query_field = self::$db->query("show full columns from `{$table}`");

        $field = [];
        foreach ($query_field as $v) {
            $name           = strtolower($v['Field']);
            $type           = $v['Type'];
            $default        = $v['Default'];
            $auto_increment = strpos($v['Extra'], 'auto_increment') !== false;

            if ($name == 'id') {
                continue;
            }
            $field[$name] = [
                'type'           => $type,
                'default'        => $default,
                'auto_increment' => $auto_increment,
            ];
        }
        foreach ($data as $k => $v) {
            $type    = $v['type'];
            $default = $v['default'];
            if (is_numeric($default)) {
                $default = (string)$default;
            }
            if (!$field[$k]) {
                $auto_increment = $v['auto_increment'] ? "auto_increment, add constraint `inc_{$k}` unique (`{$k}`)" : '';
                self::$db->query("alter table `{$table}` add `{$k}` {$type} null" . ($default ? " default '{$default}'" : '') . " {$auto_increment}");
                self::print("已添加字段：{$table}.{$k}");
            } else {
                if ($type !== $field[$k]['type'] || $default !== $field[$k]['default'] || $v['auto_increment'] != $field[$k]['auto_increment']) {
                    $auto = '';
                    if ($v['auto_increment'] && $field[$k]['auto_increment']) {
                        $auto = 'auto_increment';
                    } else {
                        if ($v['auto_increment'] && !$field[$k]['auto_increment']) {
                            $auto = "auto_increment, add constraint `inc_{$k}` unique (`{$k}`)";
                        } else {
                            if (!$v['auto_increment'] && $field[$k]['auto_increment']) {
                                $auto = ", drop index `inc_{$k}`";
                            }
                        }
                    }
                    self::$db->query("alter table `{$table}` modify `{$k}` {$type} null" . ($default ? " default '{$default}'" : '') . " {$auto}");
                    self::print("已更新字段：{$table}.{$k}");
                }

            }
        }
        foreach ($field as $k => $v) {
            if (!$data[$k]) {
                self::$db->query("alter table `{$table}` drop {$k}");
                self::print("已删除字段：{$table}.{$k}");
            }
        }
    }

    private static function print($content)
    {
        self::$result .= $content . PHP_EOL;
        echo $content . PHP_EOL;
    }

    private static function createTable($table, $data)
    {
        $sql = "create table if not exists `{$table}`( `id` char(16) binary primary key";
        foreach ($data as $k => $v) {
            $type           = $v['type'];
            $default        = $v['default'];
            $auto_increment = $v['auto_increment'] ? "auto_increment, constraint `inc_{$k}` unique (`{$k}`)" : '';

            $sql .= ", `{$k}` {$type} null" . ($default ? " default '{$default}'" : '') . " {$auto_increment}";
        }
        $sql .= ') engine=innodb default charset=utf8mb4 collate=utf8mb4_bin';
        self::$db->query($sql);
        self::print("已创建表：{$table}");
    }

    private static function createData($table, $data)
    {
        if (!$data) {
            return;
        }
        $res = self::$db->insertAll($table, $data);
        self::print("已为表{$table}创建数据{$res}条");
    }

    private static function syncIndex($table, $data = [])
    {
        $query_index = self::$db->query("show index from `{$table}`");
        $index       = [];
        foreach ($query_index as $v) {
            $name  = strtolower($v['Key_name']);
            $type  = $v['Non_unique'] ? 'index' : 'unique';
            $field = $v['Column_name'];
            if ($name == 'primary') {
                continue;
            }
            if ($index[$name]) {
                $index[$name]['field'] .= ',' . $field;
            } else {
                $index[$name] = [
                    'type'  => $type,
                    'field' => $field,
                ];
            }
        }
        foreach ($data as $k => $v) {
            if (!$index[$k]) {
                self::$db->query("create " . ($v['type'] == 'unique' ? 'unique' : '') . " index {$k} on `{$table}`({$v['field']})");
                self::print("已添加索引：{$table}.{$k}");
            } else {
                if ($v['type'] !== $index[$k]['type'] || $v['field'] !== $index[$k]['field']) {
                    self::$db->query("drop index {$k} on `{$table}`");
                    self::$db->query("create " . ($v['type'] == 'unique' ? 'unique' : '') . " index {$k} on `{$table}`({$v['field']})");
                    self::print("已更新索引：{$table}.{$k}");
                }
            }
        }
        foreach ($index as $k => $v) {
            if (strpos($k, 'inc_') === 0) {
                continue;
            }
            if (!$data[$k]) {
                self::$db->query("drop index {$k} on `{$table}`");
                self::print("已删除索引：{$table}.{$k}");
            }
        }
    }

    private static function fix($data)
    {
        $res = [
            'field' => [],
            'index' => [],
            'data'  => [],
        ];
        foreach ($data['field'] as $k => &$v) {
            if (!is_array($v)) {
                $v = ['type' => $v];
            }
            $res['field'][strtolower($k)] = $v;
        }
        foreach ($data['index'] as $k => &$v) {
            if (!is_array($v)) {
                $v = [
                    'field' => $v,
                    'type'  => strpos($k, 'unq_') === 0 ? 'unique' : 'index',
                ];
            }
            $v['field']                   = strtolower($v['field']);
            $res['index'][strtolower($k)] = $v;
        }
        $res['data'] = $data['data'];
        return $res;
    }

}