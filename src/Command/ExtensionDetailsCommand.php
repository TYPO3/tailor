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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Service\FormatService;

/**
 * Command for TER REST endpoint `GET /extension/{key}`
 */
class ExtensionDetailsCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch details about an extension')
            ->setResultFormat(FormatService::FORMAT_DETAIL)
            ->addArgument('extensionkey', InputArgument::REQUIRED, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $input->getArgument('extensionkey');
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension/' . $this->extensionKey);
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Fetching details for extension %s', $this->extensionKey),
            sprintf('Successfully fetched extensions details for extension %s.', $this->extensionKey),
            sprintf('Extension details for extension %s could not be fetched.', $this->extensionKey)
        );
    }
}
