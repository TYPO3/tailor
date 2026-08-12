<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Command\Extension;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\Tailor\Command\Extension\RegisterExtensionCommand;
use TYPO3\Tailor\Exception\ExtensionKeyMissingException;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class RegisterExtensionCommandTest extends AbstractCommandTestCase
{
    private function command(): RegisterExtensionCommand
    {
        return new RegisterExtensionCommand('ter:extension:register');
    }

    #[Test]
    public function extensionKeyIsRegisteredWithAPostRequest(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'my_ext'], 201));

        self::assertSame(0, $tester->execute(['extensionkey' => 'my_ext']));
        self::assertSame('POST', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/my_ext', $this->request()['url']);
        self::assertDisplayContains('Successfully registered extension key my_ext.', $tester);
    }

    #[Test]
    public function createdStatusIsTreatedAsSuccess(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'my_ext'], 201));

        self::assertSame(0, $tester->execute(['extensionkey' => 'my_ext']));
    }

    #[Test]
    public function missingExtensionKeyThrowsException(): void
    {
        // No argument, no composer.json entry and no environment variable
        $this->setEnvironment(['TYPO3_EXTENSION_KEY' => '']);

        $tester = $this->apiTester($this->command());

        $this->expectException(ExtensionKeyMissingException::class);
        $this->expectExceptionCode(1605706548);

        $tester->execute([]);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Key already taken.', 400));

        self::assertSame(1, $tester->execute(['extensionkey' => 'my_ext']));
        self::assertDisplayContains('Could not register extension key my_ext.', $tester);
        self::assertDisplayContains('Key already taken. (HTTP 400)', $tester);
    }
}
