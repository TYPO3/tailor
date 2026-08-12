<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Command\Auth;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\Tailor\Command\Auth\RefreshTokenCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class RefreshTokenCommandTest extends AbstractCommandTestCase
{
    private function command(): RefreshTokenCommand
    {
        return new RefreshTokenCommand('ter:token:refresh');
    }

    #[Test]
    public function refreshTokenIsSentAsFormEncodedBody(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['access_token' => 'new-token']));

        self::assertSame(0, $tester->execute(['token' => 'refresh-me']));
        self::assertSame('POST', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'auth/token/refresh', $this->request()['url']);
        self::assertSame('token=refresh-me', $this->requestBody());
        self::assertSame('application/x-www-form-urlencoded', $this->requestHeaders()['content-type'] ?? '');
    }

    #[Test]
    public function basicAuthenticationIsUsed(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['token' => 'refresh-me']);

        self::assertSame(
            'Basic ' . base64_encode('test-user:test-password'),
            $this->requestHeaders()['authorization'] ?? ''
        );
    }

    #[Test]
    public function successIsReported(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['access_token' => 'new-token']));
        $tester->execute(['token' => 'refresh-me']);

        self::assertDisplayContains('Refreshing an access token', $tester);
        self::assertDisplayContains('Access token was successfully refreshed.', $tester);
        self::assertDisplayContains('Access token: new-token', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Token expired.', 400));

        self::assertSame(1, $tester->execute(['token' => 'refresh-me']));
        self::assertDisplayContains('Access token could not be refreshed.', $tester);
        self::assertDisplayContains('Token expired. (HTTP 400)', $tester);
    }
}
