<?php

namespace Tests\TurboM3u8;

use TurboM3u8\library\Insert;
use TurboM3u8\library\Read;

class InsertTest extends TestCase
{
    private function reader(string $fixture = 'plain.m3u8'): Read
    {
        return new Read($this->fixture($fixture));
    }

    public function testInsertsAdBeforeTargetSegment(): void
    {
        $reader = $this->reader();
        (new Insert($reader))->add(10, 'ad.ts', 5)->insert();

        $lines = $this->linesOf($reader);
        $adIndex = array_search('ad.ts', $lines, true);

        $this->assertNotFalse($adIndex, '广告分片应被插入');
        $this->assertSame('#EXTINF:5,', $lines[$adIndex - 1], '广告前应有对应的 EXTINF');
        $this->assertSame('#EXT-X-DISCONTINUITY', $lines[$adIndex + 1], '广告后应接 DISCONTINUITY');
    }

    public function testInsertUpdatesTotalTime(): void
    {
        $reader = $this->reader();
        (new Insert($reader))->add(10, 'ad.ts', 5)->insert();

        $this->assertSame(40.5, $reader->getTotalTime());
    }

    public function testInsertKeepsOriginalSegments(): void
    {
        $reader = $this->reader();
        (new Insert($reader))->add(10, 'ad.ts', 5)->insert();

        $lines = $this->linesOf($reader);

        foreach (['seg/0001.ts', 'seg/0002.ts', 'seg/0003.ts', 'seg/0004.ts'] as $seg) {
            $this->assertContains($seg, $lines);
        }
        $this->assertSame('#EXTM3U', $lines[0]);
        $this->assertSame('#EXT-X-ENDLIST', end($lines));
    }

    public function testInsertWithoutAddIsNoop(): void
    {
        $reader = $this->reader();
        $result = (new Insert($reader))->insert();

        $this->assertSame($reader, $result);
        $this->assertSame(35.5, $reader->getTotalTime());
        $this->assertCount(13, $reader->getContentAsLines());
    }

    public function testAddIsChainable(): void
    {
        $reader = $this->reader();
        $insert = new Insert($reader);

        $this->assertSame($insert, $insert->add(10, 'ad1.ts', 5));
    }

    public function testInsertPointBeyondDurationIsIgnored(): void
    {
        $reader = $this->reader();
        (new Insert($reader))->add(9999, 'ad.ts', 5)->insert();

        $this->assertNotContains('ad.ts', $this->linesOf($reader));
        $this->assertSame(35.5, $reader->getTotalTime());
    }

    public function testInsertOnEncryptedPlaylistKeepsKeyLine(): void
    {
        $reader = $this->reader('aes.m3u8');
        (new Insert($reader))->add(10, 'ad.ts', 5)->insert();

        $this->assertContains(
            '#EXT-X-KEY:METHOD=AES-128,URI="key/a.key",IV=0x00000001',
            $this->linesOf($reader)
        );
    }
}
