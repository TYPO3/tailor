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
use TYPO3\Tailor\Command\Extension\ExtensionDetailsCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class ExtensionDetailsCommandTest extends AbstractCommandTestCase
{
    private function command(): ExtensionDetailsCommand
    {
        return new ExtensionDetailsCommand('ter:extension:details');
    }

    #[Test]
    public function detailsAreRequestedForTheGivenExtensionKey(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'news', 'downloads' => 42]));

        self::assertSame(0, $tester->execute(['extensionkey' => 'news']));
        self::assertSame('GET', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/news', $this->request()['url']);
    }

    #[Test]
    public function responseIsRenderedAsDetails(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'news', 'downloads' => 42]));
        $tester->execute(['extensionkey' => 'news']);

        self::assertDisplayContains('Fetching details for extension news', $tester);
        self::assertDisplayContains('Successfully fetched extensions details for extension news.', $tester);
        self::assertDisplayContains('Key: news', $tester);
        self::assertDisplayContains('Downloads: 42', $tester);
    }

    #[Test]
    public function bearerTokenIsUsedForAuthentication(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'news']);

        self::assertSame('Bearer test-token', $this->requestHeaders()['authorization'] ?? '');
    }

    #[Test]
    public function failingRequestReportsReasonAndReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Extension not found.', 404, 1603956982));

        self::assertSame(1, $tester->execute(['extensionkey' => 'unknown']));
        self::assertDisplayContains('Extension details for extension unknown could not be fetched.', $tester);
        self::assertDisplayContains('Extension not found. (HTTP 404, code 1603956982)', $tester);
    }

    #[Test]
    public function rawOptionOutputsTheUnformattedResponse(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'news']));
        $tester->execute(['extensionkey' => 'news', '--raw' => null]);

        self::assertDisplayContains('{"key":"news"}', $tester);
        self::assertStringNotContainsString('Fetching details', self::display($tester));
    }
}
