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
use TYPO3\Tailor\Formatter\ConsoleFormatter;

/**
 * Command for TER REST endpoint `DELETE /extension/{key}`
 */
class DeleteExtensionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Delete an extension')
            ->setResultFormat(ConsoleFormatter::FORMAT_NONE)
            ->setConfirmationRequired(true)
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $this->getExtensionKey($input);
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('DELETE', 'extension/' . $this->extensionKey);
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Deleting extension %s', $this->extensionKey),
            sprintf('Extension %s successfully deleted.', $this->extensionKey),
            sprintf('Could not delete extension %s.', $this->extensionKey),
            sprintf('Are you sure you want to delete the extension %s?', $this->extensionKey)
        );
    }
}
