<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Filesystem;

use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Filesystem\EmConfVersionReplacer;

class EmConfVersionReplacerTest extends TestCase
{
    /**
     * @test
     */
    public function replaceVersionReplacesProperVersion(): void
    {
        $emConfContents = file_get_contents(__DIR__ . '/../Fixtures/EmConf/emconf_valid.php');
        $tempFile = tempnam('/tmp/', 'tailer_emconf.php');
        file_put_contents($tempFile, $emConfContents);
        $subject = new EmConfVersionReplacer($tempFile);
        $subject->setVersion('6.9.0');
        $contents = file_get_contents($tempFile);
        self::assertStringContainsString('\'version\' => \'6.9.0\'', $contents);
        unlink($tempFile);
    }
}
