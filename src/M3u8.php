<?php

namespace TurboM3u8;

use TurboM3u8\library\Read;
use TurboM3u8\library\Build;
use TurboM3u8\library\Util;
use TurboM3u8\library\Clip;
use TurboM3u8\library\Insert;
use TurboM3u8\library\Download;

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

    public function clip(int $starttime = 0, int $duration = null, $method = 'left'){
        (new Clip($this->getReader(), $starttime, $duration))->clip($method);
        return $this;
    }

    public function download($dir){
        (new Download($this->getReader()))->setSaveDir($dir)->download();
        return $this;
    }

    public function insert(int $starttime, $file){

        return $this;
    }

    public function save($file_name = null){
        if(!$file_name){
            $file_name = $this->file_name;
        }
        $build =  (new Build())->parse($this->file_name);
        $content = $build->buildM3u8($this->getReader());
        return Util::save($file_name, $content);
    }
}