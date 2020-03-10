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
        $this->readerInfo['total_time'] = 0;
        $this->readerInfo['encrypt_method'] = '';
        $this->readerInfo['encrypt_key_file'] = '';
        $this->readerInfo['iv'] = null;
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
        }elseif(Util::isTsFileLine($str)){
            $lineData['type'] = Util::LINE_TYPE_TSFILE;
            $lineData['totaltime'] = $this->readerInfo['total_time'];
        }elseif(strpos($str, '#EXT-X-KEY')!==false){
            $lineData['type'] = Util::LINE_TYPE_KEY;
            $this->getEncryptInfo($str);
        }elseif(preg_match('/\#EXT-X-VERSION:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_VERSION;
            $this->readerInfo['version'] = $res[1];
        }elseif(Util::isDefineLine($str)){
            $lineData['type'] = Util::LINE_TYPE_DEFINED;
        }elseif(preg_match('/\#EXT-X-TARGETDURATION:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_TARGETDURATION;
        }elseif(preg_match('/\#EXT-X-MEDIA-SEQUENCE:(.*?)$/', $str, $res)){
            $lineData['type'] = Util::LINE_TYPE_SEQUENCE;
        }elseif(Util::isEndLine($str)){
            $lineData['type'] = Util::LINE_TYPE_END;
        }

        $this->reader->setInfo($this->readerInfo);

        return $lineData;
    }

    protected function getEncryptInfo($str){
        $einfo = Util::parseKeyLine($str);

        if( isset($einfo['METHOD']))
        {
            $this->readerInfo['encrypt_method'] = $einfo['METHOD'];
        }
        if( isset($einfo['URI']))
        {
            $this->readerInfo['encrypt_key_file'] = $einfo['URI'];
        }
        if( isset($einfo['IV']))
        {
            $this->readerInfo['iv'] = $einfo['IV'];
        }
        unset($einfo);
    }

}

