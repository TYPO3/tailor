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
    public function defaultExcludeFromPackagingConfigurationIsUsedOnNonExistingEnvVar(): void
    {
        unset($_ENV);

        self::assertContains(
            'vendor',
            $this->invokeMethod('getExcludeConfiguration', [])['directories']
        );
    }

    /**
     * @test
     */
    public function defaultExcludeFromPackagingConfigurationIsUsedOnEmptyPath(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = '';

        self::assertContains(
            'vendor',
            $this->invokeMethod('getExcludeConfiguration', [])['directories']
        );
    }

    /**
     * @test
     */
    public function customExcludeFromPackagingConfigurationIsUsed(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        self::assertSame(
            ['directories' => ['dummy'], 'files' => ['dummy']],
            $this->invokeMethod('getExcludeConfiguration', [])
        );
    }

    /**
     * @test
     */
    public function throwsExceptionOnMissingCustomConfiguration(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid_path.php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1605734677);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    /**
     * @test
     */
    public function throwsExceptionOnInvalidCustomConfiguration(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_invalid.php');

        $this->expectException(RequiredConfigurationMissing::class);
        $this->expectExceptionCode(1605734681);

        new VersionService('1.0.0', 'my_ext', '/dummyPath');
    }

    /**
     * @test
     */
    public function getVersionFilenameTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=' . __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php');

        self::assertSame(
            '/dummyPath/my_ext_1.0.0.zip',
            $this->invokeMethod('getVersionFilename', [])
        );
    }

    /**
     * @test
     */
    public function getVersionFilenameAsMd5Test(): void
    {
        unset($_ENV);
        putenv('TYPO3_EXCLUDE_FROM_PACKAGING=');
        $_ENV['TYPO3_EXCLUDE_FROM_PACKAGING'] = __DIR__ . '/../Fixtures/ExcludeFromPackaging/config_valid.php';

        self::assertSame(
            'cf2d6e211e53d983056761055c95791b',
            $this->invokeMethod('getVersionFilename', [true])
        );
    }

    /**
     * Invoke a protected / private method from VersionService
     *
     * @param string $methodName
     * @param array  $arguments
     *
     * @return mixed
     * @throws \ReflectionException
     */
    protected function invokeMethod(string $methodName, array $arguments)
    {
        $mock = $this
            ->getMockBuilder(VersionService::class)
            ->setConstructorArgs(['1.0.0', 'my_ext', '/dummyPath'])
            ->getMock();

        $method = new ReflectionMethod(VersionService::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($mock, $arguments);
    }
}
