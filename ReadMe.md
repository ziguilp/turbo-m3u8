## M3U8裁切、插入广告、基本信息、下载远程m3u8【支持m3u8V3以下，更高版本暂未支持】

### 环境要求

- PHP **7.4 ~ 8.4**（PHP 8.4 下已进行语法检查和部分冒烟验证；完整测试及跨版本矩阵仍需验证）
- `ext-curl`（仅 `download()` 需要）

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

`$duration` 传 `null`（或省略）表示**从 `$fromTime` 裁到结尾**：

```php
$obj->clip(10);          // 等价于 clip(10, null)，保留 10s 之后的全部分片
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

### 运行测试

```bash
composer install
composer test          # 等价于 phpunit
```

### 从旧版本升级（行为变更）

以下两处是**有意的行为变更**，升级前请确认调用方代码：

1. **`M3u8::input()` 不再缓存实例**

   旧实现按 `md5($file)` 缓存对象，而 `clip()` / `insert()` / `merge()` 会就地改写 reader 的内存副本。
   后果是同一文件第二次 `input()` 拿到的是上一次操作后的残留状态，而非磁盘上的原始内容 ——
   在队列 worker、Swoole 等长驻进程里必然踩到。现在每次 `input()` 都返回全新实例。

   ```php
   $a = M3u8::input($file);
   $a->clip(0, 5);                       // 破坏性操作
   $b = M3u8::input($file);              // 现在：完整原始内容；旧版：被裁过的副本
   ```

   若依赖旧的缓存行为（复用同一对象继续操作），请自行保存变量引用，不要重复调用 `input()`。

2. **`clip($start, null)` 语义修正为「裁到结尾」**

   旧实现中终止条件 `$duration >= $this->duration` 会把 `null` 折算成 `0` 参与比较，
   导致在第一个分片处就 `break`，`clip(10, null)` 只输出 1 个分片。现在会正确保留到结尾。

此外以下为不影响正常路径的缺陷修复：

- `Util::save()` 在目标目录不存在时不再抛 `TypeError`（PHP 8 下 `flock(false, ...)` 会致命），改为返回 `false`
- `Download` 进度计算在总时长为 0 时不再抛 `DivisionByZeroError`
- `Download::setSaveDir()` 的目录权限由十进制 `755`（实为八进制 `01363`，建出 `0341`）修正为 `0755`
- `Read` 读取失败时抛出真实原因（不存在 / 不可读 / 远程请求失败），不再统一误报成 "is not m3u8"
- `Read` 未设置文件时读取会抛 `SourceException`；显式设置的空内容保留在内存中，不再触发文件读取
- `Util::save()` 对空路径、含 NUL 的路径返回 `false`，避免 PHP 8 的 `ValueError`
- 行内容中的 `null` 按空行处理，POST 字段中的 `null` 按空字符串编码，避免 PHP 8.1+ 的 Deprecated

### 已知未修复项

- **`Clip` 的时长基准**：以「第一个命中分片的累计时间」为基准，因此 `clip(0, 5)` 实际会保留约 20s
  （2 个 10s 分片）。这是既有语义，本次兼容性改造未触及，测试中只锁定了「结果变短且以
  `#EXT-X-ENDLIST` 收尾」这一不变量。
- **`Read::setContent()` / `setContentLines()` 不校验内容**：两者都调用了 `check()` 却丢弃返回值，
  非法内容会被静默接受；只有 `setFile()` 会真正校验并抛 `SourceException`。
  `tests/ReadTest.php` 中以 characterization test 锁定了当前行为。
- **仅支持 m3u8 V3 及以下**，更高版本（master playlist、`#EXT-X-MAP`、`#EXT-X-BYTERANGE` 等）未支持。
