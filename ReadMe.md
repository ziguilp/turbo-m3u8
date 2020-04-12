## M3U8裁切、插入广告、基本信息、下载远程m3u8【支持m3u8V3以下，更高版本暂未支持】


### 使用
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

### 下载指定m3u8到某个文件夹【包含文件中的ts】，m3u8会自动保存为index.m3u8文件,所以该文件夹必须是给该资源独立使用的，否则面临被覆盖的风险


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

### 合并m3u8文件

##### 所合并的文件必须和原文件一致的加密方法和密钥或者不加密

```php
$file1 = '/clip_60_d43ccd5edc1b5f2c67eaae3760b7aaf2.m3u8';
$file2 = '/uploads/20200412/9bd2664d3e9df07f777acd1c0b8ae625.m3u8';
$obj = M3u8::input($file1);
$obj->merge($file2);
$obj->save('download/merge.m3u8','');
```


