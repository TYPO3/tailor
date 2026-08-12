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
use TYPO3\Tailor\Command\Extension\CreateExtensionArtefactCommand;
use TYPO3\Tailor\Exception\FormDataProcessingException;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class CreateExtensionArtefactCommandTest extends AbstractCommandTestCase
{
    use ExtensionDirectoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExtensionDirectory('1.2.3');
    }

    protected function tearDown(): void
    {
        $this->removeExtensionDirectory();
        parent::tearDown();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new CreateExtensionArtefactCommand('ter:extension:artefact'));
    }

    #[Test]
    public function artefactIsCreatedInTheTransactionDirectory(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute([
            'version' => '1.2.3',
            'extensionkey' => 'my_ext',
            '--path' => $this->extensionDirectory,
        ]);

        self::assertSame(0, $exitCode);
        self::assertFileExists($this->workingDirectory . '/tailor-version-artefact/my_ext_1.2.3.zip');
        self::assertDisplayContains('Extension artefact successfully generated', $tester);
    }

    #[Test]
    public function artefactContainsTheExtensionFilesOnRootLevel(): void
    {
        $this->tester()->execute([
            'version' => '1.2.3',
            'extensionkey' => 'my_ext',
            '--path' => $this->extensionDirectory,
        ]);

        $zip = new \ZipArchive();
        $zip->open($this->workingDirectory . '/tailor-version-artefact/my_ext_1.2.3.zip');

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        self::assertContains('ext_emconf.php', $names);
        self::assertContains('composer.json', $names);
    }

    #[Test]
    public function excludedFilesAreNotPackaged(): void
    {
        $this->writeExtensionFile('composer.lock', '{}');
        $this->writeExtensionFile('.gitignore', 'vendor');
        mkdir($this->extensionDirectory . '/vendor');
        file_put_contents($this->extensionDirectory . '/vendor/autoload.php', '<?php');

        $this->tester()->execute([
            'version' => '1.2.3',
            'extensionkey' => 'my_ext',
            '--path' => $this->extensionDirectory,
        ]);

        $zip = new \ZipArchive();
        $zip->open($this->workingDirectory . '/tailor-version-artefact/my_ext_1.2.3.zip');

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        self::assertNotContains('composer.lock', $names);
        self::assertNotContains('.gitignore', $names);
        self::assertNotContains('vendor/autoload.php', $names);
    }

    #[Test]
    public function versionMismatchInEmConfIsRejected(): void
    {
        $tester = $this->tester();

        $this->expectException(FormDataProcessingException::class);
        $this->expectExceptionCode(1605563410);

        // The extension directory carries version 1.2.3
        $tester->execute([
            'version' => '9.9.9',
            'extensionkey' => 'my_ext',
            '--path' => $this->extensionDirectory,
        ]);
    }

    #[Test]
    public function nonZipArtefactIsRejected(): void
    {
        $this->writeExtensionFile('artefact.tar', 'nope');

        $tester = $this->tester();

        $this->expectException(FormDataProcessingException::class);
        $this->expectExceptionCode(1605562904);

        $tester->execute([
            'version' => '1.2.3',
            'extensionkey' => 'my_ext',
            '--artefact' => $this->extensionDirectory . '/artefact.tar',
        ]);
    }
}
