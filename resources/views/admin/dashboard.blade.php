@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="navbar">
    <a href="{{ route('admin.dashboard') }}" class="navbar-brand brand-logo">
        <img src="../images/CareerNext.png" alt="Career Next" onerror="this.style.display='none'">
        <span>Career Next</span>
    </a>

    <div class="navbar-user">
        <div class="user-badge">
            <div class="user-avatar"><i class="fas fa-shield-halved"></i></div>
            <div>
                <div class="user-name">{{ session('Name') }}</div>
                <div class="user-role">{{ session('UserType') }}</div>
            </div>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-logout">
                <i class="fas fa-right-from-bracket"></i> Logout
            </button>
        </form>
    </div>
</nav>

<!-- ═══════════════ MAIN ═══════════════ -->

<div class="main-content">

    <!-- Page header -->
    <div class="page-header">
        <h1>Admin Dashboard 🛡️</h1>
        <p>Welcome back, {{ session('Name') }}. Here's a live overview of the platform.</p>
        <a href="{{ route('admin.index') }}" class="back-btn"><i class="fas fa-arrow-left"></i> Home</a>
    </div>


    <!-- ── Stat cards ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon ic-purple"><i class="fas fa-users"></i></div>
                <span class="stat-badge">All roles</span>
            </div>
            <div class="stat-value">{{ $totalUsers }}</div>
            <div class="stat-label">Total Users</div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon ic-blue"><i class="fas fa-user-tie"></i></div>
                <span class="stat-badge">{{ $confirmedJobSeekers }} confirmed</span>
            </div>
            <div class="stat-value">{{ $totalJobSeekers }}</div>
            <div class="stat-label">Job Seekers</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon ic-green"><i class="fas fa-building"></i></div>
                <span class="stat-badge">{{ $confirmedEmployers }} confirmed</span>
            </div>
            <div class="stat-value">{{ $totalEmployers }}</div>
            <div class="stat-label">Employers</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon ic-pink"><i class="fas fa-briefcase"></i></div>
                <span class="stat-badge">Active</span>
            </div>
            <div class="stat-value">{{ $totalJobs }}</div>
            <div class="stat-label">Jobs Posted</div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon ic-amber"><i class="fas fa-clock"></i></div>
                <span class="stat-badge warn">{{ $totalPending > 0 ? 'Action needed' : 'All clear' }}</span>
            </div>
            <div class="stat-value">{{ $totalPending }}</div>
            <div class="stat-label">Pending Approvals</div>
        </div>
    </div>

    <!-- ── Charts ── -->
    <div class="charts-row">
        <!-- Doughnut: user split -->
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-chart-pie"></i> User Breakdown</div>
            <div class="chart-wrap"><canvas id="userChart"></canvas></div>
        </div>
        <!-- Bar: confirmed vs pending -->
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-chart-bar"></i> Confirmed vs Pending</div>
            <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
        </div>
    </div>



    <!-- ── Recent Data Tabs ── -->
    <div class="section-title"><i class="fas fa-clock-rotate-left"></i> Recent Activity</div>
    <div class="tabs-wrap">
        <div class="tab-header">
            <button class="tab-btn active" onclick="switchTab('seekers', this)">
                <i class="fas fa-user-tie"></i> Job Seekers
            </button>
            <button class="tab-btn" onclick="switchTab('employers', this)">
                <i class="fas fa-building"></i> Employers
            </button>
            <button class="tab-btn" onclick="switchTab('jobs', this)">
                <i class="fas fa-briefcase"></i> Jobs
            </button>
        </div>

        <!-- Job Seekers tab -->
        <div class="tab-panel active" id="tab-seekers">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>Email</th>
                        <th>Qualification</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @if($recentSeekers->isEmpty())
                    <tr>
                        <td colspan="6" style="text-align:center;opacity:0.5;padding:28px;">No job seekers found.</td>
                    </tr>
                    @else
                    @foreach($recentSeekers as $s)
                    <tr>
                        <td style="opacity:0.5;">{{ $s->JobSeekId }}</td>
                        <td>{{ $s->JobSeekerName }}</td>
                        <td style="opacity:0.7;">{{ $s->City }}</td>
                        <td style="opacity:0.7;">{{ $s->Email }}</td>
                        <td>{{ $s->Qualification }}</td>
                        <td><span class="badge {{ $s->Status }}">{{ $s->Status }}</span></td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Employers tab -->
        <div class="tab-panel" id="tab-employers">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>City</th>
                        <th>Email</th>
                        <th>Industry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @if($recentEmployers->isEmpty())
                    <tr>
                        <td colspan="6" style="text-align:center;opacity:0.5;padding:28px;">No employers found.</td>
                    </tr>
                    @else
                    @foreach($recentEmployers as $e)
                    <tr>
                        <td style="opacity:0.5;">{{ $e->EmployerId }}</td>
                        <td>{{ $e->CompanyName }}</td>
                        <td style="opacity:0.7;">{{ $e->City }}</td>
                        <td style="opacity:0.7;">{{ $e->Email }}</td>
                        <td>{{ $e->Area_Work }}</td>
                        <td><span class="badge {{ $e->Status }}">{{ $e->Status }}</span></td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Jobs tab -->
        <div class="tab-panel" id="tab-jobs">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Vacancies</th>
                        <th>Min. Qualification</th>
                    </tr>
                </thead>
                <tbody>
                    @if($recentJobs->isEmpty())
                    <tr>
                        <td colspan="5" style="text-align:center;opacity:0.5;padding:28px;">No jobs found.</td>
                    </tr>
                    @else
                    @foreach($recentJobs as $j)
                    <tr>
                        <td style="opacity:0.5;">{{ $j->JobId }}</td>
                        <td>{{ $j->CompanyName }}</td>
                        <td>{{ $j->JobTitle }}</td>
                        <td style="text-align:center;">
                            <span style="background:rgba(96,165,250,0.25);color:#93c5fd;padding:2px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;">
                                {{ $j->Vacancy }}
                            </span>
                        </td>
                        <td>{{ $j->MinQualification }}</td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
@yield('scripts')
<script>
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.remove('active');
        });

        document.querySelectorAll('.tab-btn').forEach(button => {
            button.classList.remove('active');
        });

        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // Charts
    Chart.defaults.color = 'rgba(255,255,255,0.75)';
    Chart.defaults.font = {
        family: 'Poppins',
        size: 12
    };

    // Doughnut – Job Seekers vs Employers
    new Chart(document.getElementById('userChart'), {
        type: 'doughnut',
        data: {
            labels: ['Job Seekers', 'Employers'],
            datasets: [{
                data: [<?php echo $totalJobSeekers; ?>, <?php echo $totalEmployers; ?>],
                backgroundColor: ['rgba(102,126,234,0.75)', 'rgba(16,185,129,0.75)'],
                borderColor: ['rgba(102,126,234,1)', 'rgba(16,185,129,1)'],
                borderWidth: 2,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 10
                    }
                }
            }
        }
    });

    // Grouped bar – Confirmed vs Pending for seekers & employers
    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: {
            labels: ['Job Seekers', 'Employers'],
            datasets: [{
                    label: 'Confirmed',
                    data: [<?php echo $confirmedJobSeekers; ?>, <?php echo $confirmedEmployers; ?>],
                    backgroundColor: 'rgba(16,185,129,0.7)',
                    borderColor: 'rgba(16,185,129,1)',
                    borderWidth: 2,
                    borderRadius: 7
                },
                {
                    label: 'Pending',
                    data: [<?php echo $pendingJobSeekers; ?>, <?php echo $pendingEmployers; ?>],
                    backgroundColor: 'rgba(245,158,11,0.7)',
                    borderColor: 'rgba(245,158,11,1)',
                    borderWidth: 2,
                    borderRadius: 7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 10
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255,255,255,0.08)'
                    },
                    ticks: {
                        color: 'rgba(255,255,255,0.75)'
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(255,255,255,0.08)'
                    },
                    ticks: {
                        color: 'rgba(255,255,255,0.75)',
                        stepSize: 1
                    },
                    beginAtZero: true
                }
            }
        }
    });
</script>
@endsection