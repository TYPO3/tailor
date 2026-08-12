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
use TYPO3\Tailor\Command\Extension\FindExtensionsCommand;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;

class FindExtensionsCommandTest extends AbstractCommandTestCase
{
    private function command(): FindExtensionsCommand
    {
        return new FindExtensionsCommand('ter:extension:find');
    }

    /**
     * A list response as TER returns it, including the pagination metadata
     * the table output prints below the table.
     *
     * @param array<string, string> $filter
     * @return array<string, mixed>
     */
    private static function extensionList(array $filter = []): array
    {
        return [
            'page' => 1,
            'per_page' => 10,
            'filter' => $filter,
            'extensions' => [
                [
                    'key' => 'news',
                    'current_version' => ['title' => 'News system', 'number' => '11.0.0', 'upload_date' => 1609459200],
                    'meta' => ['composer_name' => 'georgringer/news'],
                ],
                [
                    'key' => 'felogin',
                    'current_version' => ['title' => 'Frontend Login', 'number' => '1.0.0'],
                    'meta' => [],
                ],
            ],
        ];
    }

    #[Test]
    public function extensionListIsRequestedWithoutQueryByDefault(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(self::extensionList()));

        self::assertSame(0, $tester->execute([]));
        self::assertSame('GET', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension', $this->request()['url']);
    }

    #[Test]
    public function paginationOptionsAreMappedToQueryParameters(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(self::extensionList()));
        $tester->execute(['--page' => '2', '--per-page' => '25']);

        $url = $this->request()['url'];
        self::assertStringContainsString('page=2', $url);
        self::assertStringContainsString('per_page=25', $url);
    }

    #[Test]
    public function filterOptionsAreMappedToNestedFilterParameters(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(self::extensionList()));
        $tester->execute(['--author' => 'jane', '--typo3-version' => '12']);

        $url = urldecode($this->request()['url']);
        self::assertStringContainsString('filter[username]=jane', $url);
        self::assertStringContainsString('filter[typo3_version]=12', $url);
    }

    #[Test]
    public function resultIsRenderedAsSortedTable(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(self::extensionList()));
        $tester->execute([]);

        $display = self::display($tester);
        self::assertStringContainsString('Extension Key', $display);
        self::assertStringContainsString('Composer Name', $display);
        self::assertStringContainsString('georgringer/news', $display);
        // Rows are sorted by extension key, so felogin comes before news
        self::assertLessThan(strpos($display, 'news'), strpos($display, 'felogin'));
    }

    #[Test]
    public function paginationMetadataIsPrintedBelowTheTable(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(self::extensionList()));
        $tester->execute([]);

        self::assertDisplayContains('Page: 1, Per page: 10, Filter: -', $tester);
    }

    #[Test]
    public function appliedFilterIsDescribedInTheOutput(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(
            self::extensionList(['username' => 'jane', 'typo3_version' => '12'])
        ));
        $tester->execute(['--author' => 'jane', '--typo3-version' => '12']);

        self::assertDisplayContains('jane (Author), 12 (TYPO3 version)', $tester);
    }

    #[Test]
    public function responseWithoutFilterIsRendered(): void
    {
        $list = self::extensionList();
        $list['filter'] = null;

        $tester = $this->apiTester($this->command(), self::jsonResponse($list));

        self::assertSame(0, $tester->execute([]));
        self::assertDisplayContains('Page: 1, Per page: 10, Filter: -', $tester);
    }

    #[Test]
    public function responseWithoutPaginationMetadataIsRendered(): void
    {
        $list = self::extensionList();
        unset($list['page'], $list['per_page'], $list['filter']);

        $tester = $this->apiTester($this->command(), self::jsonResponse($list));

        self::assertSame(0, $tester->execute([]));
        self::assertDisplayContains('Page: -, Per page: -, Filter: -', $tester);
        self::assertDisplayContains('georgringer/news', $tester);
    }

    #[Test]
    public function emptyResultIsReported(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([
            'page' => 1,
            'per_page' => 10,
            'filter' => ['username' => 'nobody'],
            'extensions' => [],
        ]));

        self::assertSame(0, $tester->execute(['--author' => 'nobody']));
        self::assertDisplayContains('No extensions found for options', $tester);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Server error.', 500));

        self::assertSame(1, $tester->execute([]));
        self::assertDisplayContains('Could not fetch remote extensions.', $tester);
    }
}
