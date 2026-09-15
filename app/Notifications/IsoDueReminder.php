<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IsoDueReminder extends Notification
{
    use Queueable;

    public function __construct(
        public string $category,
        public string $title,
        public string $message,
        public ?string $url = null,
        public ?string $dueDate = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'due_date' => $this->dueDate,
        ];
    }
}
