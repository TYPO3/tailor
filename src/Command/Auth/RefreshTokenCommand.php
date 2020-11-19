<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Command\Auth;

use Symfony\Component\Console\Input\InputArgument;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;
use TYPO3\Tailor\Dto\Messages;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\HttpClientFactory;

/**
 * Command for TER REST endpoint `POST /auth/token/refresh`
 */
class RefreshTokenCommand extends AbstractClientRequestCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Refresh an access token for the TER')
            ->setDefaultAuthMethod(HttpClientFactory::BASIC_AUTH)
            ->addArgument('token', InputArgument::REQUIRED, 'The refresh token, recieved with the access token.');
    }

    protected function getRequestConfiguration(): RequestConfiguration
    {
        return new RequestConfiguration(
            'POST',
            'auth/token/refresh',
            [],
            ['token' => $this->input->getArgument('token')],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );
    }

    protected function getMessages(): Messages
    {
        return new Messages(
            'Refreshing an access token',
            'Access token was successfully refreshed.',
            'Access token could not be refreshed.'
        );
    }
}
