@extends('layouts.admin')

@section('title', 'Manage Users')

@section('content')
<!-- ═══════════ MAIN ═══════════ -->
<div class="main-content">

    <div class="page-header">
        <h1>Manage Users 👥</h1>
        <p>Approve, suspend, or remove job seeker and employer accounts.</p>
        <a href="{{ route('admin.index') }}" class="back-btn"><i class="fas fa-arrow-left"></i> Home</a>
    </div>
    
    <!-- Toast -->
    @if(session('success'))
    <div class="toast success">
        <i class="fas fa-circle-check"></i>
        {{ session('success') }}
    </div>
    @endif

    @if(session('warning'))
    <div class="toast warning">
        <i class="fas fa-triangle-exclamation"></i>
        {{ session('warning') }}
    </div>
    @endif

    @if(session('error'))
    <div class="toast error">
        <i class="fas fa-circle-xmark"></i>
        {{ session('error') }}
    </div>
    @endif

    <!-- Summary cards -->
    <div class="summary-grid">
        <a href="?status=all&type={{ $filterType }}" class="sum-card {{ $filterStatus === 'all' ? 'active-filter' : '' }}">
            <div class="sum-icon all"><i class="fas fa-users"></i></div>
            <div>
                <div class="sum-val">{{ $counts['total'] }}</div>
                <div class="sum-label">Total Users</div>
            </div>
        </a>
        <a href="?status=Confirm&type={{ $filterType }}" class="sum-card {{ $filterStatus === 'Confirm' ? 'active-filter' : '' }}">
            <div class="sum-icon confirmed"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="sum-val">{{ $counts['confirmed'] }}</div>
                <div class="sum-label">Confirmed</div>
            </div>
        </a>
        <a href="?status=Pending&type={{ $filterType }}" class="sum-card {{ $filterStatus === 'Pending' ? 'active-filter' : '' }}">
            <div class="sum-icon pending"><i class="fas fa-clock"></i></div>
            <div>
                <div class="sum-val">{{ $counts['pending'] }}</div>
                <div class="sum-label">Pending</div>
            </div>
        </a>
        <a href="?status=Suspended&type={{ $filterType }}" class="sum-card {{ $filterStatus === 'Suspended' ? 'active-filter' : '' }}">
            <div class="sum-icon suspended"><i class="fas fa-ban"></i></div>
            <div>
                <div class="sum-val">{{ $counts['suspended'] }}</div>
                <div class="sum-label">Suspended</div>
            </div>
        </a>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <form method="GET" action="{{ route('admin.manageUsers') }}">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search by name, username or email…"
                    value="{{   ($search) }}">
            </div>
            <select name="type" class="filter-select">
                <option value="all" {{ $filterType === 'all' ? 'selected' : '' }}>All Types</option>
                <option value="jobseeker" {{ $filterType === 'jobseeker' ? 'selected' : '' }}>Job Seekers</option>
                <option value="employer" {{ $filterType === 'employer' ? 'selected' : '' }}>Employers</option>
            </select>
            <select name="status" class="filter-select">
                <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                <option value="Confirm" {{ $filterStatus === 'Confirm' ? 'selected' : '' }}>Confirmed</option>
                <option value="Pending" {{ $filterStatus === 'Pending' ? 'selected' : '' }}>Pending</option>
                <option value="Suspended" {{ $filterStatus === 'Suspended' ? 'selected' : '' }}>Suspended</option>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ url()->current() }}" class="btn-reset"><i class="fas fa-rotate-left"></i> Reset</a>
        </form>
    </div>

    <!-- Tabs -->
    @if($filterType == 'all')
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('all')">
            <i class="fas fa-users"></i> All
            <span class="tab-count">{{ count($seekers) + count($employers) }}</span>
        </button>
        <button class="tab-btn" onclick="showTab('seekers')">
            <i class="fas fa-user-tie"></i> Job Seekers
            <span class="tab-count">{{ count($seekers) }}</span>
        </button>
        <button class="tab-btn" onclick="showTab('employers')">
            <i class="fas fa-building"></i> Employers
            <span class="tab-count">{{ count($employers) }}</span>
        </button>
    </div>
    @endif

    @if($filterType != 'employer')
    <!-- ── Job Seekers Table ── -->
    <div class="table-card" id="tab-seekers">
        <div class="table-card-header">
            <h3><i class="fas fa-user-tie"></i> Job Seekers</h3>
            <span style="font-size:0.78rem;color:rgba(255,255,255,0.55);">{{ count($seekers) }} records</span>
        </div>
        <div class="table-wrap">
            @if($seekers->isEmpty())
            <div class="empty-state"><i class="fas fa-user-slash"></i>
                <p>No job seekers found matching your filters.</p>
            </div>
            @else
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th>Qualification</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($seekers as $u)
                    <tr id="row-jobseeker-{{ $u->id }}">
                        <td style="opacity:0.5;">{{ $u->id }}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-sm js">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                                <div>
                                    <div class="cell-name">{{ ($u->name) }}</div>
                                    <div class="cell-sub">{{ ($u->UserName) }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>{{ ($u->Email) }}</div>
                            <div class="cell-sub">{{ ($u->Mobile) }}</div>
                        </td>
                        <td>{{ ($u->City) }}</td>
                        <td>{{ ($u->Qualification) }}</td>
                        <td>{{ ($u->Gender) }}</td>
                        <td><span class="badge {{ $u->Status }}">{{ ($u->Status) }}</span></td>
                        <td>
                            <div class="action-btns">
                                @if ($u->Status !== 'Confirm')
                                <button type="button" class="btn-action btn-approve" title="Approve"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-action="confirm" data-id="{{ $u->id }}" data-table="jobseeker" data-name="{{ $u->name }}">
                                    <i class="fas fa-check"></i>
                                </button>
                                @endif
                                @if ($u->Status !== 'Suspended')
                                <button type="button" class="btn-action btn-suspend" title="Suspend"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-action="suspend" data-id="{{ $u->id }}" data-table="jobseeker" data-name="{{ $u->name }}">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @endif
                                <button type="button" class="btn-action btn-delete" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#confirmModal"
                                    data-action="delete" data-id="{{ $u->id }}" data-table="jobseeker" data-name="{{ $u->name }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif
    @if($filterType != 'jobseeker')
    <!-- ── Employers Table ── -->
    <div class="table-card" id="tab-employers">
        <div class="table-card-header">
            <h3><i class="fas fa-building"></i> Employers</h3>
            <span style="font-size:0.78rem;color:rgba(255,255,255,0.55);">{{ count($employers) }} records</span>
        </div>
        <div class="table-wrap">
            @if($employers->isEmpty())
            <div class="empty-state"><i class="fas fa-building-circle-xmark"></i>
                <p>No employers found matching your filters.</p>
            </div>
            @else
            <table>
                <thead>     
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th>Area of Work</th>
                        <th>Contact Person</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employers as $u)
                    <tr id="row-employer-{{ $u->id }}">
                        <td style="opacity:0.5;">{{ $u->id }}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-sm emp">{{ strtoupper(substr($u->name, 0, 1)) }}</div>
                                <div>
                                    <div class="cell-name">{{ $u->name }}</div>
                                    <div class="cell-sub">{{ $u->UserName }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>{{ $u->Email }}</div>
                            <div class="cell-sub">{{ $u->Mobile }}</div>
                        </td>
                        <td>{{ $u->City }}</td>
                        <td>{{ $u->Area_Work }}</td>
                        <td>{{ $u->ContactPerson }}</td>
                        <td><span class="badge {{ $u->Status }}">{{ $u->Status }}</span></td>
                        <td>
                            <div class="action-btns">
                                @if ($u->Status !== 'Confirm')
                                <button type="button" class="btn-action btn-approve" title="Approve"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmModal"
                                    data-action="confirm"
                                    data-id="{{ $u->id }}"
                                    data-table="employer"
                                    data-name="{{ $u->name }}">
                                    <i class="fas fa-check"></i>
                                </button>
                                @endif
                                @if ($u->Status !== 'Suspended')
                                <button type="button" class="btn-action btn-suspend" title="Suspend"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmModal"
                                    data-action="suspend"
                                    data-id="{{ $u->id }}"
                                    data-table="employer"
                                    data-name="{{ $u->name }}">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @endif
                                <button type="button" class="btn-action btn-delete" title="Delete"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmModal"
                                    data-action="delete"
                                    data-id="{{ $u->id }}"
                                    data-table="employer"
                                    data-name="{{ $u->name }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif


</div><!-- /main-content -->

<!-- ═══════════ CONFIRM MODAL (Bootstrap) ═══════════ -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered custom-confirm-modal">
        <div class="modal-content">

            <form method="POST" id="confirmForm">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">

                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p id="modal-desc"></p>

                    <strong id="modal-user-name"></strong>

                    <input type="hidden" name="user_action_type" id="modal-action">
                    <input type="hidden" name="id" id="modal-id">
                    <input type="hidden" name="table" id="modal-table">
                </div>

                <div class="modal-footer">
                    <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                        class="btn btn-primary"
                        id="modal-confirm-btn">

                        <span class="btn-text">Confirm</span>

                        <span class="spinner-border spinner-border-sm d-none"
                            id="confirm-loader"
                            role="status">
                        </span>

                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // =====================================================
        // TAB SWITCHING
        // =====================================================
        function showTab(tab) {

            const seekers = document.getElementById('tab-seekers');
            const employers = document.getElementById('tab-employers');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            if (tab === 'all') {

                if (seekers) seekers.style.display = '';
                if (employers) employers.style.display = '';

            } else if (tab === 'seekers') {

                if (seekers) seekers.style.display = '';
                if (employers) employers.style.display = 'none';

            } else {

                if (seekers) seekers.style.display = 'none';
                if (employers) employers.style.display = '';

            }

            const buttons = document.querySelectorAll('.tab-btn');

            if (tab === 'all' && buttons[0]) buttons[0].classList.add('active');
            if (tab === 'seekers' && buttons[1]) buttons[1].classList.add('active');
            if (tab === 'employers' && buttons[2]) buttons[2].classList.add('active');
        }


        // =====================================================
        // BOOTSTRAP MODAL
        // =====================================================

        const modalElement = document.getElementById('confirmModal');

        if (!modalElement) return;

        modalElement.addEventListener('show.bs.modal', function(event) {

            const button = event.relatedTarget;

            const action = button.dataset.action;
            const id = button.dataset.id;
            const table = button.dataset.table;
            const name = button.dataset.name;

            const form = document.getElementById('confirmForm');

            document.getElementById('modal-action').value = action;
            document.getElementById('modal-id').value = id;
            document.getElementById('modal-table').value = table;
            document.getElementById('modal-user-name').textContent = name;

            const title = document.querySelector('#confirmModal .modal-title');
            const desc = document.getElementById('modal-desc');
            const confirmBtn = document.getElementById('modal-confirm-btn');

            confirmBtn.className = "btn";

            switch (action) {

                case 'confirm':

                    title.textContent = "Approve Account";
                    desc.textContent = "Approve this account?";

                    confirmBtn.classList.add('btn-success');

                    confirmBtn.querySelector('.btn-text').textContent = "Approve";

                    form.action = `/admin/manage-users/${table}/${id}/confirm`;

                    break;


                case 'suspend':

                    title.textContent = "Suspend Account";
                    desc.textContent = "Suspend this account?";

                    confirmBtn.classList.add('btn-warning');

                    confirmBtn.querySelector('.btn-text').textContent = "Suspend";

                    form.action = `/admin/manage-users/${table}/${id}/suspend`;

                    break;


                case 'delete':

                    title.textContent = "Delete Account";
                    desc.textContent = "Delete this account permanently?";

                    confirmBtn.classList.add('btn-danger');

                    confirmBtn.querySelector('.btn-text').textContent = "Delete";

                    form.action = `/admin/manage-users/${table}/${id}`;

                    break;
            }

        });


        // =====================================================
        // AJAX SUBMIT
        // =====================================================

        const form = document.getElementById('confirmForm');

        form.addEventListener('submit', async function(e) {

            e.preventDefault();

            const loader = document.getElementById('confirm-loader');
            const btn = document.getElementById('modal-confirm-btn');
            const btnText = btn.querySelector('.btn-text');

            const action = document.getElementById('modal-action').value;

            btn.disabled = true;
            btnText.style.display = 'none';
            loader.classList.remove('d-none');

            try {

                const response = await fetch(form.action, {

                    method: action === 'delete' ? 'DELETE' : 'POST',

                    headers: {

                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,

                        'Accept': 'application/json',

                        'X-Requested-With': 'XMLHttpRequest'

                    },

                    credentials: 'same-origin',

                    body: action === 'delete' ?
                        null : new FormData(form)

                });

                if (!response.ok) {

                    const text = await response.text();

                    console.error(text);

                    throw new Error('Server returned ' + response.status);

                }

                const result = await response.json();

                if (!result.success) {

                    throw new Error(result.message);

                }

                bootstrap.Modal.getInstance(modalElement).hide();

                showToast(result.message, 'success');

                // reload while preserving current filters/search
                setTimeout(() => {

                    window.location.reload();

                }, 1200);

            } catch (err) {

                console.error(err);

                showToast(err.message || 'Something went wrong.', 'error');

            } finally {

                btn.disabled = false;

                btnText.style.display = '';

                loader.classList.add('d-none');

            }

        });


        // =====================================================
        // TOAST
        // =====================================================

        function showToast(message, type = 'success') {

            document.querySelectorAll('.toast.dynamic').forEach(t => t.remove());

            const toast = document.createElement('div');

            toast.className = `toast ${type} dynamic`;

            toast.innerHTML = `
            <i class="fas ${type==='success'
                ? 'fa-circle-check'
                : 'fa-circle-xmark'}"></i>
            ${message}
        `;

            const header = document.querySelector('.page-header');

            header.insertAdjacentElement('afterend', toast);

            requestAnimationFrame(() => {

                toast.classList.add('visible');

            });

            setTimeout(() => {

                toast.classList.remove('visible');

                setTimeout(() => {

                    toast.remove();

                }, 300);

            }, 1000);
        }

    });
</script>
@endsection