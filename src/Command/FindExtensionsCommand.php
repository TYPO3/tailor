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

use Symfony\Component\Console\Input\InputOption;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Service\FormatService;

/**
 * Command for TER REST endpoint `GET /extension`
 */
class FindExtensionsCommand extends AbstractClientRequestCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Fetch a list of extensions from TER')
            ->setResultFormat(FormatService::FORMAT_TABLE)
            ->addOption(
                'page',
                '',
                InputOption::VALUE_OPTIONAL,
                'Page number for paginated result'
            )
            ->addOption(
                'per-page',
                '',
                InputOption::VALUE_OPTIONAL,
                'FPer page limit for paginated result'
            )
            ->addOption(
                'author',
                '',
                InputOption::VALUE_OPTIONAL,
                'Filter by a specific author. Use the TYPO3 username.'
            )
            ->addOption(
                'typo3-version',
                '',
                InputOption::VALUE_OPTIONAL,
                'Only list extensions compatible with a specific major TYPO3 version, use it like --typo3-version=10'
            );
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('GET', 'extension', $this->getQuery($this->input->getOptions()));
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            'Fetching registered remote extensions',
            'Successfully fetched remote extensions.',
            'Could not fetch remote extensions.'
        );
    }

    protected function getQuery(array $options): array
    {
        $query = [];

        if ($options['page'] !== null) {
            $query['page'] = $options['page'];
        }
        if ($options['per-page'] !== null) {
            $query['per_page'] = $options['per-page'];
        }
        if ($options['author'] !== null) {
            $query['filter']['username'] = $options['author'];
        }
        if ($options['typo3-version'] !== null) {
            $query['filter']['typo3_version'] = $options['typo3-version'];
        }

        return $query;
    }
}
