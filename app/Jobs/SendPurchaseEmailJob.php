<?php

namespace App\Jobs;

use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PurchaseCreatedMail;
use App\Models\Order;
use App\Support\QueueNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class SendPurchaseEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $orderId,
        public string $mailClass
    ) {
        $this->onQueue(QueueNames::EMAILS);
    }

    public function handle(): void
    {
        $order = Order::with('user')->findOrFail($this->orderId);

        $mailable = match ($this->mailClass) {
            PurchaseCreatedMail::class => new PurchaseCreatedMail($order),
            PaymentApprovedMail::class => new PaymentApprovedMail($order),
            PaymentFailedMail::class => new PaymentFailedMail($order),
            default => throw new InvalidArgumentException('Unsupported mailable: '.$this->mailClass),
        };

        Mail::to($order->user->email)->send($mailable);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendPurchaseEmailJob failed permanently', [
            'order_id' => $this->orderId,
            'mail_class' => $this->mailClass,
            'message' => $exception?->getMessage(),
        ]);
    }
}
