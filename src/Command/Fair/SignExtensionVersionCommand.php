<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Fair;

use FAIR\DID\Keys\EdDsaKey;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Service\FairConfigurationService;

/**
 * Command for signing an already-published extension version with a local FAIR DID.
 *
 * Downloads the extension ZIP from extensions.typo3.org, computes SHA hashes,
 * creates an Ed25519 signature and submits the FAIR metadata via PATCH
 * /extension/{key}/{version} — without re-uploading the binary.
 */
class SignExtensionVersionCommand extends Command
{
    private const DEFAULT_BASE_URI = 'https://extensions.typo3.org';
    private const DEFAULT_API_VERSION = 'v1';
    private const API_ENTRY_POINT = '/api/';

    protected function configure(): void
    {
        $this
            ->setDescription('Sign an already-published extension version on TER with a local FAIR DID')
            ->addArgument('extensionkey', InputArgument::REQUIRED, 'The extension key')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to sign, e.g. 1.2.3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = (string)$input->getArgument('extensionkey');
        $version = (string)$input->getArgument('version');

        // 1. Validate a local DID exists for this extension
        $config = new FairConfigurationService();
        if (!$config->didExists($extensionKey)) {
            $io->error(sprintf(
                'No local DID found for extension "%s". Run fair:did:create first.',
                $extensionKey
            ));
            return Command::FAILURE;
        }

        // 2. Load DID identifier and private signing key
        $didData = $config->loadDidData($extensionKey);
        $did = $didData['did'] ?? null;
        if ($did === null) {
            $io->error('DID data is corrupt: missing "did" field.');
            return Command::FAILURE;
        }

        $keysData = $config->loadKeysData($extensionKey);
        $privateMultibase = $keysData['verificationKey']['private'] ?? null;
        if ($privateMultibase === null) {
            $io->error('No private verification key found in keys.json. Cannot sign.');
            return Command::FAILURE;
        }

        // 3. Download the extension ZIP from extensions.typo3.org
        $baseUri = rtrim(Variables::get('TYPO3_REMOTE_BASE_URI') ?: self::DEFAULT_BASE_URI, '/');
        $zipUrl = sprintf('%s/extension/download/%s/%s/zip/', $baseUri, $extensionKey, $version);

        $io->writeln(sprintf('Downloading ZIP from <info>%s</info>...', $zipUrl));
        $downloadClient = HttpClient::create(['max_redirects' => 5]);

        try {
            $zipResponse = $downloadClient->request('GET', $zipUrl, [
                'headers' => ['User-Agent' => 'Tailor - Your TYPO3 Extension Helper'],
            ]);
            $zipContents = $zipResponse->getContent();
        } catch (\Throwable $e) {
            $io->error('Failed to download extension ZIP: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // 4. Compute SHA hashes of the ZIP
        $sha256 = hash('sha256', $zipContents);
        $sha384 = hash('sha384', $zipContents);
        $sha512 = hash('sha512', $zipContents);

        // 5. Create Ed25519 signature over the hex-encoded SHA-384
        $signature = EdDsaKey::from_private($privateMultibase)->sign($sha384);

        // 6. Submit FAIR metadata via PATCH (no binary re-upload)
        $io->writeln('Submitting FAIR signature and hashes to TER...');
        $terClient = $this->createTerApiClient();

        try {
            $patchResponse = $terClient->request('PATCH', 'extension/' . $extensionKey . '/' . $version, [
                'body' => [
                    'sha256' => $sha256,
                    'sha384' => $sha384,
                    'sha512' => $sha512,
                    'didSignature' => $signature,
                ],
            ]);
            $patchStatus = $patchResponse->getStatusCode();
            $patchContent = (array)(json_decode($patchResponse->getContent(false), true) ?? []);
        } catch (\Throwable $e) {
            $io->error('Failed to submit FAIR metadata to TER: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($patchStatus >= 200 && $patchStatus < 300) {
            $io->success(sprintf(
                'Version %s of extension %s successfully signed and registered with FAIR metadata.',
                $version,
                $extensionKey
            ));
            return Command::SUCCESS;
        }

        $errorMessage = $patchContent['error_description'] ?? $patchContent['message'] ?? 'Unknown error (Status ' . $patchStatus . ')';
        $io->error('Could not submit FAIR metadata to TER: ' . $errorMessage);
        return Command::FAILURE;
    }

    /**
     * Creates an authenticated Symfony HTTP client for the TER REST API.
     */
    private function createTerApiClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        $remoteBaseUri = Variables::get('TYPO3_REMOTE_BASE_URI') ?: self::DEFAULT_BASE_URI;
        $apiVersion = Variables::get('TYPO3_API_VERSION') ?: self::DEFAULT_API_VERSION;
        $baseUri = rtrim($remoteBaseUri, '/') . self::API_ENTRY_POINT . trim($apiVersion, '/') . '/';

        $options = [
            'base_uri' => $baseUri,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Tailor - Your TYPO3 Extension Helper',
            ],
            'max_redirects' => 0,
        ];

        if (Variables::has('TYPO3_API_TOKEN')) {
            $options['auth_bearer'] = Variables::get('TYPO3_API_TOKEN');
        } elseif (Variables::has('TYPO3_API_USERNAME') && Variables::has('TYPO3_API_PASSWORD')) {
            $options['auth_basic'] = [Variables::get('TYPO3_API_USERNAME'), Variables::get('TYPO3_API_PASSWORD')];
        } else {
            throw new \InvalidArgumentException('No authentication credentials are defined.', 1606995339);
        }

        return HttpClient::create($options);
    }
}
