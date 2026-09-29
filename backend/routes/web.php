<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChecklistController;
use App\Http\Controllers\Api\V1\ChecklistItemController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PushDeviceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TimeEntryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('task-index.html'));
});

Route::get('/api/v1/integrations/oauth/{provider}/callback', [IntegrationController::class, 'oauthCallback'])
    ->middleware('throttle:20,1')->name('api.v1.integrations.oauth.callback');

Route::prefix('api/v1/auth')->group(function (): void {
    Route::get('/csrf-token', fn () => response()->json(['data' => ['csrf_token' => csrf_token()]]))
        ->name('api.v1.auth.csrf-token');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.v1.auth.login');
    Route::get('/password/reset/{token}', function (string $token) {
        return response()->json([
            'success' => true,
            'data' => ['token' => $token, 'email' => request()->query('email')],
        ]);
    })->name('password.reset');
    Route::post('/password/forgot', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:password-reset')->name('api.v1.auth.password.email');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('api.v1.auth.password.update');

    Route::middleware(AuthenticateApiToken::class)->group(function (): void {
        Route::get('/user', [AuthController::class, 'user'])->name('api.v1.auth.user');
        Route::patch('/user', [UserController::class, 'profile'])->name('api.v1.auth.user.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationNotification'])
            ->middleware('throttle:6,1')->name('api.v1.auth.verification.send');
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    });
});

Route::middleware(AuthenticateApiToken::class)->prefix('api/v1/users')->name('api.v1.users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::patch('/{user}', [UserController::class, 'update'])->name('update');
    Route::put('/{user}/roles', [UserController::class, 'assignRoles'])->name('roles.update');
});

Route::middleware(AuthenticateApiToken::class)->prefix('api/v1')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.v1.dashboard');
    Route::get('/reports/tasks', [ReportController::class, 'tasks'])->name('api.v1.reports.tasks');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::get('/notification-preferences', [NotificationController::class, 'preferences'])->name('api.v1.notification-preferences.index');
    Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences'])->name('api.v1.notification-preferences.update');
    Route::post('/push-devices', [PushDeviceController::class, 'store'])->middleware('throttle:30,1')->name('api.v1.push-devices.store');
    Route::delete('/push-devices/{device}', [PushDeviceController::class, 'destroy'])->name('api.v1.push-devices.destroy');
    Route::get('/integrations', [IntegrationController::class, 'index'])->name('api.v1.integrations.index');
    Route::post('/integrations', [IntegrationController::class, 'store'])->name('api.v1.integrations.store');
    Route::post('/integrations/oauth/{provider}/authorize', [IntegrationController::class, 'authorizeOAuth'])
        ->middleware('throttle:5,1')->name('api.v1.integrations.oauth.authorize');
    Route::post('/integrations/{integration}/test', [IntegrationController::class, 'test'])->name('api.v1.integrations.test');
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy'])->name('api.v1.integrations.destroy');
    Route::get('/organization', [OrganizationController::class, 'show'])->name('api.v1.organization.show');
    Route::post('/organization', [OrganizationController::class, 'store'])->name('api.v1.organization.store');
    Route::patch('/organization', [OrganizationController::class, 'update'])->name('api.v1.organization.update');

    Route::get('/roles', [RoleController::class, 'index'])->name('api.v1.roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('api.v1.roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('api.v1.roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('api.v1.roles.destroy');
    Route::get('/permissions', [RoleController::class, 'permissions'])->name('api.v1.permissions.index');

    Route::get('/departments', [DepartmentController::class, 'index'])->name('api.v1.departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('api.v1.departments.store');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->name('api.v1.departments.show');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('api.v1.departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('api.v1.departments.destroy');

    Route::get('/teams', [TeamController::class, 'index'])->name('api.v1.teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('api.v1.teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('api.v1.teams.show');
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('api.v1.teams.update');
    Route::put('/teams/{team}/members', [TeamController::class, 'updateMembers'])->name('api.v1.teams.members.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('api.v1.teams.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->name('api.v1.projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('api.v1.projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('api.v1.projects.show');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('api.v1.projects.update');
    Route::put('/projects/{project}/members', [ProjectController::class, 'updateMembers'])->name('api.v1.projects.members.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('api.v1.projects.destroy');

    Route::get('/tasks', [TaskController::class, 'index'])->name('api.v1.tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('api.v1.tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('api.v1.tasks.show');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('api.v1.tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('api.v1.tasks.status.update');
    Route::put('/tasks/{task}/assignees', [TaskController::class, 'updateAssignees'])->name('api.v1.tasks.assignees.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('api.v1.tasks.destroy');

    Route::post('/tasks/{task}/checklists', [ChecklistController::class, 'store'])->name('api.v1.tasks.checklists.store');
    Route::patch('/checklists/{checklist}', [ChecklistController::class, 'update'])->name('api.v1.checklists.update');
    Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('api.v1.checklists.destroy');
    Route::post('/checklists/{checklist}/items', [ChecklistItemController::class, 'store'])->name('api.v1.checklists.items.store');
    Route::patch('/checklist-items/{item}', [ChecklistItemController::class, 'update'])->name('api.v1.checklist-items.update');
    Route::delete('/checklist-items/{item}', [ChecklistItemController::class, 'destroy'])->name('api.v1.checklist-items.destroy');

    Route::get('/tasks/{task}/comments', [CommentController::class, 'index'])->name('api.v1.tasks.comments.index');
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('api.v1.tasks.comments.store');
    Route::patch('/comments/{comment}', [CommentController::class, 'update'])->name('api.v1.comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('api.v1.comments.destroy');
    Route::get('/tasks/{task}/attachments', [AttachmentController::class, 'index'])->name('api.v1.tasks.attachments.index');
    Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('api.v1.tasks.attachments.store');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('api.v1.attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('api.v1.attachments.destroy');
    Route::get('/tasks/{task}/activity', [ActivityController::class, 'task'])->name('api.v1.tasks.activity.index');
    Route::get('/tags', [TagController::class, 'index'])->name('api.v1.tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('api.v1.tags.store');
    Route::put('/tasks/{task}/tags', [TagController::class, 'updateTaskTags'])->name('api.v1.tasks.tags.update');
    Route::get('/time-entries', [TimeEntryController::class, 'index'])->name('api.v1.time-entries.index');
    Route::post('/time-entries', [TimeEntryController::class, 'store'])->name('api.v1.time-entries.store');
    Route::delete('/time-entries/{entry}', [TimeEntryController::class, 'destroy'])->name('api.v1.time-entries.destroy');
    Route::post('/tasks/{task}/timer/start', [TimeEntryController::class, 'start'])->name('api.v1.tasks.timer.start');
    Route::post('/tasks/{task}/timer/stop', [TimeEntryController::class, 'stop'])->name('api.v1.tasks.timer.stop');
});
