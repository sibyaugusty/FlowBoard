<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Activity;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $comment = $task->comments()->create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        $boardId = $task->column->board_id;
        Activity::log($boardId, Auth::id(), 'comment_added', "commented on \"{$task->title}\"", $comment);

        // Notify task assignees
        foreach ($task->assignees as $assignee) {
            if ($assignee->id != Auth::id()) {
                Notification::create([
                    'id' => Str::uuid(),
                    'user_id' => $assignee->id,
                    'type' => 'comment_added',
                    'data' => json_encode([
                        'message' => Auth::user()->name . " commented on \"{$task->title}\"",
                        'task_id' => $task->id,
                        'board_id' => $boardId,
                    ]),
                ]);
            }
        }

        // Also notify creator if different from commenter
        if ($task->created_by != Auth::id() && !$task->assignees->contains('id', $task->created_by)) {
            Notification::create([
                'id' => Str::uuid(),
                'user_id' => $task->created_by,
                'type' => 'comment_added',
                'data' => json_encode([
                    'message' => Auth::user()->name . " commented on \"{$task->title}\"",
                    'task_id' => $task->id,
                    'board_id' => $boardId,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'comment' => $comment->load('user'),
        ]);
    }

    public function destroy(Comment $comment)
    {
        if ($comment->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }
}
