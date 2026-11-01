
@extends('layouts.admin')

@section('title', 'Manage Documents')

@section('content')

<style>
/* ==========================================
   DOCUMENTS PAGE
   ========================================== */

.documents-page {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.documents-page *,
.documents-page *::before,
.documents-page *::after {
    box-sizing: border-box;
}

/* Page header */
.documents-page .page-header {
    width: 100%;
    margin-bottom: 28px;
}

/* Stats */
.documents-page .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    width: 100%;
    margin-bottom: 26px;
}

.documents-page .stat-card {
    min-width: 0;
}

/* Filter */
.documents-page .glass-card {
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

.documents-page .documents-filter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.documents-page .documents-filter-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

/* Document list card */
.documents-page .documents-list-card {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow: hidden;
}

.documents-page .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

/* ==========================================
   TABLE - FULL CARD WIDTH
   ========================================== */

.documents-page .documents-table-scroll {
    display: block;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    margin: 0;
    padding: 0;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.documents-page table.documents-table {
    display: table !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed !important;
    margin: 0 !important;
    border-collapse: collapse;
    border-spacing: 0;

    --bs-table-bg: transparent;
    --bs-table-color: #fff;
    --bs-table-hover-bg: rgba(255,255,255,.09);
    --bs-table-hover-color: #fff;
    --bs-table-border-color: rgba(255,255,255,.13);
}

.documents-page .documents-table th,
.documents-page .documents-table td {
    overflow-wrap: anywhere;
}

.documents-page .documents-table thead th {
    padding: 15px 12px;
    background: rgba(74, 57, 151, .48);
    color: rgba(255,255,255,.82);
    border-bottom: 1px solid rgba(255,255,255,.17);
    font-size: .76rem;
    font-weight: 700;
    letter-spacing: .035em;
    text-transform: uppercase;
    white-space: nowrap;
    vertical-align: middle;
}

.documents-page .documents-table tbody td {
    padding: 14px 12px;
    background: rgba(255,255,255,.045);
    color: #fff;
    border-bottom: 1px solid rgba(255,255,255,.12);
    font-size: .85rem;
    vertical-align: middle;
}

.documents-page .documents-table tbody tr:hover td {
    background: rgba(255,255,255,.11);
    color: #fff;
}

.documents-page .documents-table tbody tr:last-child td {
    border-bottom: 0;
}

/* Column widths */
.documents-page .documents-table th:nth-child(1),
.documents-page .documents-table td:nth-child(1) {
    width: 19%;
}

.documents-page .documents-table th:nth-child(2),
.documents-page .documents-table td:nth-child(2) {
    width: 29%;
}

.documents-page .documents-table th:nth-child(3),
.documents-page .documents-table td:nth-child(3) {
    width: 19%;
}

.documents-page .documents-table th:nth-child(4),
.documents-page .documents-table td:nth-child(4) {
    width: 13%;
}

.documents-page .documents-table th:nth-child(5),
.documents-page .documents-table td:nth-child(5) {
    width: 20%;
}

/* User */
.documents-page .documents-user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.documents-page .documents-user-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(255,255,255,.16);
    color: #fff;
    font-weight: 700;
}

.documents-page .documents-user-info {
    min-width: 0;
}

.documents-page .documents-user-name,
.documents-page .documents-file-type {
    color: #fff;
    font-size: .86rem;
    font-weight: 600;
}

.documents-page .documents-user-id,
.documents-page .documents-file-name {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.67);
    font-size: .74rem;
    overflow-wrap: anywhere;
}

/* Status */
.documents-page .status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 75px;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: .73rem;
    font-weight: 700;
    white-space: nowrap;
}

.documents-page .status-badge.pending {
    background: rgba(250,204,21,.22);
    color: #ffe58a;
}

.documents-page .status-badge.approved {
    background: rgba(34,197,94,.22);
    color: #78edb0;
}

.documents-page .status-badge.rejected {
    background: rgba(239,68,68,.22);
    color: #ffaaaa;
}

/* ==========================================
   ACTION BUTTONS - MANAGE USERS STYLE
   ========================================== */

.documents-page .action-buttons {
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.documents-page .btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    padding: 0;
    border: 0;
    border-radius: 9px;
    font-size: .95rem;
    cursor: pointer;
    text-decoration: none;
    transition: transform .2s ease, filter .2s ease;
}

.documents-page .btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(1.12);
}

/* View */
.documents-page .btn-action.view {
    background: rgba(59,130,246,.30);
    color: #9bc8ff;
}

/* Approve */
.documents-page .btn-action.approve {
    background: rgba(34,197,94,.34);
    color: #72edaa;
}

/* Reject */
.documents-page .btn-action.reject {
    background: rgba(236,72,153,.30);
    color: #ffadd3;
}

/* Empty state */
.documents-page .empty-state {
    padding: 45px 20px;
    text-align: center;
    color: rgba(255,255,255,.8);
}

.documents-page .empty-state > i {
    display: block;
    margin-bottom: 12px;
    font-size: 2rem;
}

/* Pagination */
.documents-page .pagination-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    width: 100%;
    padding: 18px 20px;
}

.documents-page .pagination-info {
    color: rgba(255,255,255,.8);
    font-size: .83rem;
}

.documents-page .pagination-links {
    min-width: 0;
}

.documents-page .per-page-select {
    max-width: 150px;
}

/* ==========================================
   MODALS
   ========================================== */

.documents-page .modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(24,18,54,.68);
    backdrop-filter: blur(5px);
}

.documents-page .modal-overlay.open {
    display: flex;
}

.documents-page .custom-modal {
    width: 100%;
    max-width: 460px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 17px;
    background: linear-gradient(145deg,#7167c9,#7951a8);
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}

.documents-page .modal-body {
    padding: 25px;
    color: #fff;
}

.documents-page .modal-title {
    margin-bottom: 18px;
    color: #fff;
    font-size: 1.2rem;
    font-weight: 700;
}

.documents-page .modal-info-box {
    padding: 15px;
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 12px;
    background: rgba(255,255,255,.12);
}

.documents-page .modal-info-box p {
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.documents-page .modal-info-box .info-label {
    color: rgba(255,255,255,.68);
    font-size: .76rem;
    font-weight: 600;
}

.documents-page .modal-text {
    margin: 17px 0;
    color: rgba(255,255,255,.88);
    font-size: .88rem;
    line-height: 1.6;
}

.documents-page .modal-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
}

.documents-page .btn-modal-cancel,
.documents-page .btn-modal-confirm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 9px 17px;
    border: 0;
    border-radius: 9px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

.documents-page .btn-modal-cancel {
    background: rgba(255,255,255,.17);
}

.documents-page .btn-modal-confirm.approve {
    background: #16a34a;
}

.documents-page .btn-modal-confirm.reject {
    background: #dc426b;
}

.documents-page [x-cloak] {
    display: none !important;
}

/* ==========================================
   RESPONSIVE
   ========================================== */

@media (max-width: 900px) {
    .documents-page .documents-table-scroll {
        overflow-x: auto;
    }

    .documents-page table.documents-table {
        min-width: 800px !important;
        table-layout: auto !important;
    }
}

@media (max-width: 700px) {
    .documents-page .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .documents-page .documents-filter-grid {
        grid-template-columns: 1fr;
    }

    .documents-page .pagination-container {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .documents-page .stats-grid {
        grid-template-columns: 1fr;
    }

    .documents-page .modal-body {
        padding: 19px;
    }

    .documents-page .modal-actions {
        flex-direction: column-reverse;
    }

    .documents-page .modal-actions button {
        width: 100%;
    }
}
</style>

<div x-data="documentsManager()" class="main-content documents-page">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">
                <i class="fas fa-file-shield"></i>
            </div>
            <div class="page-header-text">
                <h1>Documents</h1>
                <p>Verify or reject documents uploaded by job seekers</p>
            </div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="stats-grid">

        <a href="?status=all&search={{ urlencode($search) }}&per_page=10"
           class="stat-card {{ $filterStatus === 'all' ? 'active' : '' }}">
            <div class="stat-value">{{ $counts['total'] }}</div>
            <div class="stat-label">
                <i class="fas fa-folder-open"></i> Total Documents
            </div>
        </a>

        <a href="?status=approved&search={{ urlencode($search) }}&per_page=10"
           class="stat-card {{ $filterStatus === 'approved' ? 'active' : '' }}">
            <div class="stat-value">{{ $counts['approved'] }}</div>
            <div class="stat-label">
                <i class="fas fa-check-circle"></i> Approved
            </div>
        </a>

        <a href="?status=pending&search={{ urlencode($search) }}&per_page=10"
           class="stat-card {{ $filterStatus === 'pending' ? 'active' : '' }}">
            <div class="stat-value">{{ $counts['pending'] }}</div>
            <div class="stat-label">
                <i class="fas fa-clock"></i> Pending
            </div>
        </a>

        <a href="?status=rejected&search={{ urlencode($search) }}&per_page={{ $perPage }}"
           class="stat-card {{ $filterStatus === 'rejected' ? 'active' : '' }}">
            <div class="stat-value">{{ $counts['rejected'] }}</div>
            <div class="stat-label">
                <i class="fas fa-times-circle"></i> Rejected
            </div>
        </a>

    </div>

    <!-- FILTER SECTION -->
    <div class="section-title">
        <i class="fas fa-filter"></i> Filter Documents
    </div>

    <div class="glass-card">
        <form method="GET"
              action="{{ route('admin.manage.documents') }}"
              data-ajax-html>

            <div class="form-body">
                <div class="documents-filter-grid">

                    <div class="form-group">
                        <label class="form-label" for="search">
                            <i class="fas fa-magnifying-glass"></i> Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            id="search"
                            class="form-input"
                            placeholder="Search by user or filename..."
                            value="{{ $search }}"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-circle-check"></i> Status
                        </label>

                        <select name="status" id="status" class="form-select">
                            <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>
                                All Statuses
                            </option>
                            <option value="approved" {{ $filterStatus === 'approved' ? 'selected' : '' }}>
                                Approved
                            </option>
                            <option value="pending" {{ $filterStatus === 'pending' ? 'selected' : '' }}>
                                Pending
                            </option>
                            <option value="rejected" {{ $filterStatus === 'rejected' ? 'selected' : '' }}>
                                Rejected
                            </option>
                        </select>
                    </div>

                </div>
            </div>

            <div class="form-actions documents-filter-actions">
                <button type="submit" class="btn-brand">
                    <i class="fas fa-search"></i> Apply Filters
                </button>

                <a href="{{ route('admin.manage.documents') }}" class="btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>

        </form>
    </div>

    <!-- DOCUMENT LIST -->
    <div class="section-title">
        <i class="fas fa-file-alt"></i> Document List
    </div>

    <div class="glass-card delay documents-list-card">

        <div class="card-header">
            <div class="card-header-icon">
                <i class="fas fa-table-list"></i>
            </div>

            <div>
                <div class="card-header-title">Verification Queue</div>
                <div class="card-header-sub">
                    Review and manage document submissions
                </div>
            </div>
        </div>

        @if($documents->isEmpty())

            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No documents found matching your filters.</p>
            </div>

        @else

            <!-- SINGLE TABLE WRAPPER -->
            <div class="documents-table-scroll"
                 role="region"
                 aria-label="Scrollable documents table"
                 tabindex="0">

                <table class="table align-middle mb-0 documents-table">

                    <colgroup>
                        <col style="width: 19%">
                        <col style="width: 29%">
                        <col style="width: 19%">
                        <col style="width: 13%">
                        <col style="width: 20%">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Document</th>
                            <th>Upload Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($documents as $doc)

                            <tr>

                                <!-- USER -->
                                <td>
                                    <div class="documents-user-cell">

                                        <div class="documents-user-avatar">
                                            {{ strtoupper(substr($doc->JobSeekerName, 0, 1)) }}
                                        </div>

                                        <div class="documents-user-info">
                                            <div class="documents-user-name">
                                                {{ $doc->JobSeekerName }}
                                            </div>

                                            <div class="documents-user-id">
                                                ID: {{ $doc->JobSeekId }}
                                            </div>
                                        </div>

                                    </div>
                                </td>

                                <!-- DOCUMENT -->
                                <td>
                                    <div class="documents-file-type">
                                        {{ ucwords(str_replace('_', ' ', $doc->DocumentType)) }}
                                    </div>

                                    <div class="documents-file-name">
                                        {{ $doc->OriginalFileName }}
                                    </div>
                                </td>

                                <!-- UPLOAD DATE -->
                                <td>
                                    {{ \Carbon\Carbon::parse($doc->UploadDate)->format('d M Y, h:i A') }}
                                </td>

                                <!-- STATUS -->
                                <td>
                                    <span class="status-badge {{ strtolower($doc->VerificationStatus) }}">
                                        {{ $doc->VerificationStatus }}
                                    </span>
                                </td>

                                <!-- ACTIONS -->
                                <td>
                                    <div class="action-buttons">

                                        <a
                                            href="{{ route('admin.documents.view', $doc->DocId) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn-action view"
                                            title="View Document"
                                            aria-label="View Document"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        @if($doc->VerificationStatus === 'pending')

                                            <button
                                                type="button"
                                                class="btn-action approve"
                                                title="Accept Document"
                                                aria-label="Accept Document"
                                                data-file="{{ $doc->OriginalFileName }}"
                                                data-user="{{ $doc->JobSeekerName }}"
                                                @click="openApproveModal({{ $doc->DocId }}, $el.dataset.file, $el.dataset.user)"
                                            >
                                                <i class="fas fa-check"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="btn-action reject"
                                                title="Reject Document"
                                                aria-label="Reject Document"
                                                data-file="{{ $doc->OriginalFileName }}"
                                                data-user="{{ $doc->JobSeekerName }}"
                                                @click="openRejectModal({{ $doc->DocId }}, $el.dataset.file, $el.dataset.user)"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>

                                        @elseif($doc->VerificationStatus === 'approved')

                                            <button
                                                type="button"
                                                class="btn-action reject"
                                                title="Reject Document"
                                                aria-label="Reject Document"
                                                data-file="{{ $doc->OriginalFileName }}"
                                                data-user="{{ $doc->JobSeekerName }}"
                                                @click="openRejectModal({{ $doc->DocId }}, $el.dataset.file, $el.dataset.user)"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>

                                        @elseif($doc->VerificationStatus === 'rejected')

                                            <button
                                                type="button"
                                                class="btn-action approve"
                                                title="Accept Document"
                                                aria-label="Accept Document"
                                                data-file="{{ $doc->OriginalFileName }}"
                                                data-user="{{ $doc->JobSeekerName }}"
                                                @click="openApproveModal({{ $doc->DocId }}, $el.dataset.file, $el.dataset.user)"
                                            >
                                                <i class="fas fa-check"></i>
                                            </button>

                                        @endif

                                    </div>
                                </td>

                            </tr>

                        @endforeach

                    </tbody>
                </table>

            </div>

            <!-- PAGINATION -->
            @if($documents->hasPages())

                <div class="pagination-container">

                    <div class="pagination-info">
                        Showing {{ $documents->firstItem() }}
                        to {{ $documents->lastItem() }}
                        of {{ $documents->total() }} results
                    </div>

                    <div class="pagination-links">
                        {{ $documents->appends([
                            'status' => $filterStatus,
                            'search' => $search,
                            'per_page' => $perPage,
                        ])->links() }}
                    </div>

                    <select
                        class="per-page-select"
                        aria-label="Documents per page"
                        onchange="if (!window.CareerNextButtonLoading) window.location=this.value"
                    >
                        @foreach([10, 25, 50, 100] as $size)
                            <option
                                value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}"
                                {{ $perPage == $size ? 'selected' : '' }}
                            >
                                {{ $size }} per page
                            </option>
                        @endforeach
                    </select>

                </div>

            @endif

        @endif

    </div>

    <!-- ======================================
         APPROVE MODAL
         ====================================== -->

    <div
        x-show="approveModalOpen"
        x-cloak
        class="modal-overlay"
        :class="approveModalOpen ? 'open' : ''"
        @click.self="approveModalOpen = false"
        @keydown.escape.window="approveModalOpen = false"
    >

        <div class="custom-modal">

            <form
                method="POST"
                id="approveForm"
                data-ajax
                data-ajax-refresh-target=".cn-admin-content"
            >

                @csrf

                <div class="modal-body">

                    <div class="modal-title">
                        Accept Document
                    </div>

                    <div class="modal-info-box">

                        <p class="info-label">File Name</p>
                        <p x-text="selectedFileName || '—'"></p>

                        <p class="info-label" style="margin-top:8px;">
                            Job Seeker
                        </p>
                        <p x-text="selectedUserName || '—'"></p>

                    </div>

                    <p class="modal-text">
                        This document will be marked as accepted and the job seeker will be notified.
                    </p>

                    <div class="modal-actions">

                        <button
                            type="button"
                            class="btn-modal-cancel"
                            @click="approveModalOpen = false"
                        >
                            <i class="fas fa-xmark"></i>
                            Cancel
                        </button>

                        <input
                            type="hidden"
                            name="docId"
                            :value="selectedDocId"
                        >

                        <button type="submit" class="btn-modal-confirm approve">
                            <i class="fas fa-check"></i>
                            Accept
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

    <!-- ======================================
         REJECT MODAL
         ====================================== -->

    <div
        x-show="rejectModalOpen"
        x-cloak
        class="modal-overlay"
        :class="rejectModalOpen ? 'open' : ''"
        @click.self="rejectModalOpen = false"
        @keydown.escape.window="rejectModalOpen = false"
    >

        <div class="custom-modal">

            <form
                method="POST"
                id="rejectForm"
                data-ajax
                data-ajax-refresh-target=".cn-admin-content"
            >

                @csrf

                <div class="modal-body">

                    <div class="modal-title">
                        Reject Document
                    </div>

                    <div class="modal-info-box">

                        <p class="info-label">File Name</p>
                        <p x-text="selectedFileName || '—'"></p>

                        <p class="info-label" style="margin-top:8px;">
                            Job Seeker
                        </p>
                        <p x-text="selectedUserName || '—'"></p>

                    </div>

                    <p class="modal-text">
                        This document will be marked as rejected. The job seeker can upload another document later.
                    </p>

                    <div class="modal-actions">

                        <button
                            type="button"
                            class="btn-modal-cancel"
                            @click="rejectModalOpen = false"
                        >
                            <i class="fas fa-xmark"></i>
                            Cancel
                        </button>

                        <input
                            type="hidden"
                            name="docId"
                            :value="selectedDocId"
                        >

                        <button type="submit" class="btn-modal-confirm reject">
                            <i class="fas fa-trash"></i>
                            Reject
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('scripts')

<script>
    const approveUrl = @json(url('/admin/manage-documents'));

    function documentsManager() {
        return {
            approveModalOpen: false,
            rejectModalOpen: false,

            selectedDocId: null,
            selectedFileName: '',
            selectedUserName: '',

            openApproveModal(id, file, user) {
                this.selectedDocId = id;
                this.selectedFileName = file;
                this.selectedUserName = user;

                document.getElementById('approveForm').setAttribute(
                    'action',
                    `${approveUrl}/${id}/approve`
                );

                this.approveModalOpen = true;
                this.rejectModalOpen = false;
            },

            openRejectModal(id, file, user) {
                this.selectedDocId = id;
                this.selectedFileName = file;
                this.selectedUserName = user;

                document.getElementById('rejectForm').setAttribute(
                    'action',
                    `${approveUrl}/${id}/reject`
                );

                this.rejectModalOpen = true;
                this.approveModalOpen = false;
            }
        };
    }
</script>

@endsection
