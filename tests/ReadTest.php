<?php

namespace Tests\TurboM3u8;

use TurboM3u8\Exception\SourceException;
use TurboM3u8\library\Read;

class ReadTest extends TestCase
{
    public function testReadingWithoutFileThrowsSourceException(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('File name is empty');

        (new Read())->getContent();
    }

    public function testSetFileRejectsInvalidPaths(): void
    {
        foreach (['', null, "invalid\0.m3u8"] as $file) {
            try {
                (new Read())->setFile($file);
                $this->fail('Invalid path must be rejected');
            } catch (SourceException $e) {
                $this->assertStringContainsString('File name is empty or invalid', $e->getMessage());
            }
        }
    }

    public function testEmptyContentStaysInMemory(): void
    {
        $reader = new Read();
        $reader->setContent('');

        $this->assertSame('', $reader->getContent());
        $this->assertSame([], $reader->getContentAsLines());
        $this->assertSame([], $reader->getContentAsLines());
    }

    public function testEmptyContentLinesStayInMemory(): void
    {
        $reader = new Read();
        $reader->setContentLines([]);

        $this->assertSame('', $reader->getContent());
        $this->assertSame([], $reader->getContentAsLines());
    }

    public function testNullLinesAreIgnored(): void
    {
        $reader = new Read();
        $reader->setContentLines(['#EXTM3U', null, '#EXT-X-ENDLIST']);

        $this->assertSame(['#EXTM3U', '#EXT-X-ENDLIST'], $this->linesOf($reader));
        $this->assertSame("#EXTM3U\n\n#EXT-X-ENDLIST", $reader->getContent());
    }

    public function testParsesPlainPlaylistInfo(): void
    {
        $info = (new Read($this->fixture('plain.m3u8')))->getInfo();

        $this->assertSame('3', $info['version']);
        $this->assertSame(35.5, $info['total_time']);
        $this->assertSame('', $info['encrypt_method']);
        $this->assertSame('', $info['encrypt_key_file']);
        $this->assertNull($info['iv']);
    }

    public function testParsesEncryptionInfo(): void
    {
        $info = (new Read($this->fixture('aes.m3u8')))->getInfo();

        $this->assertSame('AES-128', $info['encrypt_method']);
        // URI 的引号应被剥掉
        $this->assertSame('key/a.key', $info['encrypt_key_file']);
        $this->assertSame('0x00000001', $info['iv']);
        $this->assertSame(20.0, $info['total_time']);
    }

    public function testTotalTimeAccumulatesAcrossSegments(): void
    {
        $reader = new Read($this->fixture('plain.m3u8'));
        $reader->getContentAsLines();

        $this->assertSame(35.5, $reader->getTotalTime());
    }

    public function testGettersExposeFileNameAndFileInfo(): void
    {
        $reader = new Read($this->fixture('plain.m3u8'));

        $this->assertSame($this->fixture('plain.m3u8'), $reader->getFileName());
        $this->assertSame('m3u8', $reader->getFileInfo()['extension']);
        $this->assertSame('plain', $reader->getFileInfo()['filename']);
    }

    /**
     * 回归：读取失败必须报出真实原因。
     * 修复前 file_get_contents 返回 false 被当成空内容，最终误报成 "is not m3u8"，
     * 把"文件不存在/无权限/远程 404"全部掩盖掉。
     */
    public function testMissingFileReportsRealReason(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('does not exist');

        (new Read($this->fixture('no-such-file.m3u8')))->getInfo();
    }

    public function testNonM3u8FileThrowsSourceException(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('is not m3u8');

        (new Read($this->fixture('not_m3u8.txt')))->getInfo();
    }

    public function testSetContentAcceptsRawString(): void
    {
        $reader = new Read();
        $reader->setContent(file_get_contents($this->fixture('plain.m3u8')));

        $this->assertSame(35.5, $reader->getTotalTime());
        $this->assertSame('#EXTM3U', $this->linesOf($reader)[0]);
    }

    public function testSetContentLinesAcceptsArray(): void
    {
        $reader = new Read();
        $reader->setContentLines([
            '#EXTM3U',
            '#EXT-X-TARGETDURATION:10',
            '#EXTINF:4.000,',
            'a.ts',
            '#EXTINF:6.000,',
            'b.ts',
            '#EXT-X-ENDLIST',
        ]);

        $this->assertSame(10.0, $reader->getTotalTime());
        $this->assertSame('#EXTM3U', $reader->getContentAsLines()[0]['content']);
    }

    /**
     * 已知缺陷（characterization test，锁定当前行为，不是期望行为）：
     * setContent() / setContentLines() 都调用了 check() 但丢弃其返回值，
     * 因此非法内容会被静默接受；只有 setFile() 会真正校验并抛 SourceException。
     *
     * 若将来修复为强制校验，本用例会失败 —— 届时应有意识地更新它。
     */
    public function testSetContentDoesNotValidateContent(): void
    {
        $reader = new Read();

        $this->assertSame($reader, $reader->setContent("hello\nworld\n"));
        $this->assertSame("hello\nworld\n", $reader->getContent());
    }

    public function testSetContentLinesDoesNotValidateContent(): void
    {
        $reader = new Read();

        $this->assertSame($reader, $reader->setContentLines(['hello', 'world']));
        $this->assertSame("hello\nworld", $reader->getContent());
    }

    public function testContentIsCachedBetweenCalls(): void
    {
        $reader = new Read($this->fixture('plain.m3u8'));

        $this->assertSame($reader->getContent(), $reader->getContent());
        $this->assertSame($reader->getContentAsLines(), $reader->getContentAsLines());
    }
}
