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
use TYPO3\Tailor\Command\Extension\UpdateExtensionCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class UpdateExtensionCommandTest extends AbstractCommandTestCase
{
    private function command(): UpdateExtensionCommand
    {
        return new UpdateExtensionCommand('ter:extension:update');
    }

    #[Test]
    public function metaInformationIsSentAsFormEncodedPutRequest(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['key' => 'my_ext']));

        $exitCode = $tester->execute([
            'extensionkey' => 'my_ext',
            '--composer' => 'vendor/my-ext',
            '--issues' => 'https://example.org/issues',
            '--repository' => 'https://example.org/repo',
            '--manual' => 'https://example.org/manual',
            '--paypal' => 'https://example.org/donate',
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame('PUT', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/my_ext', $this->request()['url']);
        self::assertSame('application/x-www-form-urlencoded', $this->requestHeaders()['content-type'] ?? '');

        $body = urldecode($this->requestBody());
        self::assertStringContainsString('composer_name=vendor/my-ext', $body);
        self::assertStringContainsString('forge_link=https://example.org/issues', $body);
        self::assertStringContainsString('repository_url=https://example.org/repo', $body);
        self::assertStringContainsString('external_manual=https://example.org/manual', $body);
        self::assertStringContainsString('paypal_url=https://example.org/donate', $body);
    }

    #[Test]
    public function optionsWhichAreNotGivenAreOmittedFromTheBody(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'my_ext', '--composer' => 'vendor/my-ext']);

        $body = $this->requestBody();
        self::assertStringContainsString('composer_name', $body);
        self::assertStringNotContainsString('forge_link', $body);
        self::assertStringNotContainsString('paypal_url', $body);
    }

    #[Test]
    public function tagsImpliedByTerAreWarnedAboutButStillSent(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'my_ext', '--tags' => 'typo3,news,TYPO3-CMS-Extension']);

        $display = self::display($tester);
        self::assertStringContainsString('Every extension in TER is a TYPO3 extension', $display);
        self::assertStringContainsString('typo3, TYPO3-CMS-Extension add no discoverability there', $display);

        // The warning must not change what is sent
        self::assertStringContainsString('tags=' . urlencode('typo3,news,TYPO3-CMS-Extension'), $this->requestBody());
    }

    #[Test]
    public function singleImpliedTagUsesSingularWording(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'my_ext', '--tags' => 'typo3,news']);

        self::assertDisplayContains('typo3 adds no discoverability there', $tester);
    }

    #[Test]
    public function meaningfulTagsAreNotWarnedAbout(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'my_ext', '--tags' => 'news,e-commerce,tt_news']);

        self::assertStringNotContainsString('no discoverability', self::display($tester));
    }

    #[Test]
    public function noWarningIsShownWithoutTags(): void
    {
        $tester = $this->apiTester($this->command());
        $tester->execute(['extensionkey' => 'my_ext', '--composer' => 'vendor/my-ext']);

        self::assertStringNotContainsString('no discoverability', self::display($tester));
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Not your extension.', 403));

        self::assertSame(1, $tester->execute(['extensionkey' => 'my_ext', '--composer' => 'vendor/my-ext']));
        self::assertDisplayContains('Could not update meta information of extension my_ext.', $tester);
    }
}
