<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Board;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Board $board)
    {
        $activities = $board->activities()
            ->with('user')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['activities' => $activities]);
    }
}
