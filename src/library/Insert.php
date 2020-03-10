<?php

namespace TurboM3u8\library;

/**
 * m3u8插入
 */
class Insert{
    protected $reader = null;

    protected $startTime = 0;
    
    protected $duration = null;
    
    protected $m3u8ContentLinesArr = [];

    public function __construct( Read $reader, int $startTime = 0, int $duration = null)
    {
        $this->reader    = $reader;
        $this->startTime = $startTime;
        $this->duration  = $duration;
    }

    /**
     * 裁切
     * @param $method 起始点偏移方法 left 时间左偏 right 时间右偏移
     */
    public function clip($method = 'left'){

        if($this->startTime == 0 && !$this->duration){
            return $this->reader;
        }

        $method = strtolower($method);
        $contents = $this->reader->getContentAsLines();
        $duration = 0;
        $startTime = 0;
        $lines = [];
        foreach ($contents as $key => $line) {
            #非结束语句而且在时间允许的范围内有效
            if( $line['totaltime'] == 0  ){
                $lines[] = $line['content'];
            }else{
                
                if($startTime == 0){
                    $startTime = doubleval($line['totaltime']);
                }

                $duration = doubleval($line['totaltime']) - $startTime;
                
                if($duration >= $this->duration && ($line['type'] == Util::LINE_TYPE_TSFILE || $line['type'] == Util::LINE_TYPE_END )){
                    $lines[] = $line['content'];
                    break;
                }

                if( $line['totaltime'] >= $this->startTime ){
                    $lines[] = $line['content'];
                }
                
            }
        }

        #添加结束语句
        $count = count($lines);
        if($count > 1){
            $lastLine = $lines[$count - 1];
            if(!Util::isEndLine($lastLine)){
                $lines[] = Util::getEndStr();
            }
        }
        $this->reader->setContentLines($lines);
        return $this->reader;
    }
}

