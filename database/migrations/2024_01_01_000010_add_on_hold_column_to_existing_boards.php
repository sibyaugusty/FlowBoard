<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Board;
use App\Models\Column;

return new class extends Migration
{
    public function up(): void
    {
        // Add an "On Hold" column to every existing board that doesn't already have one.
        // Inserted at position 2 (after "In Progress", before "Review").
        $boards = Board::with('columns')->get();

        foreach ($boards as $board) {
            $hasOnHold = $board->columns->contains(fn ($col) => strtolower($col->name) === 'on hold');

            if (! $hasOnHold) {
                // Find the position of "Review" column; new column goes right before it.
                $reviewColumn = $board->columns->first(fn ($col) => strtolower($col->name) === 'review');
                $insertPosition = $reviewColumn ? $reviewColumn->position : ($board->columns->max('position') + 1);

                // Shift columns at or after the insert position
                Column::where('board_id', $board->id)
                    ->where('position', '>=', $insertPosition)
                    ->increment('position');

                // Create the On Hold column
                Column::create([
                    'board_id' => $board->id,
                    'name' => 'On Hold',
                    'position' => $insertPosition,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove "On Hold" columns that were auto-inserted
        Column::where('name', 'On Hold')->delete();
    }
};
