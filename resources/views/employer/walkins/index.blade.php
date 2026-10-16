@extends('layouts.employer')

@section('title','Walkins')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Page header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div class="page-header-text">
                <h1>Manage Walk-in</h1>
                <p>Schedule walk-in interviews and manage your listings</p>
            </div>
        </div>
        <a href="{{ route('employer.dashboard') }}" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Panel</a>
    </div>

    <!-- ── POST WALK-IN FORM ── -->
    <div class="section-title"><i class="fas fa-calendar-plus"></i> Schedule a Walk-in Interview</div>

    <div class="glass-card">
        <div class="form-header">
            <div class="form-header-icon"><i class="fas fa-pen-to-square"></i></div>
            <div>
                <div class="form-header-title">New Walk-in Listing</div>
                <div class="form-header-sub">Fill in the details below to publish a walk-in drive</div>
            </div>
        </div>

        <form method="POST"
            action="{{ route('employer.walkins.store') }}"
            id="walkinForm"
            onsubmit="return validateWalkin()">

            @csrf
            <div class="form-body">
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label" for="txtTitle"><i class="fas fa-heading"></i> Job Title</label>
                        <input class="form-input" type="text" name="txtTitle" id="txtTitle" placeholder="e.g. Software Developer">
                        <span class="field-error" id="err-txtTitle"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="txtTotal"><i class="fas fa-users"></i> Total Vacancy</label>
                        <input class="form-input" type="number" name="txtTotal" id="txtTotal" placeholder="e.g. 5" min="1">
                        <span class="field-error" id="err-txtTotal"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group full">
                        <label class="form-label"><i class="fas fa-graduation-cap"></i> Qualification</label>
                        <textarea class="form-textarea" name="txtQual" id="txtQual" placeholder="List required qualifications, separated by commas..."></textarea>
                        <span class="field-error" id="err-txtQual"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>



                    <div class="form-group full">
                        <label class="form-label" for="txtDesc"><i class="fas fa-align-left"></i> Description</label>
                        <textarea class="form-textarea" name="txtDesc" id="txtDesc" placeholder="Describe the role, responsibilities, and requirements..."></textarea>
                        <span class="field-error" id="err-txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="txtDate"><i class="fas fa-calendar-days"></i> Interview Date</label>
                        <input class="form-input" type="date" name="txtDate" id="txtDate">
                        <span class="field-error" id="err-txtDate"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="txtTime"><i class="fas fa-clock"></i> Interview Time</label>
                        <input class="form-input" type="time" name="txtTime" id="txtTime">
                        <span class="field-error" id="err-txtTime"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                </div>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Schedule Walk-in</button>
                <button type="reset" class="btn-reset" onclick="resetForm()"><i class="fas fa-rotate-left"></i> Clear</button>
            </div>
        </form>
    </div>

    <!-- ── LISTED WALK-INS TABLE ── -->
    <div class="section-title"><i class="fas fa-list-check"></i> Posted Walk-in Drives</div>

    <div class="glass-card delay">
        <div class="table-header">
            <div class="table-header-left">
                <div class="form-header-icon"><i class="fas fa-table-list"></i></div>
                <div>
                    <div class="table-title">Your Walk-in Listings</div>
                </div>
            </div>
            <span class="record-count">
                {{ $walkins->count() }}
                Record{{ $walkins->count() != 1 ? 's' : '' }}
            </span>
        </div>

        @if($walkins->isNotEmpty())
        <div class="table-wrap">
            <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Job Title</th>
                        <th>Vacancy</th>
                        <th>Qualification</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($walkins as $w)
                    <tr>
                        <td><span class="badge">{{ $w->WalkInId }}</span></td>
                        <td class="job-title-cell">{{ $w->JobTitle }}</td>
                        <td><span class="vacancy-badge"><i class="fas fa-user-group"></i>{{ $w->Vacancy }}</span></td>
                        <td><span class="badge">{{ $w->MinQualification }}</span></td>
                        <td class="desc-cell" title="{{ $w->Description }}">{{ $w->Description }}</td>
                        <td><span class="date-badge"><i class="fas fa-calendar-day"></i>{{ $w->InterviewDate }}</span></td>
                        <td><span class="time-badge"><i class="fas fa-clock"></i>{{ $w->InterviewTime }}</span></td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <a href="#" class="btn-edit"
                                    onclick="openEditModal(
                                    '{{ $w->WalkInId }}',
                                    '{{ $w->JobTitle }}',
                                    '{{ $w->Vacancy }}',
                                    '{{ $w->MinQualification }}',
                                    '{{ $w->Description }}',
                                    '{{ $w->InterviewDate }}',
                                    '{{ $w->InterviewTime }}',
                                    '{{ route('employer.walkins.update', $w) }}'
                                        ); return false;">
                                    <i class="fas fa-pen-to-square"></i> Edit
                                </a>
                                <a href="#" class="btn-delete"
                                    onclick="confirmDelete(
                                    '{{ $w->WalkInId }}',
                                    '{{ $w->JobTitle }}',
                                    '{{ route('employer.walkins.destroy', $w) }}'
                                   ); return false;">
                                    <i class=" fas fa-trash-can"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>No walk-in drives scheduled yet. Use the form above to add your first listing.</p>
        </div>
        @endif
    </div>

</div><!-- /main-content -->

<!-- ══ DELETE CONFIRM MODAL ══ -->
<div class="modal-overlay" id="deleteModal" onclick="handleOverlayClick(event)">
    <div class="modal">
        <div class="modal-body-confirm">
            <div class="modal-icon-danger"><i class="fas fa-trash-can"></i></div>
            <div class="modal-confirm-title">Delete Walk-in Listing?</div>
            <div class="modal-confirm-text" id="deleteModalText">This action cannot be undone.</div>
        </div>
        <div class="modal-confirm-actions">
            <button class="btn-confirm-cancel" onclick="closeDeleteModal()"><i class="fas fa-xmark"></i> Cancel</button>
            <form id="deleteForm" method="POST">

                @csrf
                @method('DELETE')

                <button type="submit"
                    class="btn-confirm-delete">
                    <i class="fas fa-trash-can"></i>
                    Yes, Delete
                </button>

            </form>
        </div>
    </div>
</div>

<!-- ══ EDIT CONFIRM MODAL ══ -->
<div class="modal-overlay" id="editModal" onclick="handleEditOverlayClick(event)">
    <div class="modal" style="max-width: 600px;">

        <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 24px 28px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.13); position: sticky; top: 0; background: rgba(102, 126, 234, 0.6); backdrop-filter: blur(12px); z-index: 2;">
            <div class="modal-header-left" style="display: flex; align-items: center; gap: 12px;">
                <div class="form-header-icon" style="width:40px;height:40px;font-size:1rem;margin:0;"><i class="fas fa-pen-to-square"></i></div>
                <div>
                    <div class="modal-title" style="font-size: 1.05rem; font-weight: 700; color: #fff;">Edit Walk-in Listing</div>
                </div>
            </div>
            <button class="btn-close" style="width: 34px; height: 34px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 9px; color: rgba(255, 255, 255, 0.7); cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; transition: all 0.18s;" onclick="closeEditModal()"><i class="fas fa-xmark"></i></button>
        </div>

        <form method="POST"
            id="editWalkinForm"
            onsubmit="return validateEditWalkin()">

            @csrf
            @method('PUT')
            <input type="hidden" name="walkinId" id="edit_walkinId">
            <div class="form-body" style="padding: 24px 28px;">
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label" for="edit_txtTitle"><i class="fas fa-heading"></i> Job Title</label>
                        <input class="form-input" type="text" name="txtTitle" id="edit_txtTitle">
                        <span class="field-error" id="err-edit_txtTitle"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_txtTotal"><i class="fas fa-users"></i> Total Vacancy</label>
                        <input class="form-input" type="number" name="txtTotal" id="edit_txtTotal" min="1">
                        <span class="field-error" id="err-edit_txtTotal"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="edit_txtQual"><i class="fas fa-graduation-cap"></i> Qualification</label>
                        <textarea class="form-textarea" name="txtQual" id="edit_txtQual"></textarea>
                        <span class="field-error" id="err-edit_txtQual"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="edit_txtDesc"><i class="fas fa-align-left"></i> Description</label>
                        <textarea class="form-textarea" name="txtDesc" id="edit_txtDesc"></textarea>
                        <span class="field-error" id="err-edit_txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_txtDate"><i class="fas fa-calendar-days"></i> Interview Date</label>
                        <input class="form-input" type="date" name="txtDate" id="edit_txtDate">
                        <span class="field-error" id="err-edit_txtDate"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_txtTime"><i class="fas fa-clock"></i> Interview Time</label>
                        <input class="form-input" type="time" name="txtTime" id="edit_txtTime">
                        <span class="field-error" id="err-edit_txtTime"><i class="fas fa-triangle-exclamation"></i> Required</span>
                    </div>

                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;padding:16px 28px 24px;border-top:1px solid rgba(255,255,255,0.13);">
                <button type="button" class="btn-confirm-cancel" onclick="closeEditModal()"><i class="fas fa-xmark"></i> Cancel</button>
                <button type="submit" class="btn-submit" style="margin:0;"><i class="fas fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('scripts')
<script>
    /* ── Form validation ── */
    function validateWalkin() {
        const required = ['txtTitle', 'txtTotal', 'txtDesc', 'txtDate', 'txtTime'];
        let valid = true;
        required.forEach(function(id) {
            const el = document.getElementById(id);
            const err = document.getElementById('err-' + id);
            if (!el.value.trim()) {
                if (err) err.classList.add('visible');
                el.style.borderColor = 'rgba(252,165,165,0.6)';
                valid = false;
            } else {
                if (err) err.classList.remove('visible');
                el.style.borderColor = '';
            }
        });
        return valid;
    }
    document.querySelectorAll('.form-input,.form-textarea,.form-select').forEach(function(el) {
        el.addEventListener('change', function() {
            const err = document.getElementById('err-' + el.id);
            if (err) err.classList.remove('visible');
            el.style.borderColor = '';
        });
        el.addEventListener('input', function() {
            const err = document.getElementById('err-' + el.id);
            if (err) err.classList.remove('visible');
            el.style.borderColor = '';
        });
    });

    function resetForm() {
        document.querySelectorAll('.field-error').forEach(e => e.classList.remove('visible'));
        document.querySelectorAll('.form-input,.form-textarea').forEach(e => e.style.borderColor = '');
    }
    document.getElementById('deleteForm').action

    function confirmDelete(id, title, deleteUrl) {
        document.getElementById('deleteModalText').textContent =
            'Are you sure you want to delete "' + title + '"?';

        document.getElementById('deleteForm').action = deleteUrl;

        document.getElementById('deleteModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('deleteModal')) closeDeleteModal();
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
            closeEditModal();
        }
    });

    /* ── Edit confirm modal ── */
    function openEditModal(id, title, vacancy, qual, desc, date, time, updateUrl) {

        document.getElementById('editWalkinForm').action = updateUrl;
        document.getElementById('edit_walkinId').value = id;
        document.getElementById('edit_txtTitle').value = title;
        document.getElementById('edit_txtTotal').value = vacancy;
        document.getElementById('edit_txtQual').value = qual;
        document.getElementById('edit_txtDesc').value = desc;
        document.getElementById('edit_txtDate').value = date;
        document.getElementById('edit_txtTime').value = time;

        document.getElementById('editModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('open');
        document.body.style.overflow = '';
    }

    function handleEditOverlayClick(e) {
        if (e.target === document.getElementById('editModal')) closeEditModal();
    }

    function validateEditWalkin() {
        const required = ['edit_txtTitle', 'edit_txtTotal', 'edit_txtQual', 'edit_txtDesc', 'edit_txtDate', 'edit_txtTime'];
        let valid = true;
        required.forEach(function(id) {
            const el = document.getElementById(id);
            const err = document.getElementById('err-' + id);
            if (!el.value.trim()) {
                if (err) err.classList.add('visible');
                el.style.borderColor = 'rgba(252,165,165,0.6)';
                valid = false;
            } else {
                if (err) err.classList.remove('visible');
                el.style.borderColor = '';
            }
        });
        return valid;
    }
</script>
@endsection