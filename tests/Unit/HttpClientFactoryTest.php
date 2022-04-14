<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TYPO3\Tailor\Dto\RequestConfiguration;
use TYPO3\Tailor\HttpClientFactory;

class HttpClientFactoryTest extends TestCase
{
    protected $requestConfiguration;

    protected function setUp(): void
    {
        $this->requestConfiguration = new RequestConfiguration(
            'GET',
            '/endpoint',
            ['parameter' => 'value'],
            ['data' => 'some value'],
            ['accept' => 'application/xml']
        );
    }

    /**
     * @test
     */
    public function createHttpClientThrowsExceptionOnMissingCredentials(): void
    {
        $this->expectExceptionCode(1606995339);
        HttpClientFactory::create($this->requestConfiguration);
    }

    /**
     * @test
     */
    public function createHttpClientWithDefaultsTest(): void
    {
        unset($_ENV);
        $_ENV['TYPO3_REMOTE_BASE_URI'] = '';
        $_ENV['TYPO3_API_VERSION'] = '';
        $_ENV['TYPO3_API_TOKEN'] = 'token123';

        $httpClient = HttpClientFactory::create($this->requestConfiguration);
        $defaultOptions =  $this->getDefaultOptions($httpClient);

        self::assertSame(
            'https://extensions.typo3.org/api/v1/',
            $defaultOptions['base_uri']
        );

        self::assertContains('accept: application/xml', $defaultOptions['headers']);
        self::assertContains('User-Agent: Tailor - Your TYPO3 Extension Helper', $defaultOptions['headers']);

        self::assertSame(['parameter' => 'value'], $defaultOptions['query']);
        self::assertSame('data=some+value', $defaultOptions['body']);
    }

    /**
     * @test
     */
    public function createHttpClientWithFallbackTest(): void
    {
        unset($_ENV);
        $_ENV['TYPO3_API_TOKEN'] = 'token123';

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame(
            'https://extensions.typo3.org/api/v1/',
            $this->getDefaultOptions($httpClient)['base_uri']
        );
    }

    /**
     * @test
     */
    public function createHttpClientWithOverrideTest(): void
    {
        unset($_ENV);
        $_ENV['TYPO3_REMOTE_BASE_URI'] = 'some_remote';
        $_ENV['TYPO3_API_VERSION'] = 'v123';
        $_ENV['TYPO3_API_TOKEN'] = 'token123';

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame(
            'some_remote/api/v123/',
            $this->getDefaultOptions($httpClient)['base_uri']
        );
    }

    /**
     * @test
     */
    public function createHttpClientWithOverridePutenvTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_REMOTE_BASE_URI=some_other_remote');
        putenv('TYPO3_API_VERSION=v123');
        $_ENV['TYPO3_API_VERSION'] = 'v321';
        putenv('TYPO3_API_TOKEN=token123');

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame(
            'some_other_remote/api/v321/',
            $this->getDefaultOptions($httpClient)['base_uri']
        );
    }

    /**
     * @test
     */
    public function createHttpClientWithBearerAuthTest(): void
    {
        unset($_ENV);
        $_ENV['TYPO3_API_TOKEN'] = 'token123';
        $_ENV['TYPO3_API_USERNAME'] = 'user';
        $_ENV['TYPO3_API_PASSWORD'] = 'pass';

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame('token123', $this->getDefaultOptions($httpClient)['auth_bearer']);
    }

    /**
     * @test
     */
    public function createHttpClientWithBearerAuthOverrideTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_API_TOKEN=token123');
        $_ENV['TYPO3_API_TOKEN'] = 'token321';
        putenv('TYPO3_API_USERNAME=user');
        putenv('TYPO3_API_PASSWORD=pass');

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame('token321', $this->getDefaultOptions($httpClient)['auth_bearer']);
    }

    /**
     * @test
     */
    public function createHttpClientWithBasicAuthTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_API_TOKEN=');
        putenv('TYPO3_API_USERNAME=user');
        $_ENV['TYPO3_API_USERNAME'] = 'overridenUser';
        putenv('TYPO3_API_PASSWORD=pass');

        $httpClient = HttpClientFactory::create($this->requestConfiguration);

        self::assertSame('overridenUser:pass', $this->getDefaultOptions($httpClient)['auth_basic']);
    }

    /**
     * @test
     */
    public function createHttpClientWithBasicAuthEnforcedTest(): void
    {
        unset($_ENV);
        putenv('TYPO3_API_TOKEN=someToken123');
        $_ENV['TYPO3_API_TOKEN'] = 'overridenToken';
        putenv('TYPO3_API_USERNAME=user');
        putenv('TYPO3_API_PASSWORD=pass');
        $_ENV['TYPO3_API_PASSWORD'] = 'overridenPass';

        $httpClient = HttpClientFactory::create(
            new RequestConfiguration('GET', '/endpoint', [], [], [], false, HttpClientFactory::BASIC_AUTH)
        );

        self::assertSame('user:overridenPass', $this->getDefaultOptions($httpClient)['auth_basic']);
    }

    protected function getDefaultOptions(HttpClientInterface $httpClient): array
    {
        $defaultOptions = (new \ReflectionClass($httpClient))->getProperty('defaultOptions');
        $defaultOptions->setAccessible(true);

        return $defaultOptions->getValue($httpClient);
    }
}
