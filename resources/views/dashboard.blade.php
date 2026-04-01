@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="fb-dashboard">
    <div class="container-fluid px-4">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="fb-flash fb-flash-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="fb-flash fb-flash-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- Welcome Banner --}}
        <div class="fb-welcome-banner">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h2>
                        @php
                            $hour = now()->format('H');
                            if ($hour < 12) $greeting = 'Good Morning';
                            elseif ($hour < 17) $greeting = 'Good Afternoon';
                            else $greeting = 'Good Evening';
                        @endphp
                        {{ $greeting }}, {{ Auth::user()->name }}! 👋
                    </h2>
                    <p>Here's an overview of your boards and projects.</p>
                </div>
                <div class="fb-welcome-icon d-none d-md-block">
                    <i class="bi bi-kanban"></i>
                </div>
            </div>
        </div>

        {{-- Boards Section --}}
        <div class="fb-section-header">
            <h4><i class="bi bi-collection me-2"></i>Your Boards</h4>
            <button class="btn fb-btn-primary btn-sm" id="createBoardBtn" data-bs-toggle="modal" data-bs-target="#createBoardModal">
                <i class="bi bi-plus-lg me-1"></i> New Board
            </button>
        </div>

        @if ($boards->count() > 0)
            <div class="fb-boards-grid">
                {{-- Existing Boards --}}
                @foreach ($boards as $board)
                    <a href="{{ route('boards.show', $board) }}" class="fb-board-card">
                        @if ($board->color)
                            <div class="fb-board-color-bar" style="background-color: {{ $board->color }};"></div>
                        @else
                            <div class="fb-board-color-bar" style="background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
                        @endif
                        <h5 class="fb-board-card-title">{{ $board->name }}</h5>
                        <p class="fb-board-card-desc">{{ $board->description ?: 'No description' }}</p>
                        <div class="fb-board-card-meta">
                            <span><i class="bi bi-columns-gap"></i> {{ $board->columns->count() }} columns</span>
                            <span><i class="bi bi-card-checklist"></i> {{ $board->columns->sum(fn($c) => $c->tasks->count()) }} tasks</span>
                            <span><i class="bi bi-people"></i> {{ $board->members->count() + 1 }} members</span>
                        </div>
                    </a>
                @endforeach

                {{-- Create Card --}}
                <div class="fb-create-board-card" data-bs-toggle="modal" data-bs-target="#createBoardModal">
                    <i class="bi bi-plus-circle"></i>
                    <span>Create New Board</span>
                </div>
            </div>
        @else
            {{-- Empty State --}}
            <div class="fb-empty-state">
                <i class="bi bi-kanban d-block"></i>
                <h5>No boards yet</h5>
                <p>Create your first board to start organizing tasks and collaborating with your team.</p>
                <button class="btn fb-btn-primary" data-bs-toggle="modal" data-bs-target="#createBoardModal">
                    <i class="bi bi-plus-lg me-2"></i>Create Your First Board
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Create Board Modal --}}
<div class="modal fade" id="createBoardModal" tabindex="-1" aria-labelledby="createBoardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--fb-radius-xl); border: none; box-shadow: var(--fb-shadow-xl);">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold" id="createBoardModalLabel">
                    <i class="bi bi-kanban me-2 text-primary"></i>Create New Board
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <form id="createBoardForm">
                    <div class="mb-3">
                        <label for="boardName" class="form-label" style="font-size: 0.8125rem; font-weight: 500;">Board Name</label>
                        <input type="text" class="form-control fb-input" id="boardName" name="name" placeholder="e.g. Sprint Planning" required style="padding-left: 0.875rem !important;">
                    </div>
                    <div class="mb-3">
                        <label for="boardDescription" class="form-label" style="font-size: 0.8125rem; font-weight: 500;">Description <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control fb-input" id="boardDescription" name="description" rows="3" placeholder="What is this board about?" style="padding-left: 0.875rem !important; resize: none;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="boardColor" class="form-label" style="font-size: 0.8125rem; font-weight: 500;">Board Color</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="color" class="form-control form-control-color" id="boardColor" name="color" value="#6366f1" style="width: 42px; height: 38px; border-radius: var(--fb-radius); cursor: pointer;">
                            <div class="d-flex gap-1 align-items-center flex-wrap">
                                @foreach(['#6366f1','#8b5cf6','#ec4899','#ef4444','#f59e0b','#10b981','#3b82f6','#06b6d4'] as $color)
                                    <button type="button" class="btn p-0 border-0" onclick="document.getElementById('boardColor').value='{{ $color }}'" style="width: 28px; height: 28px; border-radius: 50%; background-color: {{ $color }}; cursor: pointer; transition: transform 0.15s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"></button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn fb-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn fb-btn-primary" id="submitCreateBoard">
                    <i class="bi bi-plus-lg me-1"></i>Create Board
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
