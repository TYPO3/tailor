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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;

/**
 * Command for TER REST endpoint `POST /extension/{key}/transfer/{username}`
 */
class TransferExtensionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $username;

    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Transfer ownership of an extension key')
            ->setConfirmationRequired(true)
            ->addArgument('username', InputArgument::REQUIRED, 'The TYPO3 username the extension should be transfered to')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->username = $input->getArgument('username');
        $this->extensionKey = $this->getExtensionKey($input);
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('POST', 'extension/' . $this->extensionKey . '/transfer/' . $this->username);
    }

    protected function getMessages(): Messages
    {
        $variables = [$this->extensionKey, $this->username];

        return new Messages(
            sprintf('Transferring extension key %s to %s', ...$variables),
            sprintf('Extension key %s successfully transferred to %s.', ...$variables),
            sprintf('Could not transfer extension key %s to %s.', ...$variables),
            sprintf('Are you sure you want to transfer the extension key %s to %s?', ...$variables)
        );
    }
}
