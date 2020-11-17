<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Auth;

use Symfony\Component\Console\Input\InputOption;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\HttpClientFactory;

/**
 * Command for TER REST endpoint `POST /auth/token`
 */
class CreateTokenCommand extends AbstractClientRequestCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Request an access token for the TER')
            ->setDefaultAuthMethod(HttpClientFactory::BASIC_AUTH)
            ->addOption(
                'name',
                '',
                InputOption::VALUE_OPTIONAL,
                'The name for the new access token'
            )
            ->addOption(
                'expires',
                '',
                InputOption::VALUE_OPTIONAL,
                'The name for the new access token'
            )
            ->addOption(
                'scope',
                '',
                InputOption::VALUE_OPTIONAL,
                'The scopes for the access token as comma separated list',
                'extension:read,extension:write'
            )
            ->addOption(
                'extensions',
                '',
                InputOption::VALUE_OPTIONAL,
                'The extensions, the access token should have access to'
            );
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration('POST', 'auth/token', $this->getQuery($this->input->getOptions()));
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            'Creating an access token',
            'Access token was successfully created.',
            'Access token could not be created.'
        );
    }

    protected function getQuery(array $options): array
    {
        $query = [];

        if ($options['name'] !== null) {
            $query['name'] = $options['name'];
        }
        if ($options['expires'] !== null) {
            $query['expires'] = $options['expires'];
        }
        if ($options['scope'] !== null) {
            $query['scope'] = $options['scope'];
        }
        if ($options['extensions'] !== null) {
            $query['extensions'] = $options['extensions'];
        }

        return $query;
    }
}
