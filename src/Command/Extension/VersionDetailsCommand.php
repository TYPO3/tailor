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
 * Command for TER REST endpoint `GET /extension/{key}/{version}`
 */
class VersionDetailsCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $version;

    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch details about an extension version')
            ->setResultFormat(ConsoleFormatter::FORMAT_DETAIL)
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3')
            ->addArgument('extensionkey', InputArgument::OPTIONAL, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->version = $input->getArgument('version');
        $this->extensionKey = $this->getExtensionKey($input);
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension/' . $this->extensionKey . '/' . $this->version);
    }

    protected function getMessages(): Messages
    {
        $variables = [$this->version, $this->extensionKey];

        return new Messages(
            sprintf('Fetching details about version %s of extension %s', ...$variables),
            sprintf('Successfully fetched details for version %s of extension %s.', ...$variables),
            sprintf('Could not fetch details for version %s of extension %s.', ...$variables)
        );
    }
}
