<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Exception\FormDataProcessingException;
use TYPO3\Tailor\Exception\RequiredOptionMissingException;
use TYPO3\Tailor\Service\FormatService;
use ZipArchive;

/**
 * Command for TER REST endpoint `POST /extension/{key}/{version}`
 */
class UploadExtensionVersionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    /** @var string */
    protected $version;

    /** @var array */
    protected $filesToRemove = [];

    /** @var array */
    protected $directoriesToRemove = [];

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Publishes a new version of an extension to TER')
            ->setResultFormat(FormatService::FORMAT_DETAIL)
            ->addArgument(
                'extensionkey',
                InputArgument::REQUIRED,
                'The extension key'
            )
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'The version to publish, e.g. 1.2.3'
            )
            ->addOption(
                'description',
                '',
                InputOption::VALUE_REQUIRED,
                'Description of the new version (e.g. release notes)'
            )
            ->addOption(
                'path',
                '',
                InputOption::VALUE_REQUIRED,
                'Path to the extension folder'
            )
            ->addOption(
                'artefact',
                '',
                InputOption::VALUE_REQUIRED,
                'Path or URL to a zip file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $input->getArgument('extensionkey');
        $this->version = $input->getArgument('version');
        parent::execute($input, $output);
        return (int)$this->requestService->run();
    }

    protected function generateRequestConfiguration(): RequestConfiguration
    {
        $formDataPart = $this->getFormDataPart($this->input->getOptions());

        return new RequestConfiguration(
            'POST',
            'extension/' . $this->extensionKey . '/' . $this->version,
            [],
            $formDataPart->bodyToIterable(),
            $formDataPart->getPreparedHeaders()->toArray()
        );
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Publishing version %s of extension %s', $this->version, $this->extensionKey),
            sprintf('Version %s of extension %s successfully published.', $this->version, $this->extensionKey),
            sprintf('Could not publish version %s of extension %s.', $this->version, $this->extensionKey)
        );
    }

    /**
     * Create FormDataPart from given options.
     * This also creates a proper DataPart (containing the version ZipArchive)
     * from either a given path or an existing ZipArchive (local or remote).
     *
     * @param array $options
     * @return FormDataPart
     */
    protected function getFormDataPart(array $options): FormDataPart
    {
        if ($options['path'] === null && $options['artefact'] === null) {
            throw new RequiredOptionMissingException('Either \'path\' or \'artefact\' must be defined.', 1605529398);
        }

        if ($options['description'] === null) {
            throw new RequiredOptionMissingException('Please add a \'description\' for the new version.', 1605529399);
        }

        if ($options['path'] !== null) {
            $this->createZipArchive((string)$options['path']);
        } else {
            $filename = strtolower(trim((string)$options['artefact']));
            if (!preg_match('/\.zip$/', $filename)) {
                throw new FormDataProcessingException('Can only process \'.zip\' files', 1605562904);
            }
            // Check if we deal with a remote file
            if (preg_match('/^http[s]?:\/\//', $filename)) {
                $tempFilename = 'remote-archive-' . $this->getVersionFilename(true)  . '.zip';
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
            $extractPath = 'temp-' . $this->getVersionFilename(true);
            // If we deal with e.g. Github release zip files, the extension is wrapped into another
            // directory. Therefore we have to add the root path here since the final ZipArchive is
            // required to provide all extension files on root level.
            $rootFolderPath = preg_match('/\/$/', $firstNameIndex) ? '/' . trim($firstNameIndex, '/') : '';
            // Extract the given zip file so validation is possible
            $zipArchive->extractTo($extractPath);
            $zipArchive->close();
            $this->createZipArchive($extractPath . $rootFolderPath);
            // Add files and directories for cleanup
            $this->directoriesToRemove[] = $extractPath;
            $this->filesToRemove[] = $filename;
        }

        $versionFilePath = realpath($this->getVersionFilename());

        if (!$versionFilePath) {
            throw new FormDataProcessingException('Could not find necessary version file.', 1605562674);
        }

        $this->filesToRemove[] = $versionFilePath;

        return new FormDataPart([
            'description' => (string)$options['description'],
            'gplCompliant' => '1',
            'file' => DataPart::fromPath($versionFilePath)
        ]);
    }

    /**
     * Create the final ZipArchive for the given directory after validation
     * of the given files (e.g. ext_emconf.php).
     *
     * @param string $path Path to the directory, whose contest should be added to the ZipArchive
     * @return ZipArchive The generated ZipArchive
     */
    protected function createZipArchive(string $path): ZipArchive
    {
        $fullPath = realpath($path);

        if (!$fullPath) {
            throw new FormDataProcessingException('Path is not valid.', 1605562741);
        }

        $zipArchive = new ZipArchive();
        $zipArchive->open($this->getVersionFilename(), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $emConfAvailable = false;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $fileRealPath = $file->getRealPath();

            // Do not add directories (will be added with the corresponding file anyways).
            // Also exclude Dotfiles (e.g. `.gitignore`) and files in hidden directories (e.g. `.git`).
            if ($file->isDir()
                || strpos($filename, '.') === 0
                || strpos($fileRealPath, '/.') !== false
            ) {
                continue;
            }

            if ($filename === 'ext_emconf.php') {
                $emConfAvailable = $this->validateEmConf($fileRealPath);
            }

            // Add the files including their directories
            $zipArchive->addFile($fileRealPath, substr($fileRealPath, strlen($fullPath) + 1));
        }

        if (!$emConfAvailable) {
            throw new FormDataProcessingException('No or invalid ext_emconf.php found in the folder.', 1605563410);
        }

        $zipArchive->close();

        return $zipArchive;
    }

    /**
     * Check if the version in ext_emconf matches the given version
     * and a proper TYPO3 dependency is included.
     *
     * @param string $filePath Path to the ext_emconf.php file
     * @return bool TRUE if the ext_emconf is valid, FALSE otherwise
     */
    protected function validateEmConf(string $filePath): bool
    {
        $_EXTKEY = $this->extensionKey;
        include $filePath;
        if (!isset($EM_CONF[$_EXTKEY])) {
            return false;
        }
        if (!isset($EM_CONF[$_EXTKEY]['version'], $EM_CONF[$_EXTKEY]['constraints']['depends']['typo3'])
            || (string)$EM_CONF[$_EXTKEY]['version'] !== $this->version
        ) {
            return false;
        }

        return true;
    }

    /**
     * Remove a directory and its contents recursive
     *
     * @param string $directory The directory to remove
     * @return bool TRUE if the directory was removed successfully, FALSE otherwise
     */
    protected function removeDirectory(string $directory): bool
    {
        $directory = realpath($directory);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }
            unlink($file->getPathname());
        }

        return rmdir($directory);
    }

    /**
     * Return the composed version filename with the proper patter
     *
     * @param bool $hash If TRUE, a hash of the version filename will be returned
     * @return string The version filename, or its md5 hash
     */
    protected function getVersionFilename(bool $hash = false): string
    {
        $filename = sprintf('%s_%s.zip', $this->extensionKey, $this->version);

        return $hash ? md5($filename): $filename;
    }

    /**
     * Clean up all marked files and directories. This usually
     * includes the final ZipArchive but not the given path
     * from which the ZipArchive was created.
     */
    protected function cleanUp(): void
    {
        foreach ($this->filesToRemove as $file) {
            unlink($file);
        }

        foreach ($this->directoriesToRemove as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function __destruct()
    {
        $this->cleanUp();
    }
}
