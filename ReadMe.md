## M3U8裁切、插入广告、基本信息【支持m3u8V3以下，更高版本暂未支持】


##### 可远程下载指定m3u8【包含文件中的ts】

~~~
composer require turbo/turbo-m3u8
~~~

### 获取m3u8文件信息(文件总时长，文件加密方式及密钥地址和IV等信息)

```php
use TurboM3u8\M3u8;

$obj = M3u8::input($m3u8FilePath);

$obj->getInfo();

```

### 裁切

##### 非精准裁切，由于ts的时间精度决定

```php
$fromTime = 10;
$duration = 20;
$obj->clip($fromTime, $duration);
$obj->save($newFileName);
```

### 下载


```php
$obj->download($saveDir, function($process){
    echo "下载进度：{$process}%\n";
});
```

### 插入广告

##### 非精准插入，由于ts的精度决定，所插入广告必须和原文件一致的加密方法和密钥或者不加密

```php
$fromTime = 10;
$duration = 20;
$obj->insert( $fromTime, $adFileName, $duration);
$obj->save($newFileName);
```


