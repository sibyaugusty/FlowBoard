<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Column;
use App\Models\Activity;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        $column = Column::findOrFail($validated['column_id']);
        $maxPosition = $column->tasks()->max('position') ?? -1;

        $task = Task::create([
            'column_id' => $validated['column_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'position' => $maxPosition + 1,
            'created_by' => Auth::id(),
        ]);

        // Assign users
        if (!empty($validated['assignees'])) {
            $task->assignees()->sync($validated['assignees']);

            foreach ($validated['assignees'] as $userId) {
                if ($userId != Auth::id()) {
                    Notification::create([
                        'id' => Str::uuid(),
                        'user_id' => $userId,
                        'type' => 'task_assigned',
                        'data' => json_encode([
                            'message' => Auth::user()->name . " assigned you to \"{$task->title}\"",
                            'task_id' => $task->id,
                            'board_id' => $column->board_id,
                        ]),
                    ]);
                }
            }
        }

        Activity::log($column->board_id, Auth::id(), 'task_created', "created task \"{$task->title}\"", $task);

        return response()->json([
            'success' => true,
            'task' => $task->load('assignees', 'creator'),
        ]);
    }

    public function show(Task $task)
    {
        $task->load('assignees', 'creator', 'comments.user', 'attachments.user', 'column.board');

        return response()->json(['task' => $task]);
    }

    public function update(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        $task->update(array_filter($validated, fn($key) => $key !== 'assignees', ARRAY_FILTER_USE_KEY));

        if (isset($validated['assignees'])) {
            $newAssignees = collect($validated['assignees'])->diff($task->assignees->pluck('id'));
            $task->assignees()->sync($validated['assignees']);

            foreach ($newAssignees as $userId) {
                if ($userId != Auth::id()) {
                    Notification::create([
                        'id' => Str::uuid(),
                        'user_id' => $userId,
                        'type' => 'task_assigned',
                        'data' => json_encode([
                            'message' => Auth::user()->name . " assigned you to \"{$task->title}\"",
                            'task_id' => $task->id,
                            'board_id' => $task->column->board_id,
                        ]),
                    ]);
                }
            }
        }

        Activity::log(
            $task->column->board_id,
            Auth::id(),
            'task_updated',
            "updated task \"{$task->title}\"",
            $task
        );

        return response()->json([
            'success' => true,
            'task' => $task->load('assignees', 'creator'),
        ]);
    }

    public function destroy(Task $task)
    {
        $boardId = $task->column->board_id;
        $title = $task->title;
        $task->delete();

        Activity::log($boardId, Auth::id(), 'task_deleted', "deleted task \"{$title}\"");

        return response()->json(['success' => true]);
    }

    public function move(Request $request, Task $task)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'position' => 'required|integer|min:0',
        ]);

        $oldColumn = $task->column;
        $newColumn = Column::findOrFail($validated['column_id']);

        // Update positions in old column
        Task::where('column_id', $oldColumn->id)
            ->where('position', '>', $task->position)
            ->decrement('position');

        // Update positions in new column
        Task::where('column_id', $newColumn->id)
            ->where('position', '>=', $validated['position'])
            ->increment('position');

        $task->update([
            'column_id' => $validated['column_id'],
            'position' => $validated['position'],
        ]);

        $description = $oldColumn->id !== $newColumn->id
            ? "moved task \"{$task->title}\" from \"{$oldColumn->name}\" to \"{$newColumn->name}\""
            : "reordered task \"{$task->title}\" in \"{$newColumn->name}\"";

        Activity::log($newColumn->board_id, Auth::id(), 'task_moved', $description, $task);

        // Notify assigned users about the move
        if ($oldColumn->id !== $newColumn->id) {
            foreach ($task->assignees as $assignee) {
                if ($assignee->id != Auth::id()) {
                    Notification::create([
                        'id' => Str::uuid(),
                        'user_id' => $assignee->id,
                        'type' => 'task_moved',
                        'data' => json_encode([
                            'message' => Auth::user()->name . " moved \"{$task->title}\" to \"{$newColumn->name}\"",
                            'task_id' => $task->id,
                            'board_id' => $newColumn->board_id,
                        ]),
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $boardId = $request->get('board_id');

        $tasks = Task::where('title', 'like', "%{$query}%")
            ->whereHas('column', function ($q) use ($boardId) {
                $q->where('board_id', $boardId);
            })
            ->with('assignees', 'column')
            ->limit(20)
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function filter(Request $request)
    {
        $boardId = $request->get('board_id');
        $priority = $request->get('priority');
        $userId = $request->get('user_id');

        $query = Task::whereHas('column', function ($q) use ($boardId) {
            $q->where('board_id', $boardId);
        })->with('assignees', 'column', 'creator');

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($userId) {
            $query->whereHas('assignees', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            });
        }

        return response()->json(['tasks' => $query->get()]);
    }
}
