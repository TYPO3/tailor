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

/**
 * Creates a throwaway extension directory for commands working on the filesystem.
 *
 * Commands packaging an extension put their transaction directory into the
 * current working directory, so the working directory is moved into the
 * temporary structure as well and restored afterwards.
 */
trait ExtensionDirectoryTrait
{
    /** @var string */
    private $workingDirectory = '';

    /** @var string */
    private $extensionDirectory = '';

    /** @var string */
    private $previousWorkingDirectory = '';

    private function createExtensionDirectory(string $version = '1.2.3'): void
    {
        $this->previousWorkingDirectory = (string)getcwd();
        $this->workingDirectory = sys_get_temp_dir() . '/tailor-test-' . bin2hex(random_bytes(6));
        $this->extensionDirectory = $this->workingDirectory . '/my_ext';

        mkdir($this->extensionDirectory, 0777, true);
        chdir($this->workingDirectory);

        $this->writeExtensionFile('composer.json', (string)json_encode([
            'name' => 'vendor/my-ext',
            'type' => 'typo3-cms-extension',
            'extra' => ['typo3/cms' => ['extension-key' => 'my_ext']],
        ], JSON_PRETTY_PRINT));

        $this->writeExtensionFile('ext_emconf.php', <<<PHP
            <?php

            \$EM_CONF[\$_EXTKEY] = [
                'title' => 'My extension',
                'state' => 'stable',
                'version' => '{$version}',
                'constraints' => [
                    'depends' => [
                        'typo3' => '12.4.0-13.4.99',
                    ],
                ],
            ];
            PHP);
    }

    private function writeExtensionFile(string $relativePath, string $contents): void
    {
        $filename = $this->extensionDirectory . '/' . $relativePath;
        $directory = dirname($filename);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($filename, $contents);
    }

    private function extensionFile(string $relativePath): string
    {
        return (string)file_get_contents($this->extensionDirectory . '/' . $relativePath);
    }

    private function removeExtensionDirectory(): void
    {
        if ($this->previousWorkingDirectory !== '') {
            chdir($this->previousWorkingDirectory);
            $this->previousWorkingDirectory = '';
        }

        if ($this->workingDirectory === '' || !is_dir($this->workingDirectory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workingDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->workingDirectory);
        $this->workingDirectory = '';
    }
}
