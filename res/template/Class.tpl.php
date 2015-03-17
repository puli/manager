<?php /** @var \Puli\RepositoryManager\Api\Php\Clazz $class */ ?>

<?php if ($class->getNamespaceName()): ?>
namespace <?php echo $class->getNamespaceName() ?>;

<?php endif ?>
<?php if ($class->hasImports()): ?>
<?php foreach ($class->getImports() as $import): ?>
<?php if ($class->getNamespaceName() !== $import->getNamespaceName()): ?>
use <?php echo $import ?>;
<?php endif ?>
<?php endforeach ?>

<?php endif ?>
<?php if ($class->getDescription()): ?>
/**
<?php foreach (explode("\n", $class->getDescription()) as $line): ?>
 *<?php echo rtrim(' '.$line)."\n" ?>
<?php endforeach ?>
 */
<?php endif ?>
class <?php echo $class->getShortClassName() ?><?php if ($class->hasParentClass()): ?> extends <?php echo $class->getParentClass() ?><?php endif ?><?php if ($class->hasImplementedInterfaces()): ?> implements <?php echo implode(', ', $class->getImplementedInterfaces()) ?><?php endif ?><?php echo "\n" ?>
{
<?php $first = true ?>
<?php foreach ($class->getMethods() as $method): ?>
<?php if (!$first) echo "\n" ?>
<?php if ($method->getDescription() || $method->hasArguments() || $method->hasReturnValue()): ?>
    /**
<?php if ($method->getDescription()): ?>
<?php foreach (explode("\n", $method->getDescription()) as $line): ?>
     *<?php echo rtrim(' '.$line)."\n" ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($method->getDescription() && ($method->hasArguments() || $method->hasReturnValue())): ?>
     *
<?php endif ?>
<?php if ($method->hasArguments()): ?>
<?php foreach ($method->getArguments() as $arg): ?>
     * @param <?php echo $arg->getType() ?> $<?php echo $arg->getName() ?><?php if ($arg->getDescription()): ?> <?php echo $arg->getDescription() ?><?php endif ?><?php echo "\n" ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($method->hasArguments() && $method->hasReturnValue()): ?>
     *
<?php endif ?>
<?php if ($method->hasReturnValue()): ?>
     * @return <?php echo $method->getReturnValue()->getType() ?><?php if ($method->getReturnValue()->getDescription()): ?> <?php echo $method->getReturnValue()->getDescription() ?><?php endif ?><?php echo "\n" ?>
<?php endif ?>
     */
<?php endif ?>
    public function <?php echo $method->getName() ?>(<?php echo implode(', ', $method->getArguments()) ?>)
    {
<?php if ($method->getBody()): ?>
<?php foreach (explode("\n", $method->getBody()) as $line): ?>
<?php echo rtrim('        '.$line)."\n" ?>
<?php endforeach ?>
<?php endif ?>
<?php if ($method->getBody() && $method->hasReturnValue()) echo "\n" ?>
<?php if ($method->hasReturnValue()): ?>
        return <?php echo $method->getReturnValue() ?>;
<?php endif ?>
    }
<?php $first = false ?>
<?php endforeach ?>
}
