<?php

namespace TurboM3u8;
use TurboM3u8\library\Read;
use TurboM3u8\library\Build;

class M3u8{
    
    static $instance = [];

    public $file_name = null;

    public $file_reader = null;

    public function __construct($file)
    {
        $this->file_name = $file;
    }

    /**
     * 输入m3u8
     */
    static public function input($file){
        $hash = md5($file);
        if(!isset(self::$instance[$hash])){
            self::$instance[$hash] = new self($file);
        }
        return self::$instance[$hash];
    }

    public function getReader(){
        if(!$this->file_reader){
            $this->file_reader = new Read($this->file_name);
        }
        return $this->file_reader;
    }

    public function getInfo(){

        return $this->getReader()->getInfo();
    }

    public function clip(int $starttime, int $duration = null){

        return $this;
    }

    public function insert(int $starttime, $file){

        return $this;
    }

    public function build(){

        return $this;
    }
}