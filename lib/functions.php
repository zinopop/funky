<?php


class functions {

    private static $uuid_mtime_queue = [];

    public static function formatException($request, $e) {
        if ($request) {
            $content = $request->server('request_method') . ' ' . $request->server('path_info') . ($request->server('query_string') ? ('?' . $request->server('query_string')) : '') . PHP_EOL;
            if ($request->server('request_method') == 'POST') {
                $content .= $request->content() . PHP_EOL;
            }
        } else {
            $content = '';
        }

        $content .= $e->getMessage() . PHP_EOL;
        $content .= $e->getFile() . ':' . $e->getLine() . PHP_EOL;
        $content .= $e->getTraceAsString();
        return $content;
    }

    public static function uuid($ext = null) {
        static $seq = 0;
        static $cluster_id = null;
        if ($cluster_id === null) {
            $cluster_id = (int)config::get('server.cluster');
        }

        $mtime      = (int)(microtime(true) * 1000);
        $server_id  = server::getServerId();
        $process_id = server::getWorkerId();

        if (count(self::$uuid_mtime_queue) > 256) {
            $last = array_shift(self::$uuid_mtime_queue);
            if ($last == $mtime) {
                usleep(1000);
                $mtime++;
            }
        }
        array_push(self::$uuid_mtime_queue, $mtime);

        $seq++;
        if ($seq >= 256) {
            $seq = 0;
        }

        $for_count = 61;
        $bin       = str_pad(decbin($mtime), 42, '0', STR_PAD_LEFT);
        $bin       .= str_pad(decbin($cluster_id), 4, '0', STR_PAD_LEFT);
        $bin       .= str_pad(decbin($server_id), 6, '0', STR_PAD_LEFT);
        $bin       .= str_pad(decbin($process_id), 4, '0', STR_PAD_LEFT);
        if ($ext !== null) {
            $bin       .= str_pad(decbin((int)$ext), 16, '0', STR_PAD_LEFT);
            $for_count = (int)$for_count + 16;
        }
        $bin .= str_pad(decbin($seq), 8, '0', STR_PAD_LEFT);

        $uuid = '';
        for ($i = 0; $i < $for_count; $i += 4) {
            $uuid .= dechex(bindec(substr($bin, $i, 4)));
        }
        return $uuid;
    }

    public static function crackUuid($uuid) {

        $bin = '';
        for ($j = 0; $j < strlen($uuid); $j++) {
            $bin .= str_pad(decbin(hexdec(substr($uuid, $j, 1))), 4, '0', STR_PAD_LEFT);
        }
        $res = [
            'mtime'      => (bindec(substr(substr($bin, 0, 42), 1)) / 1000),
            'cluster_id' => bindec(substr($bin, 42, 4)),
            'server_id'  => bindec(substr($bin, 46, 6)),
            'process_id' => bindec(substr($bin, 52, 4)),
        ];
        if (strlen($uuid) > 16) {
            $res['ext'] = bindec(substr($bin, 56, 16));
        }
        return $res;
    }

    public static function crackUrl($url) {
        $fmt_array = explode('/', $url);
        $idx       = count($fmt_array);
        $img       = $fmt_array[$idx - 1];
        $uuid      = substr($img, 0, strpos($img, '.'));
        return $uuid;
    }

    public static function randomString($length, $dict = '') {
        if (!$dict) {
            $dict = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $dict[rand(0, strlen($dict) - 1)];
        }
        return $str;
    }

    public static function date($format = null, $time = null) {
        if (!$format) {
            $format = 'Y-m-d H:i:s';
        }
        if (!$time) {
            $time = time();
        }
        return date($format, $time);
    }

    public static function getIpInfo($ip, $detail = false,$force = false) {
        $cache = new cache('ip_info');
        $ip_info  = $cache->get($ip);
        if ($ip_info && !$force) {
            if(!$detail) return $ip_info['City'];
            return $ip_info;
        }
        $ip_info = \juhe\common::getIpInfo($ip,true);
        if(!$ip_info) return null;
        $cache->set($ip, $ip_info, 3600);

        if(!$detail) return $ip_info['City'];
        return $ip_info;

    }

    public static function getWeatherData($city,$force = false)
    {
        $cache = new cache('weather_info');
        $result  = $cache->get($city);
        if ($result && !$force) {
            return $result;
        }
        $weather_info = \juhe\common::getWeatherData($city);
        if(!$weather_info) return null;
        $cache->set($city, $weather_info, 3600);
        return $weather_info;
    }

    public static function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    public static function birthday($birthday) {
        $age = strtotime($birthday);
        if ($age === false) {
            return false;
        }
        list($y1, $m1, $d1) = explode("-", date("Y-m-d", $age));
        $now = strtotime("now");
        list($y2, $m2, $d2) = explode("-", date("Y-m-d", $now));
        $age = $y2 - $y1;
        if ((int)($m2 . $d2) < (int)($m1 . $d1)) {
            $age -= 1;
        }
        return $age;
    }


    public static function sensitiveIsLegal($word,$detail = false)
    {
        $wordFilePath = ROOT.'/sensitive/words.txt';
        $handle = \DfaFilter\SensitiveHelper::init()->setTreeByFile($wordFilePath);
        $islegal = $handle->islegal($word);
        if($detail) {
            if($islegal){
                return [
                    [
                        $handle->getBadWord($word)
                    ],true
                ];
            }
            return [[],false];
        }else{
            return $islegal;
        }
    }

    public static function personInfo($id)
    {
        $user = \model\user::info($id);
        if($user){
            return $user;
        }
        $star = \model\star::info($id);
        if($star){
            $star['avatar'] = upload::url($star['avatar']);
            return $star;
        }
        return null;
    }

    public static function getFileUuid($url)
    {
        if (strpos($url, 'http') !== false) {
            $fmt_array = explode('/', $url);
            $idx       = count($fmt_array);
            $img       = $fmt_array[$idx - 1];
            $uuid      = substr($img, 0, strpos($img, '.'));
            return $uuid;
        }
        return $url;
    }

    public static function getTimeOffset($date1,$date2)
    {
        $time1 = strtotime($date1);
        $time2 = strtotime($date2);
        $year1  = date("Y",$time1);
        $month1 = date("m",$time1);
        $year2  = date("Y",$time2);
        $month2 = date("m",$time2);
        $monthPix = abs(($year1 * 12 + $month1) - ($year2 * 12 + $month2));
        return $monthPix;
    }

    public static function offsetHour($begin_time,$end_time)
    {
        if($begin_time < $end_time){
            $starttime = $begin_time;
            $endtime = $end_time;
        }else{
            $starttime = $end_time;
            $endtime = $begin_time;
        }
        $hour = abs(ceil((strtotime($starttime)-strtotime($endtime))/3600));
        return $hour;
    }

    public static function excelImport(string $file = '', int $sheet = 0, int $columnCnt = 0, &$options = [])
    {
        try {
            /* 转码 */
            $file = iconv("utf-8", "gb2312", $file);

            if (empty($file) OR !file_exists($file)) {
                throw new \Exception('文件不存在!');
            }

            /** @var Xlsx $objRead */
            $objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');

            if (!$objRead->canRead($file)) {
                /** @var Xls $objRead */
                $objRead = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');

                if (!$objRead->canRead($file)) {
                    throw new \Exception('只支持导入Excel文件！');
                }
            }

            /* 如果不需要获取特殊操作，则只读内容，可以大幅度提升读取Excel效率 */
            empty($options) && $objRead->setReadDataOnly(true);
            /* 建立excel对象 */
            $obj = $objRead->load($file);
            /* 获取指定的sheet表 */
            $currSheet = $obj->getSheet($sheet);

            if (isset($options['mergeCells'])) {
                /* 读取合并行列 */
                $options['mergeCells'] = $currSheet->getMergeCells();
            }

            if (0 == $columnCnt) {
                /* 取得最大的列号 */
                $columnH = $currSheet->getHighestColumn();
                /* 兼容原逻辑，循环时使用的是小于等于 */
                $columnCnt = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnH);
            }

            /* 获取总行数 */
            $rowCnt = $currSheet->getHighestRow();
            $data   = [];

            /* 读取内容 */
            for ($_row = 1; $_row <= $rowCnt; $_row++) {
                $isNull = true;

                for ($_column = 1; $_column <= $columnCnt; $_column++) {
                    $cellName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($_column);
                    $cellId   = $cellName . $_row;
                    $cell     = $currSheet->getCell($cellId);

                    if (isset($options['format'])) {
                        /* 获取格式 */
                        $format = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        /* 记录格式 */
                        $options['format'][$_row][$cellName] = $format;
                    }

                    if (isset($options['formula'])) {
                        /* 获取公式，公式均为=号开头数据 */
                        $formula = $currSheet->getCell($cellId)->getValue();

                        if (0 === strpos($formula, '=')) {
                            $options['formula'][$cellName . $_row] = $formula;
                        }
                    }

                    if (isset($format) && 'm/d/yyyy' == $format) {
                        /* 日期格式翻转处理 */
                        $cell->getStyle()->getNumberFormat()->setFormatCode('yyyy/mm/dd');
                    }

                    $data[$_row][$cellName] = trim($currSheet->getCell($cellId)->getFormattedValue());

                    if (!empty($data[$_row][$cellName])) {
                        $isNull = false;
                    }
                }

                /* 判断是否整行数据为空，是的话删除该行数据 */
                if ($isNull) {
                    unset($data[$_row]);
                }
            }

            return $data;
        } catch (\Exception $e) {
            return false;
        }
    }



    public static function pinyinLong($zh)
    {
        $ret = "";
        $s1  = iconv("UTF-8", "GBK//IGNORE", $zh);
        $s2  = iconv("GBK", "UTF-8", $s1);
        if ($s2 == $zh) {
            $zh = $s1;
        }
        for ($i = 0; $i < strlen($zh); $i++) {
            $s1 = substr($zh, $i, 1);
            $p  = ord($s1);
            if ($p > 160) {
                $s2  = substr($zh, $i++, 2);
                $ret .= self::getfirstchar($s2);
            } else {
                $ret .= $s1;
            }
        }
        return $ret;
    }

    public static function getFirstChar($str)
    {
        if (empty($str)) {
            return '';
        }

        $fir = $fchar = ord($str[0]);
        if ($fchar >= ord('A') && $fchar <= ord('z')) {
            return strtoupper($str[0]);
        }

        $s1 = @iconv('UTF-8', 'gb2312//IGNORE', $str);
        $s2 = @iconv('gb2312', 'UTF-8', $s1);
        $s  = $s2 == $str ? $s1 : $str;
        if (!isset($s[0]) || !isset($s[1])) {
            return '';
        }

        $asc = ord($s[0]) * 256 + ord($s[1]) - 65536;

        if (is_numeric($str)) {
            return $str;
        }

        if (($asc >= -20319 && $asc <= -20284) || $fir == 'A') {
            return 'A';
        }
        if (($asc >= -20283 && $asc <= -19776) || $fir == 'B') {
            return 'B';
        }
        if (($asc >= -19775 && $asc <= -19219) || $fir == 'C') {
            return 'C';
        }
        if (($asc >= -19218 && $asc <= -18711) || $fir == 'D') {
            return 'D';
        }
        if (($asc >= -18710 && $asc <= -18527) || $fir == 'E') {
            return 'E';
        }
        if (($asc >= -18526 && $asc <= -18240) || $fir == 'F') {
            return 'F';
        }
        if (($asc >= -18239 && $asc <= -17923) || $fir == 'G') {
            return 'G';
        }
        if (($asc >= -17922 && $asc <= -17418) || $fir == 'H') {
            return 'H';
        }
        if (($asc >= -17417 && $asc <= -16475) || $fir == 'J') {
            return 'J';
        }
        if (($asc >= -16474 && $asc <= -16213) || $fir == 'K') {
            return 'K';
        }
        if (($asc >= -16212 && $asc <= -15641) || $fir == 'L') {
            return 'L';
        }
        if (($asc >= -15640 && $asc <= -15166) || $fir == 'M') {
            return 'M';
        }
        if (($asc >= -15165 && $asc <= -14923) || $fir == 'N') {
            return 'N';
        }
        if (($asc >= -14922 && $asc <= -14915) || $fir == 'O') {
            return 'O';
        }
        if (($asc >= -14914 && $asc <= -14631) || $fir == 'P') {
            return 'P';
        }
        if (($asc >= -14630 && $asc <= -14150) || $fir == 'Q') {
            return 'Q';
        }
        if (($asc >= -14149 && $asc <= -14091) || $fir == 'R') {
            return 'R';
        }
        if (($asc >= -14090 && $asc <= -13319) || $fir == 'S') {
            return 'S';
        }
        if (($asc >= -13318 && $asc <= -12839) || $fir == 'T') {
            return 'T';
        }
        if (($asc >= -12838 && $asc <= -12557) || $fir == 'W') {
            return 'W';
        }
        if (($asc >= -12556 && $asc <= -11848) || $fir == 'X') {
            return 'X';
        }
        if (($asc >= -11847 && $asc <= -11056) || $fir == 'Y') {
            return 'Y';
        }
        if (($asc >= -11055 && $asc <= -10247) || $fir == 'Z') {
            return 'Z';
        }

        return '';
    }

}