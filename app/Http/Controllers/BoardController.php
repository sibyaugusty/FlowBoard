<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $ownedBoards = Board::where('user_id', $user->id)->with('members', 'columns.tasks')->latest()->get();
        $memberBoards = $user->memberBoards()->with('owner', 'columns.tasks')->latest()->get();
        $boards = $ownedBoards->merge($memberBoards)->unique('id');

        return view('dashboard', compact('boards'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['user_id'] = Auth::id();

        $board = Board::create($validated);

        // Create default columns
        $defaultColumns = ['In Progress', 'On Hold', 'Review', 'Done'];
        foreach ($defaultColumns as $index => $name) {
            $board->columns()->create([
                'name' => $name,
                'position' => $index,
            ]);
        }

        Activity::log($board->id, Auth::id(), 'board_created', "created board \"{$board->name}\"", $board);

        return response()->json(['success' => true, 'board' => $board->load('columns')]);
    }

    public function show(Board $board)
    {
        $this->authorizeBoard($board);

        $board->load([
            'columns.tasks.assignees',
            'columns.tasks.comments',
            'columns.tasks.attachments',
            'members',
            'owner'
        ]);

        $boardMembers = $board->members->push($board->owner)->unique('id');

        return view('boards.show', compact('board', 'boardMembers'));
    }

    public function update(Request $request, Board $board)
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
        ]);

        $board->update($validated);

        Activity::log($board->id, Auth::id(), 'board_updated', "updated board \"{$board->name}\"", $board);

        return response()->json(['success' => true, 'board' => $board]);
    }

    public function destroy(Board $board)
    {
        $this->authorizeBoard($board);
        $board->delete();
        return response()->json(['success' => true]);
    }

    public function addMember(Request $request, Board $board)
    {
        $this->authorizeBoard($board);

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if ($board->user_id === $user->id) {
            return response()->json(['error' => 'User is already the board owner'], 422);
        }

        if ($board->members->contains($user)) {
            return response()->json(['error' => 'User is already a member'], 422);
        }

        $board->members()->attach($user->id, ['role' => 'member']);

        Activity::log($board->id, Auth::id(), 'member_added', "added {$user->name} to the board", $board);

        // Create notification
        \App\Models\Notification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'type' => 'board_invitation',
            'data' => json_encode([
                'message' => Auth::user()->name . ' added you to board "' . $board->name . '"',
                'board_id' => $board->id,
            ]),
        ]);

        return response()->json(['success' => true, 'member' => $user]);
    }

    public function removeMember(Board $board, $userId)
    {
        $this->authorizeBoard($board);
        $board->members()->detach($userId);

        Activity::log($board->id, Auth::id(), 'member_removed', "removed a member from the board", $board);

        return response()->json(['success' => true]);
    }

    private function authorizeBoard(Board $board)
    {
        $user = Auth::user();
        if (!$board->isAccessibleBy($user)) {
            abort(403, 'Unauthorized');
        }
    }
}
