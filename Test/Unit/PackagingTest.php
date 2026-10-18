<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Test\Unit;

use PHPUnit\Framework\TestCase;

class PackagingTest extends TestCase
{
    private const OSL_LINE = ' * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.';

    private const SKIP_DIRS = ['.git', 'vendor', '.phpunit.cache'];

    public function testEveryPhpPhraseIsInEnUsCsv(): void
    {
        $phrases = [];
        foreach ($this->sourceFiles(['php']) as $file) {
            if (str_starts_with($this->relative($file), 'Test/')) {
                continue;
            }
            preg_match_all(
                "#__\\(\\s*(['\"])((?:(?!\\1)[^\\\\]|\\\\.)*)\\1#",
                (string)file_get_contents($file),
                $matches
            );
            foreach ($matches[2] as $phrase) {
                $phrases[] = stripcslashes($phrase);
            }
        }

        self::assertNotEmpty($phrases);
        $this->assertTranslated($phrases);
    }

    public function testEverySystemXmlTranslatableTextIsInEnUsCsv(): void
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->load($this->root() . '/etc/adminhtml/system.xml'));
        $xpath = new \DOMXPath($dom);

        $phrases = [];
        foreach ($xpath->query('//*[@translate]') ?: [] as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('translate'))) ?: [] as $child) {
                foreach ($xpath->query($child, $element) ?: [] as $node) {
                    $phrases[] = $node->nodeValue;
                }
            }
        }

        self::assertNotEmpty($phrases);
        $this->assertTranslated($phrases);
    }

    public function testEveryPhpAndXmlFileCarriesOslNotice(): void
    {
        $files = $this->sourceFiles(['php', 'xml']);
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $lines = explode("\n", (string)file_get_contents($file));
            $copyright = array_search(' * Copyright © DMLab. All rights reserved.', $lines, true);

            self::assertNotFalse($copyright, $this->relative($file) . ' lacks the copyright line');
            self::assertSame(
                self::OSL_LINE,
                $lines[$copyright + 1] ?? null,
                $this->relative($file) . ' lacks the OSL notice'
            );
        }
    }

    /**
     * @param string[] $phrases
     */
    private function assertTranslated(array $phrases): void
    {
        $handle = fopen($this->root() . '/i18n/en_US.csv', 'rb');
        self::assertNotFalse($handle);
        $keys = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $keys[] = $row[0];
        }
        fclose($handle);

        foreach ($phrases as $phrase) {
            self::assertContains($phrase, $keys, sprintf('Missing from i18n/en_US.csv: "%s"', $phrase));
        }
    }

    /**
     * @param string[] $extensions
     * @return string[]
     */
    private function sourceFiles(array $extensions): array
    {
        $directories = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($this->root(), \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => !$file->isDir()
                || !in_array($file->getFilename(), self::SKIP_DIRS, true)
        );

        $files = [];
        foreach (new \RecursiveIteratorIterator($directories) as $file) {
            if (in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen($this->root()) + 1);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
