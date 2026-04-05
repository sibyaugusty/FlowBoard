<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Board;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $boards = Board::where('user_id', $user->id)
            ->orWhereHas('members', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->get();

        return view('analytics.index', compact('boards'));
    }

    public function data(Request $request)
    {
        $user = Auth::user();
        
        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(15)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        // Base query for tasks accessible to user
        $tasksQuery = Task::whereHas('column.board', function ($boardQuery) use ($user, $request) {
            $boardQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('members', function ($q2) use ($user) {
                      $q2->where('users.id', $user->id);
                  });
            });

            if ($request->filled('board_id')) {
                $boardQuery->where('id', $request->input('board_id'));
            }
        });

        // 1. Tasks Created vs Completed Over Time
        $createdData = [];
        $completedData = [];
        
        $current = $from->copy();
        while($current <= $to) {
            $dateString = $current->format('Y-m-d');
            $createdData[$dateString] = 0;
            $completedData[$dateString] = 0;
            $current->addDay();
        }

        $rangeTasks = (clone $tasksQuery)->whereBetween('created_at', [$from, $to])->get();
        foreach ($rangeTasks as $task) {
            $dateString = $task->created_at->format('Y-m-d');
            if (isset($createdData[$dateString])) {
                $createdData[$dateString]++;
            }
        }

        $completedRangeTasks = (clone $tasksQuery)->whereNotNull('completed_at')->whereBetween('completed_at', [$from, $to])->get();
        foreach ($completedRangeTasks as $task) {
            $dateString = Carbon::parse($task->completed_at)->format('Y-m-d');
            if (isset($completedData[$dateString])) {
                $completedData[$dateString]++;
            }
        }

        $labels = array_keys($createdData);
        $createdValues = array_values($createdData);
        $completedValues = array_values($completedData);

        // 2. Task Status Distribution
        $allTasks = (clone $tasksQuery)->with('column')->get();
        $statusDistribution = [];
        foreach ($allTasks as $task) {
            $colName = $task->column->name ?? 'Unknown';
            $statusDistribution[$colName] = ($statusDistribution[$colName] ?? 0) + 1;
        }

        // 3. Average Completion Time
        $totalHours = 0;
        $completedCount = $completedRangeTasks->count();
        $avgCompletionHours = 0;

        foreach ($completedRangeTasks as $task) {
            $created = Carbon::parse($task->created_at);
            $completed = Carbon::parse($task->completed_at);
            $totalHours += $completed->diffInHours($created);
        }

        if ($completedCount > 0) {
            $avgCompletionHours = round($totalHours / $completedCount, 1);
        }
        $avgCompletionDays = round($avgCompletionHours / 24, 1);

        // 4. Completion Time Trends 
        $trendData = [];
        foreach ($labels as $dateStr) {
            $trendData[$dateStr] = ['total' => 0, 'count' => 0];
        }

        foreach ($completedRangeTasks as $task) {
            $dateString = Carbon::parse($task->completed_at)->format('Y-m-d');
            if (isset($trendData[$dateString])) {
                $hours = Carbon::parse($task->completed_at)->diffInHours($task->created_at);
                $trendData[$dateString]['total'] += $hours;
                $trendData[$dateString]['count']++;
            }
        }

        $trendValues = [];
        foreach ($labels as $dateStr) {
            $item = $trendData[$dateStr];
            $trendValues[] = $item['count'] > 0 ? round($item['total'] / $item['count'], 1) : 0;
        }

        return response()->json([
            'success' => true,
            'avg_completion_hours' => $avgCompletionHours,
            'avg_completion_days' => $avgCompletionDays,
            'total_completed_in_range' => $completedCount,
            'created_vs_completed' => [
                'labels' => $labels,
                'created' => $createdValues,
                'completed' => $completedValues,
            ],
            'status_distribution' => [
                'labels' => array_keys($statusDistribution),
                'data' => array_values($statusDistribution),
            ],
            'completion_trend' => [
                'labels' => $labels,
                'data' => $trendValues,
            ]
        ]);
    }
}
