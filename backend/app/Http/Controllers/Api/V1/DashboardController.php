<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->requireOrganization($request->user());
        $tasks = Task::query()->where('organization_id', $organizationId);
        $projects = Project::query()->where('organization_id', $organizationId);
        $recentTasks = (clone $tasks)->with(['status', 'priority', 'project', 'assignees'])
            ->orderByRaw('due_at is null')->orderBy('due_at')->limit(8)->get();

        return response()->json(['data' => [
            'counts' => [
                'projects' => (clone $projects)->where('status', 'active')->count(),
                'open_tasks' => (clone $tasks)->whereHas('status', fn (Builder $query) => $query->where('is_closed', false))->count(),
                'overdue_tasks' => (clone $tasks)->whereNotNull('due_at')->where('due_at', '<', now())->whereHas('status', fn (Builder $query) => $query->where('is_closed', false))->count(),
                'completed_this_week' => (clone $tasks)->whereHas('status', fn (Builder $query) => $query->where('slug', 'completed'))->where('updated_at', '>=', now()->startOfWeek())->count(),
            ],
            'tasks_by_status' => DB::table('tasks')->join('task_statuses', 'tasks.status_id', '=', 'task_statuses.id')
                ->where('tasks.organization_id', $organizationId)->whereNull('tasks.deleted_at')
                ->select('task_statuses.name', 'task_statuses.color', DB::raw('count(*) as count'))
                ->groupBy('task_statuses.id', 'task_statuses.name', 'task_statuses.color')->orderBy('task_statuses.position')->get(),
            'upcoming_tasks' => $recentTasks,
        ]]);
    }
}
