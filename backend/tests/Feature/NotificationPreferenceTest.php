<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskMentionedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assignment_notifications_follow_saved_in_app_and_verified_email_preferences(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $type = NotificationType::factory()->create(['slug' => 'task.assigned', 'default_enabled' => true]);
        $notification = new TaskAssignedNotification([
            'id' => 'task-public-id',
            'task_number' => 'TSK-1',
            'title' => 'Prepare release',
            'project' => 'Platform',
            'assigned_by' => 'Teammate',
        ]);

        $this->assertSame(['database', 'mail'], $notification->via($user));
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'notification_type_id' => $type->id,
            'channel' => 'in_app',
            'enabled' => false,
        ]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'notification_type_id' => $type->id,
            'channel' => 'email',
            'enabled' => false,
        ]);

        $this->assertSame([], $notification->via($user));
    }

    public function test_unverified_users_never_receive_email_notifications(): void
    {
        $user = User::factory()->unverified()->create();
        NotificationType::factory()->create(['slug' => 'task.mentioned', 'default_enabled' => true]);

        $notification = new TaskMentionedNotification([
            'id' => 'task-public-id',
            'task_number' => 'TSK-2',
            'title' => 'Review the deployment plan',
            'comment_id' => 'comment-public-id',
            'mentioned_by' => 'Teammate',
        ]);

        $this->assertSame(['database'], $notification->via($user));
    }
}
