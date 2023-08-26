<?php

declare(strict_types=1);

namespace Vanta\Integration\Symfony\Temporal\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface as BundleConfiguration;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

use Temporal\Api\Enums\V1\QueryRejectCondition;

/**
 * @phpstan-type PoolWorkerConfiguration array{
 *  dataConverter: non-empty-string,
 *  roadrunnerRPC: non-empty-string,
 * }
 *
 * @phpstan-type Client array{
 *  name: non-empty-string,
 *  address: non-empty-string,
 *  namespace: non-empty-string,
 *  identity: ?non-empty-string,
 *  dataConverter: non-empty-string,
 *  queryRejectionCondition: ?int,
 * }
 *
 * @phpstan-type Worker array{
 *  name: non-empty-string,
 *  taskQueue: non-empty-string,
 *  address: non-empty-string,
 *  exceptionInterceptor: non-empty-string,
 *  maxConcurrentActivityExecutionSize: int,
 *  workerActivitiesPerSecond: float|int,
 *  maxConcurrentLocalActivityExecutionSize: int,
 *  workerLocalActivitiesPerSecond: float|int,
 *  taskQueueActivitiesPerSecond: float|int,
 *  maxConcurrentActivityTaskPollers: int,
 *  maxConcurrentWorkflowTaskExecutionSize: int,
 *  maxConcurrentWorkflowTaskPollers: int,
 *  enableSessionWorker: bool,
 *  sessionResourceId: ?non-empty-string,
 *  maxConcurrentSessionExecutionSize: int,
 *  finalizers: non-empty-array<int, non-empty-string>
 * }
 *
 *
 * @phpstan-type RawConfiguration array{
 *  defaultClient: non-empty-string,
 *  clients: array<non-empty-string, Client>,
 *  workers: array<non-empty-string, Worker>,
 *  pool: PoolWorkerConfiguration
 * }
 */
final class Configuration implements BundleConfiguration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('temporal');

        //@formatter:off
        $treeBuilder->getRootNode()
            ->fixXmlConfig('client', 'clients')
            ->fixXmlConfig('worker', 'workers')
            ->children()
                ->scalarNode('defaultClient')
                ->defaultValue('default')
                ->end()
            ->end()
            ->children()
                ->arrayNode('pool')
                    ->defaultValue([
                        'dataConverter' => 'temporal.data_converter',
                        'roadrunnerRPC' => env('RR_RPC')
                            ->__toString(),
                    ])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('dataConverter')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('roadrunnerRPC')
                                ->cannotBeEmpty()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()

            ->children()
                ->arrayNode('clients')
                ->defaultValue(['default' => [
                    'namespace'     => 'default',
                    'address'       => env('TEMPORAL_ADDRESS')->__toString(),
                    'dataConverter' => 'temporal.data_converter'],
                ])
                ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('namespace')
                                ->isRequired()->cannotBeEmpty()
                            ->end()
                            ->scalarNode('address')
                                ->defaultValue(env('TEMPORAL_ADDRESS')->__toString())->cannotBeEmpty()
                            ->end()
                            ->scalarNode('identity')
                            ->end()
                            ->scalarNode('dataConverter')
                                ->cannotBeEmpty()->defaultValue('temporal.data_converter')
                            ->end()
                            ->enumNode('queryRejectionCondition')
                                ->values([
                                    QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                    QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                    QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                    QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                ])
                                ->validate()
                                    ->ifNotInArray([
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                        QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                    ])
                                    ->thenInvalid(sprintf('"queryRejectionCondition" value is not in the enum: %s', QueryRejectCondition::class))
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->children()
                ->arrayNode('workers')
                ->defaultValue(['default' => ['taskQueue' => 'default', 'exceptionInterceptor' => 'temporal.exception_interceptor', 'finalizers' => []]])
                ->useAttributeAsKey('name')
                    ->arrayPrototype()
                    ->children()
                        ->scalarNode('maxConcurrentActivityExecutionSize')
                            ->defaultValue(0)
                        ->info('To set the maximum concurrent activity executions this worker can have.')
                        ->end()
                        ->floatNode('workerActivitiesPerSecond')
                            ->defaultValue(0)
                            ->info(
                                <<<STRING
                                      Sets the rate limiting on number of activities that can be
                                      executed per second per worker. This can be used to limit resources used by the worker.

                                      Notice that the number is represented in float, so that you can set it
                                      to less than 1 if needed. For example, set the number to 0.1 means you
                                      want your activity to be executed once for every 10 seconds. This can be
                                      used to protect down stream services from flooding.
                                    STRING
                            )
                        ->end()
                        ->scalarNode('taskQueue')
                            ->isRequired()->cannotBeEmpty()
                        ->end()
                        ->scalarNode('taskQueue')
                            ->isRequired()->cannotBeEmpty()
                        ->end()
                        ->scalarNode('exceptionInterceptor')
                            ->defaultValue('temporal.exception_interceptor')->cannotBeEmpty()
                        ->end()
                        ->arrayNode('finalizers')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('maxConcurrentLocalActivityExecutionSize')
                            ->defaultValue(0)
                            ->info('To set the maximum concurrent local activity executions this worker can have.')
                        ->end()
                        ->floatNode('workerLocalActivitiesPerSecond')
                            ->defaultValue(0)
                            ->info(
                                <<<STRING
                                      Sets the rate limiting on number of local activities that can
                                      be executed per second per worker. This can be used to limit resources used by the worker.

                                      Notice that the number is represented in float, so that you can set it
                                      to less than 1 if needed. For example, set the number to 0.1 means you
                                      want your local activity to be executed once for every 10 seconds. This
                                      can be used to protect down stream services from flooding.
                                    STRING
                            )
                        ->end()
                        ->integerNode('taskQueueActivitiesPerSecond')
                            ->defaultValue(0)
                            ->info(
                                <<<STRING
                                      Sets the rate limiting on number of activities that can be executed per second.

                                      This is managed by the server and controls activities per second for your
                                      entire taskqueue whereas WorkerActivityTasksPerSecond controls activities only per worker.

                                      Notice that the number is represented in float, so that you can set it
                                      to less than 1 if needed. For example, set the number to 0.1 means you
                                      want your activity to be executed once for every 10 seconds. This can be
                                      used to protect down stream services from flooding.
                                    STRING
                            )
                        ->end()
                        ->integerNode('maxConcurrentActivityTaskPollers')
                            ->defaultValue(0)
                            ->info(
                                <<<STRING
                                       Sets the maximum number of goroutines that will concurrently poll the temporal-server to retrieve activity tasks.
                                       Changing this value will affect the rate at which the worker is able to consume tasks from a task queue.
                                    STRING
                            )
                        ->end()
                        ->integerNode('maxConcurrentWorkflowTaskExecutionSize')
                            ->defaultValue(0)
                            ->info('To set the maximum concurrent workflow task executions this worker can have.')
                        ->end()
                        ->integerNode('maxConcurrentWorkflowTaskPollers')
                            ->defaultValue(0)
                            ->info(
                                <<<STRING
                                      Sets the maximum number of goroutines that will concurrently
                                      poll the temporal-server to retrieve workflow tasks. Changing this value
                                      will affect the rate at which the worker is able to consume tasks from a task queue.
                                    STRING
                            )
                        ->end()
                        ->booleanNode('enableSessionWorker')
                            ->defaultValue(false)
                            ->info('Session workers is for activities within a session. Enable this option to allow worker to process sessions.')
                        ->end()
                        ->scalarNode('sessionResourceId')
                            ->defaultValue(null)
                            ->info(
                                <<<STRING
                                       The identifier of the resource consumed by sessions.

                                       It's the user's responsibility to ensure there's only one worker using this resourceID.
                                       For now, if user doesn't specify one, a new uuid will be used as the resourceID.
                                    STRING
                            )
                        ->end()
                        ->integerNode('maxConcurrentSessionExecutionSize')
                            ->defaultValue(1000)
                            ->info('Sets the maximum number of concurrently running sessions the resource support.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
