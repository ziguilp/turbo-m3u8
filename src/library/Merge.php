<?php

namespace TurboM3u8\library;

use TurboM3u8\Exception\ParamException;

/**
 * m3u8合并，合并文件的信息将以第一个文件为准【注意合并的加密方式和加密密钥应一致】
 */
class Merge{

    public static function merge(Read ...$readers){
        if(empty($readers)){
            throw new ParamException("Files cannot be empty");
        }
        $lastKey = count($readers) - 1;
        if($lastKey < 1){
            throw new ParamException("Files Count cannot be 1");
        }
        $newContentLines = [];
        $currentKey = 0;
        foreach ($readers as $key => $reader) {
            $builder = (new Build())->parse($reader->getFileName());
            $newLines = $builder->buildM3u8($reader, true);
            $reader->setContentLines($newLines);
            if($key > $currentKey){
                $currentKey = $key;
                $newContentLines[] = "#EXT-X-DISCONTINUITY";
            }
            foreach ($reader->getContentAsLines() as $m3u8 => $line) {
                if($key == 0){
                    if($line['type'] != Util::LINE_TYPE_END){
                        $newContentLines[] = $line['content'];
                    }
                }else if($key == $lastKey){
                    if($line['type'] == Util::LINE_TYPE_INF || $line['type'] == Util::LINE_TYPE_TSFILE || $line['type'] == Util::LINE_TYPE_END){
                        $newContentLines[] = $line['content'];
                    }
                }else{
                    if($line['type'] == Util::LINE_TYPE_INF || $line['type'] == Util::LINE_TYPE_TSFILE){
                        $newContentLines[] = $line['content'];
                    }
                }
            }
        }
        unset($readers, $reader, $lastKey, $key, $m3u8, $line);
        $newreader = new Read();
        $newreader->setContentLines($newContentLines);
        unset($newContentLines);
        return $newreader;
    }
    
    public static function mergeFiles(String ...$files){
        $readers = [];

        foreach ($files as $key => $value) {
            $readers[] = new Read($value);
        }

        return call_user_func_array('\TurboM3u8\library\Merge::merge', $readers);
    }
}