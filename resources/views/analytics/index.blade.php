@extends('layouts.app')

@section('title', 'Performance Analytics')

@push('styles')
<style>
    .analytics-header {
        background-color: var(--fb-white);
        border-bottom: 1px solid var(--fb-gray-200);
        padding: 1.5rem 0;
        margin-bottom: 2rem;
    }
    .chart-card {
        background: var(--fb-white);
        border-radius: var(--fb-radius-lg);
        box-shadow: var(--fb-shadow-sm);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid var(--fb-gray-200);
        height: 100%;
    }
    .chart-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--fb-gray-800);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .metric-card {
        background: linear-gradient(135deg, var(--fb-primary), #4f46e5);
        color: white;
        border-radius: var(--fb-radius-lg);
        padding: 2rem;
        text-align: center;
        box-shadow: var(--fb-shadow-md);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
    }
    .metric-value {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1;
        margin: 1rem 0;
    }
    .metric-label {
        font-size: 0.875rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .analytics-filter-bar {
        display: flex;
        align-items: flex-end;
        gap: 1rem;
    }
</style>
@endpush

@section('content')
<div class="analytics-header">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-graph-up text-primary me-2"></i>Performance Analytics</h1>
                <p class="text-muted mb-0 small">Track your task productivity and completion trends.</p>
            </div>
            
            <div class="analytics-filter-bar bg-light p-2 rounded-3 border align-items-end">
                <div>
                    <label class="form-label small fw-semibold text-muted mb-1 px-1">Project / Board</label>
                    <select id="boardFilter" class="form-select form-select-sm fb-input" style="min-width: 160px; height: 31px;">
                        <option value="">All Boards</option>
                        @foreach($boards as $board)
                            <option value="{{ $board->id }}">{{ $board->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold text-muted mb-1 px-1">From</label>
                    <input type="date" id="dateFrom" class="form-control form-control-sm fb-input" value="{{ \Carbon\Carbon::now()->subDays(15)->format('Y-m-d') }}">
                </div>
                <div>
                    <label class="form-label small fw-semibold text-muted mb-1 px-1">To</label>
                    <input type="date" id="dateTo" class="form-control form-control-sm fb-input" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>
                <button class="btn btn-sm fb-btn-primary" id="refreshAnalyticsBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <div id="analyticsLoader" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted fw-semibold">Crunching numbers...</p>
    </div>

    <div id="analyticsDashboard">
        <div class="row g-4 mb-4">
            <!-- Metric Card -->
            <div class="col-md-4">
                <div class="metric-card">
                    <i class="bi bi-stopwatch fs-1 opacity-75"></i>
                    <div class="metric-value" id="avgTimeValue">--</div>
                    <div class="metric-label" id="avgTimeLabel">Average Completion Days</div>
                    <div class="mt-3 small py-1 px-3 bg-white bg-opacity-25 rounded-pill">
                        Based on <span id="totalCompletedMetric">0</span> completed tasks
                    </div>
                </div>
            </div>

            <!-- Tasks Created vs Completed -->
            <div class="col-md-8">
                <div class="chart-card">
                    <h5 class="chart-card-title"><i class="bi bi-activity text-primary"></i>Tasks Overview</h5>
                    <div style="height: 300px;">
                        <canvas id="overviewChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Status Distribution -->
            <div class="col-md-4">
                <div class="chart-card">
                    <h5 class="chart-card-title"><i class="bi bi-pie-chart text-primary"></i>Task Placement</h5>
                    <div style="height: 300px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Completion Time Trend -->
            <div class="col-md-8">
                <div class="chart-card">
                    <h5 class="chart-card-title"><i class="bi bi-bar-chart text-primary"></i>Completion Time Trend</h5>
                    <div style="height: 300px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<!-- Chart.js via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    $(document).ready(function() {
        // Chart color palette aligned with FlowBoard theme
        const colors = {
            primary: '#4f46e5',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            gray: '#9ca3af',
            purple: '#8b5cf6',
            pink: '#ec4899',
            lightPrimary: 'rgba(79, 70, 229, 0.1)',
            lightSuccess: 'rgba(16, 185, 129, 0.1)'
        };

        // Chart instances
        let overviewChart = null;
        let statusChart = null;
        let trendChart = null;

        // Default Chart.js Config
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#6b7280';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.9)';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.maintainAspectRatio = false;

        function loadAnalytics() {
            const from = $('#dateFrom').val();
            const to = $('#dateTo').val();
            const board_id = $('#boardFilter').val();
            
            const btn = $('#refreshAnalyticsBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>');
            
            $('#analyticsDashboard').css('opacity', '0.5');

            const base_url = ($('meta[name="app-url"]').attr('content') || '').replace(/\/+$/, '');
            
            $.ajax({
                url: base_url + '/api/analytics/data',
                method: 'GET',
                data: { from: from, to: to, board_id: board_id },
                success: function(res) {
                    renderDashboard(res);
                },
                error: function() {
                    fbToast('Failed to load analytics data', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-arrow-clockwise me-1"></i>Refresh');
                    $('#analyticsDashboard').css('opacity', '1').hide().fadeIn(300);
                }
            });
        }

        function renderDashboard(data) {
            // Update Metric Card
            // If avg completion days < 1, show hours instead
            if (data.avg_completion_hours < 24) {
                $('#avgTimeValue').text(data.avg_completion_hours);
                $('#avgTimeLabel').text('Average Completion Hours');
            } else {
                $('#avgTimeValue').text(data.avg_completion_days);
                $('#avgTimeLabel').text('Average Completion Days');
            }
            $('#totalCompletedMetric').text(data.total_completed_in_range);

            // Render Overview Chart (Line)
            const ctxOverview = document.getElementById('overviewChart').getContext('2d');
            if (overviewChart) overviewChart.destroy();
            overviewChart = new Chart(ctxOverview, {
                type: 'line',
                data: {
                    labels: data.created_vs_completed.labels,
                    datasets: [
                        {
                            label: 'Created',
                            data: data.created_vs_completed.created,
                            borderColor: colors.primary,
                            backgroundColor: colors.lightPrimary,
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointBackgroundColor: colors.primary
                        },
                        {
                            label: 'Completed',
                            data: data.created_vs_completed.completed,
                            borderColor: colors.success,
                            backgroundColor: colors.lightSuccess,
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointBackgroundColor: colors.success
                        }
                    ]
                },
                options: {
                    plugins: { legend: { position: 'top', align: 'end' } },
                    scales: {
                        y: { beginAtZero: true, suggestedMax: 5, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    },
                    interaction: { mode: 'index', intersect: false }
                }
            });

            // Render Status Chart (Doughnut)
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            if (statusChart) statusChart.destroy();
            
            const statusPalette = [colors.primary, colors.info, colors.warning, colors.success, colors.purple, colors.pink, colors.gray];
            
            statusChart = new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: data.status_distribution.labels,
                    datasets: [{
                        data: data.status_distribution.data,
                        backgroundColor: statusPalette.slice(0, data.status_distribution.labels.length),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // Render Trend Chart (Bar)
            const ctxTrend = document.getElementById('trendChart').getContext('2d');
            if (trendChart) trendChart.destroy();

            trendChart = new Chart(ctxTrend, {
                type: 'bar',
                data: {
                    labels: data.completion_trend.labels,
                    datasets: [{
                        label: 'Average Hours',
                        data: data.completion_trend.data,
                        backgroundColor: colors.info,
                        borderRadius: 4,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Initialize
        loadAnalytics();

        // Bind refresh
        $('#refreshAnalyticsBtn').on('click', loadAnalytics);
        $('#boardFilter').on('change', loadAnalytics);
    });
</script>
@endpush
