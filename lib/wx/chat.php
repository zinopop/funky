<?php
namespace wx;

use config;
use http;

class chat
{
    public static function send($from_id,$content){
        if (!is_string($content)) {
            $content = json_encode($content);
        }
        $content = '[' . date('m-d H:i') . '] ' . config::get('server.name') . "[换行]" .$content;
        $result = http::post("http://101.200.155.100:9001/",[
            "a"=>"<&&>SendMessage<&>{$from_id}<&>{$content}<&><&>1"
        ]);
        return $result;
    }

    public static function sendAudio($from_id,$silk,$time)
    {
        $result = http::post("http://101.200.155.100:9001/",[
            "a"=>"<&&>SendVoice<&>{$from_id}<&>{$silk}<&>$time<&>1"
        ]);
        return $result;
    }

    public static function sendMusic($wxid,$content,$type = 5)
    {
//        $result = http::post("http://101.200.155.100:9001/",[
//            "a"=>"<&&>SendAppMsg<&>{$wxid}<&>说好不哭<&>于政测试<&>https://music.163.com/m/song?id=1308032189&userid=132641176&from=message<&>https://timgsa.baidu.com/timg?image&quality=80&size=b9999_10000&sec=1577446374781&di=8d5774f195c18cdf329544c8b361ce26&imgtype=0&src=http%3A%2F%2Fn.sinaimg.cn%2Fsinacn20190918ac%2F40%2Fw480h360%2F20190918%2F13fd-ietnfsq4805017.jpg<&>1"
//        ]);
        $content2 = "<?xml version=\"1.0\"?>
<msg>
	<appmsg appid=\"wx8dd6ecd81906fd84\" sdkver=\"0\">
		<title>春、恋、花以外の（Cover：匀子）</title>
		<des>茶玖/熊太kuma/池树</des>
		<action>view</action>
		<type>3</type>
		<showtype>0</showtype>
		<content />
		<url>http://music.163.com/song/1308032189/?userid=132641176</url>
		<dataurl>http://music.163.com/song/media/outer/url?id=1308032189&amp;userid=132641176</dataurl>
		<lowurl />
		<lowdataurl />
		<recorditem><![CDATA[]]></recorditem>
		<thumburl />
		<messageaction />
		<extinfo />
		<sourceusername />
		<sourcedisplayname />
		<commenturl />
		<appattach>
			<totallen>0</totallen>
			<attachid />
			<emoticonmd5 />
			<fileext />
			<cdnthumburl>305d020100045630540201000204180385eb02032f4f5602049c7ac2dc02045e05c639042f6175706170706d73675f356664393835353764333764623936615f313537373433363732383737315f3333363433360204010400030201000400</cdnthumburl>
			<cdnthumblength>24590</cdnthumblength>
			<cdnthumbheight>120</cdnthumbheight>
			<cdnthumbwidth>85</cdnthumbwidth>
			<aeskey>1aa8b8983cbf132b98b4a312b0daaca6</aeskey>
			<cdnthumbaeskey>1aa8b8983cbf132b98b4a312b0daaca6</cdnthumbaeskey>
			<encryver>1</encryver>
		</appattach>
		<weappinfo>
			<pagepath />
			<username />
			<appid />
			<appservicetype>0</appservicetype>
		</weappinfo>
	</appmsg>
	<fromusername>yuzheng175091</fromusername>
	<scene>0</scene>
	<appinfo>
		<version>49</version>
		<appname>网易云音乐</appname>
	</appinfo>
	<commenturl></commenturl>
</msg>";
        $result = http::post("http://101.200.155.100:9001/",[
            "a"=>"<&&>SendAppMsgRaw<&>{$wxid}<&>{$content}<&>{$type}<&>1"
        ]);
        return $result;
    }
}