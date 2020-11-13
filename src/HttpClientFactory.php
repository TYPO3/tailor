<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor;

use Symfony\Component\HttpClient\HttpClient;

class HttpClientFactory
{
    public static function create(string $apiVersion)
    {
        $options = [
            'base_uri' => self::getRemoteUri() . 'api/' . $apiVersion . '/',
            // @todo: needs to be "true" for live
            'verify_peer' => false,
            'json' => true
        ];
        if (!empty($_ENV['TYPO3_API_TOKEN'])) {
            $options['auth_bearer'] = $_ENV['TYPO3_API_TOKEN'];
        } elseif (!empty($_ENV['TYPO3_API_USERNAME']) && !empty($_ENV['TYPO3_API_PASSWORD'])) {
            $options['auth_basic'] = [$_ENV['TYPO3_API_USERNAME'], $_ENV['TYPO3_API_PASSWORD']];
        }
        return HttpClient::create($options);
    }
    private static function getRemoteUri(): string
    {
        return rtrim($_ENV['TYPO3_REMOTE_BASE_URI'], '/') . '/';
    }
}