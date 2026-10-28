<?php
/*
 * @Author        : turbo 664120459@qq.com
 * @Date          : 2026-10-25 23:42:50
 * @LastEditors   : turbo 664120459@qq.com
 * @LastEditTime  : 2026-10-28 22:44:56
 * @FilePath      : /turbo-m3u8/tests/M3u8Test.php
 * @Description   : 
 * 
 * Copyright (c) 2026 by ${git_name}, All Rights Reserved. 
 */

namespace Tests\TurboM3u8;

use TurboM3u8\library\Read;
use TurboM3u8\M3u8;
use TurboM3u8\library\Util;
use TurboM3u8\library\Build;

class M3u8Reader extends Read
{
    public function setContentLinesValue(array $list)
    {
        $this->content_lines = $list;
        return $this;
    }
}


class M3u8Test extends TestCase
{
    private function source(string $fixture = 'plain.m3u8'): M3u8
    {
        return M3u8::input($this->fixture($fixture));
    }

    /**
     * 回归：input() 不再按文件名缓存实例。
     * 修复前 self::$instance[md5($file)] 会让同一文件的第二次 input()
     * 拿到上一次 clip/insert/merge 之后的残留对象。
     */
    public function testInputReturnsNewInstanceEachTime(): void
    {
        $first = $this->source();
        $second = $this->source();

        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->getReader(), $second->getReader());
    }

    /**
     * 端到端锁定同一个回归：破坏性操作之后重新 input，
     * 必须读到磁盘上的原始内容，而不是被裁过的内存副本。
     */
    public function testInputDoesNotLeakClipStateBetweenCalls(): void
    {
        $first = $this->source();
        $this->assertSame(35.5, $first->getInfo()['total_time']);

        $first->clip(0, 5);

        $second = $this->source();
        $this->assertSame(35.5, $second->getInfo()['total_time']);

        $out = $this->tmpDir() . '/second.m3u8';
        $second->save($out);

        $content = file_get_contents($out);
        $this->assertStringContainsString('seg/0004.ts', $content, '重新 input 后应仍持有完整的原始播放列表');
        $this->assertStringContainsString('seg/0001.ts', $content);
    }

    public function testGetInfoReturnsPlaylistMetadata(): void
    {
        $info = $this->source('aes.m3u8')->getInfo();

        $this->assertSame('AES-128', $info['encrypt_method']);
        $this->assertSame('key/a.key', $info['encrypt_key_file']);
        $this->assertSame(20.0, $info['total_time']);
    }

    public function testReaderIsLazilyCreatedAndMemoised(): void
    {
        $obj = $this->source();

        $this->assertNull($obj->file_reader);
        $reader = $obj->getReader();
        $this->assertInstanceOf(Read::class, $reader);
        $this->assertSame($reader, $obj->getReader());
    }

    public function testFluentChainReturnsSelf(): void
    {
        $obj = $this->source();

        $this->assertSame($obj, $obj->clip(0, 5));
        $this->assertSame($obj, $obj->insert(10, 'ad.ts', 5));
        $this->assertSame($obj, $obj->merge($this->fixture('aes.m3u8')));
    }

    public function testClipThenSaveRoundTrip(): void
    {
        $out = $this->tmpDir() . '/clip.m3u8';

        $this->source()->clip(0, 5)->save($out);

        $content = file_get_contents($out);
        $this->assertStringContainsString('#EXTM3U', $content);
        $this->assertStringContainsString('seg/0001.ts', $content);
        $this->assertStringNotContainsString('seg/0004.ts', $content);
        $this->assertStringEndsWith('#EXT-X-ENDLIST', $content);
    }

    public function testClipWithNullDurationKeepsTail(): void
    {
        $out = $this->tmpDir() . '/tail.m3u8';

        $this->source()->clip(10, null)->save($out);

        $content = file_get_contents($out);
        $this->assertStringContainsString('seg/0004.ts', $content, 'duration 为 null 应裁到结尾');
    }

    public function testSaveRewritesSegmentPathsRelativeToSource(): void
    {
        $out = $this->tmpDir() . '/rewritten.m3u8';

        $this->source()->save($out);

        $content = file_get_contents($out);
        $this->assertStringContainsString($this->fixtureDir() . '/seg/0001.ts', $content);
    }

    public function testSaveWithEmptyPrefixPathKeepsRelativePaths(): void
    {
        $out = $this->tmpDir() . '/relative.m3u8';

        // README 中 merge 后 save($file, '') 的用法：不拼接源目录，输出以 / 开头的路径
        $this->source()->save($out, '');

        $this->assertStringContainsString("\n/seg/0001.ts", file_get_contents($out));
    }

    public function testSaveWithoutFileNameWritesBackToSource(): void
    {
        // tmpDir() 每次调用都会新建唯一目录，必须只取一次
        $dir = $this->tmpDir();
        $copy = $dir . '/copy.m3u8';
        copy($this->fixture('plain.m3u8'), $copy);

        $this->assertTrue(M3u8::input($copy)->save());

        $content = file_get_contents($copy);
        $this->assertStringContainsString($dir, $content);
        $this->assertStringNotContainsString("\nseg/0001.ts", $content);
    }

    public function testSaveReturnsTrueOnSuccess(): void
    {
        $this->assertTrue($this->source()->save($this->tmpDir() . '/ok.m3u8'));
    }

    public function testMergeReplacesInternalReader(): void
    {
        $obj = $this->source();
        $before = $obj->getReader();

        $obj->merge($this->fixture('aes.m3u8'));

        $this->assertNotSame($before, $obj->getReader());
        $this->assertSame(55.5, $obj->getInfo()['total_time']);
    }

    public function testInsertThenSaveRoundTrip(): void
    {
        $out = $this->tmpDir() . '/insert.m3u8';

        $this->source()->insert(10, 'ad.ts', 5)->save($out);

        $content = file_get_contents($out);
        $this->assertStringContainsString('#EXTINF:5,', $content);
        $this->assertStringContainsString($this->fixtureDir() . '/ad.ts', $content);
        $this->assertStringContainsString('#EXT-X-DISCONTINUITY', $content);
    }

    public function testConstructorAcceptsFileDirectly(): void
    {
        $obj = new M3u8($this->fixture('plain.m3u8'));

        $this->assertSame($this->fixture('plain.m3u8'), $obj->file_name);
        $this->assertSame(35.5, $obj->getInfo()['total_time']);
    }
}
