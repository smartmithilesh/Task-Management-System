<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsNotificationPreferences;

    /** @param array{id: string, task_number: string, title: string, project: string, assigned_by: string} $task */
    public function __construct(private readonly array $task)
    {
        $this->afterCommit = true;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $this->channelsFor($notifiable, 'task.assigned') : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['event' => 'task.assigned', ...$this->task];
    }

    /** @return array{title: string, body: string, data: array<string, string>} */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Task assigned',
            'body' => $this->task['task_number'].': '.$this->task['title'],
            'data' => ['event' => 'task.assigned', 'task_id' => $this->task['id']],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were assigned '.$this->task['task_number'])
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->task['title'])
            ->line('Project: '.$this->task['project'])
            ->line('Assigned by '.$this->task['assigned_by'].'.')
            ->action('View tasks', rtrim((string) config('app.url'), '/').'/tasks/');
    }
}
