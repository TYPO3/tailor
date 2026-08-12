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
use TYPO3\Tailor\Command\Extension\TransferExtensionCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class TransferExtensionCommandTest extends AbstractCommandTestCase
{
    private function command(): TransferExtensionCommand
    {
        return new TransferExtensionCommand('ter:extension:transfer');
    }

    #[Test]
    public function confirmedTransferPostsToTheTransferEndpoint(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([]));
        $tester->setInputs(['yes']);

        self::assertSame(0, $tester->execute(['username' => 'jane', 'extensionkey' => 'my_ext']));
        self::assertSame('POST', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/my_ext/transfer/jane', $this->request()['url']);
        self::assertDisplayContains('Extension key my_ext successfully transferred to jane.', $tester);
    }

    #[Test]
    public function confirmationNamesExtensionAndTargetUser(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->setInputs(['yes']);
        $tester->execute(['username' => 'jane', 'extensionkey' => 'my_ext']);

        self::assertDisplayContains('Are you sure you want to transfer the extension key my_ext to jane?', $tester);
    }

    #[Test]
    public function declinedConfirmationAbortsWithoutRequest(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->setInputs(['no']);

        self::assertSame(0, $tester->execute(['username' => 'jane', 'extensionkey' => 'my_ext']));
        self::assertSame([], $this->requests);
        self::assertDisplayContains('Execution aborted.', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Unknown user.', 400));
        $tester->setInputs(['yes']);

        self::assertSame(1, $tester->execute(['username' => 'nobody', 'extensionkey' => 'my_ext']));
        self::assertDisplayContains('Could not transfer extension key my_ext to nobody.', $tester);
    }
}
