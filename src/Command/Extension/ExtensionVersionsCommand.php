<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
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
use TYPO3\Tailor\Service\FormatService;

/**
 * Command for TER REST endpoint `GET /extension/{key}/versions`
 */
class ExtensionVersionsCommand extends AbstractClientRequestCommand
{
    /** @var string */
    protected $extensionKey;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch details for all versions of the extension')
            ->setResultFormat(FormatService::FORMAT_DETAIL)
            ->addArgument('extensionkey', InputArgument::REQUIRED, 'The extension key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->extensionKey = $input->getArgument('extensionkey');
        // @todo the response format needs to be adjusted!
        return parent::execute($input, $output);
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension/' . $this->extensionKey . '/versions');
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            sprintf('Fetching details for all version of extension %s', $this->extensionKey),
            sprintf('Successfully fetched details for all versions of extension %s.', $this->extensionKey),
            sprintf('Could not fetch details for all version of extension %s.', $this->extensionKey)
        );
    }
}
