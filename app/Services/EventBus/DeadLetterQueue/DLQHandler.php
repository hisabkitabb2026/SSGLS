<?php

namespace App\Services\EventBus\DeadLetterQueue;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Dead Letter Queue Handler
 *
 * Handles messages that failed processing and ended up in the DLQ
 */
class DLQHandler
{
    protected AMQPStreamConnection $connection;

    protected AMQPChannel $channel;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('event-bus');
        $this->connect();
    }

    /**
     * Establish connection to RabbitMQ
     */
    protected function connect(): void
    {
        $rabbitmqConfig = $this->config['broker']['rabbitmq'];

        $this->connection = new AMQPStreamConnection(
            $rabbitmqConfig['host'],
            $rabbitmqConfig['port'],
            $rabbitmqConfig['username'],
            $rabbitmqConfig['password'],
            $rabbitmqConfig['vhost'],
        );

        $this->channel = $this->connection->channel();
    }

    /**
     * Start listening to DLQ
     */
    public function listen(int $timeout = 0): void
    {
        $dlqName = $this->config['dead_letter']['queue']['name'];

        Log::info("Starting DLQ listener on queue: {$dlqName}");

        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume(
            $dlqName,
            '',
            false,
            false,
            false,
            false,
            fn (AMQPMessage $message) => $this->handleMessage($message)
        );

        try {
            $this->channel->consume(null, $timeout);
        } catch (\Exception $e) {
            Log::error("Error consuming DLQ messages: {$e->getMessage()}");
        }
    }

    /**
     * Handle dead letter message
     */
    protected function handleMessage(AMQPMessage $message): void
    {
        try {
            $data = json_decode($message->body, true, flags: JSON_THROW_ON_ERROR);

            Log::warning('Dead letter message received', [
                'message_id' => $message->get('message_id'),
                'event_type' => $data['type'] ?? 'unknown',
                'aggregate_id' => $data['aggregate_id'] ?? null,
            ]);

            // Store in DLQ database
            $this->storeDeadLetter($message, $data);

            // Alert administrators
            $this->notifyAdministrators($data);

            // Check retry count
            $retryCount = intval($message->get('x-death')[0]['count'] ?? 0);
            $maxRetries = $this->config['dead_letter']['queue']['max_retries'] ?? 5;

            if ($retryCount < $maxRetries) {
                // Republish to main queue with updated retry count
                $this->republishWithRetry($message, $retryCount);
            } else {
                Log::error('Message exceeded max retries', [
                    'message_id' => $message->get('message_id'),
                    'retry_count' => $retryCount,
                ]);
            }

            $message->ack();
        } catch (\Exception $e) {
            Log::error("Error handling dead letter message: {$e->getMessage()}", [
                'error' => $e->getMessage(),
                'message_id' => $message->get('message_id'),
            ]);

            $message->nack(false); // Don't requeue
        }
    }

    /**
     * Store dead letter in database
     */
    protected function storeDeadLetter(AMQPMessage $message, array $data): void
    {
        $retryCount = intval($message->get('x-death')[0]['count'] ?? 0);

        DB::table('dead_letter_messages')->insert([
            'id' => $message->get('message_id'),
            'event_id' => $data['id'] ?? null,
            'event_type' => $data['type'] ?? null,
            'aggregate_type' => $data['aggregate_type'] ?? null,
            'aggregate_id' => $data['aggregate_id'] ?? null,
            'payload' => json_encode($data),
            'headers' => json_encode($message->get_properties()),
            'retry_count' => $retryCount,
            'created_at' => now(),
        ]);
    }

    /**
     * Republish message to main queue for retry
     */
    protected function republishWithRetry(AMQPMessage $message, int $retryCount): void
    {
        $retryDelay = $this->calculateRetryDelay($retryCount);

        // Schedule message for delayed delivery
        $newMessage = new AMQPMessage(
            $message->body,
            array_merge(
                $message->get_properties(),
                [
                    'headers' => array_merge(
                        $message->get('headers') ?? [],
                        ['x-retry-count' => $retryCount + 1]
                    ),
                    'expiration' => $retryDelay,
                ]
            )
        );

        $this->channel->basic_publish(
            $newMessage,
            $this->config['events']['exchange']['name'],
            $message->get('routing_key')
        );

        Log::info('Message republished for retry', [
            'message_id' => $message->get('message_id'),
            'retry_count' => $retryCount + 1,
            'delay_ms' => $retryDelay,
        ]);
    }

    /**
     * Calculate retry delay with exponential backoff
     */
    protected function calculateRetryDelay(int $retryCount): int
    {
        $initialDelay = $this->config['retry']['initial_delay'] ?? 1000;
        $maxDelay = $this->config['retry']['max_delay'] ?? 300000;
        $multiplier = $this->config['retry']['multiplier'] ?? 2;

        $delay = intval($initialDelay * pow($multiplier, $retryCount));

        return min($delay, $maxDelay);
    }

    /**
     * Notify administrators of dead letter
     */
    protected function notifyAdministrators(array $data): void
    {
        try {
            $admins = DB::table('users')
                ->where('is_admin', true)
                ->where('is_active', true)
                ->pluck('email')
                ->toArray();

            if (empty($admins)) {
                return;
            }

            foreach ($admins as $email) {
                // Send notification (implement your mail logic)
                Log::info('Notifying admin about dead letter', ['email' => $email]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to notify administrators: {$e->getMessage()}");
        }
    }

    /**
     * Get dead letters from database
     */
    public function getDeadLetters(array $filters = []): Builder
    {
        $query = DB::table('dead_letter_messages');

        if ($filters['event_type'] ?? null) {
            $query->where('event_type', $filters['event_type']);
        }

        if ($filters['aggregate_type'] ?? null) {
            $query->where('aggregate_type', $filters['aggregate_type']);
        }

        if ($filters['from_date'] ?? null) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] ?? null) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Requeue dead letter message
     */
    public function requeue(string $messageId): bool
    {
        try {
            $record = DB::table('dead_letter_messages')
                ->where('id', $messageId)
                ->first();

            if (! $record) {
                return false;
            }

            $data = json_decode($record->payload, true);

            $message = new AMQPMessage(
                $record->payload,
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            $this->channel->basic_publish(
                $message,
                $this->config['events']['exchange']['name'],
                "dlq.requeue.{$record->event_type}"
            );

            DB::table('dead_letter_messages')
                ->where('id', $messageId)
                ->update(['requeued_at' => now()]);

            Log::info('Dead letter requeued', ['message_id' => $messageId]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to requeue dead letter: {$e->getMessage()}", [
                'message_id' => $messageId,
            ]);

            return false;
        }
    }

    /**
     * Delete old dead letters
     */
    public function cleanup(int $daysOld = 30): int
    {
        $date = now()->subDays($daysOld);

        $deleted = DB::table('dead_letter_messages')
            ->where('created_at', '<', $date)
            ->delete();

        Log::info("Cleaned up {$deleted} old dead letter messages");

        return $deleted;
    }

    /**
     * Disconnect from broker
     */
    public function disconnect(): void
    {
        if (isset($this->channel) && $this->channel->is_open()) {
            $this->channel->close();
        }

        if (isset($this->connection) && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
