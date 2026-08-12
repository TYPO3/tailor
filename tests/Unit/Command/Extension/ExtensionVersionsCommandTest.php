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
use TYPO3\Tailor\Command\Extension\ExtensionVersionsCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class ExtensionVersionsCommandTest extends AbstractCommandTestCase
{
    private function command(): ExtensionVersionsCommand
    {
        return new ExtensionVersionsCommand('ter:extension:versions');
    }

    #[Test]
    public function versionsEndpointIsRequestedForTheGivenExtensionKey(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([]));

        self::assertSame(0, $tester->execute(['extensionkey' => 'news']));
        self::assertSame('GET', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/news/versions', $this->request()['url']);
    }

    #[Test]
    public function extensionKeyIsTakenFromTheEnvironmentIfNoArgumentIsGiven(): void
    {
        $this->setEnvironment(['TYPO3_EXTENSION_KEY' => 'my_ext']);

        $tester = $this->apiTester($this->command());

        // Resolving the key from the environment is deprecated, but still supported
        set_error_handler(static fn(int $errno): bool => $errno === E_USER_DEPRECATED);
        try {
            $tester->execute([]);
        } finally {
            restore_error_handler();
        }

        self::assertSame(self::BASE_URI . 'extension/my_ext/versions', $this->request()['url']);
    }

    #[Test]
    public function nestedVersionDataIsRenderedAsDetails(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([
            ['number' => '1.0.0', 'typo3_versions' => [11, 12]],
        ]));
        $tester->execute(['extensionkey' => 'news']);

        self::assertDisplayContains('Fetching details for all versions of extension news', $tester);
        self::assertDisplayContains('Number: 1.0.0', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Not found.', 404));

        self::assertSame(1, $tester->execute(['extensionkey' => 'news']));
        self::assertDisplayContains('Could not fetch details for all version of extension news.', $tester);
    }
}
