<?php

namespace App\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MitraApplicationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $status,
        protected string $title,
        protected string $message,
        protected string $actionUrl,
        protected string $actionLabel = 'Lihat Detail',
        protected ?string $notes = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo ' . ($notifiable->name ?? 'User') . ',')
            ->line($this->message);

        if (! empty($this->notes)) {
            $mail->line('Catatan admin: ' . $this->notes);
        }

        return $mail
            ->action($this->actionLabel, $this->actionUrl)
            ->line('Terima kasih telah menggunakan marketplace pertanian.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'notes' => $this->notes,
            'sent_at' => now()->toDateTimeString(),
        ];
    }
}
