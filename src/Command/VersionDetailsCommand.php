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
use TYPO3\Tailor\Service\FormatService;

/**
 * Command for TER REST endpoint `GET /extension/{key}/{version}`
 */
class VersionDetailsCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    /** @var string */
    protected $version;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch details about an extension version')
            ->setResultFormat(FormatService::FORMAT_DETAIL)
            ->addArgument('extensionkey', InputArgument::REQUIRED, 'The extension key')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to publish, e.g. 1.2.3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $input->getArgument('extensionkey');
        $this->version = $input->getArgument('version');
        parent::execute($input, $output);
        return (int)$this->requestService->run();
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension/' . $this->extensionKey . '/' . $this->version);
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Fetching details about version %s of extension %s', $this->version, $this->extensionKey),
            sprintf('Successfully fetched details for version %s of extension %s.', $this->version, $this->extensionKey),
            sprintf('Could not fetch details for version %s of extension %s.', $this->version, $this->extensionKey)
        );
    }
}