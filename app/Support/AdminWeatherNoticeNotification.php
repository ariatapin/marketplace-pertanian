<?php

namespace App\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminWeatherNoticeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $severity,
        protected string $title,
        protected string $message,
        protected string $actionUrl,
        protected string $actionLabel = 'Lihat Detail',
        protected ?string $scope = null,
        protected ?string $targetLabel = null,
        protected ?string $validUntil = null,
        protected ?int $noticeId = null,
        protected ?string $dispatchKey = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'scope' => $this->scope,
            'target_label' => $this->targetLabel,
            'valid_until' => $this->validUntil,
            'notice_id' => $this->noticeId,
            'dispatch_key' => $this->dispatchKey,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
