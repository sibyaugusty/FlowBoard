<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FileAttachmentController;

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [BoardController::class, 'index'])->name('dashboard');

    // Boards
    Route::post('/api/boards', [BoardController::class, 'store']);
    Route::get('/boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::put('/api/boards/{board}', [BoardController::class, 'update']);
    Route::delete('/api/boards/{board}', [BoardController::class, 'destroy']);
    Route::post('/api/boards/{board}/members', [BoardController::class, 'addMember']);
    Route::delete('/api/boards/{board}/members/{user}', [BoardController::class, 'removeMember']);

    // Columns
    Route::post('/api/boards/{board}/columns', [ColumnController::class, 'store']);
    Route::put('/api/columns/{column}', [ColumnController::class, 'update']);
    Route::delete('/api/columns/{column}', [ColumnController::class, 'destroy']);
    Route::post('/api/boards/{board}/columns/reorder', [ColumnController::class, 'reorder']);

    // Tasks
    Route::post('/api/tasks', [TaskController::class, 'store']);
    Route::get('/api/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/api/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/api/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('/api/tasks/{task}/move', [TaskController::class, 'move']);
    Route::get('/api/tasks/search', [TaskController::class, 'search']);
    Route::get('/api/tasks/filter', [TaskController::class, 'filter']);

    // Comments
    Route::post('/api/tasks/{task}/comments', [CommentController::class, 'store']);
    Route::delete('/api/comments/{comment}', [CommentController::class, 'destroy']);

    // Activities
    Route::get('/api/boards/{board}/activities', [ActivityController::class, 'index']);

    // Notifications
    Route::get('/api/notifications', [NotificationController::class, 'index']);
    Route::post('/api/notifications/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/api/notifications/{notification}/mark-read', [NotificationController::class, 'markOneAsRead']);

    // File Attachments
    Route::post('/api/tasks/{task}/attachments', [FileAttachmentController::class, 'store']);
    Route::delete('/api/attachments/{attachment}', [FileAttachmentController::class, 'destroy']);
    Route::get('/api/attachments/{attachment}/download', [FileAttachmentController::class, 'download']);
});
