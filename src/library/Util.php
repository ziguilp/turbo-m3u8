<?php

namespace TurboM3u8\library;

class Util{

    const LINE_TYPE_DEFINED = 'DEFINED';
    const LINE_TYPE_VERSION = 'VERSION';
    const LINE_TYPE_TARGETDURATION = 'TARGETDURATION';
    const LINE_TYPE_SEQUENCE = 'SEQUENCE';
    const LINE_TYPE_KEY = 'KEY';
    const LINE_TYPE_INF = 'INF';
    const LINE_TYPE_END = 'END';
    const LINE_TYPE_TSFILE = 'TSFILE';

    const STR_MARK_KEY = "";

    public static function getEndStr(){
        return '#EXT-X-ENDLIST';
    }

    public static function replaceKeyStr($str, $config){
        $parse = self::parseKeyLine($str);
        if(isset($config['encrypt_method'])){
            $parse['METHOD'] = $config['encrypt_method'];
        }
        if(isset($config['encrypt_key_file'])){
            $parse['URI'] = "\"".$config['encrypt_key_file']."\"";
        }
        if(isset($config['iv']) && $config['iv']){
            $parse['IV'] = $config['iv'];
        }

        $str = [];

        foreach($parse as $key => $v){
            $str[] = $key.'='.$v;
        }

        return "#EXT-X-KEY:".implode(",",$str);

    }

    public static function parseKeyLine($str){
        $arr = explode(",", str_replace("#EXT-X-KEY:",'', $str));
        $res = [];
        foreach ($arr as $key => $value) {
            $a = explode("=", $value);
            if($a && isset($a[0]) && isset($a[1]) ){
               
                if($a[0] == 'URI'){
                    $a[1] = str_replace("\"", '', $a[1]);
                }

                $res[$a[0]] = $a[1];
            }
        }
        unset($key, $value, $a, $arr);
        return $res;
    }

    public static function isKeyLine($str){
        return preg_match('/\#EXT-X-KEY:(.*?)$/', $str);
    }

    public static function isVersionLine($str){
        return preg_match('/\#EXT-X-VERSION:(.*?)$/', $str);
    }

    public static function isTsTimeLine($str){
        return preg_match('/\#EXTINF:(.*?)$/', $str);
    }

    public static function isTsFileLine($str){
        return !preg_match('/^\#EXT(.*?)$/', $str) && !preg_match('/^\#/', $str);
    }

    public static function isDefineLine($str){
        return preg_match('/\#EXTM3U/', $str);
    }

    public static function isEndLine($str){
        return preg_match('/\#EXT-X-ENDLIST/', $str);
    }

    public static function save($file_name, $content){
        $handle = fopen($file_name, 'w+');
        flock($handle, LOCK_EX);
        $res = fwrite($handle, $content);
        flock($handle, LOCK_UN);
        fclose($handle);
        return $res === false ? false : true;
    }

    public static function curl( $url = null, $postFields = null, $header = [] ) {
        if( empty($url) )
        {
            return "";
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
        $postBodyString = "";
        if (is_array($postFields) && 0 < count($postFields)) {
    
            foreach ($postFields as $k => $v) { 
                $postBodyString .= "$k=" . urlencode($v) . "&";
            }
            unset ($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
        }
    
        $default_header = ['content-type: application/x-www-form-urlencoded;charset=utf-8'];
        $headers = empty( $header ) ? $default_header : array_merge( $default_header, $header );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $reponse = curl_exec($ch);
    
        if (curl_errno($ch)) {
    
            throw new \Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new \Exception($reponse, $httpStatusCode);
            }
        }
    
        curl_close($ch);
        return $reponse;
    }

}
