<?php

namespace TurboM3u8\library;

use TurboM3u8\library\Read;

/**
 * 裁切m3u8
 */
class Clip{

    protected $reader = null;

    protected $startTime = 0;
    
    protected $duration = null;
    
    protected $m3u8ContentLinesArr = [];

    public function __construct( Read &$reader, int $startTime = 0, int $duration = null)
    {
        $this->reader    = $reader;
        $this->startTime = $startTime;
        $this->duration  = $duration;
    }

    /**
     * 裁切
     * @param $method 方法 left 时间左偏 right 时间右偏移
     */
    public function clip($method = 'left'){
        $method = strtolower($method);
        $contents = $this->reader->getContentAsLines();
        $duration = 0;
        $lines = [];
        foreach ($contents as $key => $value) {
            if(){
                
            }
        }
    }

}

