<?php

namespace TurboM3u8\library;

use TurboM3u8\library\Read;
use TurboM3u8\library\Util;

/**
 * 解析m3u8
 */
class Parse{

    protected $reader = null;
    protected $readerInfo = null;

    public function __construct(Read $reader)
    {
        $this->reader = $reader;
        $this->readerInfo = $reader->getInfo();
    }

    public function analyse($str){
        $lineData = [
            'content' => $str,
            'type' => '',
            'totaltime' => 0,
        ];
        if(preg_match('/\#EXTINF:(.*?)\,/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_INF;
            $lineData['totaltime'] = $this->readerInfo['total_time'] += doubleval($res[1]);
        }elseif(strpos($str, '#EXT-X-KEY')!==false){
            $lineData['type'] = Util::LINE_TYPE_KEY;
            $this->getEncryptInfo($str);
        }elseif(preg_match('/\#EXT-X-VERSION:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_VERSION;
            $this->readerInfo['version'] = $res[1];
        }elseif(preg_match('/\#EXTM3U$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_DEFINED;
        }elseif(preg_match('/\#EXT-X-TARGETDURATION:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_TARGETDURATION;
        }elseif(preg_match('/\#EXT-X-MEDIA-SEQUENCE:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_SEQUENCE;
        }elseif(preg_match('/\#EXT-X-ENDLIST:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_END;
        }elseif(!preg_match('/^\#EXT/', $str, $res) && !preg_match('/^\#/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_TSFILE;
        }

        $this->reader->setInfo($this->readerInfo);

        return $lineData;
    }

    protected function getEncryptInfo($str){
        $arr = explode(",", str_replace("#EXT-X-KEY:",'', $str));
        foreach ($arr as $key => $value) {
            $a = explode("=", $value);
            if($a && isset($a[0]) ){
                $k = strtoupper($a[0]);
                if( $k === "METHOD")
                {
                    $this->readerInfo['encrypt_method'] = isset($a[1]) ? $a[1] : '';
                }
                else
                if( $k === "URI")
                {
                    $this->readerInfo['encrypt_key_file'] = isset($a[1]) ? str_replace("\"","", $a[1]) : '';
                }else
                if( $k === "IV")
                {
                    $this->readerInfo['iv'] = isset($a[1]) ? $a[1] : '';
                }
            }
        }
        unset($key, $value, $k, $a, $arr);
    }

}

