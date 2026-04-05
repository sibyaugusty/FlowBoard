@extends('layouts.app')

@section('title', $board->name)

@section('content')
{{-- Board Header --}}
<div class="fb-board-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2">
                @if($board->color)
                    <div style="width:14px; height:14px; border-radius:4px; background:{{ $board->color }};"></div>
                @endif
                <h1 class="fb-board-title">{{ $board->name }}</h1>
            </div>
            @if($board->description)
                <p class="fb-board-desc mt-1">{{ $board->description }}</p>
            @endif
        </div>
        <div class="d-flex align-items-center gap-3">
            {{-- Members --}}
            <div class="fb-board-members">
                @foreach($boardMembers->take(5) as $member)
                    <div class="fb-avatar-sm" title="{{ $member->name }}">{{ $member->initials }}</div>
                @endforeach
                @if($boardMembers->count() > 5)
                    <div class="fb-avatar-sm" style="background:var(--fb-gray-300);color:var(--fb-gray-600);">+{{ $boardMembers->count() - 5 }}</div>
                @endif
            </div>

            {{-- Actions --}}
            <button class="btn fb-btn-primary btn-sm" id="createTaskBtn" data-bs-toggle="modal" data-bs-target="#createTaskModal" title="Create Task">
                <i class="bi bi-plus-lg me-1"></i>Create Task
            </button>
            <button class="btn fb-btn-secondary btn-sm" id="addColumnBtn" title="Create Column">
                <i class="bi bi-layout-three-columns me-1"></i>Create Column
            </button>
            <button class="btn fb-btn-secondary btn-sm" id="addMemberBtn" title="Add Member">
                <i class="bi bi-person-plus me-1"></i>Add
            </button>
            <button class="btn fb-btn-secondary btn-sm" id="activitySidebarBtn" title="Activity">
                <i class="bi bi-clock-history me-1"></i>Activity
            </button>
            <div class="dropdown">
                <button class="btn fb-btn-secondary btn-sm" data-bs-toggle="dropdown">
                    <i class="bi bi-gear"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end fb-dropdown">
                    <li>
                        <a class="dropdown-item small" href="{{ route('dashboard') }}">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item small text-danger" id="deleteBoardBtn">
                            <i class="bi bi-trash me-2"></i>Delete Board
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Board Columns --}}
<div class="fb-board-content" id="boardColumnsContainer" data-board-id="{{ $board->id }}">
    @foreach($board->columns as $column)
        <div class="fb-column" data-column-id="{{ $column->id }}">
            <div class="fb-column-header">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fb-column-title mb-0">{{ $column->name }}</h6>
                    <span class="fb-column-count">{{ $column->tasks->count() }}</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm p-0 text-muted" data-bs-toggle="dropdown" style="line-height:1;">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end fb-dropdown" style="min-width:140px;">
                        <li><button class="dropdown-item small fb-column-rename"><i class="bi bi-pencil me-2"></i>Rename</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item small text-danger fb-column-delete"><i class="bi bi-trash me-2"></i>Delete</button></li>
                    </ul>
                </div>
            </div>
            <div class="fb-task-list">
                @foreach($column->tasks->sortBy('position') as $task)
                    <div class="fb-task-card" data-task-id="{{ $task->id }}">
                        <div class="fb-task-priority-bar" style="background:{{ $task->priority_color }};"></div>
                        <div class="fb-task-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fb-task-title">{{ $task->title }}</span>
                                <button class="btn btn-sm p-0 text-muted fb-task-delete" title="Delete" style="line-height:1; opacity:0; transition:opacity 0.15s;">
                                    <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>
                                </button>
                            </div>
                            <div class="fb-task-meta">
                                @if($task->due_date)
                                    <span class="fb-task-due {{ $task->is_overdue ? 'overdue' : '' }}">
                                        <i class="bi bi-calendar3 me-1"></i>{{ $task->due_date->format('M j') }}
                                    </span>
                                @endif
                                @if($task->comments->count())
                                    <span><i class="bi bi-chat-dots me-1"></i>{{ $task->comments->count() }}</span>
                                @endif
                                @if($task->attachments->count())
                                    <span><i class="bi bi-paperclip me-1"></i>{{ $task->attachments->count() }}</span>
                                @endif
                            </div>
                            @if($task->assignees->count())
                                <div class="fb-task-assignees">
                                    @foreach($task->assignees->take(3) as $assignee)
                                        <div class="fb-avatar-xs" title="{{ $assignee->name }}">{{ $assignee->initials }}</div>
                                    @endforeach
                                    @if($task->assignees->count() > 3)
                                        <div class="fb-avatar-xs fb-avatar-extra">+{{ $task->assignees->count() - 3 }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    @endforeach
</div>
{{-- Task Detail Modal --}}
<div class="modal fade" id="taskDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--fb-radius-xl); border: none; box-shadow: var(--fb-shadow-xl);">
            <div class="modal-header border-0 px-4 pt-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-card-checklist me-2 text-primary"></i>Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                {{-- Loaded dynamically --}}
            </div>
        </div>
    </div>
</div>

{{-- Create Task Modal --}}
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--fb-radius-xl); border: none; box-shadow: var(--fb-shadow-xl);">
            <div class="modal-header border-0 px-4 pt-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Create Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control fb-input" id="createTaskTitle" placeholder="Enter task title" style="padding-left:0.875rem!important;">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description (optional)</label>
                    <textarea class="form-control fb-input" id="createTaskDesc" rows="3" placeholder="Add details..." style="padding-left:0.875rem!important;resize:none;"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Target Column</label>
                        <select class="form-select fb-input" id="createTaskColumn" style="padding-left:0.875rem!important;">
                            <!-- Populated dynamically via JS -->
                        </select>
                    </div>
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select class="form-select fb-input" id="createTaskPriority" style="padding-left:0.875rem!important;">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                     <div class="col-md-6">
                        <label class="form-label small fw-semibold">Due Date (optional)</label>
                        <input type="date" class="form-control fb-input" id="createTaskDueDate" style="padding-left:0.875rem!important;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assignees (optional)</label>
                        <select class="form-select fb-input" id="createTaskAssignees" multiple style="padding-left:0.875rem!important; font-size:0.875rem; min-height:auto; height:38px;" title="Ctrl+click for multiple">
                            @foreach($boardMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn fb-btn-primary w-100" id="submitCreateTaskModalBtn">Create Task</button>
                    <button class="btn fb-btn-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Activity Sidebar --}}
<div class="fb-activity-sidebar" id="activitySidebar">
    <div class="fb-activity-sidebar-header">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Activity</h6>
        <button class="btn btn-sm p-0 text-muted" id="closeActivitySidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="fb-activity-sidebar-body" id="activityList">
        <p class="text-muted small text-center py-3">Loading...</p>
    </div>
</div>

{{-- Pass board members data to JavaScript --}}
@push('scripts')
<script>
    window._fbBoardMembers = @json($boardMembers->map(fn($m) => ['id' => $m->id, 'name' => $m->name])->values());
</script>
@endpush
@endsection
