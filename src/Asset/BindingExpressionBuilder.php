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

use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Webmozart\Expression\Constraint\EndsWith;
use Webmozart\Expression\Constraint\Equals;
use Webmozart\Expression\Constraint\NotEquals;
use Webmozart\Expression\Constraint\NotSame;
use Webmozart\Expression\Constraint\Same;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;
use Webmozart\Expression\Logic\Conjunction;
use Webmozart\Expression\Selector\Method;
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
     * @param Expression|null $expr The {@link AssetMapping} expression.
     *
     * @return Expression The built expression.
     */
    public function buildExpression(Expression $expr = null)
    {
        if (!$this->defaultExpression) {
            $this->defaultExpression = Expr::method('isEnabled', Expr::same(true))
                ->andMethod('getTypeName', Expr::same(DiscoveryUrlGenerator::BINDING_TYPE))
                ->andMethod('getBinding', Expr::method('getQuery', Expr::endsWith('{,/**/*}')));
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
        if ($expr instanceof Method) {
            switch ($expr->getMethodName()) {
                case 'getGlob':
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

                    return Expr::method('getBinding', Expr::method('getQuery', $queryExpr));

                case 'getServerName':
                    return Expr::method(
                        'getParameterValue',
                        DiscoveryUrlGenerator::SERVER_PARAMETER,
                        $expr->getExpression()
                    );

                case 'getServerPath':
                    return Expr::method(
                        'getParameterValue',
                        DiscoveryUrlGenerator::PATH_PARAMETER,
                        $expr->getExpression()
                    );
            }
        }

        return $expr;
    }
}
