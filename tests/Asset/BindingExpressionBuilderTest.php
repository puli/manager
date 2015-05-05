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
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\Manager\Asset\BindingExpressionBuilder;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
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
        $expr = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr, $this->builder->buildExpression());
    }

    public function testBuildExpressionWithCustomCriteria()
    {
        $expr1 = Expr::startsWith('abcd', AssetMapping::UUID)
            ->orSame('local', AssetMapping::SERVER_NAME)
            ->orX(
                Expr::same('/path', AssetMapping::GLOB)
                    ->andSame('css', AssetMapping::PUBLIC_PATH)
            );

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andX(
                Expr::startsWith('abcd', BindingDescriptor::UUID)
                    ->orKey(BindingDescriptor::PARAMETER_VALUES, Expr::key(DiscoveryUrlGenerator::SERVER_PARAMETER, Expr::same('local')))
                    ->orX(
                        Expr::same('/path{,/**/*}', BindingDescriptor::QUERY)
                            ->andKey(BindingDescriptor::PARAMETER_VALUES, Expr::key(DiscoveryUrlGenerator::PATH_PARAMETER, Expr::same('css')))
                    )
            );

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForSame()
    {
        $expr1 = Expr::same('/path', AssetMapping::GLOB);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andSame('/path{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForEquals()
    {
        $expr1 = Expr::equals('/path', AssetMapping::GLOB);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andEquals('/path{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForNotSame()
    {
        $expr1 = Expr::notSame('/path', AssetMapping::GLOB);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andNotSame('/path{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForNotEquals()
    {
        $expr1 = Expr::notEquals('/path', AssetMapping::GLOB);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andNotEquals('/path{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }

    public function testAppendDefaultQuerySuffixForEndsWith()
    {
        $expr1 = Expr::endsWith('.css', AssetMapping::GLOB);

        $expr2 = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
            ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
            ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY)
            ->andEndsWith('.css{,/**/*}', BindingDescriptor::QUERY);

        $this->assertEquals($expr2, $this->builder->buildExpression($expr1));
    }
}
