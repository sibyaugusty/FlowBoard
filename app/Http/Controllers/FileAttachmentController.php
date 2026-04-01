<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use App\Models\Task;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments', 'public');

        $attachment = $task->attachments()->create([
            'user_id' => Auth::id(),
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $boardId = $task->column->board_id;
        Activity::log($boardId, Auth::id(), 'attachment_added', "attached \"{$attachment->filename}\" to \"{$task->title}\"", $attachment);

        return response()->json([
            'success' => true,
            'attachment' => $attachment->load('user'),
        ]);
    }

    public function destroy(FileAttachment $attachment)
    {
        if ($attachment->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['success' => true]);
    }

    public function download(FileAttachment $attachment)
    {
        return Storage::disk('public')->download($attachment->path, $attachment->filename);
    }
}
