<?php

namespace TurboM3u8\library;

/**
 * 生成m3u8
 */
class Build{

    /**
     * 前置域名
     */
    protected $domain = '';

    /**
     * 路径
     */
    protected $prefixPath = '';

    protected $prefixPathArr = [];

    protected $cache = [];

    public function __construct(String $prefix = '')
    {
        $this->setPrefixPath($prefix);
    }

    /**
     * 设置域名
     */
    public function setDomain(String $domain){
        $this->domain = $domain;
        return $this;
    }

    /**
     * 设置路径
     */
    public function setPrefixPath(String $prefix){
        $pathDir = str_replace( $this->domain, '', $prefix);
        $this->prefixPathArr = explode("/", $pathDir);
        $this->prefixPath = $pathDir;
        $this->cache = [];
        return $this;
    }
    

    public function getDomain(){
        return $this->domain;
    }

    /**
     * 解析m3u8地址，得出域名和路径文件夹
     */
    public function parse(String $m3u8FileName){
        $pathInfo = parse_url($m3u8FileName);
        if($pathInfo && isset($pathInfo['scheme'])&&isset($pathInfo['host'])){
            $this->setDomain($pathInfo['scheme'].'://'.$pathInfo['host']);
        }
        $pathInfo = pathinfo($m3u8FileName);
        if($pathInfo){
            if(isset($pathInfo['dirname'])){
                $this->setPrefixPath($pathInfo['dirname']);
            }
        }
        return $this;
    }

    /**
     * 根据路径前置符获取真实路径目录地址
     */
    public function getPreFixPath($positionFix = '/', $counts = 0){

        $cacheIndex = $positionFix;
        if($positionFix == '../'){
            $cacheIndex = $positionFix.'_'.$counts;
        }elseif($positionFix == ''){
            $cacheIndex = $positionFix.'_';
        }

        if(!isset($this->cache[$cacheIndex])){
            if($positionFix == '/'){
                $this->cache[$cacheIndex] = '';
            }elseif($positionFix == './' || $positionFix == ''){
                $this->cache[$cacheIndex] = implode("/", $this->prefixPathArr);
            }elseif($positionFix == '../' && $counts > 1){
                $arrs = array_slice($this->prefixPathArr, 0,  -1 * ($counts - 1));
                $this->cache[$cacheIndex] = implode("/", $arrs);
            }else{
                $this->cache[$cacheIndex] = '';
            }

            if( $this->cache[$cacheIndex] == '/' ){

                $this->cache[$cacheIndex] = '';
            }
        }
        
        return $this->cache[$cacheIndex];
    }

    /**
     * 构造新路径
     */
    public function build(String $str){

        $str = trim($str);
        if(preg_match('/^https?\:/',$str)){
            return $str;
        }
        if(preg_match('/^\.\.\//',$str)){
            $dots = explode("../", $str);
            return $this->domain.$this->getPreFixPath('../', count($dots)).'/'.str_replace("../", '', $str);
        }
        if(preg_match('/^\.\//',$str)){
            return $this->domain.$this->getPreFixPath('./').str_replace("./", '/', $str);
        }
        if(preg_match('/^\//',$str)){
            return $this->domain.$str;
        }
        return $this->domain.$this->getPreFixPath('').'/'.$str;
    }

}

