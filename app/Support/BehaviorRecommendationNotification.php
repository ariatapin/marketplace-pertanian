<?php

namespace App\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BehaviorRecommendationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $status,
        private readonly string $title,
        private readonly string $message,
        private readonly string $roleTarget,
        private readonly string $ruleKey,
        private readonly string $dispatchKey,
        private readonly ?string $targetLabel = null,
        private readonly ?string $validUntil = null,
        private readonly ?string $actionUrl = null,
        private readonly string $actionLabel = 'Lihat Rekomendasi'
    ) {
    }

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
            'category' => 'behavior_recommendation',
            'role_target' => $this->roleTarget,
            'rule_key' => $this->ruleKey,
            'dispatch_key' => $this->dispatchKey,
            'target_label' => $this->targetLabel,
            'valid_until' => $this->validUntil,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}

