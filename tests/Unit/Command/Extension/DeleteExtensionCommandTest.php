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
use TYPO3\Tailor\Command\Extension\DeleteExtensionCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class DeleteExtensionCommandTest extends AbstractCommandTestCase
{
    private function command(): DeleteExtensionCommand
    {
        return new DeleteExtensionCommand('ter:extension:delete');
    }

    #[Test]
    public function confirmedDeletionSendsDeleteRequest(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([]));
        $tester->setInputs(['yes']);

        self::assertSame(0, $tester->execute(['extensionkey' => 'my_ext']));
        self::assertSame('DELETE', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/my_ext', $this->request()['url']);
        self::assertDisplayContains('Extension my_ext successfully deleted.', $tester);
    }

    #[Test]
    public function confirmationIsAskedBeforeDeleting(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->setInputs(['yes']);
        $tester->execute(['extensionkey' => 'my_ext']);

        self::assertDisplayContains('Are you sure you want to delete the extension my_ext?', $tester);
    }

    #[Test]
    public function declinedConfirmationAbortsWithoutRequest(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->setInputs(['no']);

        self::assertSame(0, $tester->execute(['extensionkey' => 'my_ext']));
        self::assertSame([], $this->requests);
        self::assertDisplayContains('Execution aborted.', $tester);
    }

    #[Test]
    public function responseContentIsNotRendered(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['secret' => 'do-not-print']));
        $tester->setInputs(['yes']);
        $tester->execute(['extensionkey' => 'my_ext']);

        self::assertStringNotContainsString('do-not-print', self::display($tester));
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Not allowed.', 403));
        $tester->setInputs(['yes']);

        self::assertSame(1, $tester->execute(['extensionkey' => 'my_ext']));
        self::assertDisplayContains('Could not delete extension my_ext.', $tester);
    }
}
