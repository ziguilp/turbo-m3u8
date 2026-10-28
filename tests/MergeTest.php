<?php

namespace Tests\TurboM3u8;

use TurboM3u8\Exception\ParamException;
use TurboM3u8\library\Merge;
use TurboM3u8\library\Read;

class MergeTest extends TestCase
{
    public function testMergesTwoReaders(): void
    {
        $reader = Merge::merge(
            new Read($this->fixture('plain.m3u8')),
            new Read($this->fixture('aes.m3u8'))
        );

        $lines = $this->linesOf($reader);

        // 第一个文件的头部与全部分片
        $this->assertSame('#EXTM3U', $lines[0]);
        $this->assertSame('#EXT-X-VERSION:3', $lines[1]);
        $this->assertContains($this->fixtureDir() . '/seg/0001.ts', $lines);
        $this->assertContains($this->fixtureDir() . '/seg/0004.ts', $lines);
        // 第二个文件的分片
        $this->assertContains($this->fixtureDir() . '/a0001.ts', $lines);
        $this->assertContains($this->fixtureDir() . '/a0002.ts', $lines);
        $this->assertSame('#EXT-X-ENDLIST', end($lines));
    }

    public function testMergeInsertsDiscontinuityBetweenSources(): void
    {
        $reader = Merge::merge(
            new Read($this->fixture('plain.m3u8')),
            new Read($this->fixture('aes.m3u8'))
        );

        $lines = $this->linesOf($reader);
        $discontinuity = array_keys(
            array_filter($lines, function ($l) { return $l === '#EXT-X-DISCONTINUITY'; })
        );

        $this->assertCount(1, $discontinuity, '两个文件之间应只有一个 DISCONTINUITY');
        // 且位于第一个文件的最后一个分片之后
        $this->assertSame(
            $this->fixtureDir() . '/seg/0004.ts',
            $lines[$discontinuity[0] - 1]
        );
    }

    public function testMergeTotalTimeIsSumOfSources(): void
    {
        $reader = Merge::merge(
            new Read($this->fixture('plain.m3u8')),
            new Read($this->fixture('aes.m3u8'))
        );

        $this->assertSame(55.5, $reader->getTotalTime());
    }

    public function testMergeKeepsOnlyFirstFileHeader(): void
    {
        $reader = Merge::merge(
            new Read($this->fixture('plain.m3u8')),
            new Read($this->fixture('aes.m3u8'))
        );

        $lines = $this->linesOf($reader);

        $this->assertSame(1, count(array_filter($lines, function ($l) { return $l === '#EXTM3U'; })));
        // 第二个文件的 KEY 行不应混入（合并要求加密方式一致，以第一个文件为准）
        $this->assertNotContains('#EXT-X-KEY:METHOD=AES-128,URI="' . $this->fixtureDir() . '/key/a.key",IV=0x00000001', $lines);
    }

    public function testMergeFilesAcceptsVariadicPaths(): void
    {
        $reader = Merge::mergeFiles(
            $this->fixture('plain.m3u8'),
            $this->fixture('aes.m3u8')
        );

        $this->assertInstanceOf(Read::class, $reader);
        $this->assertSame(55.5, $reader->getTotalTime());
    }

    public function testMergeThreeSources(): void
    {
        $reader = Merge::mergeFiles(
            $this->fixture('plain.m3u8'),
            $this->fixture('aes.m3u8'),
            $this->fixture('aes.m3u8')
        );

        $lines = $this->linesOf($reader);

        $this->assertSame(75.5, $reader->getTotalTime());
        $this->assertSame(
            2,
            count(array_filter($lines, function ($l) { return $l === '#EXT-X-DISCONTINUITY'; }))
        );
        $this->assertSame('#EXT-X-ENDLIST', end($lines));
    }

    public function testMergeSingleReaderThrows(): void
    {
        $this->expectException(ParamException::class);
        $this->expectExceptionMessage('cannot be 1');

        Merge::merge(new Read($this->fixture('plain.m3u8')));
    }

    public function testMergeWithoutReaderThrows(): void
    {
        $this->expectException(ParamException::class);
        $this->expectExceptionMessage('cannot be empty');

        Merge::merge();
    }
}
