<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project  - inspiring people to share!
 * (c) 2020 Oliver Bartsch & Benni Mack
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace TYPO3\Tailor\Tests\Unit\Environment;

use PHPUnit\Framework\TestCase;
use TYPO3\Tailor\Environment\Variables;

class VariablesTest extends TestCase
{
    /**
     * @test
     */
    public function hasVariableTest(): void
    {
        unset($_ENV);

        self::assertFalse(Variables::has('NOT_SET'));

        $_ENV['EMPTY'] = '';
        putenv('EMPTY_PUTENV=');

        self::assertFalse(Variables::has('EMPTY'));
        self::assertTrue(Variables::has('EMPTY', true));
        self::assertFalse(Variables::has('EMPTY_PUTENV'));
        self::assertTrue(Variables::has('EMPTY_PUTENV', true));

        $_ENV['NOT_EMPTY'] = 'some value';
        putenv('NOT_EMPTY_PUTENV=some value');

        self::assertTrue(Variables::has('NOT_EMPTY'));
        self::assertTrue(Variables::has('NOT_EMPTY', true));
        self::assertTrue(Variables::has('NOT_EMPTY_PUTENV'));
        self::assertTrue(Variables::has('NOT_EMPTY_PUTENV', true));
    }

    /**
     * @test
     */
    public function getVariableTest(): void
    {
        unset($_ENV);

        self::assertEquals('', Variables::get('NOT_SET'));

        $_ENV['EMPTY'] = '';
        putenv('EMPTY_PUTENV=');

        self::assertEquals('', Variables::get('EMPTY'));
        self::assertEquals('', Variables::get('EMPTY_PUTENV'));

        $_ENV['NOT_EMPTY'] = 'some value';
        putenv('NOT_EMPTY_PUTENV=some value');

        self::assertEquals('some value', Variables::get('NOT_EMPTY'));
        self::assertEquals('some value', Variables::get('NOT_EMPTY_PUTENV'));

        putenv('OVERRIDE=');
        self::assertEquals('', Variables::get('OVERRIDE'));
        putenv('OVERRIDE=overriden');
        self::assertEquals('overriden', Variables::get('OVERRIDE'));
        $_ENV['OVERRIDE'] = 'overriden2';
        self::assertEquals('overriden2', Variables::get('OVERRIDE'));
        putenv('OVERRIDE=');
        // Putenv can't override $_ENV so we still get `overriden2`
        self::assertEquals('overriden2', Variables::get('OVERRIDE'));
        $_ENV['OVERRIDE'] = '';
        self::assertEquals('', Variables::get('OVERRIDE'));
    }
}
