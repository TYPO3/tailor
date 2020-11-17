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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TYPO3\Tailor\Dto\RequestConfiguration;

/**
 * Factory for creating a Symfony HTTP client
 */
final class HttpClientFactory
{
    public const BEARER_AUTH = 1;
    public const BASIC_AUTH = 2;
    public const ALL_AUTH = 4;

    private const API_ENTRY_POINT = '/api/';
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
            'max_redirects' => 0,
            // @todo: Below options needs to be "true" for live
            'verify_peer' => false,
            'verify_host' => false,
        ];
        if ($requestConfiguration->getQuery() !== []) {
            $options['query'] = $requestConfiguration->getQuery();
        }
        if ($requestConfiguration->getBody() !== []) {
            $options['body'] = $requestConfiguration->getBody();
        }
        if (!empty($_ENV['TYPO3_API_TOKEN'])
            && ($defaultAuthMethod === self::BEARER_AUTH || $defaultAuthMethod === self::ALL_AUTH)
        ) {
            $options['auth_bearer'] = $_ENV['TYPO3_API_TOKEN'];
        } elseif (!empty($_ENV['TYPO3_API_USERNAME']) && !empty($_ENV['TYPO3_API_PASSWORD'])
            && ($defaultAuthMethod === self::BASIC_AUTH || $defaultAuthMethod === self::ALL_AUTH)
        ) {
            $options['auth_basic'] = [$_ENV['TYPO3_API_USERNAME'], $_ENV['TYPO3_API_PASSWORD']];
        }
        return HttpClient::create($options);
    }

    protected static function getBaseUri(): string
    {
        $remoteBaseUri = $_ENV['TYPO3_REMOTE_BASE_URI'] ?? '';
        $apiVersion = $_ENV['TYPO3_API_VERSION'] ?? self::DEFAULT_API_VERSION;

        if ($remoteBaseUri === '') {
            throw new \InvalidArgumentException(
                'Environment variable \'TYPO3_REMOTE_BASE_URI\' is not set',
                1605276986
            );
        }

        return trim($remoteBaseUri, '/') . self::API_ENTRY_POINT . trim($apiVersion, '/') . '/';
    }
}
