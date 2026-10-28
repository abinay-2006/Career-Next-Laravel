@php($isEmployerAnalytics = $role === 'employer')
<section class="cn-analytics" data-analytics-url="{{ route($role . '.analytics') }}" data-role="{{ $role }}" @if(!$isEmployerAnalytics) data-application-url="{{ route('jobseeker.applications.show', ['application' => '__APPLICATION__']) }}" @endif aria-labelledby="analyticsHeading">
    <div class="cn-analytics-heading">
        <div>
            <p class="cn-analytics-eyebrow">Activity overview</p>
            <h2 id="analyticsHeading">{{ $isEmployerAnalytics ? 'Hiring analytics' : 'Your career activity' }}</h2>
            <p>{{ $isEmployerAnalytics ? 'Track your listings and candidate activity.' : 'Keep track of your applications and progress.' }}</p>
        </div>
        <label class="cn-analytics-filter" for="analyticsRange">
            <span>Date range</span>
            <select id="analyticsRange" class="form-select">
                <option value="7" @selected($analytics['range'] === '7')>Last 7 days</option>
                <option value="30" @selected($analytics['range'] === '30')>Last 30 days</option>
                <option value="90" @selected($analytics['range'] === '90')>Last 90 days</option>
                <option value="all" @selected($analytics['range'] === 'all')>All time</option>
            </select>
        </label>
    </div>

    <div class="cn-analytics-cards" aria-live="polite">
        @foreach($analytics['cards'] as $index => $card)
            <article class="cn-analytics-stat">
                <span class="cn-analytics-stat-icon"><i class="fas {{ ['fa-chart-line', 'fa-circle-check', 'fa-layer-group', 'fa-calendar-days'][$index] }}" aria-hidden="true"></i></span>
                <span class="cn-analytics-stat-copy"><span>{{ $card['label'] }}</span><strong data-stat-value="{{ $index }}">{{ $card['value'] }}</strong><small data-stat-detail="{{ $index }}">{{ $card['detail'] }}</small></span>
            </article>
        @endforeach
    </div>

    <div class="cn-analytics-charts">
        <article class="cn-analytics-panel cn-analytics-trend-panel">
            <div class="cn-analytics-panel-heading"><div><h3>Applications over time</h3><p data-trend-caption>{{ $analytics['range'] === 'all' ? 'Monthly activity' : 'Daily activity' }}</p></div><i class="fas fa-chart-line" aria-hidden="true"></i></div>
            <div class="cn-chart-wrap"><canvas id="activityTrendChart" role="img" aria-label="Applications over time chart"></canvas><p class="cn-chart-empty" data-empty-for="trend" hidden>No application activity for this period yet.</p></div>
        </article>
        @if($isEmployerAnalytics)
            <article class="cn-analytics-panel">
                <div class="cn-analytics-panel-heading"><div><h3>Job status</h3><p>All your job listings</p></div><i class="fas fa-briefcase" aria-hidden="true"></i></div>
                <div class="cn-chart-wrap cn-chart-wrap-doughnut"><canvas id="jobStatusChart" role="img" aria-label="Job status distribution chart"></canvas><p class="cn-chart-empty" data-empty-for="jobs" hidden>No job listings yet.</p></div>
            </article>
            <article class="cn-analytics-panel">
                <div class="cn-analytics-panel-heading"><div><h3>Applications by job</h3><p>Top listings by applicant volume</p></div><i class="fas fa-chart-column" aria-hidden="true"></i></div>
                <div class="cn-chart-wrap"><canvas id="jobApplicationsChart" role="img" aria-label="Application volume by job chart"></canvas><p class="cn-chart-empty" data-empty-for="job-applications" hidden>No applications received yet.</p></div>
            </article>
        @endif
        <article class="cn-analytics-panel">
            <div class="cn-analytics-panel-heading"><div><h3>Application status</h3><p data-status-caption>{{ $analytics['range'] === 'all' ? 'All time' : 'Last ' . $analytics['range'] . ' days' }}</p></div><i class="fas fa-chart-pie" aria-hidden="true"></i></div>
            <div class="cn-chart-wrap cn-chart-wrap-doughnut"><canvas id="applicationStatusChart" role="img" aria-label="Application status distribution chart"></canvas><p class="cn-chart-empty" data-empty-for="applications" hidden>No applications for this period yet.</p></div>
        </article>
    </div>

    <div class="cn-analytics-recent">
        @if($isEmployerAnalytics)
            <article class="cn-analytics-panel"><div class="cn-analytics-panel-heading"><div><h3>Recent applicants</h3><p>Latest applications across your jobs</p></div><a href="{{ route('employer.applications.index') }}">View applications <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Job</th><th>Received</th><th>Status</th></tr></thead><tbody data-recent-applications>@forelse($analytics['recentApplications'] as $application)<tr><td>{{ $application['title'] }}</td><td>{{ $application['date'] ? \Illuminate\Support\Carbon::parse($application['date'])->format('M j, Y') : 'Date unavailable' }}</td><td><span class="cn-status-pill">{{ $application['status'] }}</span></td></tr>@empty<tr><td colspan="3" class="cn-analytics-empty-row">No applications received yet.</td></tr>@endforelse</tbody></table></div>
            </article>
            <article class="cn-analytics-panel"><div class="cn-analytics-panel-heading"><div><h3>Recent jobs</h3><p>Your latest job listings</p></div><a href="{{ route('employer.jobs.index') }}">Manage jobs <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Job title</th><th>Status</th></tr></thead><tbody data-recent-jobs>@forelse($analytics['recentJobs'] as $job)<tr><td>{{ $job['title'] }}</td><td><span class="cn-status-pill">{{ $job['status'] }}</span></td></tr>@empty<tr><td colspan="2" class="cn-analytics-empty-row">You have not posted any jobs yet.</td></tr>@endforelse</tbody></table></div>
            </article>
        @else
            <article class="cn-analytics-panel"><div class="cn-analytics-panel-heading"><div><h3>Recent applications</h3><p>Your latest applications and their current status</p></div><a href="{{ route('jobseeker.applications.index') }}">View history <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Job</th><th>Company</th><th>Applied</th><th>Status</th></tr></thead><tbody data-recent-applications>@forelse($analytics['recentApplications'] as $application)<tr><td><a href="{{ route('jobseeker.applications.show', $application['id']) }}">{{ $application['title'] }}</a></td><td>{{ $application['company'] }}</td><td>{{ $application['date'] ? \Illuminate\Support\Carbon::parse($application['date'])->format('M j, Y') : 'Date unavailable' }}</td><td><span class="cn-status-pill">{{ $application['status'] }}</span></td></tr>@empty<tr><td colspan="4" class="cn-analytics-empty-row">Your submitted applications will appear here.</td></tr>@endforelse</tbody></table></div>
            </article>
        @endif
    </div>

    <p class="cn-analytics-updated" aria-live="polite">Updated <time data-last-updated datetime="{{ $analytics['updatedAt'] }}">{{ \Illuminate\Support\Carbon::parse($analytics['updatedAt'])->format('g:i A') }}</time><span data-refresh-error hidden> · Refresh failed; showing the last available data.</span></p>
    <script>window.cnDashboardAnalytics = {{ \Illuminate\Support\Js::from($analytics) }};</script>
</section>
