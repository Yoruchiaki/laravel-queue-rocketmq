<?php

namespace Nichozuo\LaravelQueueRocketMQ;

use Nichozuo\LaravelQueueRocketMQ\Queue\Connectors\RocketMQConnector;
use Illuminate\Support\ServiceProvider;

class LaravelQueueRocketMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rocketmq.php', 'queue.connections.rocketmq'
        );
    }

    /**
     * Register the application's event listeners.
     * @return void
     */
    public function boot()
    {
        $queue = $this->app['queue'];

        $queue->addConnector('rocketmq', function () {
            return new RocketMQConnector();
        });
    }
}
