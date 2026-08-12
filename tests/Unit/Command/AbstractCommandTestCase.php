<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use TYPO3\Tailor\Command\AbstractClientRequestCommand;

/**
 * Base class for command tests.
 *
 * Commands talking to the TER API get a MockHttpClient handed in, which is
 * passed through HttpClientFactory just like a real client. The recorded
 * requests therefore show the actual method, URL and options the command
 * would have sent.
 */
abstract class AbstractCommandTestCase extends TestCase
{
    protected const BASE_URI = 'https://ter.example.org/api/v1/';

    /** @var array<int, array{method: string, url: string, options: array}> */
    protected $requests = [];

    /** @var array<string, string|false> */
    private $environmentBackup = [];

    protected function setUp(): void
    {
        $this->requests = [];
        $this->setEnvironment([
            'TYPO3_API_TOKEN' => 'test-token',
            'TYPO3_API_USERNAME' => 'test-user',
            'TYPO3_API_PASSWORD' => 'test-password',
            'TYPO3_REMOTE_BASE_URI' => 'https://ter.example.org',
            'TYPO3_API_VERSION' => 'v1',
            'TYPO3_EXTENSION_KEY' => '',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->environmentBackup as $name => $value) {
            if ($value === false) {
                unset($_ENV[$name]);
                putenv($name);
            } else {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
        $this->environmentBackup = [];
    }

    /**
     * @param array<string, string> $variables
     */
    protected function setEnvironment(array $variables): void
    {
        foreach ($variables as $name => $value) {
            if (!array_key_exists($name, $this->environmentBackup)) {
                $this->environmentBackup[$name] = $_ENV[$name] ?? getenv($name);
            }
            if ($value === '') {
                unset($_ENV[$name]);
                putenv($name);
                continue;
            }
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    /**
     * Build a tester for a command which answers with the given responses in order.
     */
    protected function apiTester(AbstractClientRequestCommand $command, MockResponse ...$responses): CommandTester
    {
        $command->setHttpClient($this->client(...$responses));

        return new CommandTester($command);
    }

    protected function client(MockResponse ...$responses): MockHttpClient
    {
        $queue = $responses;

        return new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($queue) ?? self::jsonResponse([]);
        });
    }

    /**
     * @param array<mixed> $data
     */
    protected static function jsonResponse(array $data, int $status = 200): MockResponse
    {
        return new MockResponse((string)json_encode($data), ['http_code' => $status]);
    }

    protected static function errorResponse(string $message, int $status = 400, ?int $code = null): MockResponse
    {
        $body = ['status' => $status, 'message' => $message];
        if ($code !== null) {
            $body['code'] = $code;
        }

        return self::jsonResponse($body, $status);
    }

    /**
     * @return array{method: string, url: string, options: array}
     */
    protected function request(int $index = 0): array
    {
        self::assertArrayHasKey($index, $this->requests, sprintf('No request was sent at index %d.', $index));

        return $this->requests[$index];
    }

    /**
     * The request body as it would go over the wire.
     */
    protected function requestBody(int $index = 0): string
    {
        $body = $this->request($index)['options']['body'] ?? '';

        if (is_string($body)) {
            return $body;
        }

        if (is_resource($body)) {
            return (string)stream_get_contents($body);
        }

        if (is_callable($body)) {
            $content = '';
            while (($chunk = $body(8192)) !== '') {
                $content .= $chunk;
            }
            return $content;
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(int $index = 0): array
    {
        $headers = [];
        foreach ($this->request($index)['options']['headers'] ?? [] as $key => $value) {
            // Normalized options carry headers as "Name: value" lines
            if (is_int($key) && is_string($value) && str_contains($value, ':')) {
                [$name, $headerValue] = explode(':', $value, 2);
                $headers[strtolower(trim($name))] = trim($headerValue);
                continue;
            }
            $headers[strtolower((string)$key)] = is_array($value) ? (string)reset($value) : (string)$value;
        }

        return $headers;
    }

    /**
     * Console output with all whitespace collapsed.
     *
     * SymfonyStyle wraps its output at the terminal width, which would
     * otherwise split the very strings a test asserts on.
     */
    protected static function display(CommandTester $tester): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    protected static function assertDisplayContains(string $needle, CommandTester $tester): void
    {
        self::assertStringContainsString($needle, self::display($tester));
    }
}
