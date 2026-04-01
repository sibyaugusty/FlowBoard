<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Board;
use App\Models\Column;
use App\Models\Task;
use App\Models\Comment;
use App\Models\Activity;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@flowboard.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $member = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@flowboard.test',
            'password' => 'password',
            'role' => 'member',
        ]);

        $member2 = User::create([
            'name' => 'Bob Wilson',
            'email' => 'bob@flowboard.test',
            'password' => 'password',
            'role' => 'member',
        ]);

        // Board 1: Product Development
        $board1 = Board::create([
            'name' => 'Product Development',
            'description' => 'Main product development board for sprint tracking',
            'user_id' => $admin->id,
            'color' => '#6366f1',
        ]);
        $board1->members()->attach([$member->id, $member2->id]);

        $col1 = Column::create(['board_id' => $board1->id, 'name' => 'Backlog', 'position' => 0]);
        $col2 = Column::create(['board_id' => $board1->id, 'name' => 'To Do', 'position' => 1]);
        $col3 = Column::create(['board_id' => $board1->id, 'name' => 'In Progress', 'position' => 2]);
        $col4 = Column::create(['board_id' => $board1->id, 'name' => 'Review', 'position' => 3]);
        $col5 = Column::create(['board_id' => $board1->id, 'name' => 'Done', 'position' => 4]);

        // Tasks for Board 1
        $task1 = Task::create([
            'column_id' => $col2->id, 'title' => 'Design new landing page',
            'description' => 'Create a modern landing page with hero section, features, and CTA',
            'due_date' => now()->addDays(5), 'priority' => 'high', 'position' => 0, 'created_by' => $admin->id,
        ]);
        $task1->assignees()->attach([$member->id]);

        $task2 = Task::create([
            'column_id' => $col3->id, 'title' => 'Implement user authentication',
            'description' => 'Set up login, registration, and password reset flows',
            'due_date' => now()->addDays(3), 'priority' => 'urgent', 'position' => 0, 'created_by' => $admin->id,
        ]);
        $task2->assignees()->attach([$member2->id]);

        $task3 = Task::create([
            'column_id' => $col3->id, 'title' => 'Set up CI/CD pipeline',
            'description' => 'Configure GitHub Actions for automated testing and deployment',
            'due_date' => now()->addDays(7), 'priority' => 'medium', 'position' => 1, 'created_by' => $member->id,
        ]);
        $task3->assignees()->attach([$admin->id, $member->id]);

        $task4 = Task::create([
            'column_id' => $col4->id, 'title' => 'API documentation',
            'description' => 'Write comprehensive API docs using Swagger/OpenAPI',
            'due_date' => now()->addDays(2), 'priority' => 'medium', 'position' => 0, 'created_by' => $member2->id,
        ]);

        $task5 = Task::create([
            'column_id' => $col5->id, 'title' => 'Database schema design',
            'description' => 'Design and implement the initial database schema',
            'due_date' => now()->subDays(1), 'priority' => 'high', 'position' => 0, 'created_by' => $admin->id,
        ]);
        $task5->assignees()->attach([$admin->id]);

        $task6 = Task::create([
            'column_id' => $col1->id, 'title' => 'Mobile responsive design',
            'description' => 'Ensure all pages work perfectly on mobile devices',
            'due_date' => now()->addDays(14), 'priority' => 'low', 'position' => 0, 'created_by' => $member->id,
        ]);

        $task7 = Task::create([
            'column_id' => $col1->id, 'title' => 'Performance optimization',
            'description' => 'Optimize page load times and reduce bundle size',
            'due_date' => now()->addDays(21), 'priority' => 'low', 'position' => 1, 'created_by' => $admin->id,
        ]);

        $task8 = Task::create([
            'column_id' => $col2->id, 'title' => 'Email notification system',
            'description' => 'Build email notification service for user events',
            'due_date' => now()->addDays(10), 'priority' => 'medium', 'position' => 1, 'created_by' => $admin->id,
        ]);
        $task8->assignees()->attach([$member->id, $member2->id]);

        // Comments
        Comment::create(['task_id' => $task2->id, 'user_id' => $admin->id, 'body' => 'Make sure to use bcrypt for password hashing.']);
        Comment::create(['task_id' => $task2->id, 'user_id' => $member2->id, 'body' => 'Already implemented. Working on the forgot password flow now.']);
        Comment::create(['task_id' => $task1->id, 'user_id' => $member->id, 'body' => 'I\'ve started with the wireframes. Will share by EOD.']);
        Comment::create(['task_id' => $task3->id, 'user_id' => $member->id, 'body' => 'Should we use GitHub Actions or GitLab CI?']);
        Comment::create(['task_id' => $task3->id, 'user_id' => $admin->id, 'body' => 'Let\'s go with GitHub Actions since our repo is on GitHub.']);

        // Activities
        Activity::log($board1->id, $admin->id, 'board_created', 'created board "Product Development"', $board1);
        Activity::log($board1->id, $admin->id, 'task_created', 'created task "Design new landing page"', $task1);
        Activity::log($board1->id, $admin->id, 'task_created', 'created task "Implement user authentication"', $task2);
        Activity::log($board1->id, $member->id, 'task_created', 'created task "Set up CI/CD pipeline"', $task3);
        Activity::log($board1->id, $admin->id, 'task_moved', 'moved "Implement user authentication" to "In Progress"', $task2);

        // Board 2: Marketing Campaign
        $board2 = Board::create([
            'name' => 'Marketing Campaign',
            'description' => 'Q1 2024 marketing campaign planning and execution',
            'user_id' => $member->id,
            'color' => '#ec4899',
        ]);
        $board2->members()->attach([$admin->id]);

        $mcol1 = Column::create(['board_id' => $board2->id, 'name' => 'Ideas', 'position' => 0]);
        $mcol2 = Column::create(['board_id' => $board2->id, 'name' => 'Planning', 'position' => 1]);
        $mcol3 = Column::create(['board_id' => $board2->id, 'name' => 'Execution', 'position' => 2]);
        $mcol4 = Column::create(['board_id' => $board2->id, 'name' => 'Completed', 'position' => 3]);

        Task::create([
            'column_id' => $mcol1->id, 'title' => 'Social media strategy',
            'description' => 'Plan social media content for Q1', 'due_date' => now()->addDays(10),
            'priority' => 'high', 'position' => 0, 'created_by' => $member->id,
        ]);

        Task::create([
            'column_id' => $mcol2->id, 'title' => 'Blog post series',
            'description' => 'Write 4 blog posts about product features', 'due_date' => now()->addDays(15),
            'priority' => 'medium', 'position' => 0, 'created_by' => $member->id,
        ]);

        Task::create([
            'column_id' => $mcol3->id, 'title' => 'Email newsletter design',
            'description' => 'Design monthly newsletter template', 'due_date' => now()->addDays(3),
            'priority' => 'high', 'position' => 0, 'created_by' => $admin->id,
        ]);

        Activity::log($board2->id, $member->id, 'board_created', 'created board "Marketing Campaign"', $board2);
    }
}
