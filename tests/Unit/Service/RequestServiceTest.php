<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project - inspiring people to share!
 * (c) 2020-2024 Oliver Bartsch, Benni Mack & Elias Häußler
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Service\RequestService;

final class RequestServiceTest extends TestCase
{
    /**
     * @param array<string, mixed> $content
     */
    #[Test]
    #[DataProvider('createFailureReasonTestDataProvider')]
    public function createFailureReasonTest(array $content, int $status, string $expected): void
    {
        self::assertSame($expected, RequestService::createFailureReason($content, $status));
    }

    /**
     * Data provider for createFailureReasonTest
     */
    public static function createFailureReasonTestDataProvider(): \Generator
    {
        yield 'Message and error code' => [
            ['status' => 500, 'code' => 1603956982, 'message' => 'An error occured on handling the request.'],
            500,
            'An error occured on handling the request. (HTTP 500, code 1603956982)',
        ];
        yield 'Message without error code' => [
            ['message' => 'Extension key not found.'],
            404,
            'Extension key not found. (HTTP 404)',
        ];
        yield 'OAuth style error description wins over message' => [
            ['error_description' => 'The access token is invalid.', 'message' => 'Unauthorized'],
            401,
            'The access token is invalid. (HTTP 401)',
        ];
        yield 'Empty response body' => [
            [],
            502,
            'Unknown (HTTP 502)',
        ];
        yield 'Error code sent as string' => [
            ['message' => 'Denied.', 'code' => 'invalid_grant'],
            400,
            'Denied. (HTTP 400, code invalid_grant)',
        ];
        yield 'Meaningless error code is omitted' => [
            ['message' => 'Denied.', 'code' => 0],
            400,
            'Denied. (HTTP 400)',
        ];
        yield 'Non scalar error code is omitted' => [
            ['message' => 'Denied.', 'code' => ['nested']],
            400,
            'Denied. (HTTP 400)',
        ];
    }
}
