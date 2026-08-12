<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Command\Extension;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\Tailor\Command\Extension\SetExtensionVersionCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class SetExtensionVersionCommandTest extends AbstractCommandTestCase
{
    use ExtensionDirectoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExtensionDirectory('1.0.0');
    }

    protected function tearDown(): void
    {
        $this->removeExtensionDirectory();
        parent::tearDown();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new SetExtensionVersionCommand('set-version'));
    }

    #[Test]
    public function versionIsWrittenToComposerJsonAndEmConf(): void
    {
        $this->writeExtensionFile('composer.json', '{' . PHP_EOL . '    "version": "1.0.0"' . PHP_EOL . '}');

        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory, '--no-docs' => null]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('"version": "2.3.4"', $this->extensionFile('composer.json'));
        self::assertStringContainsString("'version' => '2.3.4'", $this->extensionFile('ext_emconf.php'));
    }

    #[Test]
    public function versionMustConsistOfThreeDigits(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '1.2', '--path' => $this->extensionDirectory]);

        self::assertSame(1, $exitCode);
        self::assertDisplayContains('The given version "1.2" must contain three digits', $tester);
    }

    #[Test]
    public function missingEmConfIsReported(): void
    {
        unlink($this->extensionDirectory . '/ext_emconf.php');

        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertSame(1, $exitCode);
        self::assertDisplayContains('No \'ext_emconf.php\' found', $tester);
    }

    #[Test]
    public function missingComposerJsonIsReported(): void
    {
        unlink($this->extensionDirectory . '/composer.json');

        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertSame(1, $exitCode);
        self::assertDisplayContains('No \'composer.json\' found', $tester);
    }

    #[Test]
    public function nonExistingPathIsReported(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->workingDirectory . '/nope']);

        self::assertSame(1, $exitCode);
        self::assertDisplayContains('does not exist', $tester);
    }

    #[Test]
    public function releaseAndVersionAreUpdatedInGuidesXml(): void
    {
        $this->writeExtensionFile('Documentation/guides.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <guides>
                <extension release="1.0.0" version="1.0"/>
            </guides>
            XML);

        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertSame(0, $exitCode);
        $guides = $this->extensionFile('Documentation/guides.xml');
        self::assertStringContainsString('release="2.3.4"', $guides);
        self::assertStringContainsString('version="2.3"', $guides);
    }

    #[Test]
    public function xmlPrologVersionIsNotTouched(): void
    {
        $this->writeExtensionFile('Documentation/guides.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <guides>
                <extension release="1.0.0" version="1.0"/>
            </guides>
            XML);

        $this->tester()->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $this->extensionFile('Documentation/guides.xml'));
    }

    #[Test]
    public function releaseAndVersionAreUpdatedInLegacySettingsCfg(): void
    {
        $this->writeExtensionFile('Documentation/Settings.cfg', <<<'CFG'
            [general]
            release = 1.0.0
            version = 1.0
            CFG);

        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertSame(0, $exitCode);
        $settings = $this->extensionFile('Documentation/Settings.cfg');
        self::assertStringContainsString('release = 2.3.4', $settings);
        self::assertStringContainsString('version = 2.3', $settings);
    }

    #[Test]
    public function noDocsOptionSkipsDocumentationUpdate(): void
    {
        $this->writeExtensionFile('Documentation/guides.xml', '<guides><extension release="1.0.0" version="1.0"/></guides>');

        $tester = $this->tester();
        $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory, '--no-docs' => null]);

        self::assertStringContainsString('release="1.0.0"', $this->extensionFile('Documentation/guides.xml'));
    }

    #[Test]
    public function environmentVariableSkipsDocumentationUpdate(): void
    {
        $this->setEnvironment(['TYPO3_DISABLE_DOCS_VERSION_UPDATE' => '1']);
        $this->writeExtensionFile('Documentation/guides.xml', '<guides><extension release="1.0.0" version="1.0"/></guides>');

        $tester = $this->tester();
        $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertStringContainsString('release="1.0.0"', $this->extensionFile('Documentation/guides.xml'));
    }

    #[Test]
    public function missingDocumentationFilesAreReportedAsNote(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['version' => '2.3.4', '--path' => $this->extensionDirectory]);

        self::assertSame(0, $exitCode);
        self::assertDisplayContains('Documentation version update is enabled but was not performed', $tester);
    }
}
