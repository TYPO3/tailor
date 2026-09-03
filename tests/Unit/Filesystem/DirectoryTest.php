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
use TYPO3\Tailor\Filesystem\Directory;

class DirectoryTest extends TestCase
{
    /** @var string */
    private $temporaryDirectory = '';

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/tailor-test-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            (new Directory())->remove($this->temporaryDirectory);
        }
    }

    #[Test]
    public function directoryIsRemovedWithItsContent(): void
    {
        mkdir($this->temporaryDirectory . '/nested/deeper', 0777, true);
        file_put_contents($this->temporaryDirectory . '/artefact.zip', 'content');
        file_put_contents($this->temporaryDirectory . '/nested/deeper/file.txt', 'content');

        self::assertTrue((new Directory())->remove($this->temporaryDirectory));
        self::assertDirectoryDoesNotExist($this->temporaryDirectory);
    }

    #[Test]
    public function removingANonExistingDirectoryReturnsFalse(): void
    {
        self::assertFalse((new Directory())->remove($this->temporaryDirectory . '/never-created'));
    }

    #[Test]
    public function removingAFileReturnsFalse(): void
    {
        $file = $this->temporaryDirectory . '/artefact.zip';
        file_put_contents($file, 'content');

        self::assertFalse((new Directory())->remove($file));
        self::assertFileExists($file);
    }
}
