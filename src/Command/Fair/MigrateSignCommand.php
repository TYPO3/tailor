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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Service\FairService;

/**
 * Command for signing an arbitrary extension version with a local Ed25519 PEM key
 * and writing the resulting FAIR artifact metadata to a local JSON file.
 *
 * Unlike fair:extension:sign, this command does not require a local fairpm config
 * store or TER API credentials — it is intended for batch / migration use-cases.
 */
class MigrateSignCommand extends Command
{
    private const DEFAULT_BASE_URI = 'https://extensions.typo3.org';

    protected function configure(): void
    {
        $this
            ->setDescription('Sign an extension version with a local Ed25519 PEM key and write FAIR metadata to a JSON file')
            ->addArgument('extensionkey', InputArgument::REQUIRED, 'The extension key (e.g. news)')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to sign (e.g. 14.0.1)')
            ->addOption(
                'out',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the JSON output file (created or merged into)',
                'releases.json'
            )
            ->addOption(
                'key',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the Ed25519 private key in PEM format'
            )
            ->setHelp('bin/tailor fair:migrate:sign news 14.0.0 --key ~/.config/private.pem --out news.releases.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extensionKey = (string)$input->getArgument('extensionkey');
        $version = (string)$input->getArgument('version');
        $outFile = (string)$input->getOption('out');
        $keyFile = (string)$input->getOption('key');

        // 1. Validate --key option
        if ($keyFile === '') {
            $io->error('Option --key is required. Provide a path to an Ed25519 PEM private key.');
            return Command::FAILURE;
        }

        if (!is_readable($keyFile)) {
            $io->error(sprintf('Key file "%s" does not exist or is not readable.', $keyFile));
            return Command::FAILURE;
        }

        if (!function_exists('sodium_crypto_sign_detached')) {
            $io->error('Ed25519 signing requires the PHP sodium extension (ext-sodium).');
            return Command::FAILURE;
        }

        $secretKey = $this->loadEd25519SecretKeyFromPem((string)file_get_contents($keyFile));
        if ($secretKey === null) {
            $io->error(sprintf('Failed to load Ed25519 private key from "%s". Expected a PKCS#8 PEM file (BEGIN PRIVATE KEY).', $keyFile));
            return Command::FAILURE;
        }

        // 2. Resolve did:web: identifier (deterministic, no local config needed)
        $did = (new FairService())->resolveDidWeb($extensionKey);

        // 3. Build ZIP URL using the fileadmin path convention
        $baseUri = rtrim(Variables::get('TYPO3_REMOTE_BASE_URI') ?: self::DEFAULT_BASE_URI, '/');
        $a = $extensionKey[0];
        $b = $extensionKey[1] ?? $a;
        $zipUrl = sprintf('%s/fileadmin/ter/%s/%s/%s_%s.zip', $baseUri, $a, $b, $extensionKey, $version);

        // 4. Download the extension ZIP
        $io->writeln(sprintf('Downloading ZIP from <info>%s</info>...', $zipUrl));
        $client = HttpClient::create(['max_redirects' => 5]);

        try {
            $response = $client->request('GET', $zipUrl, [
                'headers' => ['User-Agent' => 'Tailor - Your TYPO3 Extension Helper'],
            ]);
            $zipContents = $response->getContent();
        } catch (\Throwable $e) {
            $io->error('Failed to download extension ZIP: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // 7. Compute SHA hashes
        $sha256 = hash('sha256', $zipContents);
        $sha384 = hash('sha384', $zipContents);
        $sha512 = hash('sha512', $zipContents);

        // 8. Sign the hex-encoded SHA-384 with the Ed25519 secret key
        $didSignature = bin2hex(sodium_crypto_sign_detached($sha384, $secretKey));

        // 9. Build the artifact record
        $artifactRecord = [
            'url' => $zipUrl,
            'content-type' => 'application/zip',
            'signer' => $did,
            'sha256' => $sha256,
            'sha384' => $sha384,
            'sha512' => $sha512,
            'didSignature' => $didSignature,
        ];

        // 10. Merge into the output JSON file
        $this->mergeReleaseRecord($outFile, $version, $zipUrl, $artifactRecord);

        $io->success(sprintf(
            'Version %s of extension %s signed and written to %s.',
            $version,
            $extensionKey,
            $outFile
        ));
        $io->table([], [
            ['DID (signer)', $did],
            ['ZIP URL', $zipUrl],
            ['SHA-256', $sha256],
            ['SHA-384', $sha384],
            ['SHA-512', $sha512],
            ['didSignature', $didSignature],
            ['Output file', $outFile],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Parse a PKCS#8 Ed25519 PEM private key and return the 64-byte libsodium secret key
     * (seed || public key), or null if the PEM cannot be decoded.
     *
     * A PKCS#8 Ed25519 DER blob is always 48 bytes:
     *   30 2e 30 05 06 03 2b 65 70 04 22 04 20 [32-byte seed]
     * The seed occupies the final 32 bytes.
     */
    private function loadEd25519SecretKeyFromPem(string $pem): ?string
    {
        // Strip PEM envelope and decode
        $der = base64_decode(
            preg_replace('/-----[^-]+-----|[\r\n\s]+/', '', $pem) ?? '',
            strict: true
        );

        if ($der === false || strlen($der) < 32) {
            return null;
        }

        // The 32-byte Ed25519 seed is always the last 32 bytes of the PKCS#8 blob
        $seed = substr($der, -32);

        $keypair = sodium_crypto_sign_seed_keypair($seed);
        return sodium_crypto_sign_secretkey($keypair);
    }

    private function mergeReleaseRecord(
        string $outFile,
        string $version,
        string $zipUrl,
        array $artifactRecord
    ): void {
        $data = ['releases' => []];
        if (file_exists($outFile)) {
            $decoded = json_decode((string)file_get_contents($outFile), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $releaseIndex = null;
        foreach ($data['releases'] as $i => $release) {
            if (($release['version'] ?? '') === $version) {
                $releaseIndex = $i;
                break;
            }
        }

        if ($releaseIndex !== null) {
            $packages = $data['releases'][$releaseIndex]['artifacts']['package'] ?? [];
            $artIndex = null;
            foreach ($packages as $j => $art) {
                if (($art['url'] ?? '') === $zipUrl) {
                    $artIndex = $j;
                    break;
                }
            }

            if ($artIndex !== null) {
                $data['releases'][$releaseIndex]['artifacts']['package'][$artIndex] = $artifactRecord;
            } else {
                $data['releases'][$releaseIndex]['artifacts']['package'][] = $artifactRecord;
            }
        } else {
            $data['releases'][] = [
                'version' => $version,
                'artifacts' => [
                    'package' => [$artifactRecord],
                ],
            ];
        }

        usort($data['releases'], static fn(array $a, array $b) => version_compare($a['version'] ?? '', $b['version'] ?? ''));

        file_put_contents(
            $outFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
}
