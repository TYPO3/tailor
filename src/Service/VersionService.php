<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\FormDataProcessingException;
use TYPO3\Tailor\Exception\RequiredConfigurationMissing;
use TYPO3\Tailor\Validation\EmConfVersionValidator;
use ZipArchive;

/**
 * Service for creating extension version archives
 */
class VersionService
{
    private const EXCLUDE_FROM_PACKAGING = __DIR__ . '/../../conf/ExcludeFromPackaging.php';

    /** @var string */
    protected $version;

    /** @var string */
    protected $extension;

    /** @var string */
    protected $transactionPath;

    /** @var array */
    protected $excludeConfiguration = [];

    public function __construct(string $version, string $extension, string $transactionPath)
    {
        $this->version = $version;
        $this->extension = $extension;
        $this->transactionPath = $transactionPath;
        $this->excludeConfiguration = $this->getExcludeConfiguration();
    }

    /**
     * Create the final ZipArchive for the given directory after validation
     * of the given files (e.g. ext_emconf.php).
     *
     * @param string $path Path to the directory, whose content should be added to the ZipArchive
     * @return string The full path to the ZipArchive
     */
    public function createZipArchiveFromPath(string $path): string
    {
        $fullPath = realpath($path);

        if (!$fullPath) {
            throw new FormDataProcessingException('Path is not valid.', 1605562741);
        }

        $zipArchive = new ZipArchive();
        $zipArchive->open($this->getVersionFilename(), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $emConfValid = false;

        $iterator = new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator($iterator, function ($current) use ($fullPath) {
                // @todo Find a more performant way for filtering

                $filepath = $current->getRealPath();
                $filename = $current->getFilename();

                if (!$filepath || !$filename || !($path = substr($filepath, strlen($fullPath) + 1))) {
                    return false;
                }

                if ($current->isDir()) {
                    // if $current is a directory, check for excluded directories
                    foreach ($this->excludeConfiguration['directories'] as $excludeDirectory) {
                        if (preg_match('/^' . $excludeDirectory . '/i', $path)) {
                            return false;
                        }
                    }
                }

                if ($current->isFile()) {
                    // if $current is a file, check for excluded files
                    foreach ($this->excludeConfiguration['files'] as $excludeFile) {
                        if (preg_match('/' . $excludeFile . '$/i', $filename)) {
                            return false;
                        }
                    }
                }

                return true;
            }),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $fileRealPath = $file->getRealPath();

            // Do not add directories (will be added with the corresponding file anyways).
            if ($file->isDir()) {
                continue;
            }

            if ($filename === 'ext_emconf.php') {
                $emConfValid = (new EmConfVersionValidator($fileRealPath))->isValid($this->version);
            }

            // Add the files including their directories
            $zipArchive->addFile($fileRealPath, substr($fileRealPath, strlen($fullPath) + 1));
        }

        if (!$emConfValid) {
            throw new FormDataProcessingException('No or invalid ext_emconf.php found in the folder.', 1605563410);
        }

        $zipArchive->close();

        return $this->getVersionFilePath();
    }

    /**
     * Extract the given artefact (from either local or remote),
     * store it in a temporary transaction path and finally call
     * createZipArchiveFromPath() to create the final ZipArchive.
     *
     * @param string $filename The filename of the artefact to create the ZipArchive from
     * @return string The full path to the ZipArchive
     */
    public function createZipArchiveFromArtefact(string $filename): string
    {
        // Only process files with .zip extension
        if (!preg_match('/\.zip$/', $filename)) {
            throw new FormDataProcessingException('Can only process \'.zip\' files.', 1605562904);
        }
        // Check if we deal with a remote file
        if (preg_match('/^http[s]?:\/\//', $filename)) {
            $tempFilename = $this->transactionPath . '/remote-archive-' . $this->getVersionFilename(true) . '.zip';
            // Save the remote file temporary on local disk for validation and creation of the final ZipArchive
            if (file_put_contents($tempFilename, fopen($filename, 'rb')) === false) {
                throw new FormDataProcessingException('Could not processed remote file.', 1605562356);
            }
            $filename = $tempFilename;
        }
        $filename = realpath($filename) ?: '';
        if (!is_file($filename)) {
            throw new FormDataProcessingException('No such file.', 1605562482);
        }
        $zipArchive = new ZipArchive();
        $zipFile = $zipArchive->open($filename);
        if (!$zipFile || $zipArchive->numFiles <= 0) {
            throw new FormDataProcessingException('No files in given directory.', 1605562663);
        }
        $firstNameIndex = $zipArchive->getNameIndex(0) ?: '';
        $extractPath = $this->transactionPath . '/temp-' . $this->getVersionFilename(true);
        // If we deal with e.g. Github release zip files, the extension is wrapped into another
        // directory. Therefore we have to add the root path here since the final ZipArchive is
        // required to provide all extension files on root level.
        $rootFolderPath = preg_match('/\/$/', $firstNameIndex) ? '/' . trim($firstNameIndex, '/') : '';
        // Extract the given zip file so we can validate the content
        // and create a proper ZipArchive for the request.
        $zipArchive->extractTo($extractPath);
        $zipArchive->close();
        $this->createZipArchiveFromPath($extractPath . $rootFolderPath);

        return $this->getVersionFilePath();
    }

    /**
     * Return the full path to the composed version file
     *
     * @return string The full path to the version file
     * @throws FormDataProcessingException Thrown if path can not be determined
     */
    public function getVersionFilePath(): string
    {
        $versionFilePath = realpath($this->getVersionFilename());

        if (!$versionFilePath) {
            throw new FormDataProcessingException('Could not find version file in given path.', 1605562674);
        }

        return $versionFilePath;
    }

    /**
     * Return the composed version filename with the proper patter
     *
     * @param bool $hash If TRUE, a hash of the version filename will be returned
     * @return string The version filename, or its md5 hash
     */
    protected function getVersionFilename(bool $hash = false): string
    {
        $filename = sprintf('%s/%s_%s.zip', $this->transactionPath, $this->extension, $this->version);

        return $hash ? md5($filename): $filename;
    }

    /**
     * Return the configuration for directories and files which
     * should be excluded from packaging (the final ZipArchive).
     *
     * @return array
     */
    protected function getExcludeConfiguration(): array
    {
        $exludeConfigurationFile = Variables::has('TYPO3_EXCLUDE_FROM_PACKAGING')
            ? Variables::get('TYPO3_EXCLUDE_FROM_PACKAGING')
            : self::EXCLUDE_FROM_PACKAGING;

        if (!file_exists($exludeConfigurationFile)) {
            throw new \InvalidArgumentException(
                'The exclude from packaging configuration file \'' . $exludeConfigurationFile . '\' does not exist.',
                1605734677
            );
        }

        $configuration = require $exludeConfigurationFile;

        if (!is_array($configuration) || !isset($configuration['directories'], $configuration['files'])) {
            throw new RequiredConfigurationMissing(
                'Given exclude from packaging configuration must include \'directories\' and \'files\'.',
                1605734681
            );
        }

        return $configuration;
    }
}
