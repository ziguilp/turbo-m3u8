<?php

namespace TurboM3u8\library;

use TurboM3u8\Exception\ParamException;

/**
 * 下载m3u8
 */
class Download{
    protected $reader = null;

    protected $save_dir = null;

    protected $process_callback = null;

    public function __construct( Read $reader, $dir = null)
    {
        $this->reader    = $reader;
        if($dir){
            $this->setSaveDir($dir);
        }
    }

    public function setProcess($process_callback){
        if(is_callable($process_callback)){
            $this->process_callback = $process_callback;
        }
        return $this;
    }

    public function setSaveDir($dir){
        if(!is_dir($dir)){
            if(!@mkdir($dir, 0755, true) && !is_dir($dir)){
                throw new ParamException("Cannot create directory: {$dir}");
            }
        }
        $this->save_dir = $dir;
        return $this;
    }

    /**
     * 下载
     */
    public function download(){
        if(!preg_match('/^https?\:/',$this->reader->getFileName())){
            throw new ParamException('不支持下载的资源');
        }
        $build =  (new Build())->parse($this->reader->getFileName());
        // $originConetent = $this->reader->getContent();
        // $content = $build->buildM3u8($this->reader);
        // $this->reader->setContent($content);
        $lines = $this->reader->getContentAsLines();
        $config = $this->reader->getInfo();
        $newContent = [];
        $totalTime = $config['total_time'];
        $downloadedTime = 0;
        $downloadedProcess = 0;
        foreach ($lines as $key => $value) {
            if($value['type'] == Util::LINE_TYPE_TSFILE)
            {
                $newContent[] = $this->downloadItem($build->build($value['content']));

            }elseif($value['type'] == Util::LINE_TYPE_KEY){
                
                $newContent[] = Util::replaceKeyStr($value['content'],[
                    'encrypt_method' => $config['encrypt_method'] ,
                    'iv' => $config['iv'],
                    'encrypt_key_file' => $this->downloadItem($build->build($config['encrypt_key_file'])),
                ]);
            }else{
                $newContent[] = $value['content'];
            }
            if( $value['type'] == Util::LINE_TYPE_INF){
                if(preg_match('/\#EXTINF:(.*?)\,/', $value['content'], $res) && isset($res[1])){
                    $downloadedTime += doubleval($res[1]);
                }
                $downloadedProcess = $this->calcProcess($downloadedTime, $totalTime);
                if($this->process_callback && is_callable($this->process_callback)){
                    call_user_func($this->process_callback, $downloadedProcess);
                }
            }
        }

        unset($key, $value);

        $filename = $this->save_dir.'/index.m3u8';
        $filename = str_replace('//','/',$filename);

        $res = Util::save($filename, implode("\n", $newContent));

        if(!$res){
            return false;
        }

        return $filename;
    }

    /**
     * 下载进度百分比
     *
     * $totalTime 为 0 时（空 m3u8 或分片时长全为 0）不能做除法：
     * PHP 7 只是 warning 并得到 INF，PHP 8 直接抛 DivisionByZeroError。
     */
    protected function calcProcess($downloadedTime, $totalTime){
        if($totalTime <= 0){
            return 0;
        }
        return round($downloadedTime / $totalTime, 4) * 100;
    }

    public function downloadItem($item){
        $pathInfo = pathinfo($item);
        if(strpos($pathInfo['basename'],'?')!== false){
            $pathInfo['basename'] = explode("?",$pathInfo['basename'])[0];
        }
        $file = $this->save_dir.'/'.$pathInfo['basename'];
        if(file_exists($file)) return $pathInfo['basename'];
        $content = Util::curl($item);
        $save = Util::save($file, $content);
        
        return $save ? $pathInfo['basename'] : false;
    }
}

