<?php

class upload
{

    private static $img   = [
        'jpg'  => 1,
        'png'  => 2,
        'gif'  => 3,
        'jpeg' => 4,
    ];
    private static $audio = [
        'mp3' => 1,
        'wav' => 2,
        'pcm' => 3,
    ];
    private static $video = [
        'mp4' => 1,
        'avi' => 2,
    ];
    private static $file  = [
        'xlsx' => 1,
        'xls'  => 2,
        'txt'  => 3,
    ];

    public static function download($url)
    {
        $data = http::get($url);
        if (!$data) {
            return ['远程图片获取失败', false];
        }
        $file = '/tmp/' . functions::uuid();
        file_put_contents($file, $data);
        defer(function () use ($file) {
            unlink($file);
        });
        return self::image($file);
    }

    public static function image($file)
    {
        $info = getimagesize($file);
        if (!$info) {
            return ['图片格式非法', false];
        }
        if ($info[0] > config::get('upload.image.width')) {
            return ['图片宽度不能大于' . config::get('upload.image.width'), false];
        }
        $size = filesize($file);
        if ($size > config::get('upload.image.size') * 1024 * 1024) {
            return ['图片大小不能大于' . config::get('upload.image.size') . 'M', false];
        }

        $ext = str_replace('image/', '', $info['mime']);

        [$extindex, $success] = self::getIndex('img', $ext);

        if (!$success) {
            return [$extindex, false];
        }

        $uuid = functions::uuid($extindex);

        $crack = functions::crackUuid($uuid);
        $mtime = $crack['mtime'];

        $path = date('/ym/d/', $mtime);

        $full = config::get('upload.path') . $path;

        $name = $uuid . '.' . $ext;

        if (!is_dir($full)) {
            mkdir($full, 0777, true);
        }
        copy($file, $full . $name);
        chmod($full . $name, 0666);
        return [$uuid, true];
    }

    public static function getIndex($type, $ext)
    {
        switch ($type) {
            case 'img':
                if (self::$img[$ext] === null) {
                    return ['图片类型未找到', false];
                }
                $index = self::$img[$ext];
                break;
            case 'audio':
                if (self::$audio[$ext] === null) {
                    return ['音频类型未找到', false];
                }
                $index = self::$audio[$ext];
                break;
            case 'video':
                if (self::$video[$ext] === null) {
                    return ['视频类型未找到', false];
                }
                $index = self::$audio[$ext];
                break;
            case 'file':
                if (self::$file[$ext] === null) {
                    return ['文件类型未找到', false];
                }
                $index = self::$file[$ext];
                break;
            default:
                return ['资源类型不合法', false];
        }
        return [$index, true];
    }

    public static function audio($file)
    {
        $size = $file['size'];
        $ext  = self::getExt($file['name']);

        [$extindex, $success] = self::getIndex('audio', $ext);
        if (!$success) {
            return [$extindex, false];
        }

        if ($size > config::get('upload.audio.size') * 1024 * 1024) {
            return ['音频大小不能大于' . config::get('upload.audio.size') . 'M', false];
        }

        $uuid = functions::uuid($extindex);

        $crack = functions::crackUuid($uuid);

        $mtime = $crack['mtime'];

        $path = date('/ym/d/', $mtime);

        $full = config::get('upload.path') . $path;

        $name = $uuid . '.' . $ext;

        if (!is_dir($full)) {
            mkdir($full, 0777, true);
        }
        copy($file['tmp_name'], $full . $name);
        chmod($full . $name, 0666);
        return [$uuid, true];
    }

    public static function getExt($filename)
    {
        $arr = explode('.', $filename);
        return $arr[count($arr) - 1];
    }

    public static function video($file)
    {
        $size = $file['size'];
        $ext  = self::getExt($file['name']);
        [$extindex, $success] = self::getIndex('video', $ext);
        if (!$success) {
            return [$extindex, false];
        }

        if ($size > config::get('upload.video.size') * 1024 * 1024) {
            return ['视频大小不能大于' . config::get('upload.video.size') . 'M', false];
        }

        $uuid = functions::uuid($extindex);

        $crack = functions::crackUuid($uuid);

        $mtime = $crack['mtime'];

        $path = date('/ym/d/', $mtime);

        $full = config::get('upload.path') . $path;

        $name = $uuid . '.' . $ext;
        if (!is_dir($full)) {
            mkdir($full, 0777, true);
        }
        copy($file['tmp_name'], $full . $name);
        chmod($full . $name, 0666);
        return [$uuid, true];
    }

    public static function file($file)
    {
        $size = $file['size'];
        $ext  = self::getExt($file['name']);
        [$extindex, $success] = self::getIndex('file', $ext);
        if (!$success) {
            return [$extindex, false];
        }

        if ($size > config::get('upload.video.size') * 1024 * 1024) {
            return ['文件大小不能大于' . config::get('upload.video.size') . 'M', false];
        }

        $uuid = functions::uuid($extindex);

        $crack = functions::crackUuid($uuid);

        $mtime = $crack['mtime'];

        $path = date('/ym/d/', $mtime);

        $full = config::get('upload.path') . $path;

        $name = $uuid . '.' . $ext;
        if (!is_dir($full)) {
            mkdir($full, 0777, true);
        }
        copy($file['tmp_name'], $full . $name);
        chmod($full . $name, 0666);
        return [['uuid' => $uuid, 'full_name' => $full . $name], true];
    }

    public static function url($uuid, $type = 'img')
    {
        if (!$uuid) {
            return null;
        }
        $server_msg = functions::crackUuid($uuid);
        $ext        = self::getType($type, $server_msg['ext']);
        if (!$ext) {
            $ext = $type;
        }
        $host = config::get('cdn.' . $server_msg['cluster_id']);
        $path = date('/ym/d/', $server_msg['mtime']);
        $name = $uuid . '.' . $ext;
        return $host . $path . $name;
    }

    public static function getType($type, $index)
    {
        switch ($type) {
            case 'img':
                foreach (self::$img as $k => $v) {
                    if ($v == $index) {
                        $ext = $k;
                        break;
                    }
                }
                break;
            case 'audio':
                foreach (self::$audio as $k => $v) {
                    if ($v == $index) {
                        $ext = $k;
                        break;
                    }
                }
                break;
            case 'video':
                foreach (self::$video as $k => $v) {
                    if ($v == $index) {
                        $ext = $k;
                        break;
                    }
                }
                break;
            case 'file':
                foreach (self::$file as $k => $v) {
                    if ($v == $index) {
                        $ext = $k;
                        break;
                    }
                }
                break;
            default:
                return false;
        }
        if (!$ext) {
            return false;
        }
        return $ext;
    }

    public static function save($data, $ext)
    {
        $uuid = functions::uuid();
        $info = functions::crackUuid($uuid);
        $path = date('/ym/d/', $info['mtime']);
        $full = config::get('upload.path') . $path;
        if (!is_dir($full)) {
            mkdir($full, 0777, true);
        }
        $name = $uuid . '.' . $ext;
        file_put_contents($full . $name, $data);
        return $uuid;
    }

    public static function checkWav($uuid, $sr, $ch, $bits)
    {
        $head     = self::getContent($uuid, 'audio', 44);
        $info     = unpack('s', substr($head, 24, 4));
        $wav_sr   = $info[1];
        $info     = unpack('c', substr($head, 22, 2));
        $wav_ch   = $info[1];
        $info     = unpack('c', substr($head, 34, 2));
        $wav_bits = $info[1];
        return $sr == $wav_sr && $ch == $wav_ch && $bits == $wav_bits;
    }

    public static function getContent($uuid, $type = 'img', $size = 0)
    {
        if ($size == 0) {
            return file_get_contents(self::src($uuid, $type));
        } else {
            $file    = fopen(self::src($uuid, $type), 'rb');
            $content = fread($file, $size);
            fclose($file);
            return $content;
        }
    }

    public static function src($uuid, $type = 'img')
    {
        $server_msg = functions::crackUuid($uuid);
        $ext        = self::getType($type, $server_msg['ext']);
        if (!$ext) {
            $ext = $type;
        }
        $path = date('/ym/d/', $server_msg['mtime']);
        $name = $uuid . '.' . $ext;
        return config::get('upload.path') . $path . $name;
    }

    public static function getId($src)
    {
        if (strpos($src, 'http') === false) {
            return $src;
        }
        $src  = explode('/', $src);
        $idx  = count($src);
        $img  = $src[$idx - 1];
        $uuid = substr($img, 0, strpos($img, '.'));
        return $uuid;
    }

}