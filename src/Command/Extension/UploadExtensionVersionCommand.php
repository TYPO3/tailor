<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Extension;

use FAIR\DID\Keys\EdDsaKey;
use TYPO3\Tailor\Service\FairConfigurationService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Filesystem;
use TYPO3\Tailor\Formatter\ConsoleFormatter;
use TYPO3\Tailor\Helper\CommandHelper;
use TYPO3\Tailor\HttpClientFactory;
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
            ->setResultFormat(ConsoleFormatter::FORMAT_DETAIL)
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key')
            ->addOption('path', '', InputOption::VALUE_OPTIONAL, 'Path to the extension folder')
            ->addOption('artefact', '', InputOption::VALUE_OPTIONAL, 'Path or URL to a zip file')
            ->addOption('comment', '', InputOption::VALUE_OPTIONAL, 'Upload comment of the new version (e.g. release notes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->version = $input->getArgument('version');
        $this->extensionKey = CommandHelper::getExtensionKeyFromInput($input);
        $this->transactionPath = rtrim(realpath(getcwd() ?: './'), '/') . '/tailor-version-upload';

        if (!(new Filesystem\Directory())->create($this->transactionPath)) {
            throw new \RuntimeException(sprintf('Directory could not be created.'));
        }

        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        $versionService = $this->prepareVersionService($this->input->getOptions());
        $fairFields = $this->getFairpmFields($versionService->getVersionFilePath());
        $formDataPart = $this->getFormDataPart($this->input->getOptions(), $versionService, $fairFields);

        return new RequestConfiguration(
            'POST',
            'extension/' . $this->extensionKey . '/' . $this->version,
            [],
            [],
            [],
            false,
            HttpClientFactory::ALL_AUTH,
            $formDataPart
        );
    }

    private function prepareVersionService(array $options): VersionService
    {
        $versionService = new VersionService($this->version, $this->extensionKey, $this->transactionPath);

        if ($options['path'] !== null) {
            $versionService->createZipArchiveFromPath((string)$options['path']);
        } elseif ($options['artefact'] !== null) {
            $versionService->createZipArchiveFromArtefact(trim((string)$options['artefact']));
        } else {
            $versionService->createZipArchiveFromPath(getcwd() ?: './');
        }

        return $versionService;
    }

    private function getFairpmFields(string $zipFilePath): array
    {
        $config = new FairConfigurationService();

        if (!$config->didExists($this->extensionKey)) {
            return [];
        }

        $keysData = $config->loadKeysData($this->extensionKey);
        $privateMultibase = $keysData['verificationKey']['private'] ?? null;

        if ($privateMultibase === null) {
            return [];
        }

        $zipFileContents = file_get_contents($zipFilePath);
        $sha256 = hash('sha256', $zipFileContents);
        $sha384 = hash('sha384', $zipFileContents);
        $sha512 = hash('sha512', $zipFileContents);
        $signature = EdDsaKey::from_private($privateMultibase)->sign($sha384);

        return [
            'sha256' => $sha256,
            'sha384' => $sha384,
            'sha512' => $sha512,
            'didSignature' => $signature,
        ];
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
     * Create FormDataPart from given options and a prepared VersionService.
     *
     * @param array $options
     * @param VersionService $versionService
     * @return FormDataPart
     */
    protected function getFormDataPart(array $options, VersionService $versionService, array $fairFields = []): FormDataPart
    {
        if ($options['comment'] === null) {
            // The REST API requires a description to be set (just like the GUI does).
            // For now we just generate a description from the given version if non is given.
            $options['comment'] = 'Updated extension to ' . $this->version;
        }

        return new FormDataPart([
            'description' => (string)$options['comment'],
            'gplCompliant' => '1',
            'file' => DataPart::fromPath($versionService->getVersionFilePath()),
            ...$fairFields,
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
