<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesOrganizationRequests;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use AuthorizesOrganizationRequests;

    public function tasks(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->authorizePermission($actor, 'reports.view');
        $organizationId = $this->requireOrganization($actor);
        $query = Task::query()->where('organization_id', $organizationId)->with(['project', 'status', 'priority', 'assignees']);
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }
        if ($request->filled('project_id')) {
            $projectId = DB::table('projects')->where('organization_id', $organizationId)->where('public_id', $request->string('project_id'))->value('id');
            abort_if($projectId === null, 404);
            $query->where('project_id', $projectId);
        }
        if ($request->boolean('export')) {
            $tasks = $query->orderBy('created_at')->limit(10000)->get();
            $csv = fopen('php://temp', 'r+');
            fputcsv($csv, ['Task', 'Project', 'Status', 'Priority', 'Due date', 'Assignees']);
            foreach ($tasks as $task) {
                fputcsv($csv, array_map($this->safeSpreadsheetCell(...), [
                    $task->task_number,
                    $task->title,
                    $task->project?->name,
                    $task->status?->name,
                    $task->priority?->name,
                    $task->due_at?->toDateString(),
                    $task->assignees->pluck('name')->implode(', '),
                ]));
            }
            rewind($csv);
            $contents = stream_get_contents($csv);
            fclose($csv);

            $filename = 'tasks-'.now()->toDateString().'.csv';

            return response($contents, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(50));
    }

    private function safeSpreadsheetCell(?string $value): ?string
    {
        if ($value !== null && preg_match('/^\s*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
