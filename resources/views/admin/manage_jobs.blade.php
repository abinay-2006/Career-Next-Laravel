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
        <button type="button" class="tab-btn active" data-tab="all">
            <i class="fas fa-list"></i> All
        </button>
        <button type="button" class="tab-btn" data-tab="pending">
            <i class="fas fa-clock"></i> Pending
        </button>
        <button type="button" class="tab-btn" data-tab="approved">
            <i class="fas fa-check-circle"></i> Approved
        </button>
        <button type="button" class="tab-btn" data-tab="rejected">
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

                            <tr class="job-row"
                                id="job-row-{{ $row->JobId }}"
                                data-status="{{ strtolower($row->Status ?? 'pending') }}">

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
                                    <span class="badge {{ strtolower($row->Status ?? 'pending') }}"
                                          data-job-status>
                                        {{ ucfirst($row->Status ?? 'Pending') }}
                                    </span>
                                </td>

                                <td>
                                    <button type="button"
                                            class="btn-detail"
                                            data-job-id="{{ $row->JobId }}"
                                            data-approve-url="{{ route('admin.jobs.approve', ['id' => $row->JobId]) }}"
                                            data-reject-url="{{ route('admin.jobs.reject', ['id' => $row->JobId]) }}">
                                        <i class="fas fa-eye"></i> Details
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


{{-- ACTION NOTIFICATION --}}

<div id="jobActionNotice"
     role="alert"
     aria-live="polite"
     style="
        display:none;
        position:fixed;
        top:20px;
        right:20px;
        z-index:100000;
        max-width:380px;
        padding:16px 20px;
        border-radius:10px;
        font-size:14px;
        font-weight:600;
        box-shadow:0 8px 25px rgba(0,0,0,.2);
     ">
    <span id="jobActionNoticeMessage"></span>

    <button type="button"
            id="closeJobActionNotice"
            aria-label="Close notification"
            style="border:0;background:transparent;margin-left:12px;cursor:pointer;font-size:18px;">
        &times;
    </button>
</div>


{{-- SINGLE JOB DETAILS MODAL --}}

<div class="modal-overlay"
     id="modalOverlay"
     aria-hidden="true">

    <div class="custom-modal"
         id="modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalName">

        {{-- HEADER --}}

        <div class="modal-header">

            <div class="modal-header-left">

                <div class="modal-avatar" id="modalAvatar">🏢</div>

                <div>
                    <div class="modal-title" id="modalName">Job Listing</div>
                    <div class="modal-subtitle" id="modalSubtitle">Company</div>
                </div>

            </div>

            <button type="button"
                    class="btn-close"
                    id="modalCloseTop"
                    aria-label="Close">
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        {{-- JOB AND EMPLOYER DETAILS --}}

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
                        <i class="fas fa-money-bill-wave"></i> Salary Range
                    </div>
                    <div class="field-value" id="mSalary">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-graduation-cap"></i> Minimum Qualification
                    </div>
                    <div class="field-value" id="mQualification">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-info-circle"></i> Current Status
                    </div>
                    <div class="field-value" id="mStatus">—</div>
                </div>

                {{-- EMPLOYER DETAILS --}}

                <div class="field-item full">
                    <div class="field-label">
                        <i class="fas fa-address-card"></i> Employer Details
                    </div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-user"></i> Contact Person
                    </div>
                    <div class="field-value" id="mEmployerContact">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-envelope"></i> Email
                    </div>
                    <div class="field-value" id="mEmployerEmail">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-phone"></i> Mobile
                    </div>
                    <div class="field-value" id="mEmployerMobile">—</div>
                </div>

                <div class="field-item">
                    <div class="field-label">
                        <i class="fas fa-location-dot"></i> City
                    </div>
                    <div class="field-value" id="mEmployerCity">—</div>
                </div>

                <div class="field-item full">
                    <div class="field-label">
                        <i class="fas fa-map-location-dot"></i> Address
                    </div>
                    <div class="field-value" id="mEmployerAddress">—</div>
                </div>

            </div>

        </div>


        {{-- DIRECT ACTIONS: NO SECOND CONFIRMATION PANEL --}}

        <div class="modal-footer job-detail-actions">

            <button type="button"
                    class="btn-cancel"
                    id="modalCloseBottom">
                <i class="fas fa-xmark"></i> Close
            </button>

            <div id="modalActions"
                 style="display:flex;gap:10px;flex-wrap:wrap;">

                <button type="button"
                        class="btn-cancel btn-reject"
                        id="rejectJobButton">
                    <i class="fas fa-times"></i> Reject Job
                </button>

                <button type="button"
                        class="btn-approve"
                        id="approveJobButton">
                    <i class="fas fa-circle-check"></i> Approve Job
                </button>

            </div>

        </div>

    </div>

</div>

@endsection


@section('scripts')

<script>
(function () {
    'use strict';

    function initializeAdminJobs() {

        const overlay = document.getElementById('modalOverlay');

        if (!overlay || overlay.dataset.initialized === 'true') {
            return;
        }

        overlay.dataset.initialized = 'true';

        const jobs = @json($jobs->values());
        const csrfToken = @json(csrf_token());

        const jobMap = {};

        jobs.forEach(function (job) {
            jobMap[String(job.JobId)] = job;
        });

        let activeJobId = null;
        let activeUrls = {};
        let requestInProgress = false;
        let notificationTimeout = null;

        const approveButton = document.getElementById('approveJobButton');
        const rejectButton = document.getElementById('rejectJobButton');
        const modalActions = document.getElementById('modalActions');

        const notice = document.getElementById('jobActionNotice');
        const noticeMessage = document.getElementById('jobActionNoticeMessage');

        const closeModalButtons = [
            document.getElementById('modalCloseTop'),
            document.getElementById('modalCloseBottom')
        ];


        // SAFE TEXT DISPLAY

        function setText(id, value) {
            const element = document.getElementById(id);

            if (element) {
                element.textContent =
                    value !== null && value !== undefined && String(value).trim() !== ''
                        ? String(value)
                        : '—';
            }
        }


        // NOTIFICATION

        function showNotification(message, type) {

            clearTimeout(notificationTimeout);

            noticeMessage.textContent = message;

            notice.style.display = 'flex';
            notice.style.alignItems = 'center';
            notice.style.justifyContent = 'space-between';

            if (type === 'success') {
                notice.style.background = '#ecfdf5';
                notice.style.color = '#166534';
                notice.style.borderLeft = '4px solid #16a34a';
            } else {
                notice.style.background = '#fef2f2';
                notice.style.color = '#991b1b';
                notice.style.borderLeft = '4px solid #dc2626';
            }

            notificationTimeout = setTimeout(function () {
                notice.style.display = 'none';
            }, 4500);
        }


        document.getElementById('closeJobActionNotice').addEventListener('click', function () {
            notice.style.display = 'none';
            clearTimeout(notificationTimeout);
        });


        // OPEN THE SINGLE MODAL

        function openModal(jobId, approveUrl, rejectUrl) {

            const job = jobMap[String(jobId)];

            if (!job) {
                showNotification('The selected job could not be loaded.', 'error');
                console.error('Job not found in rendered job data:', jobId);
                return;
            }

            activeJobId = String(job.JobId);

            activeUrls = {
                approve: approveUrl,
                reject: rejectUrl
            };

            const status = String(job.Status || 'Pending');
            const normalizedStatus = status.trim().toLowerCase();

            setText('modalName', job.JobTitle || 'Job Listing');
            setText('modalSubtitle', job.CompanyName || 'Company');

            setText('mId', job.JobId);
            setText('mCompany', job.CompanyName);
            setText('mTitle', job.JobTitle);
            setText('mDescription', job.Description || 'No description provided.');
            setText('mVacancy', job.Vacancy);

            const salaryFrom = job.SalaryFrom;
            const salaryTo = job.SalaryTo;

            let salary = '—';

            if (salaryFrom && salaryTo) {
                salary = salaryFrom + ' – ' + salaryTo;
            } else if (salaryFrom || salaryTo) {
                salary = salaryFrom || salaryTo;
            }

            setText('mSalary', salary);
            setText('mQualification', job.MinQualification);
            setText('mStatus', status);

            setText('mEmployerContact', job.EmployerContactPerson);
            setText('mEmployerEmail', job.EmployerEmail);
            setText('mEmployerMobile', job.EmployerMobile);
            setText('mEmployerCity', job.EmployerCity);
            setText('mEmployerAddress', job.EmployerAddress);

            approveButton.style.display =
                normalizedStatus === 'approved' ? 'none' : '';

            rejectButton.style.display =
                normalizedStatus === 'rejected' ? 'none' : '';

            approveButton.disabled = false;
            rejectButton.disabled = false;

            approveButton.innerHTML =
                '<i class="fas fa-circle-check"></i> Approve Job';

            rejectButton.innerHTML =
                '<i class="fas fa-times"></i> Reject Job';

            requestInProgress = false;

            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');

            document.body.style.overflow = 'hidden';
        }


        // CLOSE MODAL

        function closeModal() {

            if (requestInProgress) {
                return;
            }

            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');

            document.body.style.overflow = '';

            activeJobId = null;
            activeUrls = {};
        }


        closeModalButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });


        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal();
            }
        });


        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape' &&
                overlay.classList.contains('open')
            ) {
                closeModal();
            }
        });


        // OPEN JOB DETAILS WHEN CLICKING DETAIL

        document.querySelectorAll('.btn-detail[data-job-id]').forEach(function (button) {

            button.addEventListener('click', function () {

                openModal(
                    button.dataset.jobId,
                    button.dataset.approveUrl,
                    button.dataset.rejectUrl
                );

            });

        });


        // UPDATE THE TABLE AFTER SUCCESS

        function updateJobStatus(jobId, status) {

            const job = jobMap[String(jobId)];

            if (job) {
                job.Status = status;
            }

            const row = document.getElementById('job-row-' + jobId);

            if (row) {

                row.dataset.status = String(status).toLowerCase();

                const badge = row.querySelector('[data-job-status]');

                if (badge) {
                    badge.textContent =
                        status.charAt(0).toUpperCase() + status.slice(1);

                    badge.className =
                        'badge ' + String(status).toLowerCase();
                }
            }

            setText('mStatus', status);

            approveButton.style.display =
                String(status).toLowerCase() === 'approved' ? 'none' : '';

            rejectButton.style.display =
                String(status).toLowerCase() === 'rejected' ? 'none' : '';

        }


        // SUBMIT APPROVE / REJECT DIRECTLY

        async function submitJobAction(action) {

            if (requestInProgress) {
                return;
            }

            if (!activeJobId || !activeUrls[action]) {
                showNotification('The selected job or action URL is missing.', 'error');
                return;
            }

            const url = activeUrls[action];

            let parsedUrl;

            try {
                parsedUrl = new URL(url, window.location.origin);
            } catch (error) {
                showNotification('The job action URL is invalid.', 'error');
                console.error('Invalid job action URL:', url);
                return;
            }

            // Only allow same-origin requests.
            if (parsedUrl.origin !== window.location.origin) {
                showNotification('Invalid job action destination.', 'error');
                return;
            }

            if (url.includes('[object') || url.includes('HTMLInputElement')) {
                showNotification('Invalid job ID in the request URL.', 'error');
                console.error('Invalid URL detected:', url);
                return;
            }

            requestInProgress = true;

            const button = action === 'approve' ? approveButton : rejectButton;
            const originalButtonContent = button.innerHTML;

            approveButton.disabled = true;
            rejectButton.disabled = true;

            button.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> ' +
                (action === 'approve' ? 'Approving...' : 'Rejecting...');

            try {

                const response = await fetch(parsedUrl.href, {
                    method: 'POST',

                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },

                    credentials: 'same-origin'
                });

                const responseText = await response.text();

                let result = {};

                try {
                    result = responseText ? JSON.parse(responseText) : {};
                } catch (error) {
                    result = {};
                }

                if (!response.ok) {

                    let errorMessage =
                        result.message ||
                        'The job action failed. HTTP status: ' + response.status;

                    if (response.status === 404) {
                        errorMessage =
                            'The job action URL or selected job was not found. Check the request URL.';
                    } else if (response.status === 419) {
                        errorMessage =
                            'Your session has expired. Refresh the page and try again.';
                    } else if (response.status === 403) {
                        errorMessage =
                            'You are not authorized to perform this action.';
                    }

                    throw new Error(errorMessage);
                }

                if (result.success === false) {
                    throw new Error(result.message || 'The job action was not completed.');
                }

                const newStatus =
                    result.status ||
                    (action === 'approve' ? 'approved' : 'rejected');

                const returnedId =
                    result.id ??
                    result.jobId ??
                    result.JobId ??
                    activeJobId;

                if (String(returnedId) !== String(activeJobId)) {
                    throw new Error('The server response contains an unexpected job ID.');
                }

                updateJobStatus(activeJobId, newStatus);

                showNotification(
                    result.message ||
                    (action === 'approve'
                        ? 'Job approved successfully.'
                        : 'Job rejected successfully.'),
                    'success'
                );

            } catch (error) {

                console.error('Job approval/rejection error:', error);

                showNotification(
                    error.message || 'Unable to process the job action.',
                    'error'
                );

                button.innerHTML = originalButtonContent;

                approveButton.disabled = false;
                rejectButton.disabled = false;

            } finally {

                requestInProgress = false;

                approveButton.disabled = false;
                rejectButton.disabled = false;

                if (activeJobId) {
                    const currentJob = jobMap[String(activeJobId)];

                    if (currentJob) {
                        const currentStatus =
                            String(currentJob.Status || '').toLowerCase();

                        if (currentStatus === 'approved') {
                            approveButton.style.display = 'none';
                        }

                        if (currentStatus === 'rejected') {
                            rejectButton.style.display = 'none';
                        }
                    }
                }

            }
        }


        approveButton.addEventListener('click', function () {
            submitJobAction('approve');
        });


        rejectButton.addEventListener('click', function () {
            submitJobAction('reject');
        });


        // STATUS FILTERS

        function showTab(tab) {

            document.querySelectorAll('.tab-btn').forEach(function (button) {
                button.classList.toggle(
                    'active',
                    button.dataset.tab === tab
                );
            });

            document.querySelectorAll('.job-row').forEach(function (row) {

                row.style.display =
                    tab === 'all' || row.dataset.status === tab
                        ? ''
                        : 'none';

            });
        }


        document.querySelectorAll('.tab-btn[data-tab]').forEach(function (button) {

            button.addEventListener('click', function () {
                showTab(button.dataset.tab);
            });

        });


        // Retain compatibility if other page code calls showTab().
        window.showTab = showTab;

        // Retain compatibility with existing closeModal() calls.
        window.closeModal = closeModal;

    }


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAdminJobs, { once: true });
    } else {
        initializeAdminJobs();
    }

})();
</script>

@endsection