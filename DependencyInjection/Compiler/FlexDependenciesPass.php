<?php

namespace Oro\Bundle\BatchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class FlexDependenciesPass
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->cleanCronBundleDependency($container);
    }

    private function cleanCronBundleDependency(ContainerBuilder $container)
    {
        if (!class_exists('\Oro\Bundle\CronBundle\OroCronBundle')) {
            $this->remove('Oro\Bundle\BatchBundle\Command\CleanupCommand', $container);
        }

        if (!class_exists('\Oro\Bundle\EntityBundle\OroEntityBundle')) {
            $this->remove('oro_batch.orm.query_builder.count_query_optimizer', $container);
            $this->remove('Oro\Bundle\BatchBundle\ORM\QueryBuilder\CountQueryBuilderOptimizer', $container);
        }
    }

    private function remove(string $string, ContainerBuilder $container)
    {
        if ($container->hasDefinition($string)) {
            $container->removeDefinition($string);
        }
    }
}
