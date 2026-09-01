<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Exception\RequiredConfigurationMissing;
use TYPO3\Tailor\Service\VersionService;

class VersionServiceTest extends TestCase
{
    /** @var list<string> */
    protected array $temporaryDirectories = [];

    #[Test]
    public function defaultExcludeFromPackagingConfigurationIsUsedOnNonExistingEnvVar(): void
    {
        unset($_ENV);

        self::assertContains(
            'vendor',
            $this->invokeMethod('getExcludeConfiguration', [])['directories']
        );
    }

    #[Test]
    public function defaultExcludeFromPackagingConfigurationIsUsedOnEmptyPath(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = '';

        self::assertContains(
            'vendor',
            $this->invokeMethod('getExcludeConfiguration', [])['directories']
        );
    }

    #[Test]
    public function customExcludeFromPackagingConfigurationIsUsed(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        self::assertSame(
            ['directories' => ['dummy'], 'files' => ['dummy']],
            $this->invokeMethod('getExcludeConfiguration', [])
        );
    }

    #[Test]
    public function throwsExceptionOnMissingCustomConfiguration(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid_path.php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1605734677);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    #[Test]
    public function throwsExceptionOnInvalidCustomConfiguration(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid.php');

        $this->expectException(RequiredConfigurationMissing::class);
        $this->expectExceptionCode(1605734681);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    #[Test]
    public function getVersionFilenameTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php');

        self::assertSame(
            '/dummyPath/my_ext_1.0.0.zip',
            $this->invokeMethod('getVersionFilename', [])
        );
    }

    #[Test]
    public function getVersionFilenameAsMd5Test(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        self::assertSame(
            'cf2d6e211e53d983056761055c95791b',
            $this->invokeMethod('getVersionFilename', [true])
        );
    }

    #[Test]
    public function excludedDirectoryContainingASlashIsNotPackaged(): void
    {
        $packagedFiles = $this->packageExtensionWithExcludeConfiguration('config_nested_directory.php');

        self::assertContains('ext_emconf.php', $packagedFiles);
        self::assertNotContains('Resources/Private/Build/gulpfile.js', $packagedFiles);
    }

    #[Test]
    public function excludedDirectoryContainingAnEscapedSlashIsNotPackaged(): void
    {
        $packagedFiles = $this->packageExtensionWithExcludeConfiguration('config_nested_directory_escaped.php');

        self::assertContains('ext_emconf.php', $packagedFiles);
        self::assertNotContains('Resources/Private/Build/gulpfile.js', $packagedFiles);
    }

    /**
     * Package an extension directory with the given exclude configuration
     * and return the filenames the created archive contains.
     *
     * @param string $configurationFilename Filename of the exclude configuration fixture
     *
     * @return list<string> The packaged filenames
     */
    protected function packageExtensionWithExcludeConfiguration(string $configurationFilename): array
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/' . $configurationFilename);

        $extensionPath = $this->createExtensionDirectory();
        $transactionPath = $this->createTemporaryDirectory();

        $archivePath = (new VersionService('1.0.0', 'my_ext', $transactionPath))
            ->createZipArchiveFromPath($extensionPath);

        $archive = new \ZipArchive();
        $archive->open($archivePath);
        $packagedFiles = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $packagedFiles[] = $archive->getNameIndex($index);
        }

        $archive->close();

        return $packagedFiles;
    }

    /**
     * Create an extension directory holding a valid ext_emconf.php and a file
     * inside the nested directory the exclude configuration names.
     */
    protected function createExtensionDirectory(): string
    {
        $path = $this->createTemporaryDirectory();

        file_put_contents(
            $path . '/ext_emconf.php',
            '<?php' . PHP_EOL
            . '$EM_CONF[$_EXTKEY] = [\'version\' => \'1.0.0\', \'constraints\' => [\'depends\' => [\'typo3\' => \'13.4.0-13.4.99\']]];' . PHP_EOL
        );

        mkdir($path . '/Resources/Private/Build', 0777, true);
        file_put_contents($path . '/Resources/Private/Build/gulpfile.js', '// build only');

        return $path;
    }

    protected function createTemporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/tailor-test-' . bin2hex(random_bytes(8));
        mkdir($path, 0777, true);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($directory);
        }

        $this->temporaryDirectories = [];

        parent::tearDown();
    }

    /**
     * Invoke a protected / private method from VersionService
     *
     * @param string $methodName
     * @param array  $arguments
     *
     * @return mixed
     * @throws \ReflectionException
     */
    protected function invokeMethod(string $methodName, array $arguments)
    {
        $mock = $this
            ->getMockBuilder(VersionService::class)
            ->setConstructorArgs(['1.0.0', 'my_ext', '/dummyPath'])
            ->getMock();

        $method = new \ReflectionMethod(VersionService::class, $methodName);

        return $method->invokeArgs($mock, $arguments);
    }
}
