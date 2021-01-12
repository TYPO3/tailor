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
use TYPO3\Tailor\Exception\InvalidComposerJsonException;
use TYPO3\Tailor\Filesystem\ComposerReader;

class ComposerReaderTest extends TestCase
{
    private const COMPOSER_FILE = 'tmp/composer.json';

    protected function setUp(): void
    {
        parent::setUp();
        mkdir('tmp');
    }

    protected function tearDown(): void
    {
        unlink(self::COMPOSER_FILE);
        rmdir('tmp');
        parent::tearDown();
    }

    /**
     * @test
     */
    public function throwsExceptionOnEmptyComposerJsonFile(): void
    {
        $this->expectExceptionCode(1610442954);
        $this->expectException(InvalidComposerJsonException::class);
        file_put_contents(self::COMPOSER_FILE, '');
        new ComposerReader('tmp');
    }

    /**
     * @test
     */
    public function throwsExceptionOnInvalidComposerJsonFile(): void
    {
        $this->expectExceptionCode(1610442954);
        $this->expectException(InvalidComposerJsonException::class);
        $composerContent = file_get_contents(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        file_put_contents(self::COMPOSER_FILE, $composerContent);
        new ComposerReader('tmp');
    }

    /**
     * @test
     */
    public function returnEmptyStringIfExtensionKeyNotGiven(): void
    {
        $composerContent = file_get_contents(__DIR__ . '/../Fixtures/Composer/composer_no_extension_key.json');
        file_put_contents(self::COMPOSER_FILE, $composerContent);
        $subject = new ComposerReader('tmp');
        self::assertEmpty($subject->getExtensionKey());
    }

    /**
     * @test
     */
    public function readCorrectExtensionKeyFromGivenComposerJsonFile(): void
    {
        $composerContent = file_get_contents(__DIR__ . '/../Fixtures/Composer/composer_with_extension_key.json');
        file_put_contents(self::COMPOSER_FILE, $composerContent);
        $subject = new ComposerReader('tmp');
        self::assertSame('my-extension', $subject->getExtensionKey());
    }
}
