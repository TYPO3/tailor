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

use Symfony\Component\Console\Input\InputOption;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Formatter\ConsoleFormatter;

/**
 * Command for TER REST endpoint `GET /extension`
 */
class FindExtensionsCommand extends AbstractClientRequestCommand
{
    private const PAGINATION_OPTION_MAPPING = ['page' => 'page', 'per-page' => 'per_page'];
    private const FILTER_OPTION_MAPPING = ['author' => 'username', 'typo3-version' => 'typo3_version'];

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch a list of extensions from TER')
            ->setResultFormat(ConsoleFormatter::FORMAT_TABLE)
            ->addOption('page', '', InputOption::VALUE_OPTIONAL, 'Page number for paginated result')
            ->addOption('per-page', '', InputOption::VALUE_OPTIONAL, 'Per page limit for paginated result')
            ->addOption('author', '', InputOption::VALUE_OPTIONAL, 'Filter by a specific author. Use the TYPO3 username.')
            ->addOption('typo3-version', '', InputOption::VALUE_OPTIONAL, 'Only list extensions compatible with a specific major TYPO3 version');
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension', $this->getQuery());
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            'Fetching registered remote extensions',
            'Successfully fetched remote extensions.',
            'Could not fetch remote extensions.'
        );
    }

    protected function getQuery(): array
    {
        $options = $this->input->getOptions();
        $query = [];

        foreach (self::PAGINATION_OPTION_MAPPING as $optionName => $queryName) {
            if ($options[$optionName] !== null) {
                $query[$queryName] = $options[$optionName];
            }
        }

        foreach (self::FILTER_OPTION_MAPPING as $optionName => $queryName) {
            if ($options[$optionName] !== null) {
                $query['filter'][$queryName] = $options[$optionName];
            }
        }

        return $query;
    }
}
