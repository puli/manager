<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Asset;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Asset\BindingExpressionBuilder;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindingExpressionBuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BindingExpressionBuilder
     */
    private $builder;

    protected function setUp()
    {
        $this->builder = new BindingExpressionBuilder();
    }

    public function testBuildDefaultExpression()
    {
        $expr = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')));

        $this->assertEquals($expr, $this->builder->buildExpression());
    }

    public function testBuildExpressionWithCustomCriteria()
    {
        $expr1 = Expr::method('getUuid', Expr::startsWith('abcd'))
            ->orMethod('getServerName', Expr::same('local'))
            ->orX(
                Expr::method('getGlob', Expr::same('/path'))
                    ->andMethod('getServerPath', Expr::same('css'))
            );

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andX(
                Expr::method('getUuid', Expr::startsWith('abcd'))
                    ->orMethod('getParameterValue', DiscoveryUrlGenerator::SERVER_PARAMETER, Expr::same('local'))
                    ->orX(
                        Expr::method('getBinding', Expr::method('getQuery', Expr::same('/path{,/**/*}')))
                            ->andMethod('getParameterValue', DiscoveryUrlGenerator::PATH_PARAMETER, Expr::same('css'))
                    )
            );

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForSame()
    {
        $expr1 = Expr::method('getGlob', Expr::same('/path'));

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::same('/path{,/**/*}')));

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForEquals()
    {
        $expr1 = Expr::method('getGlob', Expr::equals('/path'));

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::equals('/path{,/**/*}')));

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForNotSame()
    {
        $expr1 = Expr::method('getGlob', Expr::notSame('/path'));

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::notSame('/path{,/**/*}')));

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForNotEquals()
    {
        $expr1 = Expr::method('getGlob', Expr::notEquals('/path'));

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::notEquals('/path{,/**/*}')));

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForEndsWith()
    {
        $expr1 = Expr::method('getGlob', Expr::endsWith('/path'));

        $expr2 = Expr::method('isEnabled', Expr::same(true))
            ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')))
            ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('/path{,/**/*}')));

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }
}
