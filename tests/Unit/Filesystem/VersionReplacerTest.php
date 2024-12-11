<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Filesystem;

use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Filesystem\VersionReplacer;

class VersionReplacerTest extends TestCase
{
    /**
     * @test
     */
    public function replaceVersionReplacesProperVersionOfEmConf(): void
    {
        $emConfContents = file_get_contents(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        $tempFile = tempnam('/tmp/', 'tailor_emconf.php');
        file_put_contents($tempFile, $emConfContents);
        $subject = new VersionReplacer('6.9.0');
        $subject->setVersion($tempFile, '["\']version["\']\s=>\s["\']((?:[0-9]+)\.[0-9]+\.[0-9]+\s*)["\']');
        $contents = file_get_contents($tempFile);
        self::assertStringContainsString('\'version\' => \'6.9.0\'', $contents);
        unlink($tempFile);
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function replaceVersionReplacesProperReleaseOfDocumentationConfigurationDataProvider(): \Generator
    {
        yield 'guides.xml' => ['guides.xml', 'release="([0-9]+\.[0-9]+\.[0-9]+)"', 'release="6.9.0"'];
        yield 'Settings.cfg' => ['Settings.cfg', 'release\s*=\s*([0-9]+\.[0-9]+\.[0-9]+)', 'release=6.9.0'];
    }

    /**
     * @test
     * @dataProvider replaceVersionReplacesProperReleaseOfDocumentationConfigurationDataProvider
     */
    public function replaceVersionReplacesProperReleaseOfDocumentationConfiguration(
        string $docSettingsFile,
        string $docReleasePattern,
        string $expected
    ): void {
        $docSettings = file_get_contents(__DIR__ . '/../Fixtures/Documentation/' . $docSettingsFile);
        $tempFile = tempnam(sys_get_temp_dir(), 'tailor_' . $docSettingsFile);
        file_put_contents($tempFile, $docSettings);
        $subject = new VersionReplacer('6.9.0');
        $subject->setVersion($tempFile, $docReleasePattern);
        $contents = file_get_contents($tempFile);
        self::assertStringContainsString($expected, preg_replace('/\s+/', '', $contents));
        unlink($tempFile);
    }

    /**
     * @return \Generator<string, array{string, string, string}>
     */
    public static function replaceVersionReplacesProperVersionOfDocumentationConfigurationDataProvider(): \Generator
    {
        yield 'guides.xml' => ['guides.xml', 'version="([0-9]+\.[0-9]+)"', 'version="6.9"'];
        yield 'Settings.cfg' => ['Settings.cfg', 'version\s*=\s*([0-9]+\.[0-9]+)', 'version=6.9'];
    }

    /**
     * @test
     * @dataProvider replaceVersionReplacesProperVersionOfDocumentationConfigurationDataProvider
     */
    public function replaceVersionReplacesProperVersionOfDocumentationConfiguration(
        string $docSettingsFile,
        string $docVersionPattern,
        string $expected
    ): void {
        $docSettings = file_get_contents(__DIR__ . '/../Fixtures/Documentation/' . $docSettingsFile);
        $tempFile = tempnam(sys_get_temp_dir(), 'tailor_' . $docSettingsFile);
        file_put_contents($tempFile, $docSettings);
        $subject = new VersionReplacer('6.9.0');
        $subject->setVersion($tempFile, $docVersionPattern, 2);
        $contents = file_get_contents($tempFile);
        self::assertStringContainsString($expected, preg_replace('/\s+/', '', $contents));
        unlink($tempFile);
    }

    /**
     * @test
     */
    public function replaceVersionThrowsExceptionOnInvalidFile(): void
    {
        $this->expectExceptionCode(1605741968);
        (new VersionReplacer('6.9.0'))->setVersion('some/invalid/file/path.php', 'version\s*=\s*([0-9.]+)');
    }
}
