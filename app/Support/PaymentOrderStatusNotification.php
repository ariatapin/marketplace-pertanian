<?php

namespace App\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentOrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $status,
        protected string $title,
        protected string $message,
        protected string $actionUrl,
        protected string $actionLabel = 'Buka Detail',
        protected ?int $orderId = null,
        protected ?string $paymentMethod = null,
        protected ?float $paidAmount = null,
        protected ?string $dispatchKey = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'order_id' => $this->orderId,
            'payment_method' => $this->paymentMethod,
            'paid_amount' => $this->paidAmount,
            'dispatch_key' => $this->dispatchKey,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
