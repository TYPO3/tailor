<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Filesystem;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Provide functionality for handling directories on the filesystem
 */
class Directory
{
    /**
     * Create directories recursive
     *
     * @param string $path The directory path to create
     * @param int $mode The mode for the directories
     * @return bool TRUE if the directories were created successfully, FALSE otherwise
     */
    public function create(string $path, int $mode = 0777): bool
    {
        return !(!is_dir($path) && !mkdir($concurrent = $path, $mode, true) && !is_dir($concurrent));
    }

    /**
     * Remove a directory and its content recursive
     *
     * @param string $directory The directory to remove
     * @return bool TRUE if the directory was removed successfully, FALSE otherwise
     */
    public function remove(string $directory): bool
    {
        $directory = realpath($directory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }
            unlink($file->getPathname());
        }

        return rmdir($directory);
    }
}
