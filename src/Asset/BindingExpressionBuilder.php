<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Asset;

use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingState;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Webmozart\Expression\Comparison\EndsWith;
use Webmozart\Expression\Comparison\Equals;
use Webmozart\Expression\Comparison\NotEquals;
use Webmozart\Expression\Comparison\NotSame;
use Webmozart\Expression\Comparison\Same;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\Expression\Logic\Conjunction;
use Webmozart\Expression\Selector\Key;
use Webmozart\Expression\Traversal\ExpressionTraverser;
use Webmozart\Expression\Traversal\ExpressionVisitor;

/**
 * Transforms an {@link AssetMapping} expression to a {@link BindingDescriptor}
 * expression.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class BindingExpressionBuilder implements ExpressionVisitor
{
    /**
     * @var Conjunction
     */
    private $defaultExpression;

    /**
     * Builds a {@link BindingDescriptor} expression for a given
     * {@link AssetMapping} expression.
     *
     * @param Expression $expr The {@link AssetMapping} expression.
     *
     * @return Expression The built expression.
     */
    public function buildExpression(Expression $expr = null)
    {
        if (!$this->defaultExpression) {
            $this->defaultExpression = Expr::same(BindingState::ENABLED, BindingDescriptor::STATE)
                ->andSame(DiscoveryUrlGenerator::BINDING_TYPE, BindingDescriptor::TYPE_NAME)
                ->andEndsWith('{,/**/*}', BindingDescriptor::QUERY);
        }

        if (!$expr) {
            return $this->defaultExpression;
        }

        $traverser = new ExpressionTraverser();
        $traverser->addVisitor($this);

        return $this->defaultExpression->andX($traverser->traverse($expr));
    }

    /**
     * {@inheritdoc}
     */
    public function enterExpression(Expression $expr)
    {
        return $expr;
    }

    /**
     * {@inheritdoc}
     */
    public function leaveExpression(Expression $expr)
    {
        if ($expr instanceof Key) {
            switch ($expr->getKey()) {
                case AssetMapping::UUID:
                    return Expr::key(BindingDescriptor::UUID, $expr->getExpression());

                case AssetMapping::GLOB:
                    $queryExpr = $expr->getExpression();

                    if ($queryExpr instanceof Same) {
                        $queryExpr = Expr::same($queryExpr->getComparedValue().'{,/**/*}');
                    } elseif ($queryExpr instanceof Equals) {
                        $queryExpr = Expr::equals($queryExpr->getComparedValue().'{,/**/*}');
                    } elseif ($queryExpr instanceof NotSame) {
                        $queryExpr = Expr::notSame($queryExpr->getComparedValue().'{,/**/*}');
                    } elseif ($queryExpr instanceof NotEquals) {
                        $queryExpr = Expr::notEquals($queryExpr->getComparedValue().'{,/**/*}');
                    } elseif ($queryExpr instanceof EndsWith) {
                        $queryExpr = Expr::endsWith($queryExpr->getAcceptedSuffix().'{,/**/*}');
                    }

                    return Expr::key(BindingDescriptor::QUERY, $queryExpr);

                case AssetMapping::SERVER_NAME:
                    return Expr::key(
                        BindingDescriptor::PARAMETER_VALUES,
                        Expr::key(DiscoveryUrlGenerator::SERVER_PARAMETER, $expr->getExpression())
                    );

                case AssetMapping::SERVER_PATH:
                    return Expr::key(
                        BindingDescriptor::PARAMETER_VALUES,
                        Expr::key(DiscoveryUrlGenerator::PATH_PARAMETER, $expr->getExpression())
                    );
            }
        }

        return $expr;
    }
}
