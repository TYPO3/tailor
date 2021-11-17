<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\Environment\Variables;

/**
 * Factory for creating a Symfony HTTP client
 */
final class HttpClientFactory
{
    public const BEARER_AUTH = 1;
    public const BASIC_AUTH = 2;
    public const ALL_AUTH = 4;

    private const API_ENTRY_POINT = '/api/';

    private const DEFAULT_BASE_URI = 'https://extensions.typo3.org';
    private const DEFAULT_API_VERSION = 'v1';

    public static function create(RequestConfiguration $requestConfiguration): HttpClientInterface
    {
        $defaultAuthMethod = $requestConfiguration->getDefaultAuthMethod();
        $options = [
            'base_uri' => self::getBaseUri(),
            'headers' => array_replace_recursive([
                'Accept' => 'application/json',
                'User-Agent' => 'Tailor - Your TYPO3 Extension Helper',
            ], $requestConfiguration->getHeaders()),
            // REST API does not perform redirects
            'max_redirects' => 0,
        ];
        if ($requestConfiguration->getQuery() !== []) {
            $options['query'] = $requestConfiguration->getQuery();
        }
        if ($requestConfiguration->getBody() !== []) {
            $options['body'] = $requestConfiguration->getBody();
        }
        if (($defaultAuthMethod === self::BEARER_AUTH || $defaultAuthMethod === self::ALL_AUTH)
            && Variables::has('TYPO3_API_TOKEN')
        ) {
            $options['auth_bearer'] = Variables::get('TYPO3_API_TOKEN');
        } elseif (($defaultAuthMethod === self::BASIC_AUTH || $defaultAuthMethod === self::ALL_AUTH)
            && (Variables::has('TYPO3_API_USERNAME') && Variables::has('TYPO3_API_PASSWORD'))
        ) {
            $options['auth_basic'] = [Variables::get('TYPO3_API_USERNAME'), Variables::get('TYPO3_API_PASSWORD')];
        } else {
            // Since currently only requests to access restricted endpoints are implemented,
            // we can throw an exception if the request lacks authentication credentials.
            throw new \InvalidArgumentException('No authentication credentials are defined.', 1606995339);
        }
        return HttpClient::create($options);
    }

    protected static function getBaseUri(): string
    {
        $remoteBaseUri = Variables::get('TYPO3_REMOTE_BASE_URI') ?: self::DEFAULT_BASE_URI;
        $apiVersion =  Variables::get('TYPO3_API_VERSION') ?: self::DEFAULT_API_VERSION;

        return trim($remoteBaseUri, '/') . self::API_ENTRY_POINT . trim($apiVersion, '/') . '/';
    }
}
