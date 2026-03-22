<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Fair;

use FAIR\DID\Crypto\DidCodec;
use FAIR\DID\Keys\Key;
use FAIR\DID\Keys\KeyFactory;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Environment\Variables;
use TYPO3\Tailor\Helper\CommandHelper;
use TYPO3\Tailor\Service\FairConfigurationService;
use TYPO3\Tailor\Service\FairService;

/**
 * Command to generate a did:plc identifier for a TYPO3 extension.
 */
class CreateDidCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Generate a new did:plc for a TYPO3 extension')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fairService = new FairService();

        $extensionKey = CommandHelper::getExtensionKeyFromInput($input);
        $config = new FairConfigurationService();
        $plcClient = new PlcClient('https://plc.directory');

        if ($config->didExists($extensionKey)) {
            $didData = $config->loadDidData($extensionKey);
            $did = $didData['did'] ?? '';
            $plcUrl = 'https://plc.directory/' . $did;

            try {
                $plcClient->resolve_did($did);
            } catch (\Exception) {
                $io->warning('DID exists locally but has not been published to plc.directory.');
                $io->table([], [
                    ['DID', $did],
                    ['Local file', $config->getDidFile($extensionKey)],
                ]);
                return Command::FAILURE;
            }

            // DID is published — regenerate alsoKnownAs and services without touching keys.
            $keysData = $config->loadKeysData($extensionKey);
            $rotationKeys = array_map(
                fn(string $k) => KeyFactory::decode_did_key($k),
                $didData['rotationKeys'],
            );
            $verificationMethods = array_map(
                fn(string $k) => KeyFactory::decode_did_key($k),
                $didData['verificationMethods'],
            );
            $signingKey = KeyFactory::decode_private_key($keysData['rotationKey']['private']);

            try {
                $this->publishUpdate(
                    $plcClient,
                    $config,
                    $extensionKey,
                    $did,
                    $rotationKeys,
                    $verificationMethods,
                    [$fairService->resolveDidWeb($extensionKey)],
                    $this->buildServicesArray($did),
                    $signingKey,
                );
            } catch (\Exception $e) {
                $io->error('Failed to update DID on plc.directory: ' . $e->getMessage());
                return Command::FAILURE;
            }

            $io->success('DID updated successfully!');
            $io->table([], [
                ['DID', $did],
                ['plc.directory', $plcUrl],
                ['DID file', $config->getDidFile($extensionKey)],
            ]);

            return Command::SUCCESS;
        }

        // Load or initialise keys.json
        $keysData = $config->loadKeysData($extensionKey);
        $config->ensureSalt($keysData);

        // Derive the static rotation key (recovery key) using HKDF
        $staticRotationKey = $fairService->deriveRecoveryKey(
            Variables::get('TYPO3_API_USERNAME'),
            Variables::get('TYPO3_API_PASSWORD'),
            $keysData['recovery']['salt'],
        );

        // Generate fresh keys
        $rotationKey = DidCodec::generate_key_pair();
        $verificationKey = DidCodec::generate_ed25519_key_pair();

        // Build genesis PlcOperation (no services — DID not yet known)
        $keyId = substr(hash('sha256', $verificationKey->encode_public()), 0, 6);
        $operation = new PlcOperation(
            type: 'plc_operation',
            rotation_keys: [$rotationKey, $staticRotationKey],
            verification_methods: ['fair_' . $keyId => $verificationKey],
            also_known_as: [$fairService->resolveDidWeb($extensionKey)],
            services: [],
        );

        // Sign & generate DID
        $signed = DidCodec::sign_plc_operation($operation, $rotationKey);
        $did = DidCodec::generate_plc_did($signed);

        // Submit genesis to plc.directory
        try {
            $plcClient->create_did($did, $signed->jsonSerialize());
        } catch (\Exception $e) {
            $io->error('Failed to submit DID to plc.directory: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Persist config
        $config->ensureConfigDir($extensionKey);

        $keysData['rotationKey'] = [
            'private' => $rotationKey->encode_private(),
            'public' => $rotationKey->encode_public(),
        ];
        $keysData['verificationKey'] = [
            'private' => $verificationKey->encode_private(),
            'public' => $verificationKey->encode_public(),
        ];
        $config->writeKeysData($extensionKey, $keysData);
        $config->writeDidData($extensionKey, array_merge(['did' => $did], $signed->jsonSerialize()));

        // Submit follow-up update to add alsoKnownAs + services (DID is now known)
        $rotationKeys = [$rotationKey, $staticRotationKey];
        $verificationMethods = ['fair_' . $keyId => $verificationKey];

        try {
            $this->publishUpdate(
                $plcClient,
                $config,
                $extensionKey,
                $did,
                $rotationKeys,
                $verificationMethods,
                [$fairService->resolveDidWeb($extensionKey)],
                $this->buildServicesArray($did),
                $rotationKey,
            );
        } catch (\Exception $e) {
            $io->warning('DID created but service endpoint could not be published: ' . $e->getMessage() . ' Re-run this command to retry.');
        }

        $io->success('DID created successfully!');
        $io->table([], [
            ['DID', $did],
            ['Keys file', $config->getKeysFile($extensionKey)],
            ['DID file', $config->getDidFile($extensionKey)],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Build the standard FAIR package management service array for a given DID.
     */
    private function buildServicesArray(string $did): array
    {
        return [
            'fairpm_repo' => [
                'type' => 'FairPackageManagementRepo',
                'endpoint' => 'https://extensions.typo3.org/fair/v1/packages/' . $did,
            ],
        ];
    }

    /**
     * Submit a plc_operation update to plc.directory and persist the result locally.
     *
     * @param PlcClient $plcClient
     * @param FairConfigurationService $config
     * @param string $extensionKey
     * @param string $did
     * @param Key[] $rotationKeys
     * @param array<string, Key> $verificationMethods
     * @param string[] $alsoKnownAs
     * @param array<string, array> $services
     * @param Key $signingKey
     */
    private function publishUpdate(
        PlcClient $plcClient,
        FairConfigurationService $config,
        string $extensionKey,
        string $did,
        array $rotationKeys,
        array $verificationMethods,
        array $alsoKnownAs,
        array $services,
        Key $signingKey,
    ): void {
        $prev = $plcClient->get_previous_cid($did);

        $operation = new PlcOperation(
            type: 'plc_operation',
            rotation_keys: $rotationKeys,
            verification_methods: $verificationMethods,
            also_known_as: $alsoKnownAs,
            services: $services,
            prev: $prev,
        );

        $signed = DidCodec::sign_plc_operation($operation, $signingKey);
        $plcClient->update_did($did, $signed->jsonSerialize());
        $config->writeDidData($extensionKey, array_merge(['did' => $did], $signed->jsonSerialize()));
    }
}
