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
use TYPO3\Tailor\Command\Extension\UploadExtensionVersionCommand;
use TYPO3\Tailor\Exception\VersionMissingException;
use TYPO3\Tailor\Tests\Unit\Command\AbstractCommandTestCase;
use TYPO3\Tailor\Tests\Unit\GitRepositoryTrait;

class UploadExtensionVersionCommandTest extends AbstractCommandTestCase
{
    use ExtensionDirectoryTrait;
    use GitRepositoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExtensionDirectory('1.2.3');
    }

    protected function tearDown(): void
    {
        $this->removeExtensionDirectory();
        parent::tearDown();
    }

    private function command(): UploadExtensionVersionCommand
    {
        return new UploadExtensionVersionCommand('ter:extension:publish');
    }

    /**
     * @return array<string, string>
     */
    private function uploadArguments(): array
    {
        return [
            'version' => '1.2.3',
            'extensionkey' => 'my_ext',
            '--path' => $this->extensionDirectory,
        ];
    }

    #[Test]
    public function versionIsPublishedToTheVersionEndpoint(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse(['number' => '1.2.3'], 201));

        self::assertSame(0, $tester->execute($this->uploadArguments()));
        self::assertSame('POST', $this->request()['method']);
        self::assertSame(self::BASE_URI . 'extension/my_ext/1.2.3', $this->request()['url']);
        self::assertDisplayContains('Version 1.2.3 of extension my_ext successfully published.', $tester);
    }

    #[Test]
    public function artefactIsSentAsMultipartFormData(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));
        $tester->execute($this->uploadArguments());

        self::assertStringStartsWith('multipart/form-data', $this->requestHeaders()['content-type'] ?? '');

        $body = $this->requestBody();
        self::assertStringContainsString('name="description"', $body);
        self::assertStringContainsString('name="gplCompliant"', $body);
        self::assertStringContainsString('name="file"', $body);
        self::assertStringContainsString('my_ext_1.2.3.zip', $body);
    }

    #[Test]
    public function commentIsGeneratedFromTheVersionIfNotGiven(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));
        $tester->execute($this->uploadArguments());

        self::assertStringContainsString('Updated extension to 1.2.3', $this->requestBody());
    }

    #[Test]
    public function givenCommentIsSentAsDescription(): void
    {
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));
        $tester->execute($this->uploadArguments() + ['--comment' => 'Fixes a nasty bug']);

        self::assertStringContainsString('Fixes a nasty bug', $this->requestBody());
    }

    #[Test]
    public function transactionDirectoryIsRemovedAfterwards(): void
    {
        $command = $this->command();
        $tester = $this->apiTester($command, self::jsonResponse([], 201));
        $tester->execute($this->uploadArguments());

        self::assertDirectoryExists($this->workingDirectory . '/tailor-version-upload');

        // The command removes its transaction directory in the destructor,
        // so it only vanishes once the last reference is gone.
        unset($command, $tester);
        gc_collect_cycles();

        self::assertDirectoryDoesNotExist($this->workingDirectory . '/tailor-version-upload');
    }

    #[Test]
    public function versionIsTakenFromTheTagOfTheCheckedOutCommit(): void
    {
        $this->createGitRepository($this->extensionDirectory, '1.2.3');
        $tester = $this->apiTester($this->command(), self::jsonResponse(['number' => '1.2.3'], 201));

        self::assertSame(0, $tester->execute(['--path' => $this->extensionDirectory]));
        self::assertSame(self::BASE_URI . 'extension/my_ext/1.2.3', $this->request()['url']);
        self::assertDisplayContains('Using version 1.2.3 from the tag of the checked out commit.', $tester);
    }

    #[Test]
    public function versionTagMayBePrefixed(): void
    {
        $this->createGitRepository($this->extensionDirectory, 'v1.2.3');
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));

        self::assertSame(0, $tester->execute(['--path' => $this->extensionDirectory]));
        self::assertSame(self::BASE_URI . 'extension/my_ext/1.2.3', $this->request()['url']);
    }

    #[Test]
    public function extensionKeyCanBeGivenAsSoleArgument(): void
    {
        $this->createGitRepository($this->extensionDirectory, '1.2.3');
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));

        self::assertSame(0, $tester->execute(['version' => 'my_ext', '--path' => $this->extensionDirectory]));
        self::assertSame(self::BASE_URI . 'extension/my_ext/1.2.3', $this->request()['url']);
    }

    #[Test]
    public function versionIsTakenFromExtEmconfIfTheCommitIsNotTagged(): void
    {
        $this->createGitRepository($this->extensionDirectory);
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));

        self::assertSame(0, $tester->execute(['--path' => $this->extensionDirectory]));
        self::assertSame(self::BASE_URI . 'extension/my_ext/1.2.3', $this->request()['url']);
        self::assertDisplayContains('Using version 1.2.3 from ext_emconf.php.', $tester);
    }

    #[Test]
    public function ambiguousVersionTagsAreRejected(): void
    {
        $this->createGitRepository($this->extensionDirectory, '1.2.3', '1.2.4');
        $tester = $this->apiTester($this->command(), self::jsonResponse([], 201));

        $this->expectException(VersionMissingException::class);
        $this->expectExceptionMessage('tagged with more than one version (1.2.3, 1.2.4)');

        $tester->execute(['--path' => $this->extensionDirectory]);
    }

    #[Test]
    public function failingRequestReturnsFailure(): void
    {
        $tester = $this->apiTester($this->command(), self::errorResponse('Version already exists.', 400, 1603956982));

        self::assertSame(1, $tester->execute($this->uploadArguments()));
        self::assertDisplayContains('Could not publish version 1.2.3 of extension my_ext.', $tester);
        self::assertDisplayContains('Version already exists. (HTTP 400, code 1603956982)', $tester);
    }
}
