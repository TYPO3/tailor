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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Filesystem\EmConfReader;

class EmConfReaderTest extends TestCase
{
    private const EMCONF_DIRECTORY = 'tmp-emconf';
    private const EMCONF_FILE = self::EMCONF_DIRECTORY . '/ext_emconf.php';

    protected function setUp(): void
    {
        parent::setUp();
        mkdir(self::EMCONF_DIRECTORY);
    }

    protected function tearDown(): void
    {
        if (file_exists(self::EMCONF_FILE)) {
            unlink(self::EMCONF_FILE);
        }
        rmdir(self::EMCONF_DIRECTORY);
        parent::tearDown();
    }

    #[Test]
    public function returnEmptyStringIfNoEmConfExists(): void
    {
        self::assertEmpty((new EmConfReader(self::EMCONF_DIRECTORY))->getVersion());
    }

    #[Test]
    public function returnEmptyStringIfVersionNotGiven(): void
    {
        $this->useFixture('emconf_no_version.php');

        self::assertEmpty((new EmConfReader(self::EMCONF_DIRECTORY))->getVersion());
    }

    #[Test]
    public function returnEmptyStringIfEmConfHasNoValidStructure(): void
    {
        $this->useFixture('emconf_invalid.php');

        self::assertEmpty((new EmConfReader(self::EMCONF_DIRECTORY))->getVersion());
    }

    #[Test]
    public function readCorrectVersionFromGivenEmConfFile(): void
    {
        $this->useFixture('emconf_valid.php');

        self::assertSame('1.0.0', (new EmConfReader(self::EMCONF_DIRECTORY))->getVersion());
    }

    #[Test]
    public function surroundingWhitespaceOfTheVersionIsStripped(): void
    {
        file_put_contents(self::EMCONF_FILE, "<?php\n\n\$EM_CONF[\$_EXTKEY] = ['version' => '1.0.0 '];\n");

        self::assertSame('1.0.0', (new EmConfReader(self::EMCONF_DIRECTORY))->getVersion());
    }

    private function useFixture(string $filename): void
    {
        copy(__DIR__ . '/../Fixtures/EmConf/' . $filename, self::EMCONF_FILE);
    }
}
