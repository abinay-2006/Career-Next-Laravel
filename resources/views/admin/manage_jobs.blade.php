@extends('layouts.admin')

@section('title', 'Manage Jobs')

@section('content')

<!-- ══════════ MAIN ══════════ -->
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
        <a href="{{ route('admin.index') }}" class="back-btn"><i class="fas fa-arrow-left"></i> Home</a>
    </div>

    <div class="section-title"><i class="fas fa-briefcase"></i> Posted Jobs</div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="all" onclick="showTab('all')"><i class="fas fa-list"></i> All</button>
        <button class="tab-btn" data-tab="pending" onclick="showTab('pending')"><i class="fas fa-clock"></i> Pending</button>
        <button class="tab-btn" data-tab="approved" onclick="showTab('approved')"><i class="fas fa-check-circle"></i> Approved</button>
        <button class="tab-btn" data-tab="rejected" onclick="showTab('rejected')"><i class="fas fa-times-circle"></i> Rejected</button>
    </div>

    <div class="table-wrapper">
        @if ($jobs->count() > 0)
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

                <tr class="job-row"
                    data-status="{{ strtolower($row->Status ?? 'Pending') }}">

                    <td>
                        <span class="id-badge">
                            {{ $row->JobId }}
                        </span>
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
                        <span class="badge {{ strtolower($row->Status) }}">
                            {{ ucfirst($row->Status) }}
                        </span>
                    </td>

                    <td>
                        <button
                            class="btn-detail"
                            onclick="openModal({{ $row->JobId }})">
                            <i class="fas fa-eye"></i>
                            Detail
                        </button>
                    </td>

                </tr>

                @endforeach
            </tbody>
        </table>
        <div class="table-footer">
            <span>Showing all posted job listings</span>
            <span class="records-count">
                <i class="fas fa-database"></i>
                Total {{ $jobs->count() }} Record {{ $jobs->count() !== 1 ? 's' : '' }}
            </span>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No posted jobs at this time.</p>
        </div>
        @endif
    </div>

</div><!-- /main-content -->

<!-- ══════════ DETAIL MODAL ══════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="handleOverlayClick(event)">
    <div class="custom-modal" id="modal">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-avatar" id="modalAvatar">🏢</div>
                <div>
                    <div class="modal-title" id="modalName">—</div>
                    <div class="modal-subtitle" id="modalSubtitle">Job Listing</div>
                </div>
            </div>
            <button class="btn-close" onclick="closeModal()" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="fields-grid">

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-hashtag"></i> Job ID</div>
                    <div class="field-value" id="mId">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-building"></i> Company</div>
                    <div class="field-value" id="mCompany">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label"><i class="fas fa-briefcase"></i> Job Title</div>
                    <div class="field-value" id="mTitle">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label"><i class="fas fa-align-left"></i> Description</div>
                    <div class="field-value" id="mDescription">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-users"></i> Vacancies</div>
                    <div class="field-value" id="mVacancy">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-graduation-cap"></i> Min Qualification</div>
                    <div class="field-value" id="mQualification">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-info-circle"></i> Status</div>
                    <div class="field-value" id="mStatus">—</div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <form method="POST" id="jobActionForm" style="display:flex; width:100%; justify-content:space-between; align-items:center; margin:0;">
                @csrf
                <input type="hidden" name="jobId" id="modalJobId" value="">
                <button type="button" class="btn-cancel" onclick="closeModal()">
                    <i class="fas fa-xmark"></i> Close
                </button>
                <div style="display:flex; gap:10px;" id="modalActions">
                    <button type="button" class="btn-cancel btn-reject" onclick="openActionModal('reject')"
                        style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3);">
                        <i class="fas fa-times"></i> Reject Job
                    </button>
                    <button type="button" class="btn-approve" onclick="openActionModal('approve')">
                        <i class="fas fa-circle-check"></i> Approve Job
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>


<!-- ══════════ ACTION CONFIRM MODAL ══════════ -->
<div class="modal-overlay" id="actionModalOverlay" onclick="handleActionOverlayClick(event)">
    <div class="custom-modal custom-confirm-modal" id="actionModal">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-avatar" id="actionModalAvatar"><i class="fas fa-circle-question"></i></div>
                <div>
                    <div class="modal-title" id="actionModalTitle">Confirm Action</div>
                    <div class="modal-subtitle" id="actionModalSubtitle">Review this job before continuing</div>
                </div>
            </div>
            <button class="btn-close" onclick="closeActionModal()" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <div class="fields-grid">
                <div class="field-item">
                    <div class="field-label"><i class="fas fa-hashtag"></i> Job ID</div>
                    <div class="field-value" id="actionJobId">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-building"></i> Company</div>
                    <div class="field-value" id="actionCompany">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label"><i class="fas fa-briefcase"></i> Job Title</div>
                    <div class="field-value" id="actionTitle">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-info-circle"></i> Current Status</div>
                    <div class="field-value" id="actionStatus">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label"><i class="fas fa-bell"></i> Action</div>
                    <div class="field-value" id="actionLabel">—</div>
                </div>
            </div>

            <div class="field-item full" style="margin-top:14px;">
                <div class="field-label"><i class="fas fa-circle-exclamation"></i> Confirmation</div>
                <div class="field-value" id="actionMessage">—</div>
            </div>
        </div>

        <div class="modal-footer">
            <form method="POST" id="approveRejectForm" style="display:flex; width:100%; justify-content:space-between; align-items:center; margin:0;">
                @csrf
                <input type="hidden" name="jobId" id="actionModalJobId" value="">
                <input type="hidden" name="action" id="actionModalAction" value="">
                <button type="button" class="btn-cancel" onclick="closeActionModal()">
                    <i class="fas fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="btn-approve" id="actionModalConfirmBtn">
                    <i class="fas fa-circle-check"></i> Confirm
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    const jobs = @json($jobs->values());
    let activeJobId = null

    // Build lookup map
    const jobMap = {};

    jobs.forEach(job => {
        jobMap[String(job.JobId)] = job;
    });

    function openModal(id) {

        const j = jobMap[String(id)];

        console.log(j);

        if (!j) return;

        activeJobId = j.JobId;

        const status = j.Status ?? 'Pending';

        document.getElementById('modalName').textContent = j.JobTitle;
        document.getElementById('modalSubtitle').textContent = j.CompanyName;

        document.getElementById('mId').textContent = j.JobId;
        document.getElementById('mCompany').textContent = j.CompanyName;
        document.getElementById('mTitle').textContent = j.JobTitle;
        document.getElementById('mDescription').textContent = j.Description || 'No description provided.';
        document.getElementById('mVacancy').textContent = j.Vacancy;
        document.getElementById('mQualification').textContent = j.MinQualification;
        document.getElementById('mStatus').textContent = status;

        document.getElementById('modalJobId').value = j.JobId;

        const rejectBtn = document.querySelector('#modalActions .btn-reject');
        const approveBtn = document.querySelector('#modalActions .btn-approve');

        rejectBtn.style.display =
            status.toLowerCase() === 'rejected' ?
            'none' :
            '';

        approveBtn.style.display =
            status.toLowerCase() === 'approved' ?
            'none' :
            '';

        document.getElementById('modalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    const actionModalConfig = {
        approve: {
            icon: '<i class="fas fa-circle-check"></i>',
            title: 'Approve Job',
            subtitle: 'This will mark the listing as approved',
            label: 'Approve',
            message: 'This job will be approved and can continue through the normal posting flow.',
            confirmText: 'Approve Job',
            confirmClass: 'btn-approve'
        },
        reject: {
            icon: '<i class="fas fa-circle-xmark"></i>',
            title: 'Reject Job',
            subtitle: 'This will mark the listing as rejected',
            label: 'Reject',
            message: 'This job will be rejected and removed from the active review list.',
            confirmText: 'Reject Job',
            confirmClass: 'btn-cancel',
            confirmStyle: 'background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3);'
        }
    };

    function openActionModal(action) {
        const j = jobMap[String(activeJobId)];
        if (!j || !actionModalConfig[action]) return;

        const c = actionModalConfig[action];
        document.getElementById('actionModalAvatar').innerHTML = c.icon;
        document.getElementById('actionModalTitle').textContent = c.title;
        document.getElementById('actionModalSubtitle').textContent = c.subtitle;
        document.getElementById('actionJobId').textContent = j.JobId || '—';
        document.getElementById('actionCompany').textContent = j.CompanyName || '—';
        document.getElementById('actionTitle').textContent = j.JobTitle || '—';
        document.getElementById('actionStatus').textContent = j.Status || 'Pending';
        document.getElementById('actionLabel').textContent = c.label;
        document.getElementById('actionMessage').textContent = c.message;
        document.getElementById('actionModalJobId').value = j.JobId || '';
        document.getElementById('actionModalAction').value = action;

        const form = document.getElementById('approveRejectForm');

        if (action === 'approve') {
            form.action = `/admin/manage-jobs/${j.JobId}/approve`;
        } else {
            form.action = `/admin/manage-jobs/${j.JobId}/reject`;
        }

        const confirmBtn = document.getElementById('actionModalConfirmBtn');
        confirmBtn.className = c.confirmClass;
        confirmBtn.innerHTML = action === 'approve' ?
            '<i class="fas fa-circle-check"></i> ' + c.confirmText :
            '<i class="fas fa-times"></i> ' + c.confirmText;
        confirmBtn.style.cssText = c.confirmStyle || '';

        closeModal();

        const actionOverlay = document.getElementById('actionModalOverlay');

        actionOverlay.classList.remove('open');

        requestAnimationFrame(() => {
            actionOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    }

    function closeActionModal() {
        document.getElementById('actionModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    function handleActionOverlayClick(e) {
        if (e.target === document.getElementById('actionModalOverlay')) closeActionModal();
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('modalOverlay')) closeModal();
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeModal();
            closeActionModal();
        }
    });

    function showTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tab-btn[data-tab="' + tab + '"]').classList.add('active');

        let visibleCount = 0;
        document.querySelectorAll('.job-row').forEach(row => {
            if (tab === 'all' || row.dataset.status === tab) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>
@endsection
