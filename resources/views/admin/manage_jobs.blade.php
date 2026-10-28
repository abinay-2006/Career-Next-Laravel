@extends('layouts.admin')

@section('title', 'Manage Jobs')

@section('content')

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Control Panel</h1>
            <p>Review and manage posted job listings.</p>

            @if($jobs->count() > 0)
            <div class="badge-pill amber">
                <i class="fas fa-briefcase"></i>
                {{ $jobs->count() }} Job Listing{{ $jobs->count() != 1 ? 's' : '' }}
            </div>
            @else
            <div class="badge-pill">
                <i class="fas fa-check-circle"></i> All Clear
            </div>
            @endif
        </div>
    </div>

    <div class="section-title">
        <i class="fas fa-briefcase"></i> Posted Jobs
    </div>

    <div class="tabs job-status-tabs" role="group" aria-label="Filter jobs by status">
        <button type="button" class="tab-btn active" data-tab="all" onclick="showTab('all')">
            <i class="fas fa-list"></i> All
        </button>
        <button type="button" class="tab-btn" data-tab="pending" onclick="showTab('pending')">
            <i class="fas fa-clock"></i> Pending
        </button>
        <button type="button" class="tab-btn" data-tab="approved" onclick="showTab('approved')">
            <i class="fas fa-check-circle"></i> Approved
        </button>
        <button type="button" class="tab-btn" data-tab="rejected" onclick="showTab('rejected')">
            <i class="fas fa-times-circle"></i> Rejected
        </button>
    </div>

    <div class="table-wrapper">
        @if($jobs->count() > 0)

        <div class="responsive-table-wrapper"
            role="region"
            aria-label="Scrollable jobs table"
            tabindex="0">

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company Name</th>
                        <th>Job Title</th>
                        <th>Vacancies</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="jobsTableBody">
                    @foreach($jobs as $row)
                    <tr class="job-row" id="job-row-{{ $row->JobId }}"
                        data-status="{{ strtolower($row->Status ?? 'Pending') }}">

                        <td>
                            <span class="id-badge">{{ $row->JobId }}</span>
                        </td>

                        <td>{{ $row->CompanyName }}</td>

                        <td>
                            <span class="city-cell">
                                <i class="fas fa-briefcase"></i>
                                {{ $row->JobTitle }}
                            </span>
                        </td>

                        <td>
                            <span class="gender-chip male">
                                <i class="fas fa-users"></i>
                                {{ $row->Vacancy }}
                            </span>
                        </td>

                        <td>
                            <span class="badge {{ strtolower($row->Status ?? 'pending') }}" data-job-status>
                                {{ ucfirst($row->Status ?? 'Pending') }}
                            </span>
                        </td>

                        <td>
                            <button type="button"
                                class="btn-detail"
                                onclick="openModal(@json($row->JobId))">
                                <i class="fas fa-eye"></i> Detail
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>Showing posted job listings</span>
            <span class="records-count">
                <i class="fas fa-database"></i>
                Total {{ $jobs->count() }} Record{{ $jobs->count() !== 1 ? 's' : '' }}
            </span>
        </div>

        @else
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No posted jobs at this time.</p>
        </div>
        @endif
    </div>

</div>

{{-- FIRST MODAL: JOB DETAILS --}}
<div class="modal-overlay"
    id="modalOverlay"
    onclick="handleOverlayClick(event)">

    <div class="custom-modal" id="modal" role="dialog" aria-modal="true"
        aria-labelledby="modalName">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-avatar" id="modalAvatar">🏢</div>
                <div>
                    <div class="modal-title" id="modalName">—</div>
                    <div class="modal-subtitle" id="modalSubtitle">Job Listing</div>
                </div>
            </div>

            <button type="button" class="btn-close"
                onclick="closeModal()" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="fields-grid">

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-hashtag"></i> Job ID
                    </div>
                    <div class="field-value" id="mId">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-building"></i> Company
                    </div>
                    <div class="field-value" id="mCompany">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label">
                        <i class="fas fa-briefcase"></i> Job Title
                    </div>
                    <div class="field-value" id="mTitle">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label">
                        <i class="fas fa-align-left"></i> Description
                    </div>
                    <div class="field-value" id="mDescription">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-users"></i> Vacancies
                    </div>
                    <div class="field-value" id="mVacancy">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-graduation-cap"></i> Min Qualification
                    </div>
                    <div class="field-value" id="mQualification">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-info-circle"></i> Status
                    </div>
                    <div class="field-value" id="mStatus">—</div>
                </div>

            </div>
        </div>

        {{-- ACTION BUTTONS IN THE DETAILS MODAL --}}
        <div class="modal-footer job-detail-actions">

            <button type="button" class="btn-cancel" onclick="closeModal()">
                <i class="fas fa-xmark"></i> Close
            </button>

            <div id="modalActions"
                style="display:flex; gap:10px; flex-wrap:wrap;">

                <button type="button"
                    class="btn-cancel btn-reject"
                    onclick="openActionModal('reject')">
                    <i class="fas fa-times"></i> Reject Job
                </button>

                <button type="button"
                    class="btn-approve"
                    onclick="openActionModal('approve')">
                    <i class="fas fa-circle-check"></i> Approve Job
                </button>

            </div>
        </div>

    </div>
</div>


{{-- SECOND MODAL: APPROVE/REJECT CONFIRMATION --}}
<div class="modal-overlay"
    id="actionModalOverlay"
    onclick="handleActionOverlayClick(event)">

    <div class="custom-modal custom-confirm-modal"
        id="actionModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="actionModalTitle">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-avatar" id="actionModalAvatar">
                    <i class="fas fa-circle-question"></i>
                </div>

                <div>
                    <div class="modal-title" id="actionModalTitle">Confirm Action</div>
                    <div class="modal-subtitle" id="actionModalSubtitle">
                        Review this job before continuing
                    </div>
                </div>
            </div>

            <button type="button" class="btn-close"
                onclick="closeActionModal()" aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="fields-grid">

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-hashtag"></i> Job ID
                    </div>
                    <div class="field-value" id="actionJobId">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-building"></i> Company
                    </div>
                    <div class="field-value" id="actionCompany">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label">
                        <i class="fas fa-briefcase"></i> Job Title
                    </div>
                    <div class="field-value" id="actionTitle">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-info-circle"></i> Current Status
                    </div>
                    <div class="field-value" id="actionStatus">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-bell"></i> Action
                    </div>
                    <div class="field-value" id="actionLabel">—</div>
                </div>

            </div>

            <div class="field-item full" style="margin-top:14px;">
                <div class="field-label">
                    <i class="fas fa-circle-exclamation"></i> Confirmation
                </div>
                <div class="field-value" id="actionMessage">—</div>
            </div>
        </div>

        <div class="modal-footer job-confirm-actions">
            <form method="POST" id="approveRejectForm" data-ajax>
                @csrf

                <input type="hidden" name="jobId" id="actionModalJobId">
                <input type="hidden" name="action" id="actionModalAction">

                <button type="button" class="btn-cancel"
                    onclick="closeActionModal()">
                    <i class="fas fa-xmark"></i> Cancel
                </button>

                <button type="submit" class="btn-approve"
                    id="actionModalConfirmBtn">
                    <i class="fas fa-circle-check"></i> Confirm
                </button>
            </form>
        </div>

    </div>
</div>

@endsection


@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const jobs = @json($jobs -> values());

        const approveJobUrlTemplate = @json(route('admin.jobs.approve', ['id' => 'JOBIDPLACEHOLDER']));
        const rejectJobUrlTemplate = @json(route('admin.jobs.reject', ['id' => 'JOBIDPLACEHOLDER']));

        let activeJobId = null;
        let activeAction = null;

        const jobMap = {};

        jobs.forEach(function(job) {
            jobMap[String(job.JobId)] = job;
        });

        const detailsOverlay = document.getElementById('modalOverlay');
        const actionOverlay = document.getElementById('actionModalOverlay');
        const actionForm = document.getElementById('approveRejectForm');
        const confirmButton = document.getElementById('actionModalConfirmBtn');

        const actionConfig = {
            approve: {
                icon: '<i class="fas fa-circle-check"></i>',
                title: 'Approve Job',
                subtitle: 'Confirm approval of this job listing',
                label: 'Approve',
                message: 'Are you sure you want to approve this job?',
                buttonText: 'Confirm Approval',
                buttonClass: 'btn-approve',
                buttonStyle: ''
            },
            reject: {
                icon: '<i class="fas fa-circle-xmark"></i>',
                title: 'Reject Job',
                subtitle: 'Confirm rejection of this job listing',
                label: 'Reject',
                message: 'Are you sure you want to reject this job?',
                buttonText: 'Confirm Rejection',
                buttonClass: 'btn-cancel',
                buttonStyle: 'background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3);'
            }
        };

        window.openModal = function(id) {
            const job = jobMap[String(id)];

            if (!job) {
                console.error('Could not find job:', id);
                return;
            }

            activeJobId = job.JobId;

            const status = job.Status || 'Pending';
            const normalizedStatus = String(status).trim().toLowerCase();

            document.getElementById('modalName').textContent = job.JobTitle || 'Job Listing';
            document.getElementById('modalSubtitle').textContent = job.CompanyName || '';
            document.getElementById('mId').textContent = job.JobId ?? '—';
            document.getElementById('mCompany').textContent = job.CompanyName || '—';
            document.getElementById('mTitle').textContent = job.JobTitle || '—';
            document.getElementById('mDescription').textContent = job.Description || 'No description provided.';
            document.getElementById('mVacancy').textContent = job.Vacancy ?? '—';
            document.getElementById('mQualification').textContent = job.MinQualification || '—';
            document.getElementById('mStatus').textContent = status;

            document.querySelector('#modalActions .btn-reject').style.display =
                normalizedStatus === 'rejected' ? 'none' : '';

            document.querySelector('#modalActions .btn-approve').style.display =
                normalizedStatus === 'approved' ? 'none' : '';

            detailsOverlay.classList.add('open');
            actionOverlay.classList.remove('open');
            document.body.style.overflow = 'hidden';
        };

        window.closeModal = function() {
            detailsOverlay.classList.remove('open');
            document.body.style.overflow = '';
            activeJobId = null;
        };

        window.openActionModal = function(action) {
            const job = jobMap[String(activeJobId)];
            const config = actionConfig[action];

            if (!job || !config) {
                console.error('Invalid job or action.');
                return;
            }

            const status = String(job.Status || 'Pending').trim().toLowerCase();

            if (action === 'approve' && status === 'approved') {
                return;
            }

            if (action === 'reject' && status === 'rejected') {
                return;
            }

            activeAction = action;

            document.getElementById('actionModalAvatar').innerHTML = config.icon;
            document.getElementById('actionModalTitle').textContent = config.title;
            document.getElementById('actionModalSubtitle').textContent = config.subtitle;
            document.getElementById('actionJobId').textContent = job.JobId;
            document.getElementById('actionCompany').textContent = job.CompanyName || '—';
            document.getElementById('actionTitle').textContent = job.JobTitle || '—';
            document.getElementById('actionStatus').textContent = job.Status || 'Pending';
            document.getElementById('actionLabel').textContent = config.label;
            document.getElementById('actionMessage').textContent = config.message;

            document.getElementById('actionModalJobId').value = job.JobId;
            document.getElementById('actionModalAction').value = action;

            const encodedId = encodeURIComponent(job.JobId);
            actionForm.dataset.jobId = String(job.JobId);
            const urlTemplate = action === 'approve' ?
                approveJobUrlTemplate :
                rejectJobUrlTemplate;

            actionForm.action = urlTemplate.replace('JOBIDPLACEHOLDER', encodedId);

            confirmButton.disabled = false;
            confirmButton.className = config.buttonClass;
            confirmButton.innerHTML = action === 'approve' ?
                '<i class="fas fa-circle-check"></i> ' + config.buttonText :
                '<i class="fas fa-times"></i> ' + config.buttonText;

            confirmButton.style.cssText = config.buttonStyle;

            detailsOverlay.classList.remove('open');
            actionOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        };

        window.closeActionModal = function() {
            actionOverlay.classList.remove('open');

            // Return to the job details modal when cancelling.
            if (activeJobId !== null) {
                detailsOverlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        };

        window.handleOverlayClick = function(event) {
            if (event.target === detailsOverlay) {
                window.closeModal();
            }
        };

        window.handleActionOverlayClick = function(event) {
            if (event.target === actionOverlay) {
                window.closeActionModal();
            }
        };

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (actionOverlay.classList.contains('open')) {
                    window.closeActionModal();
                } else if (detailsOverlay.classList.contains('open')) {
                    window.closeModal();
                }
            }
        });

        actionForm.addEventListener('submit', function(event) {
            if (!activeAction || !activeJobId || !actionForm.action) {
                event.preventDefault();
                console.error('Cannot submit: job or action is missing.');
                return;
            }

            window.CareerNextButtonLoading?.start(
                confirmButton,
                activeAction === 'approve' ? 'Approving...' : 'Rejecting...'
            );
        });

        actionForm.addEventListener('cn:ajax-success', function(event) {
            const status = event.detail.status;
            const job = jobMap[String(activeJobId)];
            if (job) job.Status = status;
            const row = document.getElementById(`job-row-${event.detail.id}`);
            if (row) {
                row.dataset.status = status;
                const badge = row.querySelector('[data-job-status]');
                if (badge) {
                    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    badge.className = `badge ${status}`;
                }
            }
            document.getElementById('mStatus').textContent = status;
            window.closeActionModal();
        });

        window.showTab = function(tab) {
            document.querySelectorAll('.tab-btn').forEach(function(button) {
                button.classList.toggle('active', button.dataset.tab === tab);
            });

            document.querySelectorAll('.job-row').forEach(function(row) {
                row.style.display =
                    tab === 'all' || row.dataset.status === tab ? '' : 'none';
            });
        };
    });
</script>
@endsection
