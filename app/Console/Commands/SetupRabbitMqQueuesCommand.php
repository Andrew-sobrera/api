<?php

namespace App\Console\Commands;

use App\Support\QueueNames;
use Illuminate\Console\Command;
use VladimirYuldashev\LaravelQueueRabbitMQ\Console\QueueDeclareCommand;

class SetupRabbitMqQueuesCommand extends Command
{
    protected $signature = 'rabbitmq:setup-queues
                            {--force : Delete and recreate queues when they already exist}';

    protected $description = 'Declare all application RabbitMQ queues used by checkout and payments';

    public function handle(): int
    {
        $queues = [
            config('queue.connections.rabbitmq.queue', 'default'),
            QueueNames::PAYMENTS_CREATE,
            QueueNames::PAYMENTS_WEBHOOK,
            QueueNames::TICKETS_EXPIRATION,
            QueueNames::TICKETS_GENERATION,
            QueueNames::EMAILS,
        ];

        $queues = array_values(array_unique(array_filter($queues)));

        if ($this->option('force')) {
            foreach ($queues as $queue) {
                $this->call('rabbitmq:queue-delete', [
                    'name' => $queue,
                    'connection' => 'rabbitmq',
                ]);
            }
        }

        foreach ($queues as $queue) {
            $this->call(QueueDeclareCommand::class, [
                'name' => $queue,
                'connection' => 'rabbitmq',
            ]);
        }

        $this->components->info('RabbitMQ queues ready: '.implode(', ', $queues));

        return self::SUCCESS;
    }
}
