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
use TYPO3\Tailor\Command\Extension\VersionDetailsCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class VersionDetailsCommandTest extends AbstractCommandTestCase
{
    private function command(): VersionDetailsCommand
    {
        return new VersionDetailsCommand('ter:extension:version:details');
    }

    #[Test]
    public function versionAndExtensionKeyBuildTheEndpoint(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['number' => '1.2.3']));

        self::assertSame(0, $tester->execute(['version' => '1.2.3', 'extensionkey' => 'news']));
        self::assertSame('GET', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/news/1.2.3', $this->request()['url']);
    }

    #[Test]
    public function messagesNameBothVersionAndExtension(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['number' => '1.2.3']));
        $tester->execute(['version' => '1.2.3', 'extensionkey' => 'news']);

        self::assertDisplayContains('Fetching details about version 1.2.3 of extension news', $tester);
        self::assertDisplayContains('Successfully fetched details for version 1.2.3 of extension news.', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('No such version.', 404));

        self::assertSame(1, $tester->execute(['version' => '9.9.9', 'extensionkey' => 'news']));
        self::assertDisplayContains('Could not fetch details for version 9.9.9 of extension news.', $tester);
    }
}
