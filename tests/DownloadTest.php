<?php

namespace Tests\TurboM3u8;

use ReflectionMethod;
use TurboM3u8\Exception\ParamException;
use TurboM3u8\library\Download;
use TurboM3u8\library\Read;

class DownloadTest extends TestCase
{
    private function download(string $fixture = 'plain.m3u8'): Download
    {
        return new Download(new Read($this->fixture($fixture)));
    }

    /**
     * 回归：mkdir 的权限位必须写成八进制字面量。
     * 修复前是十进制 755（= 八进制 01363），实测建出的目录权限为 0341，
     * 既不可写也不可进入，随后 Util::save() 的 fopen 直接失败。
     */
    public function testSetSaveDirCreatesDirectoryWith0755(): void
    {
        $dir = $this->tmpDir() . '/nested/save-dir';

        $this->download()->setSaveDir($dir);

        $this->assertDirectoryExists($dir);
        $this->assertSame('0755', substr(sprintf('%o', fileperms($dir)), -4));
        $this->assertTrue(is_writable($dir), '下载目录必须可写，否则分片落不了盘');
    }

    public function testSetSaveDirKeepsExistingDirectory(): void
    {
        $dir = $this->tmpDir();
        $download = $this->download();

        $this->assertSame($download, $download->setSaveDir($dir));
        $this->assertDirectoryExists($dir);
    }

    public function testConstructorAcceptsSaveDir(): void
    {
        $dir = $this->tmpDir() . '/via-ctor';
        new Download(new Read($this->fixture('plain.m3u8')), $dir);

        $this->assertDirectoryExists($dir);
    }

    public function testDownloadRejectsNonHttpSource(): void
    {
        $this->expectException(ParamException::class);
        $this->expectExceptionMessage('不支持下载的资源');

        $this->download()->setSaveDir($this->tmpDir())->download();
    }

    public function testSetProcessIgnoresNonCallable(): void
    {
        $download = $this->download();

        // 不应抛异常，非法回调被忽略
        $this->assertSame($download, $download->setProcess('this-is-not-callable'));
        $this->assertSame($download, $download->setProcess(null));
    }

    public function testSetProcessAcceptsClosure(): void
    {
        $download = $this->download();

        $this->assertSame($download, $download->setProcess(function ($p) {
            return $p;
        }));
    }

    /**
     * calcProcess 是 protected，用反射直接验证进度计算。
     */
    private function calcProcess(Download $download, $downloaded, $total)
    {
        $m = new ReflectionMethod(Download::class, 'calcProcess');
        $m->setAccessible(true);

        return $m->invoke($download, $downloaded, $total);
    }

    public function testCalcProcessReturnsPercentage(): void
    {
        $download = $this->download();

        $this->assertSame(50.0, $this->calcProcess($download, 17.75, 35.5));
        $this->assertSame(100.0, $this->calcProcess($download, 35.5, 35.5));
        $this->assertSame(0.0, $this->calcProcess($download, 0, 35.5));
    }

    /**
     * 回归：total_time 为 0 时不能做除法。
     * 修复前 PHP 8 直接抛 DivisionByZeroError（PHP 7 只是 warning + INF），
     * 空 m3u8 或分片时长全为 0 时下载流程必崩。
     */
    public function testCalcProcessWithZeroTotalTimeDoesNotDivide(): void
    {
        $download = $this->download('zero_duration.m3u8');

        $this->assertSame(0, $this->calcProcess($download, 0, 0));
        $this->assertSame(0, $this->calcProcess($download, 5, 0));
    }

    public function testCalcProcessWithNegativeTotalTimeDoesNotDivide(): void
    {
        $this->assertSame(0, $this->calcProcess($this->download(), 5, -1));
    }

    public function testZeroDurationPlaylistIsParsable(): void
    {
        $reader = new Read($this->fixture('zero_duration.m3u8'));

        $this->assertSame(0.0, $reader->getTotalTime());
    }
}
