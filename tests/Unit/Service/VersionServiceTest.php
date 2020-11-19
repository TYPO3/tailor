<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\Tailor\Exception\RequiredConfigurationMissing;
use TYPO3\Tailor\Service\VersionService;

class VersionServiceTest extends TestCase
{
    /**
     * @test
     */
    public function customExcludeFromPackagingConfiurationIsUsed(): void
    {
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        $mock = $this
            ->getMockBuilder(VersionService::class)
            ->setConstructorArgs(['1.0.0', 'my_ext', '/dummyPath'])
            ->getMock();

        $method = new ReflectionMethod(VersionService::class, 'getExcludeConfiguration');
        $method->setAccessible(true);

        $this::assertSame(['directories' => ['dummy'], 'files' => ['dummy']], $method->invokeArgs($mock, []));
    }

    /**
     * @test
     */
    public function throwsExceptionOnMissingCustomConfiguration(): void
    {
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid_path.php';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1605734677);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    /**
     * @test
     */
    public function throwsExceptionOnInvalidCustomConfiguration(): void
    {
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid.php';

        $this->expectException(RequiredConfigurationMissing::class);
        $this->expectExceptionCode(1605734681);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    /**
     * @test
     */
    public function getVersionFilenameTest(): void
    {
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        $mock = $this
            ->getMockBuilder(VersionService::class)
            ->setConstructorArgs(['1.0.0', 'my_ext', '/dummyPath'])
            ->getMock();

        $method = new ReflectionMethod(VersionService::class, 'getVersionFilename');
        $method->setAccessible(true);

        $this::assertSame('/dummyPath/my_ext_1.0.0.zip', $method->invokeArgs($mock, []));
    }

    /**
     * @test
     */
    public function getVersionFilenameAsMd5Test(): void
    {
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        $mock = $this
            ->getMockBuilder(VersionService::class)
            ->setConstructorArgs(['1.0.0', 'my_ext', '/dummyPath'])
            ->getMock();

        $method = new ReflectionMethod(VersionService::class, 'getVersionFilename');
        $method->setAccessible(true);

        $this::assertSame('cf2d6e211e53d983056761055c95791b', $method->invokeArgs($mock, [true]));
    }
}
