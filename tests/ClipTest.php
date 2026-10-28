<?php

namespace Tests\TurboM3u8;

use TurboM3u8\library\Clip;
use TurboM3u8\library\Read;

class ClipTest extends TestCase
{
    private function reader(string $fixture = 'plain.m3u8'): Read
    {
        return new Read($this->fixture($fixture));
    }

    public function testZeroStartAndZeroDurationIsNoop(): void
    {
        $reader = $this->reader();
        (new Clip($reader, 0, 0))->clip();

        $this->assertSame(35.5, $reader->getTotalTime());
        $this->assertCount(13, $reader->getContentAsLines());
    }

    public function testDefaultArgumentsAreAccepted(): void
    {
        $reader = $this->reader();
        (new Clip($reader))->clip();

        $this->assertSame(35.5, $reader->getTotalTime());
    }

    /**
     * 回归：duration 为 null 表示"裁到结尾"。
     * 修复前 $duration >= null 会把 null 当 0 比较，恒为真，
     * 导致在第一个分片处就 break，clip(10, null) 只输出 1 个分片。
     */
    public function testNullDurationKeepsEverythingToTheEnd(): void
    {
        $reader = $this->reader();
        (new Clip($reader, 10, null))->clip();

        $lines = $this->linesOf($reader);

        foreach (['seg/0001.ts', 'seg/0002.ts', 'seg/0003.ts', 'seg/0004.ts'] as $seg) {
            $this->assertContains($seg, $lines, "clip(10, null) 应保留到结尾，缺失 {$seg}");
        }
        $this->assertSame('#EXT-X-ENDLIST', end($lines));
        $this->assertSame(35.5, $reader->getTotalTime());
    }

    public function testNullDurationViaPublicSignature(): void
    {
        // 覆盖 PHP 8.4 隐式 nullable 的修复点：?int $duration = null
        $reader = $this->reader();
        $clip = new Clip($reader, 0, null);

        $this->assertInstanceOf(Clip::class, $clip);
    }

    public function testDurationTruncatesAndAppendsEndList(): void
    {
        $reader = $this->reader();
        (new Clip($reader, 0, 5))->clip();

        $lines = $this->linesOf($reader);

        $this->assertContains('seg/0001.ts', $lines);
        $this->assertNotContains('seg/0004.ts', $lines);
        $this->assertSame('#EXT-X-ENDLIST', end($lines));
        $this->assertLessThan(35.5, $reader->getTotalTime());
    }

    /**
     * 注意：当前 Clip 以"第一个命中分片的累计时间"作为时长基准，
     * 因此 clip(0, 5) 实际会保留约 20s（2 个分片）。这是本次兼容性改造
     * 未触及的既有语义，此处只锁定"结果变短且以 ENDLIST 收尾"这一不变量。
     */
    public function testClipResultIsAlwaysProperlyTerminated(): void
    {
        foreach ([[0, 5], [0, 15], [10, 10], [20, 30]] as [$start, $duration]) {
            $reader = $this->reader();
            (new Clip($reader, $start, $duration))->clip();

            $lines = $this->linesOf($reader);

            $this->assertSame('#EXTM3U', $lines[0], "start={$start} duration={$duration} 首行应保留");
            $this->assertSame(
                '#EXT-X-ENDLIST',
                end($lines),
                "start={$start} duration={$duration} 应以 ENDLIST 收尾"
            );
        }
    }

    public function testClipOnEncryptedPlaylistKeepsKeyLine(): void
    {
        $reader = $this->reader('aes.m3u8');
        (new Clip($reader, 0, 5))->clip();

        $lines = $this->linesOf($reader);

        $this->assertContains('#EXT-X-KEY:METHOD=AES-128,URI="key/a.key",IV=0x00000001', $lines);
    }

    public function testClipReturnsReader(): void
    {
        $reader = $this->reader();

        $this->assertSame($reader, (new Clip($reader, 0, 5))->clip());
    }
}
