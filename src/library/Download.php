<?php

namespace TurboM3u8\library;

use TurboM3u8\Exception\ParamException;

/**
 * 下载m3u8
 */
class Download{
    protected $reader = null;

    protected $save_dir = null;

    public function __construct( Read $reader, $dir = null)
    {
        $this->reader    = $reader;
        if($dir){
            $this->setSaveDir($dir);
        }
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
        $newContent = [];
        foreach ($lines as $key => $value) {
            if($value['type'] == Util::LINE_TYPE_TSFILE)
            {
                $newContent[] = $this->downloadItem($build->build($value['content']));

            }elseif($value['type'] == Util::LINE_TYPE_KEY){
                $config = $this->reader->getInfo();
                $newContent[] = Util::replaceKeyStr($value['content'],[
                    'encrypt_method' => $config['encrypt_method'] ,
                    'iv' => $config['iv'],
                    'encrypt_key_file' => $this->downloadItem($build->build($config['encrypt_key_file'])),
                ]);
            }else{
                $newContent[] = $value['content'];
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
        $content = Util::curl($item);
        $file = $this->save_dir.'/'.$pathInfo['basename'];
        $save = Util::save($file, $content);
        
        return $save ? $pathInfo['basename'] : false;
    }
}

