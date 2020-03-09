<?php

namespace TurboM3u8\library;

use TurboM3u8\library\Read;

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
             
        if(preg_match('/\#EXTINF:(.*?)\,$/', $str, $res)){
            $this->readerInfo['total_time'] += doubleval($res[1]);
        }elseif(strpos($str, '#EXT-X-KEY')!==false){
            $this->getEncryptInfo($str);
        }elseif(preg_match('/\#EXT-X-VERSION:(.*?)$/', $str, $res)){
            $this->readerInfo['version'] = $res[1];
        }

        $this->reader->setInfo($this->readerInfo);
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

