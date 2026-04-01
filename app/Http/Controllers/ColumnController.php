<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Board;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ColumnController extends Controller
{
    public function store(Request $request, Board $board)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $maxPosition = $board->columns()->max('position') ?? -1;

        $column = $board->columns()->create([
            'name' => $validated['name'],
            'position' => $maxPosition + 1,
        ]);

        Activity::log($board->id, Auth::id(), 'column_created', "created column \"{$column->name}\"", $column);

        return response()->json(['success' => true, 'column' => $column]);
    }

    public function update(Request $request, Column $column)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $column->update($validated);

        return response()->json(['success' => true, 'column' => $column]);
    }

    public function destroy(Column $column)
    {
        $boardId = $column->board_id;
        $name = $column->name;
        $column->delete();

        Activity::log($boardId, Auth::id(), 'column_deleted', "deleted column \"{$name}\"");

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, Board $board)
    {
        $validated = $request->validate([
            'columns' => 'required|array',
            'columns.*' => 'integer|exists:columns,id',
        ]);

        foreach ($validated['columns'] as $position => $columnId) {
            Column::where('id', $columnId)->where('board_id', $board->id)->update(['position' => $position]);
        }

        return response()->json(['success' => true]);
    }
}
