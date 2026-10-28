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
    
    public function __construct($file = '')
    {
        // if(!is_file($file)){
        //     throw new SourceException("File : {$file} does not exist");
        // }
        if($file){
            $this->setFile($file);
        }
    }

    public function setFile($file){
        if(!is_string($file) || $file === '' || strpos($file, "\0") !== false){
            throw new SourceException('File name is empty or invalid, nothing to read');
        }
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
        return $this->info['encrypt_method'];
    }

    /**
     * 加密密钥地址
     */
    public function getEncryptKeyFile(){
        return $this->info['encrypt_key_file'];
    }

    /**
     * 总时长
     */
    public function getTotalTime(){
        return $this->info['total_time'];
    }

    public function getFileInfo(){
        return $this->file_info;
    }

    public function getFileName(){
        return $this->file_name;
    }


    public function getInfo(){
        return $this->info;
    }

    public function setInfo($info){
        $this->info = array_merge($this->info, $info);
        return $this;
    }

    /**
     * 设置内容
     */
    public function setContent(string $content){
        $this->readByLine(explode("\n", $content));
        $this->check();
        $this->content = $content;
        return $this;
    }
    
    /**
     * 读取内容
     */
    public function getContent(){
        if($this->content === null){
            if(!is_string($this->file_name) || $this->file_name === '' || strpos($this->file_name, "\0") !== false){
                throw new SourceException('File name is empty or invalid, nothing to read');
            }
            $content = @file_get_contents($this->file_name);
            if($content === false){
                throw new SourceException($this->describeReadFailure());
            }
            $this->content = $content;
        }
        return $this->content;
    }

    /**
     * 读取失败时给出可定位的原因，而不是被后续的 check() 误报成"不是 m3u8"
     */
    protected function describeReadFailure(){
        $file = $this->file_name;
        if(!is_string($file) || $file === ''){
            return "File name is empty, nothing to read";
        }
        if(preg_match('/^https?\:/i', $file)){
            return "File : {$file} cannot be fetched (request failed or returned no content)";
        }
        if(!file_exists($file)){
            return "File : {$file} does not exist";
        }
        if(!is_readable($file)){
            return "File : {$file} is not readable";
        }
        return "File : {$file} cannot be read";
    }

    /**
     * 以行读取内容
     */
    public function getContentAsLines(){
        if($this->content_lines === null){
            $content = $this->getContent();
            $content_lines = explode("\n", $content);
            $this->readByLine($content_lines);
            unset($content, $content_lines);
        }
        return $this->content_lines;
    }

    /**
     * 设置行内容
     */
    public function setContentLines(array $lines){
        $this->readByLine($lines);
        $this->check();
        $this->content = implode("\n", $lines);
        return $this;
    }

    /**
     * 是否为m3u8格式
     */
    protected function check(){
        $lines = $this->getContentAsLines();
        if(isset($lines[0]) && $lines[0]['content'] == "#EXTM3U"){
            return true;
        }
        return false;
    }

    protected function readByLine(array $content_lines){
        $this->content_lines = [];
        $parse = new Parse($this);
        foreach ($content_lines as $key => $line) {
            $line = trim($line ?? '');
            if($line){
                $this->content_lines[] = $parse->analyse($line);
            }
        }
        unset($content_lines, $key, $line, $parse);
        return $this;
    }

}

