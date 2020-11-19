<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Dto;

use TYPO3\Tailor\HttpClientFactory;

/**
 * Configuration for the TER REST API request
 *
 * This DTO includes all necessary request settings like
 * the HTTP method, the endpoint, the query / body parameters
 * and additional headers.
 */
class RequestConfiguration
{
    /** @var string */
    protected $method;

    /** @var string */
    protected $endpoint;

    /** @var iterable */
    protected $query;

    /** @var iterable */
    protected $body;

    /** @var iterable */
    protected $headers;

    /** @var bool */
    protected $raw;

    /** @var int */
    protected $defaultAuthMethod;

    /** @var int[] */
    protected $successCodes = [200, 201];

    public function __construct(
        string $method,
        string $endpoint,
        iterable $query = [],
        iterable $body = [],
        iterable $headers = [],
        bool $raw = false,
        int $defaultAuthMethod = HttpClientFactory::ALL_AUTH
    ) {
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->raw = $raw;
        $this->defaultAuthMethod = $defaultAuthMethod;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getQuery(): iterable
    {
        return $this->query;
    }

    public function getBody(): iterable
    {
        return $this->body;
    }

    public function getHeaders(): iterable
    {
        return $this->headers;
    }

    public function setRaw(bool $raw): self
    {
        $this->raw = $raw;
        return $this;
    }

    public function isRaw(): bool
    {
        return $this->raw;
    }

    public function setDefaultAuthMethod(int $defaultAuthMethod): self
    {
        $this->defaultAuthMethod = $defaultAuthMethod;
        return $this;
    }

    public function getDefaultAuthMethod(): int
    {
        return $this->defaultAuthMethod;
    }

    public function isSuccessful(int $statusCode): bool
    {
        return in_array($statusCode, $this->successCodes, true);
    }
}
