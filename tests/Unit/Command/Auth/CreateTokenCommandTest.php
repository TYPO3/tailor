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
use TYPO3\Tailor\Command\Auth\CreateTokenCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class CreateTokenCommandTest extends AbstractCommandTestCase
{
    private function command(): CreateTokenCommand
    {
        return new CreateTokenCommand('ter:token:create');
    }

    #[Test]
    public function tokenIsRequestedWithTheDefaultScope(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['access_token' => 'abc']));

        self::assertSame(0, $tester->execute([]));
        self::assertSame('POST', $this->request()['method']);
        self::assertStringStartsWith(self::BASE_URI . 'auth/token', $this->request()['url']);
        self::assertStringContainsString('scope=extension:read%2Cextension:write', $this->request()['url']);
    }

    #[Test]
    public function givenOptionsAreSentAsQueryParameters(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute([
            '--name' => 'my-token',
            '--expires' => '3600',
            '--scope' => 'extension:read',
            '--extensions' => 'news,felogin',
        ]);

        $url = $this->request()['url'];
        self::assertStringContainsString('name=my-token', $url);
        self::assertStringContainsString('expires=3600', $url);
        self::assertStringContainsString('scope=extension:read', $url);
        self::assertStringContainsString('extensions=news%2Cfelogin', $url);
    }

    #[Test]
    public function optionsWhichAreNotGivenAreOmittedFromTheQuery(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute([]);

        $url = $this->request()['url'];
        self::assertStringNotContainsString('name=', $url);
        self::assertStringNotContainsString('expires=', $url);
        self::assertStringNotContainsString('extensions=', $url);
    }

    #[Test]
    public function basicAuthenticationIsUsedInsteadOfTheBearerToken(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute([]);

        self::assertSame(
            'Basic ' . base64_encode('test-user:test-password'),
            $this->requestHeaders()['authorization'] ?? ''
        );
    }

    #[Test]
    public function resultIsRenderedAsKeyValuePairs(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['access_token' => 'abc', 'expires_in' => 3600]));
        $tester->execute([]);

        self::assertDisplayContains('Creating an access token', $tester);
        self::assertDisplayContains('Access token was successfully created.', $tester);
        self::assertDisplayContains('Access token: abc', $tester);
        self::assertDisplayContains('Expires in: 3600', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Invalid credentials.', 401));

        self::assertSame(1, $tester->execute([]));
        self::assertDisplayContains('Access token could not be created.', $tester);
        self::assertDisplayContains('Invalid credentials. (HTTP 401)', $tester);
    }
}
