<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\HttpClientFactory;
use TYPO3\Tailor\Writer\ConsoleWriter;

/**
 * Service for performing HTTP requests to the TER REST API
 */
class RequestService
{
    /** @var HttpClient */
    protected $client;

    /** @var RequestConfiguration */
    protected $requestConfiguration;

    /** @var ConsoleWriter */
    protected $consoleWriter;

    /** @var bool */
    protected $isRaw;

    public function __construct(RequestConfiguration $requestConfiguration, ConsoleWriter $consoleWriter)
    {
        $this->client = HttpClientFactory::create($requestConfiguration);
        $this->requestConfiguration = $requestConfiguration;
        $this->consoleWriter = $consoleWriter;
        $this->isRaw = $this->requestConfiguration->isRaw();
    }

    /**
     * Run the request by the given request configuration and
     * output the formatted result using the ConsoleWriter.
     *
     * @return bool
     */
    public function run(): bool
    {
        if (!$this->isRaw) {
            $this->consoleWriter->writeTitle();
        }

        try {
            $response = $this->client->request($this->requestConfiguration->getMethod(), $this->requestConfiguration->getEndpoint());
            $content = $response->getContent(false);
            $status = $response->getStatusCode();

            if ($this->isRaw) {
                // If no content is provided in the response, usually on 200
                // responses for requests which delete the remote resource,
                // we ensure to return at least the status code on the CLI.
                $this->consoleWriter->write($content ?: json_encode(['status' =>  $status]));
                return true;
            }

            $content = (array)(json_decode($content, true) ?? []);

            if ($this->requestConfiguration->isSuccessful($status)) {
                $this->consoleWriter->writeSuccess();
                $this->consoleWriter->writeFormattedResult($content);
            } else {
                $this->consoleWriter->writeFailure(self::createFailureReason($content, $status));
                return false;
            }
        } catch (ExceptionInterface|\InvalidArgumentException $e) {
            $this->consoleWriter->error('An error occurred: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Describe a failed request for the console.
     *
     * Next to the API's message, this reports the HTTP status and the API's own
     * error code. The code is what identifies the failing branch on the server:
     * TER answers `{"status": 500, "code": 1603956982, "message": "An error
     * occured on handling the request."}` for every masked exception, so the
     * message alone cannot tell two unrelated defects apart.
     *
     * @param array<string, mixed> $content Decoded response body
     */
    public static function createFailureReason(array $content, int $status): string
    {
        $reason = (string)($content['error_description'] ?? $content['message'] ?? '');
        $details = ['HTTP ' . $status];
        $code = $content['code'] ?? null;

        if ((is_int($code) || is_string($code)) && !in_array((string)$code, ['', '0'], true)) {
            $details[] = 'code ' . $code;
        }

        return ($reason !== '' ? $reason : 'Unknown') . ' (' . implode(', ', $details) . ')';
    }
}
