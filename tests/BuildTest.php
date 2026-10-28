<?php

namespace Tests\TurboM3u8;

use TurboM3u8\library\Build;
use TurboM3u8\library\Read;

class BuildTest extends TestCase
{
    private function localBuild(): Build
    {
        return (new Build())->parse($this->fixture('plain.m3u8'));
    }

    public function testLocalPathHasNoDomain(): void
    {
        $this->assertSame('', $this->localBuild()->getDomain());
    }

    public function testBuildResolvesRelativeSegmentPath(): void
    {
        $this->assertSame(
            $this->fixtureDir() . '/seg/0001.ts',
            $this->localBuild()->build('seg/0001.ts')
        );
    }

    public function testBuildResolvesDotSlashPath(): void
    {
        $this->assertSame(
            $this->fixtureDir() . '/seg/0002.ts',
            $this->localBuild()->build('./seg/0002.ts')
        );
    }

    public function testBuildResolvesParentDirectoryPath(): void
    {
        $this->assertSame(
            dirname($this->fixtureDir()) . '/up/0003.ts',
            $this->localBuild()->build('../up/0003.ts')
        );
    }

    public function testBuildKeepsAbsolutePathRelativeToRoot(): void
    {
        $this->assertSame('/abs/0004.ts', $this->localBuild()->build('/abs/0004.ts'));
    }

    public function testBuildLeavesAbsoluteUrlsUntouched(): void
    {
        $build = $this->localBuild();

        $this->assertSame('http://cdn.example.com/x/a.ts', $build->build('http://cdn.example.com/x/a.ts'));
        $this->assertSame('https://cdn.example.com/x/b.ts', $build->build('https://cdn.example.com/x/b.ts'));
    }

    public function testParseExtractsDomainFromUrl(): void
    {
        $build = (new Build())->parse('http://cdn.example.com/vod/2020/index.m3u8');

        $this->assertSame('http://cdn.example.com', $build->getDomain());
        $this->assertSame('http://cdn.example.com/vod/2020/a.ts', $build->build('a.ts'));
        $this->assertSame('http://cdn.example.com/vod/b.ts', $build->build('../b.ts'));
    }

    public function testSetDomainIsUsedAsPrefix(): void
    {
        $build = (new Build())->setDomain('https://cdn.example.com')->setPrefixPath('/vod/2020');

        $this->assertSame('https://cdn.example.com', $build->getDomain());
        $this->assertSame('https://cdn.example.com/vod/2020/a.ts', $build->build('a.ts'));
    }

    public function testBuildM3u8RewritesSegmentPaths(): void
    {
        $reader = new Read($this->fixture('plain.m3u8'));
        $content = $this->localBuild()->buildM3u8($reader);

        $this->assertStringContainsString($this->fixtureDir() . '/seg/0001.ts', $content);
        $this->assertStringContainsString($this->fixtureDir() . '/seg/0004.ts', $content);
        // 头部指令原样保留
        $this->assertStringContainsString('#EXTM3U', $content);
        $this->assertStringContainsString('#EXT-X-VERSION:3', $content);
        $this->assertStringContainsString('#EXT-X-ENDLIST', $content);
        // 不应再残留未解析的相对路径
        $this->assertStringNotContainsString("\nseg/0001.ts", $content);
    }

    public function testBuildM3u8RewritesKeyUri(): void
    {
        $reader = new Read($this->fixture('aes.m3u8'));
        $content = (new Build())->parse($this->fixture('aes.m3u8'))->buildM3u8($reader);

        $this->assertStringContainsString(
            '#EXT-X-KEY:METHOD=AES-128,URI="' . $this->fixtureDir() . '/key/a.key",IV=0x00000001',
            $content
        );
    }

    public function testBuildM3u8CanReturnArray(): void
    {
        $reader = new Read($this->fixture('plain.m3u8'));
        $lines = $this->localBuild()->buildM3u8($reader, true);

        $this->assertIsArray($lines);
        $this->assertSame('#EXTM3U', $lines[0]);
        $this->assertSame($this->fixtureDir() . '/seg/0001.ts', $lines[5]);
    }
}
