<?php

namespace Nichozuo\LaravelQueueRocketMQ\Queue\Connectors;

use Illuminate\Contracts\Queue\Queue;
use Nichozuo\LaravelQueueRocketMQ\Queue\RocketMQQueue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use MQ\MQClient;
use ReflectionException;

class RocketMQConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     * @param array $config
     * @return RocketMQQueue|Queue
     * @throws ReflectionException
     */
    public function connect(array $config): RocketMQQueue|Queue
    {
        $client = new MQClient(
            $config['endpoint'], $config['access_id'], $config['access_key']
        );
        return new RocketMQQueue($client, $config);
    }
}
