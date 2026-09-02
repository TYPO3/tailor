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

use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Exception\FormDataProcessingException;
use TYPO3\Tailor\Exception\RequiredConfigurationMissing;
use TYPO3\Tailor\Validation\EmConfValidationError;
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

    /** @var list<string> The paths the last created archive contains, relative to the extension directory */
    protected $packagedPaths = [];

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

        $zipArchive = new \ZipArchive();
        $zipArchive->open($this->getVersionFilename(), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $emConfValidationErrors = [EmConfValidationError::NOT_FOUND];
        $this->packagedPaths = [];

        $iterator = new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator($iterator, function ($current) use ($fullPath) {
                // @todo Find a more performant way for filtering

                $filepath = $current->getRealPath();
                $filename = $current->getFilename();

                if (!$filepath || !$filename || !($path = substr($filepath, strlen($fullPath) + 1))) {
                    return false;
                }

                if ($current->isDir()) {
                    // if $current is a directory, check for excluded directories
                    foreach ($this->excludeConfiguration['directories'] as $excludeDirectory) {
                        if (preg_match('/^' . $this->quoteExcludePattern((string)$excludeDirectory) . '/i', $path)) {
                            return false;
                        }
                    }
                }

                if ($current->isFile()) {
                    // if $current is a file, check for excluded files
                    foreach ($this->excludeConfiguration['files'] as $excludeFile) {
                        if (preg_match('/' . $this->quoteExcludePattern((string)$excludeFile) . '$/i', $filename)) {
                            return false;
                        }
                    }
                }

                return true;
            }),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $fileRealPath = $file->getRealPath();

            // Do not add directories (will be added with the corresponding file anyways).
            if ($file->isDir()) {
                continue;
            }

            if ($filename === 'ext_emconf.php') {
                $emConfValidationErrors = (new EmConfVersionValidator($fileRealPath))->collectErrors($this->version);
            }

            // Add the files including their directories
            $packagedPath = substr($fileRealPath, strlen($fullPath) + 1);
            $this->packagedPaths[] = $packagedPath;
            $zipArchive->addFile($fileRealPath, $packagedPath);
        }

        if ($emConfValidationErrors !== []) {
            throw new FormDataProcessingException($this->formatEmConfValidationErrors($emConfValidationErrors), 1605563410);
        }

        $zipArchive->close();

        return $this->getVersionFilePath();
    }

    /**
     * Quote a configured exclude entry for use within a slash delimited pattern.
     *
     * The entries are documented as plain directory and file names. Some extensions
     * however escape the slashes of a nested directory name (`Resources\/Private\/Build`)
     * to work around the unquoted interpolation used in earlier versions. Those escapes
     * are removed first, so both notations describe the very same directory.
     *
     * @param string $excludeEntry The configured directory or file name
     *
     * @return string The quoted pattern part
     */
    protected function quoteExcludePattern(string $excludeEntry): string
    {
        return preg_quote(str_replace('\\/', '/', $excludeEntry), '/');
    }

    /**
     * Warnings about the configured exclude entries.
     *
     * An entry is reported when the archive still carries what the entry names: a
     * `Resources/Private/Build/` written with a trailing slash reads like a valid
     * exclude and packages the directory anyway. That silent packaging is what the
     * filter exists to prevent, so it is reported instead of being guessed straight.
     *
     * Entries which name something the extension does not contain are not reported.
     * Exclude configurations are usually shared between extensions and carry entries
     * for directories and files only some of them have - the shipped default
     * configuration most of all.
     *
     * @return list<string> The warnings, empty if there is nothing to report
     */
    public function getExcludeWarnings(): array
    {
        $warnings = [];

        foreach (['directories', 'files'] as $type) {
            foreach ($this->excludeConfiguration[$type] as $excludeEntry) {
                $excludeEntry = (string)$excludeEntry;

                if (str_contains($excludeEntry, '\\/')) {
                    $warnings[] = sprintf(
                        'The exclude entry "%s" escapes its slashes. This is no longer required, write it as "%s".',
                        $excludeEntry,
                        str_replace('\\/', '/', $excludeEntry)
                    );
                }

                $packagedPath = $this->getPackagedPathFor($type, $excludeEntry);

                if ($packagedPath === '') {
                    continue;
                }

                $warnings[] = trim(sprintf(
                    'The exclude entry "%s" did not take effect, the archive contains "%s". %s',
                    $excludeEntry,
                    $packagedPath,
                    $this->getExcludeEntryHint($type, $excludeEntry)
                ));
            }
        }

        return $warnings;
    }

    /**
     * Find a packaged path the given exclude entry names but did not keep out of the
     * archive. Since the entry is compared against what was packaged, an entry which
     * is covered by another one - `Resources/Private` next to `Resources/Private/Build` -
     * has nothing left to report.
     *
     * @param string $type         Either `directories` or `files`
     * @param string $excludeEntry The configured directory or file name
     *
     * @return string The packaged path, empty if the entry has nothing to complain about
     */
    protected function getPackagedPathFor(string $type, string $excludeEntry): string
    {
        $entryPath = trim(str_replace(['\\/', '\\'], '/', $excludeEntry), '/');

        if (str_starts_with($entryPath, './')) {
            $entryPath = substr($entryPath, 2);
        }

        if ($entryPath === '') {
            return '';
        }

        // A file entry is matched against the filename, a path in it can never match
        if ($type === 'files' && !str_contains($entryPath, '/')) {
            return '';
        }

        foreach ($this->packagedPaths as $packagedPath) {
            if ($type === 'files' && strcasecmp($packagedPath, $entryPath) === 0) {
                return $packagedPath;
            }

            if ($type === 'directories' && stripos($packagedPath, $entryPath . '/') === 0) {
                return $packagedPath;
            }
        }

        return '';
    }

    /**
     * Hint about the most likely reason for an exclude entry not to take effect.
     *
     * @param string $type         Either `directories` or `files`
     * @param string $excludeEntry The configured directory or file name
     *
     * @return string The hint, empty if the entry looks the way it is documented
     */
    protected function getExcludeEntryHint(string $type, string $excludeEntry): string
    {
        if ($type === 'files') {
            return 'File entries are matched against the filename, they can not contain a path.';
        }

        if (str_ends_with($excludeEntry, '/')) {
            return 'Directory names are matched without a trailing slash, remove it.';
        }

        if (str_starts_with($excludeEntry, './')) {
            return 'Directory names are matched relative to the extension directory, remove the leading "./".';
        }

        if (str_contains(str_replace('\\/', '/', $excludeEntry), '\\')) {
            return 'Use "/" as directory separator.';
        }

        return '';
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
        $zipArchive = new \ZipArchive();
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

        return $hash ? md5($filename) : $filename;
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

    /**
     * @param list<EmConfValidationError::*> $errors
     */
    private function formatEmConfValidationErrors(array $errors): string
    {
        $messageParts = ['Validation of `ext_emconf.php` file failed due to the following errors:'];

        foreach ($errors as $error) {
            $messageParts[] = '  * ' . EmConfValidationError::getErrorMessage($error);
        }

        return implode(PHP_EOL, $messageParts);
    }
}
