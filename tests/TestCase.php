<?php

namespace Tests\TurboM3u8;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var string[]
     */
    private $tmpDirs = [];

    protected function fixture(string $name): string
    {
        return __DIR__ . '/fixtures/' . $name;
    }

    protected function fixtureDir(): string
    {
        return __DIR__ . '/fixtures';
    }

    /**
     * 创建一个进程内唯一的临时目录，tearDown 时自动清理
     */
    protected function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/turbo-m3u8-test-' . uniqid('', true);
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->fail("Cannot create temp dir: {$dir}");
        }
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];

        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    /**
     * 把 reader 的行内容抽成纯文本数组，便于断言
     */
    protected function linesOf($reader): array
    {
        $lines = [];
        foreach ($reader->getContentAsLines() as $line) {
            $lines[] = $line['content'];
        }

        return $lines;
    }
}
