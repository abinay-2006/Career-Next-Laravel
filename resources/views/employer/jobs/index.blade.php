@extends('layouts.employer')

@section('title','Manage Jobs')

@section('content')

<!-- ══ MAIN ══ -->
<div class="main-content">

  <!-- Page header -->
  <div class="page-header">
    <div class="page-header-left">
      <div class="page-header-icon"><i class="fas fa-list-check"></i></div>
      <div class="page-header-text">
        <h1>Manage Jobs</h1>
        <p>Post new listings and manage your existing vacancies</p>
      </div>
    </div>
  </div>

  <!-- ── POST JOB FORM ── -->
  <div class="section-title"><i class="fas fa-plus-circle"></i> Post a New Job</div>

  <div class="glass-card">
    <div class="form-header">
      <div class="form-header-icon"><i class="fas fa-pen-to-square"></i></div>
      <div>
        <div class="form-header-title">New Job Listing</div>
        <div class="form-header-sub">Fill in the details below to publish a vacancy</div>
      </div>
    </div>

    <form method="POST"
      action="{{ route('employer.jobs.store') }}"
      id="postJobForm"
      onsubmit="return validateJob()">
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
            <input class="form-input" type="number" name="txtTotal" id="txtTotal" placeholder="e.g. 3" min="1">
            <span class="field-error" id="err-txtTotal"><i class="fas fa-triangle-exclamation"></i> Required</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="txtSalaryFrom"><i class="fas fa-money-bill-wave"></i> Salary From</label>
            <input class="form-input" type="number" name="txtSalaryFrom" id="txtSalaryFrom" placeholder="e.g. 30000" min="0">
          </div>

          <div class="form-group">
            <label class="form-label" for="txtSalaryTo"><i class="fas fa-money-bill-wave"></i> Salary Upto</label>
            <input class="form-input" type="number" name="txtSalaryTo" id="txtSalaryTo" placeholder="e.g. 50000" min="0">
          </div>

          <div class="form-group">
            <label class="form-label" for="cmbQual"><i class="fas fa-graduation-cap"></i> Qualification</label>
            <select class="form-select" name="cmbQual" id="cmbQual">
              @foreach ($degrees as $degree)
              <option value="{{ $degree }}">{{ $degree }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group" id="otherGroup">
            <label class="form-label" for="txtOther"><i class="fas fa-pen"></i> Other Qualification</label>
            <input class="form-input" type="text" name="txtOther" id="txtOther" placeholder="Specify if Other selected">
          </div>

          <div class="form-group full">
            <label class="form-label" for="txtDesc"><i class="fas fa-align-left"></i> Description</label>
            <textarea class="form-textarea" name="txtDesc" id="txtDesc" placeholder="Describe the role, responsibilities, and requirements..."></textarea>
            <span class="field-error" id="err-txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
          </div>

        </div>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Post Job</button>
        <button type="reset" class="btn-reset"><i class="fas fa-rotate-left"></i> Clear</button>
      </div>
    </form>
  </div>

  <!-- ── POSTED JOBS TABLE ── -->
  <div class="section-title"><i class="fas fa-briefcase"></i> Posted Jobs</div>

  <div class="glass-card delay">
    <div class="table-header">
      <div class="table-header-left">
        <div class="form-header-icon"><i class="fas fa-table-list"></i></div>
        <div>
          <div class="table-title">Your Job Listings</div>
        </div>
      </div>
      <span class="record-count">{{ $totalRecords }} Record{{ $totalRecords !== 1 ? 's' : '' }}</span>
    </div>

    @if ($totalRecords > 0)
    <div class="table-wrap">
      <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="jobs-table">
        <thead>
          <tr>
            <th>Job Title</th>
            <th>Vacancy</th>
            <th>Qualification</th>
            <th>Description</th>
            <th>Salary</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>

          @foreach($jobs as $job)

          <tr>

            <td>{{ $job->JobTitle }}</td>

            <td>{{ $job->Vacancy }}</td>

            <td>{{ $job->MinQualification }}</td>

            <td>{{ $job->Description }}</td>

            <td>
              <div>From: {{ $job->SalaryFrom }}</div>
              <div>Upto: {{ $job->SalaryTo }}</div>
            </td>

            <td>
              @if(strtolower($job->Status) == 'approved')
              <span class="status-badge approved">
                {{ ucfirst($job->Status) }}
              </span>
              @elseif(strtolower($job->Status) == 'rejected')
              <span class="status-badge rejected">
                {{ ucfirst($job->Status) }}
              </span>
              @else
              <span class="status-badge pending">
                {{ ucfirst($job->Status) }}
              </span>
              @endif
            </td>
            <td>
              <div style="display: flex; gap: 8px;">
                <a href="#" class="btn-edit"
                  onclick="openEditModal('{{ $job->JobId }}', '{{ $job->JobTitle }}', '{{ $job->Vacancy }}', '{{ $job->MinQualification }}', '{{ $job->Description }}', '{{ $job->SalaryFrom }}', '{{ $job->SalaryTo }}'); return false;">
                  <i class="fas fa-pen-to-square"></i> Edit
                </a>
                <a href="#" class="btn-delete"
                  onclick="confirmDelete('{{ $job->JobId }}', '{{ $job->JobTitle }}'); return false;">
                  <i class="fas fa-trash-can"></i> Delete
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
      <i class="fas fa-briefcase"></i>
      <p>No jobs posted yet. Use the form above to add your first listing.</p>
    </div>
    @endif
  </div>

</div><!-- /main-content -->
<!-- ══ DELETE CONFIRM MODAL ══ -->
<div class="modal-overlay" id="deleteModal" onclick="handleOverlayClick(event)">
  <div class="modal">
    <div class="modal-body-confirm">
      <div class="modal-icon-danger"><i class="fas fa-trash-can"></i></div>
      <div class="modal-confirm-title">Delete Job Listing?</div>
      <div class="modal-confirm-text" id="deleteModalText">
        This action cannot be undone.
      </div>
    </div>
    <div class="modal-confirm-actions">
      <button class="btn-confirm-cancel" onclick="closeDeleteModal()">
        <i class="fas fa-xmark"></i> Cancel
      </button>
      <form id="deleteForm" method="POST">
        @csrf
        @method('DELETE')

        <button type="submit" class="btn-confirm-delete">
          <i class="fas fa-trash-can"></i> Yes, Delete
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ══ EDIT CONFIRM MODAL ══ -->
<div class="modal-overlay" id="editModal" onclick="handleEditOverlayClick(event)">
  <div class="modal" style="max-width: 600px;">

    <div class="modal-header">
      <div class="modal-header-left">
        <div class="form-header-icon" style="width:40px;height:40px;font-size:1rem;margin:0;"><i class="fas fa-pen-to-square"></i></div>
        <div>
          <div class="modal-title">Edit Job Listing</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeEditModal()"><i class="fas fa-xmark"></i></button>
    </div>

    <form method="POST" id="editJobForm">
      @csrf
      @method('PUT')

      <input type="hidden" name="JobId" id="edit_JobId">
      <div class="form-body">
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

          <div class="form-group">
            <label class="form-label" for="edit_txtSalaryFrom"><i class="fas fa-money-bill-wave"></i> Salary From</label>
            <input class="form-input" type="number" name="txtSalaryFrom" id="edit_txtSalaryFrom" placeholder="e.g. 30000" min="0">
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_txtSalaryTo"><i class="fas fa-money-bill-wave"></i> Salary Upto</label>
            <input class="form-input" type="number" name="txtSalaryTo" id="edit_txtSalaryTo" placeholder="e.g. 50000" min="0">
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_cmbQual"><i class="fas fa-graduation-cap"></i> Qualification</label>
            <select class="form-select" name="cmbQual" id="edit_cmbQual">
              @foreach($degrees as $degree)

              <option value="{{ $degree }}">
                {{ $degree }}
              </option>

              @endforeach
            </select>
          </div>

          <div class="form-group" id="edit_otherGroup">
            <label class="form-label" for="edit_txtOther"><i class="fas fa-pen"></i> Other Qualification</label>
            <input class="form-input" type="text" name="txtOther" id="edit_txtOther" placeholder="Specify if Other selected">
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_txtDesc"><i class="fas fa-align-left"></i> Description</label>
            <textarea class="form-textarea" name="txtDesc" id="edit_txtDesc"></textarea>
            <span class="field-error" id="err-edit_txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
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
  // ── "Other" qualification field toggle ──
  document.getElementById('cmbQual').addEventListener('change', function() {
    document.getElementById('otherGroup').style.opacity = this.value === 'Other' ? '1' : '0.5';
    document.getElementById('txtOther').disabled = (this.value !== 'Other');
  });
  // init
  document.getElementById('txtOther').disabled = true;
  document.getElementById('otherGroup').style.opacity = '0.5';

  document.getElementById('edit_cmbQual').addEventListener('change', function() {
    const otherGroup = document.getElementById('edit_otherGroup');
    otherGroup.style.opacity = this.value === 'Other' ? '1' : '0.5';
    document.getElementById('edit_txtOther').disabled = (this.value !== 'Other');
  });

  // ── Form validation ──
  function validateJob() {
    const fields = ['txtTitle', 'txtTotal', 'txtDesc'];
    let valid = true;
    fields.forEach(function(id) {
      const el = document.getElementById(id);
      const err = document.getElementById('err-' + id);
      if (!el.value.trim()) {
        err.classList.add('visible');
        el.style.borderColor = 'rgba(252,165,165,0.6)';
        valid = false;
      } else {
        err.classList.remove('visible');
        el.style.borderColor = '';
      }
    });
    return valid;
  }

  function validateEditJob() {
    const fields = ['edit_txtTitle', 'edit_txtTotal', 'edit_txtDesc'];
    let valid = true;
    fields.forEach(function(id) {
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
  document.querySelectorAll('.form-input,.form-textarea').forEach(function(el) {
    el.addEventListener('input', function() {
      const err = document.getElementById('err-' + el.id);
      if (err) err.classList.remove('visible');
      el.style.borderColor = '';
    });
  });

  // ── Delete confirm modal ──
  function confirmDelete(jobId, jobTitle) {
    document.getElementById('deleteModalText').textContent =
      'Are you sure you want to delete "' + jobTitle + '"? This cannot be undone.';
    document.getElementById('deleteForm').action =
    "{{ url('employer/jobs') }}/" + jobId;
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

  // ── Edit confirm modal ──
  function openEditModal(id, title, vacancy, qual, desc, salaryFrom, salaryTo) {
    document.getElementById('editJobForm').action =
      "{{ url('employer/jobs') }}/" + id;
    document.getElementById('edit_JobId').value = id;
    document.getElementById('edit_txtTitle').value = title;
    document.getElementById('edit_txtTotal').value = vacancy;
    document.getElementById('edit_txtDesc').value = desc;
    document.getElementById('edit_txtSalaryFrom').value = salaryFrom;
    document.getElementById('edit_txtSalaryTo').value = salaryTo;

    const qualSelect = document.getElementById('edit_cmbQual');
    const otherInput = document.getElementById('edit_txtOther');
    const otherGroup = document.getElementById('edit_otherGroup');

    let found = false;
    for (let i = 0; i < qualSelect.options.length; i++) {
      if (qualSelect.options[i].value === qual) {
        qualSelect.selectedIndex = i;
        found = true;
        break;
      }
    }

    if (!found) {
      qualSelect.value = 'Other';
      otherInput.value = qual;
      otherInput.disabled = false;
      otherGroup.style.opacity = '1';
    } else {
      otherInput.value = '';
      otherInput.disabled = true;
      otherGroup.style.opacity = '0.5';
    }

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

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeDeleteModal();
      closeEditModal();
    }
  });
</script>
@endsection