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
use TYPO3\Tailor\Command\Auth\RevokeTokenCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class RevokeTokenCommandTest extends AbstractCommandTestCase
{
    private function command(): RevokeTokenCommand
    {
        return new RevokeTokenCommand('ter:token:revoke');
    }

    #[Test]
    public function tokenToRevokeIsSentAsFormEncodedBody(): void
    {
        $tester = $this->apiTester($this->command());

        self::assertSame(0, $tester->execute(['token' => 'revoke-me']));
        self::assertSame('POST', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'auth/token/revoke', $this->request()['url']);
        self::assertSame('token=revoke-me', $this->requestBody());
        self::assertSame('application/x-www-form-urlencoded', $this->requestHeaders()['content-type'] ?? '');
    }

    #[Test]
    public function responseContentIsNotRendered(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['secret' => 'do-not-print']));
        $tester->execute(['token' => 'revoke-me']);

        self::assertDisplayContains('Access token was successfully revoked.', $tester);
        self::assertStringNotContainsString('do-not-print', self::display($tester));
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Unknown token.', 404));

        self::assertSame(1, $tester->execute(['token' => 'revoke-me']));
        self::assertDisplayContains('Access token could not be revoked.', $tester);
    }
}
