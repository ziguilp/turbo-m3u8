## M3U8裁切、插入广告、基本信息

~~~
composer require turbo/turbo-m3u8
~~~

### 基础使用方法

```php
use TurboM3u8\M3u8;

$obj = M3u8::input($m3u8FilePath);

$obj->getInfo();

```

### 裁切

##### 非精准裁切，由于ts的精度决定

```php
$fromTime = 10;
$duration = 20;
$obj->clip($fromTime, $duration);
$obj->save($newFileName);
```

### 下载


```php
$obj->download($saveDir);
```

### 插入广告

##### 非精准插入，由于ts的精度决定，所插入广告必须和原文件一致的加密方法和密钥或者不加密

```php
$fromTime = 10;
$duration = 20;
$obj->insert( $fromTime, $adFileName, $duration);
$obj->save($newFileName);
```


