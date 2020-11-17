<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
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

/**
 * Command for TER REST endpoint `POST /extension/{key}/transfer/{username}`
 */
class TransferExtensionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    /** @var string */
    protected $username;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Transfer ownership of an extension key')
            ->setConfirmationRequired(true)
            ->addArgument(
                'extensionkey',
                InputArgument::REQUIRED,
                'The extension key'
            )
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'The TYPO3 username the extension should be transfered to, e.g. georgringer'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $input->getArgument('extensionkey');
        $this->username = $input->getArgument('username');
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('POST', 'extension/' . $this->extensionKey . '/transfer/' . $this->username);
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Transferring extension %s to %s', $this->extensionKey, $this->username),
            sprintf('Extension %s successfully transferred to %s.', $this->extensionKey, $this->username),
            sprintf('Could not transfer extension key %s to %s.', $this->extensionKey, $this->username),
            sprintf('Are you sure you want to transfer the extension %s to %s?', $this->extensionKey, $this->username)
        );
    }
}
