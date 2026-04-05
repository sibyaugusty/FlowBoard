/**
 * ============================================================
 * FlowBoard — Main JavaScript
 * jQuery-based AJAX handlers for dashboard and board pages
 * ============================================================
 */

$(document).ready(function () {

    // ── Base URL & CSRF Token Setup ─────────────────────────
    const BASE_URL = ($('meta[name="app-url"]').attr('content') || '').replace(/\/+$/, '');
    const CSRF = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    });


    // ============================================================
    // TOAST HELPER
    // ============================================================
    window.fbToast = function (message, type = 'success') {
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill',
            warning: 'bi-exclamation-circle-fill'
        };
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6',
            warning: '#f59e0b'
        };
        const id = 'toast-' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center border-0 show" role="alert"
                 style="background:white; border-left:4px solid ${colors[type]} !important; box-shadow:0 10px 25px rgba(0,0,0,0.12); border-radius:0.625rem; min-width:320px; margin-bottom:0.5rem;">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2" style="font-size:0.875rem;">
                        <i class="bi ${icons[type]}" style="color:${colors[type]}; font-size:1.125rem;"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        $('#toastContainer').append(html);
        setTimeout(() => $('#' + id).fadeOut(300, function () { $(this).remove(); }), 4000);
    };


    // ============================================================
    // DASHBOARD — CREATE BOARD
    // ============================================================
    $('#submitCreateBoard').on('click', function () {
        const btn = $(this);
        const name = $('#boardName').val().trim();
        const description = $('#boardDescription').val().trim();
        const color = $('#boardColor').val();

        if (!name) {
            $('#boardName').addClass('is-invalid').focus();
            return;
        }

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Creating...');

        $.ajax({
            url: BASE_URL + '/api/boards',
            method: 'POST',
            data: { name, description, color },
            success: function (res) {
                fbToast('Board "' + res.board.name + '" created!', 'success');
                $('#createBoardModal').modal('hide');
                // Redirect to the new board
                window.location.href = BASE_URL + '/boards/' + res.board.id;
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to create board';
                fbToast(msg, 'error');
                btn.prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i>Create Board');
            }
        });
    });

    // Reset create board modal on close
    $('#createBoardModal').on('hidden.bs.modal', function () {
        $('#createBoardForm')[0]?.reset();
        $('#boardName').removeClass('is-invalid');
        $('#submitCreateBoard').prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i>Create Board');
    });

    // Remove invalid state on input
    $('#boardName').on('input', function () {
        $(this).removeClass('is-invalid');
    });


    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    function loadNotifications() {
        $.get(BASE_URL + '/api/notifications', function (res) {
            const badge = $('#notifBadge');
            if (res.unread_count > 0) {
                badge.text(res.unread_count).removeClass('d-none');
            } else {
                badge.addClass('d-none');
            }

            const list = $('#notificationList');
            if (res.notifications.length === 0) {
                list.html(`
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bell-slash fs-4"></i>
                        <p class="mb-0 mt-1 small">No notifications</p>
                    </div>`);
                return;
            }

            let html = '';
            res.notifications.forEach(n => {
                const isRead = n.read_at !== null;
                const timeAgo = formatTimeAgo(n.created_at);
                html += `
                    <div class="px-3 py-2 border-bottom ${!isRead ? 'bg-light' : ''}" style="cursor:pointer; font-size:0.8125rem;"
                         data-notif-id="${n.id}" data-board-id="${n.data?.board_id || ''}">
                        <div class="d-flex justify-content-between">
                            <span>${n.data?.message || 'Notification'}</span>
                            ${!isRead ? '<span class="ms-2 flex-shrink-0"><i class="bi bi-circle-fill text-primary" style="font-size:0.5rem;"></i></span>' : ''}
                        </div>
                        <small class="text-muted">${timeAgo}</small>
                    </div>`;
            });
            list.html(html);
        });
    }

    // Poll notifications every 30 seconds
    if ($('#notificationDropdown').length) {
        loadNotifications();
        setInterval(loadNotifications, 30000);
    }

    // Mark all as read
    $('#markAllRead').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $.post(BASE_URL + '/api/notifications/mark-read', function () {
            loadNotifications();
        });
    });

    // Click notification
    $(document).on('click', '[data-notif-id]', function () {
        const id = $(this).data('notif-id');
        const boardId = $(this).data('board-id');
        $.post(BASE_URL + '/api/notifications/' + id + '/mark-read', function () {
            if (boardId) window.location.href = BASE_URL + '/boards/' + boardId;
        });
    });


    // ============================================================
    // BOARD PAGE — KANBAN LOGIC
    // ============================================================
    const $boardContainer = $('#boardColumnsContainer');
    const boardId = $boardContainer.data('board-id');

    if ($boardContainer.length && boardId) {
        initKanban();
    }

    function initKanban() {

        // ── Drag & Drop: Tasks ──────────────────────────────
        function initSortable() {
            $('.fb-task-list').sortable({
                connectWith: '.fb-task-list',
                items: '.fb-task-card',
                placeholder: 'fb-sortable-placeholder',
                tolerance: 'pointer',
                cursor: 'grabbing',
                distance: 3,
                scrollSensitivity: 60,
                scrollSpeed: 14,

                // Clone-based helper: starts at the exact grab point,
                // avoids position jump, and isn't clipped by column overflow.
                helper: function (e, item) {
                    const $clone = item.clone()
                        .addClass('fb-drag-helper-lifted')
                        .css({
                            width: item.outerWidth(),
                            height: item.outerHeight(),
                            transition: 'none',
                            'will-change': 'transform',
                            'pointer-events': 'none'
                        });
                    return $clone;
                },
                appendTo: document.body,
                forceHelperSize: true,
                forcePlaceholderSize: true,

                start: function (event, ui) {
                    // Store the source column name for the toast
                    ui.item.data('source-column-name',
                        ui.item.closest('.fb-column').find('.fb-column-title').text().trim()
                    );

                    // Collapse the original item so only the placeholder remains
                    ui.item.addClass('fb-task-dragging');

                    // Size the placeholder to match the card
                    ui.placeholder.css({
                        height: ui.helper.outerHeight(),
                        visibility: 'visible'
                    });
                },

                stop: function (event, ui) {
                    // Reveal the original item
                    ui.item.removeClass('fb-task-dragging');

                    // Inline styles jQuery UI leaves on the item
                    ui.item.removeAttr('style');

                    // Play the spring settle animation
                    ui.item.addClass('fb-task-settling');
                    setTimeout(function () {
                        ui.item.removeClass('fb-task-settling');
                    }, 320);
                },

                update: function (event, ui) {
                    // Only fire on the receiving list
                    if (this === ui.item.parent()[0]) {
                        const taskId = ui.item.data('task-id');
                        const newColumnId = ui.item.closest('.fb-column').data('column-id');
                        const newPosition = ui.item.index();
                        const sourceColumnName = ui.item.data('source-column-name');

                        $.ajax({
                            url: BASE_URL + '/api/tasks/' + taskId + '/move',
                            method: 'POST',
                            data: { column_id: newColumnId, position: newPosition },
                            success: function (res) {
                                updateColumnCounts();

                                // Show descriptive toast
                                if (res.old_column && res.new_column && res.old_column !== res.new_column) {
                                    fbToast(
                                        `Task moved from <strong>${escapeHtml(res.old_column)}</strong> to <strong>${escapeHtml(res.new_column)}</strong>`,
                                        'info'
                                    );
                                } else {
                                    fbToast(
                                        `Task reordered in <strong>${escapeHtml(res.new_column || sourceColumnName)}</strong>`,
                                        'success'
                                    );
                                }
                            },
                            error: function () {
                                fbToast('Failed to move task', 'error');
                                location.reload();
                            }
                        });
                    }
                }
            }).disableSelection();
        }

        initSortable();

        // ── Update Column Task Counts ───────────────────────
        function updateColumnCounts() {
            $('.fb-column').each(function () {
                const count = $(this).find('.fb-task-card').length;
                $(this).find('.fb-column-count').text(count);
            });
        }


        // ── Add Column ──────────────────────────────────────
        $('#addColumnBtn').on('click', function () {
            const name = prompt('Column name:');
            if (!name || !name.trim()) return;

            $.ajax({
                url: BASE_URL + '/api/boards/' + boardId + '/columns',
                method: 'POST',
                data: { name: name.trim() },
                success: function (res) {
                    const col = res.column;
                    const html = buildColumnHtml(col.id, col.name, []);
                    $(html).appendTo('#boardColumnsContainer');
                    initSortable();
                    fbToast('Column "' + col.name + '" added', 'success');
                },
                error: function () {
                    fbToast('Failed to add column', 'error');
                }
            });
        });

        // ── Rename Column (double-click) ────────────────────
        $(document).on('dblclick', '.fb-column-title', function () {
            const $title = $(this);
            const columnId = $title.closest('.fb-column').data('column-id');
            const currentName = $title.text().trim();
            const newName = prompt('Rename column:', currentName);
            if (!newName || newName.trim() === currentName) return;

            $.ajax({
                url: BASE_URL + '/api/columns/' + columnId,
                method: 'PUT',
                data: { name: newName.trim() },
                success: function () {
                    $title.text(newName.trim());
                    fbToast('Column renamed', 'success');
                },
                error: function () {
                    fbToast('Failed to rename column', 'error');
                }
            });
        });

        // ── Delete Column ───────────────────────────────────
        $(document).on('click', '.fb-column-delete', function () {
            const $col = $(this).closest('.fb-column');
            const columnId = $col.data('column-id');
            const name = $col.find('.fb-column-title').text().trim();
            const taskCount = $col.find('.fb-task-card').length;

            const msg = taskCount > 0
                ? `Delete column "${name}"? This will remove ${taskCount} task(s)!`
                : `Delete column "${name}"?`;

            if (!confirm(msg)) return;

            $.ajax({
                url: BASE_URL + '/api/columns/' + columnId,
                method: 'DELETE',
                success: function () {
                    $col.fadeOut(300, function () { $(this).remove(); });
                    fbToast('Column deleted', 'success');
                },
                error: function () {
                    fbToast('Failed to delete column', 'error');
                }
            });
        });


        // ── Create Task (Modal) ─────────────────────────────
        const $createTaskModal = $('#createTaskModal');
        
        $createTaskModal.on('show.bs.modal', function () {
            // Reset form
            $('#createTaskTitle').val('').removeClass('is-invalid');
            $('#createTaskDesc').val('');
            $('#createTaskPriority').val('medium');
            $('#createTaskDueDate').val('');
            $('#createTaskAssignees').val([]);
            
            // Populate Target Column dropdown
            const $columnSelect = $('#createTaskColumn');
            $columnSelect.empty();
            
            $('.fb-column').each(function () {
                const colId = $(this).data('column-id');
                const colName = $(this).find('.fb-column-title').text().trim();
                $columnSelect.append(`<option value="${colId}">${escapeHtml(colName)}</option>`);
            });
        });

        $('#createTaskTitle').on('input', function() {
            $(this).removeClass('is-invalid');
        });

        $('#submitCreateTaskModalBtn').on('click', function () {
            const title = $('#createTaskTitle').val().trim();
            const description = $('#createTaskDesc').val().trim();
            const columnId = $('#createTaskColumn').val();
            const priority = $('#createTaskPriority').val() || 'medium';
            const dueDate = $('#createTaskDueDate').val() || null;
            const assignees = $('#createTaskAssignees').val() || [];

            if (!title) {
                $('#createTaskTitle').addClass('is-invalid').focus();
                return;
            }

            if (!columnId) {
                fbToast('Please create a column first', 'error');
                return;
            }

            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Creating...');

            const postData = { 
                column_id: columnId, 
                title: title, 
                description: description,
                priority: priority 
            };
            if (dueDate) postData.due_date = dueDate;
            if (assignees.length) postData.assignees = assignees;

            $.ajax({
                url: BASE_URL + '/api/tasks',
                method: 'POST',
                data: postData,
                success: function (res) {
                    const task = res.task;
                    const taskHtml = buildTaskCardHtml(task);
                    
                    // Append task to the selected column
                    $(`.fb-column[data-column-id="${columnId}"]`).find('.fb-task-list').append(taskHtml);
                    updateColumnCounts();
                    
                    fbToast('Task "' + escapeHtml(task.title) + '" created', 'success');
                    btn.prop('disabled', false).html(originalText);
                    $createTaskModal.modal('hide');
                },
                error: function () {
                    fbToast('Failed to create task', 'error');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // ── Task Detail Modal ───────────────────────────────
        $(document).on('click', '.fb-task-card', function (e) {
            if ($(e.target).closest('.fb-task-delete').length) return;
            // Don't open modal if we just finished dragging
            if ($(this).hasClass('fb-task-dragging')) return;
            const taskId = $(this).data('task-id');
            openTaskDetail(taskId);
        });

        function openTaskDetail(taskId) {
            const $modal = $('#taskDetailModal');
            $modal.find('.modal-body').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading task...</p>
                </div>`);
            $modal.modal('show');

            $.get(BASE_URL + '/api/tasks/' + taskId, function (res) {
                const task = res.task;
                renderTaskDetail(task);
            }).fail(function () {
                $modal.find('.modal-body').html('<div class="text-center text-danger py-4">Failed to load task</div>');
            });
        }

        function renderTaskDetail(task) {
            const priorityColors = { urgent: '#ef4444', high: '#f97316', medium: '#eab308', low: '#22c55e' };
            const priorityColor = priorityColors[task.priority] || '#6b7280';
            const assigneeHtml = (task.assignees || []).map(a =>
                `<span class="fb-badge fb-badge-primary me-1">${a.name}</span>`
            ).join('') || '<span class="text-muted small">No assignees</span>';

            const commentsHtml = (task.comments || []).map(c => `
                <div class="fb-comment mb-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="fb-avatar-sm" style="width:28px;height:28px;font-size:0.6rem;">${getInitials(c.user?.name || 'U')}</div>
                        <strong style="font-size:0.8125rem;">${c.user?.name || 'User'}</strong>
                        <small class="text-muted">${formatTimeAgo(c.created_at)}</small>
                    </div>
                    <div class="ms-5 small">${escapeHtml(c.body)}</div>
                </div>
            `).join('') || '<p class="text-muted small">No comments yet</p>';

            const attachmentsHtml = (task.attachments || []).map(a => `
                <div class="d-flex align-items-center gap-2 mb-2 small">
                    <i class="bi bi-paperclip"></i>
                    <a href="${BASE_URL}/api/attachments/${a.id}/download" class="fb-link">${escapeHtml(a.filename)}</a>
                    <span class="text-muted">(${formatFileSize(a.size)})</span>
                </div>
            `).join('') || '<p class="text-muted small">No attachments</p>';

            const dueText = task.due_date ? new Date(task.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Not set';
            const isOverdue = task.due_date && new Date(task.due_date) < new Date();

            const html = `
                <div class="fb-task-detail" data-task-id="${task.id}">
                    <!-- Header -->
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-1" id="taskDetailTitle" contenteditable="false" style="outline:none;">${escapeHtml(task.title)}</h5>
                            <span class="fb-badge" style="background:${priorityColor}15; color:${priorityColor}; border:1px solid ${priorityColor}30;">
                                <i class="bi bi-flag-fill me-1" style="font-size:0.6rem;"></i>${task.priority}
                            </span>
                            <span class="ms-2 small text-muted">in ${escapeHtml(task.column?.name || '')}</span>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" id="editTaskBtn" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" id="deleteTaskBtn" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>

                    <!-- Info Grid -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold mb-1"><i class="bi bi-people me-1"></i>Assignees</label>
                            <div>${assigneeHtml}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold mb-1"><i class="bi bi-calendar me-1"></i>Due Date</label>
                            <div class="small ${isOverdue ? 'text-danger fw-semibold' : ''}">${isOverdue ? '<i class="bi bi-exclamation-triangle me-1"></i>' : ''}${dueText}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold mb-1"><i class="bi bi-person me-1"></i>Created by</label>
                            <div class="small">${escapeHtml(task.creator?.name || 'Unknown')}</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-semibold"><i class="bi bi-text-left me-1"></i>Description</label>
                        <div class="p-3 rounded" style="background:var(--fb-gray-50); min-height:60px; font-size:0.875rem;">
                            ${task.description ? escapeHtml(task.description) : '<span class="text-muted">No description</span>'}
                        </div>
                    </div>

                    <!-- Edit Form (hidden by default) -->
                    <div id="taskEditForm" class="d-none mb-4 p-3 rounded" style="background:var(--fb-gray-50); border:1px solid var(--fb-gray-200);">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Title</label>
                            <input type="text" class="form-control fb-input" id="editTaskTitle" value="${escapeHtml(task.title)}" style="padding-left:0.875rem!important;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Description</label>
                            <textarea class="form-control fb-input" id="editTaskDesc" rows="3" style="padding-left:0.875rem!important;resize:none;">${escapeHtml(task.description || '')}</textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Priority</label>
                                <select class="form-select fb-input" id="editTaskPriority" style="padding-left:0.875rem!important;">
                                    <option value="low" ${task.priority==='low'?'selected':''}>Low</option>
                                    <option value="medium" ${task.priority==='medium'?'selected':''}>Medium</option>
                                    <option value="high" ${task.priority==='high'?'selected':''}>High</option>
                                    <option value="urgent" ${task.priority==='urgent'?'selected':''}>Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Due Date</label>
                                <input type="date" class="form-control fb-input" id="editTaskDueDate" value="${task.due_date || ''}" style="padding-left:0.875rem!important;">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn fb-btn-primary btn-sm" id="saveTaskEdit"><i class="bi bi-check-lg me-1"></i>Save</button>
                            <button class="btn fb-btn-secondary btn-sm" id="cancelTaskEdit">Cancel</button>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-semibold"><i class="bi bi-paperclip me-1"></i>Attachments</label>
                        <div id="attachmentsList">${attachmentsHtml}</div>
                        <div class="mt-2">
                            <input type="file" id="fileUploadInput" class="d-none">
                            <button class="btn fb-btn-secondary btn-sm" id="uploadFileBtn"><i class="bi bi-upload me-1"></i>Upload File</button>
                        </div>
                    </div>

                    <!-- Comments -->
                    <div>
                        <label class="form-label text-muted small fw-semibold"><i class="bi bi-chat-dots me-1"></i>Comments</label>
                        <div class="mb-3">
                            <div class="d-flex gap-2">
                                <input type="text" class="form-control fb-input" id="commentInput" placeholder="Write a comment..." style="padding-left:0.875rem!important;">
                                <button class="btn fb-btn-primary btn-sm px-3" id="postCommentBtn"><i class="bi bi-send"></i></button>
                            </div>
                        </div>
                        <div id="commentsList">${commentsHtml}</div>
                    </div>
                </div>`;

            $('#taskDetailModal .modal-body').html(html);

            // ── Edit Task ───────────────────────────────────
            $('#editTaskBtn').on('click', function () {
                $('#taskEditForm').toggleClass('d-none');
            });

            $('#cancelTaskEdit').on('click', function () {
                $('#taskEditForm').addClass('d-none');
            });

            $('#saveTaskEdit').on('click', function () {
                const btn = $(this);
                btn.prop('disabled', true);
                $.ajax({
                    url: BASE_URL + '/api/tasks/' + task.id,
                    method: 'PUT',
                    data: {
                        title: $('#editTaskTitle').val(),
                        description: $('#editTaskDesc').val(),
                        priority: $('#editTaskPriority').val(),
                        due_date: $('#editTaskDueDate').val() || null
                    },
                    success: function (res) {
                        fbToast('Task updated', 'success');
                        renderTaskDetail(res.task);
                        // Update card in board
                        const $card = $(`.fb-task-card[data-task-id="${task.id}"]`);
                        $card.find('.fb-task-title').text(res.task.title);
                        $card.replaceWith(buildTaskCardHtml(res.task));
                    },
                    error: function () {
                        fbToast('Failed to update task', 'error');
                        btn.prop('disabled', false);
                    }
                });
            });

            // ── Delete Task ─────────────────────────────────
            $('#deleteTaskBtn').on('click', function () {
                if (!confirm('Delete this task?')) return;
                $.ajax({
                    url: BASE_URL + '/api/tasks/' + task.id,
                    method: 'DELETE',
                    success: function () {
                        fbToast('Task deleted', 'success');
                        $('#taskDetailModal').modal('hide');
                        $(`.fb-task-card[data-task-id="${task.id}"]`).fadeOut(200, function () {
                            $(this).remove();
                            updateColumnCounts();
                        });
                    },
                    error: function () {
                        fbToast('Failed to delete task', 'error');
                    }
                });
            });

            // ── Post Comment ────────────────────────────────
            $('#postCommentBtn').on('click', postComment);
            $('#commentInput').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); postComment(); }
            });

            function postComment() {
                const body = $('#commentInput').val().trim();
                if (!body) return;
                $('#postCommentBtn').prop('disabled', true);

                $.ajax({
                    url: BASE_URL + '/api/tasks/' + task.id + '/comments',
                    method: 'POST',
                    data: { body },
                    success: function (res) {
                        const c = res.comment;
                        const commentHtml = `
                            <div class="fb-comment mb-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div class="fb-avatar-sm" style="width:28px;height:28px;font-size:0.6rem;">${getInitials(c.user?.name || 'U')}</div>
                                    <strong style="font-size:0.8125rem;">${c.user?.name || 'User'}</strong>
                                    <small class="text-muted">just now</small>
                                </div>
                                <div class="ms-5 small">${escapeHtml(c.body)}</div>
                            </div>`;
                        $('#commentsList').prepend(commentHtml);
                        $('#commentInput').val('');
                        $('#postCommentBtn').prop('disabled', false);
                    },
                    error: function () {
                        fbToast('Failed to post comment', 'error');
                        $('#postCommentBtn').prop('disabled', false);
                    }
                });
            }

            // ── Upload File ─────────────────────────────────
            $('#uploadFileBtn').on('click', function () { $('#fileUploadInput').click(); });
            $('#fileUploadInput').on('change', function () {
                const file = this.files[0];
                if (!file) return;

                const formData = new FormData();
                formData.append('file', file);

                $.ajax({
                    url: BASE_URL + '/api/tasks/' + task.id + '/attachments',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        const a = res.attachment;
                        const html = `
                            <div class="d-flex align-items-center gap-2 mb-2 small">
                                <i class="bi bi-paperclip"></i>
                                <a href="${BASE_URL}/api/attachments/${a.id}/download" class="fb-link">${escapeHtml(a.filename)}</a>
                                <span class="text-muted">(${formatFileSize(a.size)})</span>
                            </div>`;
                        $('#attachmentsList').prepend(html);
                        $('#fileUploadInput').val('');
                        fbToast('File uploaded', 'success');
                    },
                    error: function () {
                        fbToast('Failed to upload file', 'error');
                    }
                });
            });
        }

        // ── Delete Task (from card) ─────────────────────────
        $(document).on('click', '.fb-task-delete', function (e) {
            e.stopPropagation();
            const $card = $(this).closest('.fb-task-card');
            const taskId = $card.data('task-id');
            if (!confirm('Delete this task?')) return;

            $.ajax({
                url: BASE_URL + '/api/tasks/' + taskId,
                method: 'DELETE',
                success: function () {
                    $card.fadeOut(200, function () {
                        $(this).remove();
                        updateColumnCounts();
                    });
                    fbToast('Task deleted', 'success');
                },
                error: function () {
                    fbToast('Failed to delete task', 'error');
                }
            });
        });


        // ── Activity Sidebar ────────────────────────────────
        function loadActivities() {
            $.get(BASE_URL + '/api/boards/' + boardId + '/activities', function (res) {
                const $list = $('#activityList');
                if (!res.activities.length) {
                    $list.html('<p class="text-muted small text-center py-3">No activity yet</p>');
                    return;
                }
                let html = '';
                res.activities.forEach(a => {
                    html += `
                        <div class="fb-activity-item">
                            <div class="fb-activity-avatar">${getInitials(a.user?.name || 'U')}</div>
                            <div>
                                <div class="small"><strong>${a.user?.name || 'User'}</strong> ${escapeHtml(a.description)}</div>
                                <small class="text-muted">${formatTimeAgo(a.created_at)}</small>
                            </div>
                        </div>`;
                });
                $list.html(html);
            });
        }

        // Load activities on sidebar toggle
        $('#activitySidebarBtn').on('click', function () {
            $('#activitySidebar').toggleClass('open');
            if ($('#activitySidebar').hasClass('open')) {
                loadActivities();
            }
        });

        $('#closeActivitySidebar').on('click', function () {
            $('#activitySidebar').removeClass('open');
        });


        // ── Board Settings ──────────────────────────────────
        $('#deleteBoardBtn').on('click', function () {
            if (!confirm('Are you sure you want to delete this board? This action cannot be undone.')) return;
            $.ajax({
                url: BASE_URL + '/api/boards/' + boardId,
                method: 'DELETE',
                success: function () {
                    fbToast('Board deleted', 'success');
                    window.location.href = BASE_URL + '/dashboard';
                },
                error: function () {
                    fbToast('Failed to delete board', 'error');
                }
            });
        });

        // ── Add Member ──────────────────────────────────────
        $('#addMemberBtn').on('click', function () {
            const email = prompt('Enter member email:');
            if (!email || !email.trim()) return;

            $.ajax({
                url: BASE_URL + '/api/boards/' + boardId + '/members',
                method: 'POST',
                data: { email: email.trim() },
                success: function (res) {
                    fbToast(res.member.name + ' added to board', 'success');
                    location.reload();
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to add member';
                    fbToast(msg, 'error');
                }
            });
        });

    } // end initKanban


    // ============================================================
    // HTML BUILDERS
    // ============================================================
    window.buildColumnHtml = function (id, name, tasks) {
        let tasksHtml = '';
        (tasks || []).forEach(task => {
            tasksHtml += buildTaskCardHtml(task);
        });

        // Build assignee options from the board members data
        const assigneeOptions = buildAssigneeOptions();

        return `
            <div class="fb-column" data-column-id="${id}">
                <div class="fb-column-header">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="fb-column-title mb-0">${escapeHtml(name)}</h6>
                        <span class="fb-column-count">${(tasks || []).length}</span>
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
                    ${tasksHtml}
                </div>
                <div class="fb-add-task-form d-none">
                    <input type="text" class="form-control fb-input fb-new-task-input" placeholder="Task title..." style="padding-left:0.875rem!important; font-size:0.8125rem;">
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <select class="form-select fb-input fb-new-task-priority" style="padding-left:0.75rem!important; font-size:0.75rem;">
                                <option value="low">🟢 Low</option>
                                <option value="medium" selected>🟡 Medium</option>
                                <option value="high">🟠 High</option>
                                <option value="urgent">🔴 Urgent</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control fb-input fb-new-task-due" style="padding-left:0.75rem!important; font-size:0.75rem;" title="Due date">
                        </div>
                    </div>
                    <div class="mt-1">
                        <select class="form-select fb-input fb-new-task-assignees" multiple style="padding-left:0.75rem!important; font-size:0.75rem; min-height:auto; height:auto;" title="Assignees (Ctrl+click for multiple)">
                            ${assigneeOptions}
                        </select>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn fb-btn-primary btn-sm fb-save-task"><i class="bi bi-plus-lg me-1"></i>Add</button>
                        <button class="btn fb-btn-secondary btn-sm fb-cancel-task">Cancel</button>
                    </div>
                </div>
                <button class="fb-add-task-btn">
                    <i class="bi bi-plus me-1"></i> Add task
                </button>
            </div>`;
    };

    // Helper to build assignee option tags from board member elements in the page
    function buildAssigneeOptions() {
        let options = '';
        const memberData = window._fbBoardMembers || [];
        memberData.forEach(m => {
            options += `<option value="${m.id}">${escapeHtml(m.name)}</option>`;
        });
        return options;
    }

    window.buildTaskCardHtml = function (task) {
        const priorityColors = { urgent: '#ef4444', high: '#f97316', medium: '#eab308', low: '#22c55e' };
        const pc = priorityColors[task.priority] || '#6b7280';
        const assignees = (task.assignees || []).slice(0, 3).map(a =>
            `<div class="fb-avatar-xs" title="${escapeHtml(a.name)}">${getInitials(a.name)}</div>`
        ).join('');
        const extraCount = (task.assignees || []).length > 3 ? `<div class="fb-avatar-xs fb-avatar-extra">+${(task.assignees || []).length - 3}</div>` : '';

        let dueHtml = '';
        if (task.due_date) {
            const d = new Date(task.due_date);
            const isOverdue = d < new Date();
            dueHtml = `<span class="fb-task-due ${isOverdue ? 'overdue' : ''}"><i class="bi bi-calendar3 me-1"></i>${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>`;
        }

        const commentCount = task.comments?.length || 0;
        const attachCount = task.attachments?.length || 0;

        return `
            <div class="fb-task-card" data-task-id="${task.id}">
                <div class="fb-task-priority-bar" style="background:${pc};"></div>
                <div class="fb-task-content">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fb-task-title">${escapeHtml(task.title)}</span>
                        <button class="btn btn-sm p-0 text-muted fb-task-delete" title="Delete" style="line-height:1; opacity:0; transition:opacity 0.15s;">
                            <i class="bi bi-x-lg" style="font-size:0.7rem;"></i>
                        </button>
                    </div>
                    <div class="fb-task-meta">
                        ${dueHtml}
                        ${commentCount ? `<span><i class="bi bi-chat-dots me-1"></i>${commentCount}</span>` : ''}
                        ${attachCount ? `<span><i class="bi bi-paperclip me-1"></i>${attachCount}</span>` : ''}
                    </div>
                    ${assignees || extraCount ? `<div class="fb-task-assignees">${assignees}${extraCount}</div>` : ''}
                </div>
            </div>`;
    };


    // ============================================================
    // UTILITIES
    // ============================================================
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function getInitials(name) {
        if (!name) return 'U';
        return name.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() || '').join('');
    }

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return parseFloat((bytes / Math.pow(1024, i)).toFixed(1)) + ' ' + sizes[i];
    }

    // Column rename from dropdown
    $(document).on('click', '.fb-column-rename', function () {
        const $col = $(this).closest('.fb-column');
        $col.find('.fb-column-title').trigger('dblclick');
    });

    // ── Global Search ───────────────────────────────────────
    let searchTimeout;
    $('#globalSearch').on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(searchTimeout);
        if (q.length < 2) {
            $('#globalSearchResults').remove();
            return;
        }
        searchTimeout = setTimeout(function () {
            // If on a board page, search within board
            if (boardId) {
                $.get(BASE_URL + '/api/tasks/search', { q, board_id: boardId }, function (res) {
                    showSearchResults(res.tasks);
                });
            }
        }, 300);
    });

    function showSearchResults(tasks) {
        $('#globalSearchResults').remove();
        if (!tasks.length) return;
        let html = '<div id="globalSearchResults" class="fb-search-results">';
        tasks.forEach(t => {
            html += `<a href="${BASE_URL}/boards/${t.column?.board_id || ''}" class="fb-search-result-item" data-task-id="${t.id}">
                <span class="fw-medium">${escapeHtml(t.title)}</span>
                <small class="text-muted">${escapeHtml(t.column?.name || '')}</small>
            </a>`;
        });
        html += '</div>';
        $('.fb-nav-search').append(html);
    }

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.fb-nav-search').length) {
            $('#globalSearchResults').remove();
        }
    });

}); // end document.ready
