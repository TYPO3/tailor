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
 * Command for TER REST endpoint `POST /extension/{key}`
 */
class RegisterExtensionCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Register a new extension key in TER')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'Define an extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $this->getExtensionKey($input);
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('POST', 'extension/' . $this->extensionKey);
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Registering the extension key %s', $this->extensionKey),
            sprintf('Successfully registered extension key %s.', $this->extensionKey),
            sprintf('Could not register extension key %s.', $this->extensionKey)
        );
    }
}
