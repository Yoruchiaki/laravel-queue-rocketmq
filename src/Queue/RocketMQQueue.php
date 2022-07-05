<?php

namespace Nichozuo\LaravelQueueRocketMQ\Queue;

use DateInterval;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Queue\Job;
use MQ\Exception\MessageNotExistException;
use MQ\MQConsumer;
use MQ\MQProducer;
use Nichozuo\LaravelQueueRocketMQ\Queue\Jobs\RocketMQJob;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Arr;
use MQ\Model\TopicMessage;
use MQ\MQClient;
use ReflectionException;
use ReflectionMethod;

class RocketMQQueue extends Queue implements QueueContract
{
    /**
     * @var array
     */
    protected array $config;

    /**
     * @var ReflectionMethod
     */
    private ReflectionMethod $createPayload;

    /**
     * @var MQClient
     */
    protected MQClient $client;

    /**
     * RocketMQQueue constructor.
     * @param MQClient $client
     * @param array $config
     * @throws ReflectionException
     */
    public function __construct(MQClient $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
        $this->createPayload = new ReflectionMethod($this, 'createPayload');
    }

    /**
     * @return bool
     */
    public function isPlain(): bool
    {
        return (bool)Arr::get($this->config, 'plain.enable');
    }

    /**
     * @return string
     */
    public function getPlainJob(): string
    {
        return Arr::get($this->config, 'plain.job');
    }

    /**
     * Get the size of the queue.
     * @param string $queue
     * @return int
     */
    public function size($queue = null): int
    {
        return 1;
    }

    /**
     * Push a new job onto the queue.
     * @param string|object $job
     * @param mixed $data
     * @param string $queue
     * @return TopicMessage
     * @throws Exception
     */
    public function push($job, $data = '', $queue = null): TopicMessage
    {
        if ($this->isPlain()) {
            return $this->pushRaw($job->getPayload(), $queue);
        }
        $payload = $this->createPayload->getNumberOfParameters() === 3
            ? $this->createPayload($job, $queue, $data) // version >= 5.7
            : $this->createPayload($job, $data);
        return $this->pushRaw($payload, $queue);
    }

    /**
     * Push a raw payload onto the queue.
     * @param string $payload
     * @param string $queue
     * @param array $options
     * @return TopicMessage
     * @throws Exception
     */
    public function pushRaw($payload, $queue = null, array $options = []): TopicMessage
    {
        $message = new TopicMessage($payload);
        if ($this->config['use_message_tag'] && $queue) {
            $message->setMessageTag($queue);
        }
        if ($delay = Arr::get($options, 'delay', 0)) {
            $message->setStartDeliverTime(time() * 1000 + $delay * 1000);
        }
        return $this->getProducer(
            $this->config['use_message_tag'] ? $this->config['queue'] : $queue
        )->publishMessage($message);
    }

    /**
     * Push a new job onto the queue after a delay.
     * @param DateTimeInterface|DateInterval|int $delay
     * @param string|object $job
     * @param mixed $data
     * @param string $queue
     * @return TopicMessage
     * @throws Exception
     */
    public function later($delay, $job, $data = '', $queue = null): TopicMessage
    {
        $delay = method_exists($this, 'getSeconds')
            ? $this->getSeconds($delay)
            : $this->secondsUntil($delay);

        if ($this->isPlain()) {
            return $this->pushRaw($job->getPayload(), $queue, ['delay' => $delay]);
        }

        $payload = $this->createPayload->getNumberOfParameters() === 3
            ? $this->createPayload($job, $queue, $data) // version >= 5.7
            : $this->createPayload($job, $data);

        return $this->pushRaw($payload, $queue, ['delay' => $delay]);
    }

    /**
     * Pop the next job off of the queue.
     * @param null $queue
     * @return Job|RocketMQJob|null
     * @throws Exception
     */
    public function pop($queue = null): Job|RocketMQJob|null
    {
        try {

            $consumer = $this->config['use_message_tag']
                ? $this->getConsumer($this->config['queue'], $queue)
                : $this->getConsumer($queue);

            /** @var array $messages */
            $messages = $consumer->consumeMessage(1, $this->config['wait_seconds']);

        } catch (Exception $e) {
            if ($e instanceof MessageNotExistException) {
                return null;
            }

            throw $e;
        }

        return new RocketMQJob(
            $this->container ?: Container::getInstance(),
            $this,
            Arr::first($messages),
            $this->config['use_message_tag'] ? $this->config['queue'] : $queue,
            $this->connectionName ?? null
        );
    }

    /**
     * Get the consumer.
     * @param string|null $topicName
     * @param string|null $messageTag
     * @return MQConsumer
     */
    public function getConsumer(string $topicName = null, string $messageTag = null): MQConsumer
    {
        return $this->client->getConsumer(
            $this->config['instance_id'],
            $topicName ?: $this->config['queue'],
            $this->config['group_id'],
            $messageTag
        );
    }

    /**
     * Get the producer.
     * @param string|null $topicName
     * @return MQProducer
     */
    public function getProducer(string $topicName = null): MQProducer
    {
        return $this->client->getProducer(
            $this->config['instance_id'],
            $topicName ?: $this->config['queue']
        );
    }
}
