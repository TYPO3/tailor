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
use FAIR\DID\Keys\KeyFactory;
use FAIR\DID\PLC\PlcClient;
use FAIR\DID\PLC\PlcOperation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\Tailor\Helper\CommandHelper;
use TYPO3\Tailor\Service\FairConfigurationService;

/**
 * Command to update a single field of a published did:plc for a TYPO3 extension.
 */
class UpdateDidCommand extends Command
{
    private const ALLOWED_FIELDS = ['alsoKnownAs'];

    protected function configure(): void
    {
        $this
            ->setDescription('Update a field in a published did:plc for a TYPO3 extension')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key')
            ->addArgument('field', InputArgument::OPTIONAL, 'The DID document field to update (e.g. alsoKnownAs)')
            ->addArgument('value', InputArgument::OPTIONAL, 'The new value as a JSON string (e.g. \'["did:web:…"]\')')
            ->setHelp('bin/tailor fair:did:update fairy_tale alsoKnownAs \'["did:web:extensions.typo3.org:fairy_tale"]\'');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = new FairConfigurationService();

        $extensionKey = CommandHelper::getExtensionKeyFromInput($input);
        $field = (string)($input->getArgument('field') ?? '');
        $rawValue = (string)($input->getArgument('value') ?? '');

        if ($field === '' || $rawValue === '') {
            $io->error('Arguments <field> and <value> are required.');
            return Command::FAILURE;
        }

        // Guard: local DID must exist
        if (!$config->didExists($extensionKey)) {
            $io->error(sprintf(
                'No local DID found for "%s". Run fair:did:create first.',
                $extensionKey,
            ));
            return Command::FAILURE;
        }

        // Validate field
        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            $io->error(sprintf(
                'Unsupported field "%s". Allowed fields: %s.',
                $field,
                implode(', ', self::ALLOWED_FIELDS),
            ));
            return Command::FAILURE;
        }

        // Parse JSON value
        $parsedValue = json_decode($rawValue, true);
        if ($parsedValue === null) {
            $io->error('Value must be valid JSON (e.g. \'["did:web:extensions.typo3.org:my_ext"]\').');
            return Command::FAILURE;
        }

        $didData = $config->loadDidData($extensionKey);
        $keysData = $config->loadKeysData($extensionKey);
        $did = $didData['did'];

        // Reconstruct Key objects from did.json
        $rotationKeys = array_map(
            fn(string $k) => KeyFactory::decode_did_key($k),
            $didData['rotationKeys'],
        );
        $verificationMethods = array_map(
            fn(string $k) => KeyFactory::decode_did_key($k),
            $didData['verificationMethods'],
        );

        // Apply field update
        $alsoKnownAs = $didData['alsoKnownAs'] ?? [];
        switch ($field) {
            case 'alsoKnownAs':
                $alsoKnownAs = $parsedValue;
                break;
        }

        $plcClient = new PlcClient('https://plc.directory');

        // Fetch prev CID required by PLC spec
        try {
            $prev = $plcClient->get_previous_cid($did);
        } catch (\Exception $e) {
            $io->error('Failed to fetch previous CID from plc.directory: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Reconstruct signing key from keys.json
        $signingKey = KeyFactory::decode_private_key($keysData['rotationKey']['private']);

        // Build update operation
        $operation = new PlcOperation(
            type: 'plc_operation',
            rotation_keys: $rotationKeys,
            verification_methods: $verificationMethods,
            also_known_as: $alsoKnownAs,
            services: $didData['services'] ?? [],
            prev: $prev,
        );

        $signed = DidCodec::sign_plc_operation($operation, $signingKey);

        // Submit to plc.directory
        try {
            $plcClient->update_did($did, $signed->jsonSerialize());
        } catch (\Exception $e) {
            $io->error('Failed to submit update to plc.directory: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Persist updated did.json
        $config->writeDidData($extensionKey, array_merge(['did' => $did], $signed->jsonSerialize()));

        $io->success('DID updated successfully!');
        $io->table([], [
            ['DID', $did],
            ['Updated field', $field],
            ['New value', json_encode($parsedValue, JSON_UNESCAPED_SLASHES)],
            ['plc.directory', 'https://plc.directory/' . $did],
        ]);

        return Command::SUCCESS;
    }
}
