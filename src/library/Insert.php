<?php

namespace TurboM3u8\library;

/**
 * m3u8插入
 */
class Insert{
    protected $reader = null;

    protected $startTime = 0;
    
    protected $duration = null;
    
    protected $addsList = [];

    public function __construct( Read $reader )
    {
        $this->reader    = $reader;
    }


    public function add(int $insertPoint, $file, $duration){
        $this->addsList[$insertPoint] = [
            'file' => $file,
            'duration' => $duration,
        ];
        return $this;
    }

    /**
     * 裁切
     * @param $method 起始点偏移方法 left 时间左偏 right 时间右偏移
     */
    public function insert($method = 'left'){
        if(empty($this->addsList)){
            return $this->reader;
        }
        $method = strtolower($method);
        $contents = $this->reader->getContentAsLines();
        $lines = [];
        foreach ($contents as $key => $line) {
            #非结束语句而且在时间允许的范围内有效
            if( $line['totaltime'] == 0  ){
                $lines[] = $line['content'];
            }else{
                if($line['type'] == Util::LINE_TYPE_INF);{
                    $insertPoint = $this->pickInserPoint($line['totaltime']);
                    if($insertPoint){
                        
                        if($contents[$key - 1]['type'] == Util::LINE_TYPE_TSFILE)
                            $lines[] = '#EXT-X-DISCONTINUITY';

                        $lines[] = "#EXTINF:".$insertPoint['duration'].",";
                        $lines[] = $insertPoint['file'];
                        $lines[] = "#EXT-X-DISCONTINUITY";
                    }
                }
                $lines[] = $line['content'];
            }
        }
        $this->reader->setContentLines($lines);
        return $this->reader;
    }

    protected function pickInserPoint($currentTotalTime){
        foreach ($this->addsList as $key => $value) {
            if( $key <= $currentTotalTime ){
                unset($this->addsList[$key]);
                return $value;
            }
        }
        return null;
    }
}

