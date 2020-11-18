<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Extension;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Exception\RequiredOptionMissingException;
use TYPO3\Tailor\Filesystem;
use TYPO3\Tailor\Service\FormatService;
use TYPO3\Tailor\Service\VersionService;

/**
 * Command for TER REST endpoint `POST /extension/{key}/{version}`
 */
class UploadExtensionVersionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $version;

    /** @var string */
    protected $extensionKey;

    /** @var string */
    protected $transactionPath;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Publishes a new version of an extension to TER')
            ->setResultFormat(FormatService::FORMAT_DETAIL)
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key')
            ->addOption('path', '', InputOption::VALUE_REQUIRED, 'Path to the extension folder')
            ->addOption('artefact', '', InputOption::VALUE_REQUIRED, 'Path or URL to a zip file')
            ->addOption('comment', '', InputOption::VALUE_OPTIONAL, 'Upload comment of the new version (e.g. release notes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->version = $input->getArgument('version');
        $this->extensionKey = $this->getExtensionKey($input);
        $this->transactionPath = rtrim(realpath('.'), '/') . '/version-upload';

        if (!is_dir($this->transactionPath) && !mkdir($concurrent = $this->transactionPath) && !is_dir($concurrent)) {
            throw new \RuntimeException(sprintf('Directory \'%s\' could not be created.', $concurrent));
        }

        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
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
        $variables = [$this->version, $this->extensionKey];

        return new Messages(
            sprintf('Publishing version %s of extension %s', ...$variables),
            sprintf('Version %s of extension %s successfully published.', ...$variables),
            sprintf('Could not publish version %s of extension %s.', ...$variables)
        );
    }

    /**
     * Create FormDataPart from given options.
     * This also creates a proper DataPart (containing the version as ZipArchive)
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

        if ($options['comment'] === null) {
            // The REST API requires a description to be set (just like the GUI does).
            // For now we just generate a description from the given version.
            $options['comment'] = 'Updated extension to ' . $this->version;
        }

        $versionService = new VersionService($this->version, $this->extensionKey, $this->transactionPath);

        if ($options['path'] !== null) {
            $versionService->createZipArchiveFromPath((string)$options['path']);
        } else {
            $versionService->createZipArchiveFromArtefact(trim((string)$options['artefact']));
        }

        return new FormDataPart([
            'description' => (string)$options['comment'],
            'gplCompliant' => '1',
            'file' => DataPart::fromPath($versionService->getVersionFilePath())
        ]);
    }

    /**
     * Clean the transaction directory and all its content.
     * This includes the final ZipArchive, but not the given
     * path from which the ZipArchive was created.
     *
     * Note: Using __destruct(), we ensure the transaction
     * directory will be removed in any case. Even if an
     * exception is thrown.
     */
    public function __destruct()
    {
        if (!(bool)($this->transactionPath ?? false)) {
            return;
        }

        (new Filesystem\Directory())->remove($this->transactionPath);
    }
}
