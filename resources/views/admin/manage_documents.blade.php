@extends('layouts.admin')

@section('title', 'Manage Documents')

@section('content')

<div x-data="documentsManager()" class="flex flex-col">
    <!-- ══ MAIN ══ -->
    <div class="main-content">

        <!-- Page header -->
        <div class="page-header">
            <div class="page-header-left">
                <div class="page-header-icon"><i class="fas fa-file-shield"></i></div>
                <div class="page-header-text">
                    <h1>Documents</h1>
                    <p>Verify or reject documents uploaded by job seekers</p>
                </div>
            </div>
            <a href="{{ route('admin.index') }}" class="btn-back">
                <i class="fas fa-arrow-left"></i> Home
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <a href="?status=all&search={{ $search }}&per_page=10"
                class="stat-card {{ $filterStatus === 'all' ? 'active' : '' }}">
                <div class="stat-value">{{ $counts['total'] }}</div>
                <div class="stat-label"><i class="fas fa-folder-open"></i> Total Documents</div>
            </a>
            <a href="?status=approved&search={{ $search }}&per_page=10"
                class="stat-card {{ $filterStatus  === 'approved' ? 'active' : '' }}">
                <div class="stat-value">{{ $counts['approved'] }}</div>
                <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
            </a>
            <a href="?status=pending&search={{ $search }}&per_page=10"
                class="stat-card {{ $filterStatus === 'pending' ? 'active' : '' }}">
                <div class="stat-value">{{ $counts['pending'] }}</div>
                <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
            </a>
            <a href="?status=rejected&search={{ $search }}&per_page={{ $perPage }}"
                class="stat-card {{ $filterStatus === 'rejected' ? 'active' : '' }}">
                <div class="stat-value">{{ $counts['rejected'] }}</div>
                <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
            </a>
        </div>

        <!-- Filter Section -->
        <div class="section-title"><i class="fas fa-filter"></i> Filter Documents</div>

        <div class="glass-card">
            <form method="GET" action="">
                <div class="form-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="search"><i class="fas fa-magnifying-glass"></i> Search</label>
                            <input type="text" name="search" id="search" class="form-input"
                                placeholder="Search by user or filename..."
                                value="{{ $search }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="status"><i class="fas fa-circle-check"></i> Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                                <option value="approved" {{  $filterStatus === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="pending" {{ $filterStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="rejected" {{ $filterStatus === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-brand"><i class="fas fa-search"></i> Apply Filters</button>
                    <a href="{{ route('admin.manage.documents') }}" class="btn-outline"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Documents Table Section -->
        <div class="section-title"><i class="fas fa-file-alt"></i> Document List</div>

        <div class="glass-card delay">
            <div class="card-header">
                <div class="card-header-icon"><i class="fas fa-table-list"></i></div>
                <div>
                    <div class="card-header-title">Verification Queue</div>
                    <div class="card-header-sub">Review and manage document submissions</div>
                </div>
            </div>

            @if($documents->isEmpty())
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No documents found matching your filters.</p>
            </div>
            @else
            <div class="table-wrapper">
                <table>
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
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 36px; height: 36px; background: rgba(255, 255, 255, 0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: rgba(255, 255, 255, 0.8); font-weight: 600; font-size: 0.85rem;">
                                        {{ strtoupper(substr($doc->JobSeekerName,0,1)) }}
                                    </div>
                                    <div>
                                        <div style="color: #fff; font-weight: 500; font-size: 0.87rem;">{{ $doc->JobSeekerName }}</div>
                                        <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.75rem; margin-top: 2px;">ID: {{ $doc->JobSeekId }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="color: #fff; font-weight: 500; font-size: 0.87rem;">{{ ucwords(str_replace('_', ' ', $doc->DocumentType)) }}</div>
                                <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.75rem; margin-top: 2px;">{{ $doc->OriginalFileName}}</div>
                            </td>
                            <td style="color: rgba(255, 255, 255, 0.8);">{{ \Carbon\Carbon::parse($doc->UploadDate)->format('d M Y, h:i A') }}</td>
                            <td>
                                <span class="status-badge {{ strtolower($doc->VerificationStatus) }}">
                                    {{ $doc->VerificationStatus }}
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">

                                    <!-- VIEW -->
                                    <a href="{{ route('admin.documents.view',$doc->DocId) }}"
                                        target="_blank"
                                        class="btn-action view"
                                        title="View Document">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @if ($doc->VerificationStatus === 'pending')

                                    <!-- APPROVE -->
                                    <button class="btn-action approve" title="Accept Document"
                                        @click="openApproveModal(
                                                    {{ $doc->DocId }},
                                                    '{{ addslashes($doc->OriginalFileName) }}',
                                                    '{{ addslashes($doc->JobSeekerName) }}'
                                                        )">
                                        <i class="fas fa-check"></i>
                                    </button>

                                    <!-- REJECT -->
                                    <button class="btn-action reject" title="Reject Document"
                                        @click="openRejectModal(
                                                    {{ $doc->DocId }},
                                                    '{{ addslashes($doc->OriginalFileName) }}',
                                                    '{{ addslashes($doc->JobSeekerName) }}'
                                                        )">
                                        <i class="fas fa-xmark"></i>
                                    </button>

                                    @elseif ($doc->VerificationStatus === 'approved')

                                    <!-- ONLY REJECT -->
                                    <button class="btn-action reject" title="Reject Document"
                                        @click="openRejectModal(
                                                    {{ $doc->DocId }},
                                                    '{{ addslashes($doc->OriginalFileName) }}',
                                                    '{{ addslashes($doc->JobSeekerName) }}'
                                                        )">
                                        <i class="fas fa-xmark"></i>
                                    </button>

                                    @elseif ($doc->VerificationStatus === 'rejected')

                                    <!-- ONLY APPROVE -->
                                    <button class="btn-action approve" title="Accept Document"
                                        @click="openApproveModal(
                                                    {{ $doc->DocId }},
                                                    '{{ addslashes($doc->OriginalFileName) }}',
                                                    '{{ addslashes($doc->JobSeekerName) }}'
                                                        )">
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

            <!-- Pagination -->
            @if ($documents->hasPages())
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

                <select class="per-page-select"
                    onchange="window.location=this.value">

                    @foreach([10,25,50,100] as $size)
                    <option
                        value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}"
                        {{ $perPage == $size ? 'selected' : '' }}>
                        {{ $size }} per page
                    </option>
                    @endforeach

                </select>

            </div>
            @endif
            @endif
        </div>

    </div>


    <!-- ══ APPROVE MODAL ══ -->
    <div x-show="approveModalOpen"
        x-cloak
        :class="approveModalOpen ? 'modal-overlay open' : 'modal-overlay'"
        @click.self="approveModalOpen = false">

        <div class="custom-modal">
            <form method="POST" id="approveForm">
                @csrf

                <div class="modal-body">
                    <div class="modal-title">Accept Document</div>

                    <div class="modal-info-box">
                        <p class="info-label">File Name</p>
                        <p x-text="selectedFileName || '—'"></p>

                        <p class="info-label" style="margin-top:8px;">Job Seeker</p>
                        <p x-text="selectedUserName || '—'"></p>
                    </div>

                    <p class="modal-text">
                        This document will be marked as accepted and the job seeker will be notified.
                    </p>

                    <div class="modal-actions">

                        <button type="button"
                            class="btn-modal-cancel"
                            @click="approveModalOpen=false">
                            <i class="fas fa-xmark"></i> Cancel
                        </button>

                        <input type="hidden"
                            name="docId"
                            :value="selectedDocId">

                        <button type="submit"
                            class="btn-modal-confirm approve">
                            <i class="fas fa-check"></i> Accept
                        </button>

                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ REJECT MODAL ══ -->
    <div x-show="rejectModalOpen"
        x-cloak
        :class="rejectModalOpen ? 'modal-overlay open' : 'modal-overlay'"
        @click.self="rejectModalOpen = false">

        <div class="custom-modal">
            <form method="POST" id="rejectForm">
                @csrf

                <div class="modal-body">

                    <div class="modal-title">Reject Document</div>

                    <div class="modal-info-box">
                        <p class="info-label">File Name</p>
                        <p x-text="selectedFileName || '—'"></p>

                        <p class="info-label" style="margin-top:8px;">Job Seeker</p>
                        <p x-text="selectedUserName || '—'"></p>
                    </div>

                    <p class="modal-text">
                        This document will be marked as rejected. The job seeker can upload another document later.
                    </p>

                    <div class="modal-actions">

                        <button type="button"
                            class="btn-modal-cancel"
                            @click="rejectModalOpen=false">
                            <i class="fas fa-xmark"></i> Cancel
                        </button>

                        <input type="hidden"
                            name="docId"
                            :value="selectedDocId">

                        <button type="submit"
                            class="btn-modal-confirm reject">
                            <i class="fas fa-times"></i> Reject
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
    const approveUrl = "{{ url('/admin/manage-documents') }}";

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

                document.getElementById('approveForm').action =
                    `${approveUrl}/${id}/approve`;

                this.approveModalOpen = true;
            },

            openRejectModal(id, file, user) {

                this.selectedDocId = id;
                this.selectedFileName = file;
                this.selectedUserName = user;

                document.getElementById('rejectForm').action =
                    `${approveUrl}/${id}/reject`;

                this.rejectModalOpen = true;
            },

            closeApproveModal() {
                this.approveModalOpen = false;
            },

            closeRejectModal() {
                this.rejectModalOpen = false;
            }

        };
    }
</script>

@endsection
