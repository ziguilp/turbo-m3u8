<?php

namespace Tests\TurboM3u8;

use TurboM3u8\library\Util;

class UtilTest extends TestCase
{
    public function testSaveReturnsFalseForInvalidPaths(): void
    {
        foreach (['', null, "invalid\0.m3u8"] as $file) {
            $this->assertFalse(Util::save($file, 'hello'));
        }
    }

    public function testNullPostFieldDoesNotProduceDeprecation(): void
    {
        try {
            Util::curl('http://127.0.0.1:1/index.m3u8', ['token' => null]);
            $this->fail('Connection to the closed port should fail');
        } catch (\Exception $e) {
            // 应到达 cURL 的连接错误，而不是提前产生 Deprecated 异常。
            $this->assertSame(\Exception::class, get_class($e));
        }
    }

    public function testSaveWritesContent(): void
    {
        $file = $this->tmpDir() . '/out.m3u8';

        $this->assertTrue(Util::save($file, "#EXTM3U\n#EXT-X-ENDLIST"));
        $this->assertStringEqualsFile($file, "#EXTM3U\n#EXT-X-ENDLIST");
    }

    public function testSaveOverwritesExistingContent(): void
    {
        $file = $this->tmpDir() . '/out.m3u8';
        Util::save($file, 'aaaaaaaaaaaaaaaaaaaa');

        $this->assertTrue(Util::save($file, 'bb'));
        $this->assertStringEqualsFile($file, 'bb');
    }

    /**
     * 回归：fopen 失败时不能把 false 交给 flock。
     * 修复前 PHP 8 抛 TypeError: flock(): Argument #1 ($stream) must be of type resource, false given
     * （PHP 7 只是 warning），导致整个进程 fatal。
     */
    public function testSaveReturnsFalseWhenDirectoryIsMissing(): void
    {
        $this->assertFalse(Util::save($this->tmpDir() . '/no-such-dir/x.m3u8', 'hello'));
    }

    public function testSaveReturnsFalseWhenPathIsNotWritable(): void
    {
        $this->assertFalse(Util::save('/definitely-not-writable/x.m3u8', 'hello'));
    }

    public function testParseKeyLine(): void
    {
        $this->assertSame(
            ['METHOD' => 'AES-128', 'URI' => 'key/a.key', 'IV' => '0x01'],
            Util::parseKeyLine('#EXT-X-KEY:METHOD=AES-128,URI="key/a.key",IV=0x01')
        );
    }

    public function testParseKeyLineStripsQuotesFromUri(): void
    {
        $parsed = Util::parseKeyLine('#EXT-X-KEY:METHOD=AES-128,URI="https://cdn.example.com/k.key"');

        $this->assertSame('https://cdn.example.com/k.key', $parsed['URI']);
    }

    public function testParseKeyLineWithMethodNone(): void
    {
        // METHOD=NONE 也是一个合法的键值对，会被解析出来（而不是返回空数组）
        $this->assertSame(['METHOD' => 'NONE'], Util::parseKeyLine('#EXT-X-KEY:METHOD=NONE'));
    }

    public function testParseKeyLineIgnoresMalformedPairs(): void
    {
        $this->assertSame([], Util::parseKeyLine('#EXT-X-KEY:'));
        $this->assertSame(['METHOD' => 'AES-128'], Util::parseKeyLine('#EXT-X-KEY:METHOD=AES-128,broken'));
    }

    public function testReplaceKeyStrOmitsEmptyIv(): void
    {
        $this->assertSame(
            '#EXT-X-KEY:METHOD=AES-128,URI="new.key"',
            Util::replaceKeyStr('#EXT-X-KEY:METHOD=AES-128,URI="old.key"', [
                'encrypt_method' => 'AES-128',
                'iv' => null,
                'encrypt_key_file' => 'new.key',
            ])
        );
    }

    public function testReplaceKeyStrIncludesIv(): void
    {
        $this->assertSame(
            '#EXT-X-KEY:METHOD=AES-128,URI="new.key",IV=0x01',
            Util::replaceKeyStr('#EXT-X-KEY:METHOD=AES-128,URI="old.key"', [
                'encrypt_method' => 'AES-128',
                'iv' => '0x01',
                'encrypt_key_file' => 'new.key',
            ])
        );
    }

    public function testLineTypePredicates(): void
    {
        $this->assertTrue((bool)Util::isTsFileLine('seg/0001.ts'));
        $this->assertTrue((bool)Util::isTsFileLine('http://cdn.example.com/a.ts'));
        $this->assertFalse((bool)Util::isTsFileLine('#EXTINF:10.000,'));
        $this->assertFalse((bool)Util::isTsFileLine('#EXTM3U'));

        $this->assertTrue((bool)Util::isDefineLine('#EXTM3U'));
        $this->assertFalse((bool)Util::isDefineLine('#EXT-X-VERSION:3'));

        $this->assertTrue((bool)Util::isEndLine('#EXT-X-ENDLIST'));
        $this->assertFalse((bool)Util::isEndLine('#EXTINF:10.000,'));

        $this->assertTrue((bool)Util::isKeyLine('#EXT-X-KEY:METHOD=NONE'));
        $this->assertTrue((bool)Util::isVersionLine('#EXT-X-VERSION:3'));
        $this->assertTrue((bool)Util::isTsTimeLine('#EXTINF:10.000,'));
    }

    public function testGetEndStr(): void
    {
        $this->assertSame('#EXT-X-ENDLIST', Util::getEndStr());
    }

    public function testLineTypeConstants(): void
    {
        $this->assertSame('DEFINED', Util::LINE_TYPE_DEFINED);
        $this->assertSame('VERSION', Util::LINE_TYPE_VERSION);
        $this->assertSame('TARGETDURATION', Util::LINE_TYPE_TARGETDURATION);
        $this->assertSame('SEQUENCE', Util::LINE_TYPE_SEQUENCE);
        $this->assertSame('KEY', Util::LINE_TYPE_KEY);
        $this->assertSame('INF', Util::LINE_TYPE_INF);
        $this->assertSame('END', Util::LINE_TYPE_END);
        $this->assertSame('TSFILE', Util::LINE_TYPE_TSFILE);
    }

    public function testCurlWithEmptyUrlReturnsEmptyString(): void
    {
        $this->assertSame('', Util::curl(''));
        $this->assertSame('', Util::curl(null));
    }

    public function testCurlThrowsOnUnreachableHost(): void
    {
        $this->expectException(\Exception::class);

        // 保留域名但指向不可路由地址，避免依赖外网连通性
        Util::curl('http://127.0.0.1:1/index.m3u8');
    }
}
