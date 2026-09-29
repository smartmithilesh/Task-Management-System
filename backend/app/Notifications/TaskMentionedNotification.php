<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskMentionedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsNotificationPreferences;

    /** @param array{id: string, task_number: string, title: string, comment_id: string, mentioned_by: string} $comment */
    public function __construct(private readonly array $comment)
    {
        $this->afterCommit = true;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $this->channelsFor($notifiable, 'task.mentioned') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['event' => 'task.mentioned', ...$this->comment];
    }

    /** @return array{title: string, body: string, data: array<string, string>} */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'You were mentioned',
            'body' => $this->comment['mentioned_by'].' mentioned you on '.$this->comment['task_number'].'.',
            'data' => ['event' => 'task.mentioned', 'task_id' => $this->comment['id']],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned on '.$this->comment['task_number'])
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->comment['mentioned_by'].' mentioned you on '.$this->comment['title'].'.')
            ->action('View tasks', rtrim((string) config('app.url'), '/').'/tasks/');
    }
}
