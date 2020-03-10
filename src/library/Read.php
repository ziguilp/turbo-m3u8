<?php

namespace TurboM3u8\library;

use TurboM3u8\library\Parse;
use TurboM3u8\Exception\SourceException;

/**
 * 读取m3u8
 */
class Read{

    protected $file_info = null;

    protected $file_name = null;

    protected $content = null;

    protected $content_lines = null;

    protected $info = [
        'version' => "1",
        'encrypt_method' => null,
        'iv' => null,
        'encrypt_key_file' => null,
        'total_time' => 0,
    ];
    
    public function __construct($file)
    {
        // if(!is_file($file)){
        //     throw new SourceException("File : {$file} does not exist");
        // }

        $this->file_name = $file;

        $this->file_info = pathinfo($file);

        if(!$this->check()){
            throw new SourceException("File : {$file} is not m3u8 ");
        }

    }

    /**
     * 加密方法
     */
    public function getEncryptMethod(){

    }

    /**
     * 加密密钥地址
     */
    public function getEncryptKeyFile(){

    }

    /**
     * 总时长
     */
    public function getTotalTime(){

    }

    /**
     * 是否加密
     */
    public function isEncrypted(){

    }

    public function getFileInfo(){
        return $this->file_info;
    }

    public function getInfo(){
        return $this->info;
    }

    public function setInfo($info){
        $this->info = array_merge($this->info, $info);
        return $this;
    }

    /**
     * 读取内容
     */
    public function getContent(){
        if(!$this->content){
            $this->content = file_get_contents($this->file_name);
        }
        return $this->content;
    }

    /**
     * 以行读取内容
     */
    public function getContentAsLines(){
        if(!$this->content_lines){
            $content = $this->getContent();
            $content_lines = explode("\n", $content);
            $this->content_lines = [];
            $parse = new Parse($this);
            foreach ($content_lines as $key => $line) {
                $line = trim($line);
                if($line){
                    $parse->analyse($line);
                    $this->content_lines[] = $line;
                }
            }
            unset($content, $content_lines, $key, $line, $parse);
        }
        return $this->content_lines;
    }

    /**
     * 是否为m3u8格式
     */
    public function check(){
        $lines = $this->getContentAsLines();
        if(isset($lines[0]) && $lines[0] == "#EXTM3U"){
            return true;
        }
        return false;
    }

}

