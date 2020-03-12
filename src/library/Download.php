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
            mkdir($dir, 755, true);
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
                preg_match('/\#EXTINF:(.*?)\,/', $value['content'], $res);
                $downloadedTime += doubleval($res[1]);
                $downloadedProcess = round($downloadedTime / $totalTime, 4) * 100;
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

