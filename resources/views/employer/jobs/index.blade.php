@extends('layouts.employer')

@section('title','Manage Jobs')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/job-requirements.css') }}?v={{ @filemtime(public_path('assets/css/job-requirements.css')) ?: 1 }}">
@endpush

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

    <form method="POST" data-ajax data-ajax-refresh-target=".cn-dashboard-main"
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

          <div class="form-group">
            <label class="form-label" for="cmbExperience"><i class="fas fa-layer-group"></i> Experience Level</label>
            <select class="form-select" name="cmbExperience" id="cmbExperience">
              <option value="">Not specified</option>
              @foreach ($experienceLevels as $slug => $label)
              <option value="{{ $slug }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="cmbArrangement"><i class="fas fa-house-laptop"></i> Work Arrangement</label>
            <select class="form-select" name="cmbArrangement" id="cmbArrangement">
              <option value="">Not specified</option>
              @foreach ($workArrangements as $option)
              <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="cmbEmployment"><i class="fas fa-file-signature"></i> Employment Type</label>
            <select class="form-select" name="cmbEmployment" id="cmbEmployment">
              <option value="">Not specified</option>
              @foreach ($employmentTypes as $option)
              <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group full">
            <label class="form-label" for="txtDesc"><i class="fas fa-align-left"></i> Description</label>
            <textarea class="form-textarea" name="txtDesc" id="txtDesc" placeholder="Describe the role, responsibilities, and requirements..."></textarea>
            <span class="field-error" id="err-txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
          </div>

          <div class="form-group full">
            <label class="form-label"><i class="fas fa-puzzle-piece"></i> Required Skills</label>
            <div class="cn-req-picker" data-cn-req-picker="required_skills" data-cn-req-kind="skills">
              <select class="js-req-select" multiple aria-label="Required skills"></select>
              <div class="cn-req-rows js-req-rows"></div>
              <input type="hidden" name="required_skills" value="{{ old('required_skills') }}">
            </div>
            <p class="cn-req-hint">Optional — but adding at least one skill helps Smart Job Match recommend your listing to the right candidates.</p>
            <span class="cn-req-error" data-cn-error-for="required_skills" id="err-required_skills"></span>
          </div>

          <div class="form-group full">
            <label class="form-label"><i class="fas fa-laptop-code"></i> Required Software</label>
            <div class="cn-req-picker" data-cn-req-picker="required_software" data-cn-req-kind="software">
              <select class="js-req-select" multiple aria-label="Required software"></select>
              <div class="cn-req-rows js-req-rows"></div>
              <input type="hidden" name="required_software" value="{{ old('required_software') }}">
            </div>
            <p class="cn-req-hint">Optional — list tools candidates should know (IDEs, design, analytics, ERP, etc.).</p>
            <span class="cn-req-error" data-cn-error-for="required_software" id="err-required_software"></span>
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
                  data-job-id="{{ $job->JobId }}"
                  data-requirements="{{ json_encode([
                      'skills' => $job->RequiredSkills ?? [],
                      'software' => $job->RequiredSoftware ?? [],
                  ]) }}"
                  data-classification="{{ json_encode([
                      'experience' => $job->ExperienceLevel,
                      'arrangement' => $job->WorkArrangement,
                      'employment' => $job->EmploymentType,
                  ]) }}"
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
      <form id="deleteForm" method="POST" data-ajax data-ajax-refresh-target=".cn-dashboard-main">
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
  <div class="modal edit-job-modal">

    <div class="modal-header">
      <div class="modal-header-left">
        <div class="form-header-icon" style="width:40px;height:40px;font-size:1rem;margin:0;"><i class="fas fa-pen-to-square"></i></div>
        <div>
          <div class="modal-title">Edit Job Listing</div>
        </div>
      </div>
      <button class="btn-close" onclick="closeEditModal()"><i class="fas fa-xmark"></i></button>
    </div>

    <form method="POST" id="editJobForm" data-ajax data-ajax-refresh-target=".cn-dashboard-main">
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

          <div class="form-group">
            <label class="form-label" for="edit_cmbExperience"><i class="fas fa-layer-group"></i> Experience Level</label>
            <select class="form-select" name="cmbExperience" id="edit_cmbExperience">
              <option value="">Not specified</option>
              @foreach ($experienceLevels as $slug => $label)
              <option value="{{ $slug }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_cmbArrangement"><i class="fas fa-house-laptop"></i> Work Arrangement</label>
            <select class="form-select" name="cmbArrangement" id="edit_cmbArrangement">
              <option value="">Not specified</option>
              @foreach ($workArrangements as $option)
              <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_cmbEmployment"><i class="fas fa-file-signature"></i> Employment Type</label>
            <select class="form-select" name="cmbEmployment" id="edit_cmbEmployment">
              <option value="">Not specified</option>
              @foreach ($employmentTypes as $option)
              <option value="{{ $option }}">{{ $option }}</option>
              @endforeach
            </select>
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_txtDesc"><i class="fas fa-align-left"></i> Description</label>
            <textarea class="form-textarea" name="txtDesc" id="edit_txtDesc"></textarea>
            <span class="field-error" id="err-edit_txtDesc"><i class="fas fa-triangle-exclamation"></i> Required</span>
          </div>

          <div class="form-group full">
            <label class="form-label"><i class="fas fa-puzzle-piece"></i> Required Skills</label>
            <div class="cn-req-picker" id="editSkillPicker" data-cn-req-picker="required_skills" data-cn-req-kind="skills">
              <select class="js-req-select" multiple aria-label="Required skills"></select>
              <div class="cn-req-rows js-req-rows"></div>
              <input type="hidden" name="required_skills" value="">
            </div>
            <p class="cn-req-hint">Optional — adding at least one skill improves Smart Job Match accuracy.</p>
            <span class="cn-req-error" data-cn-error-for="required_skills" id="err-edit_required_skills"></span>
          </div>

          <div class="form-group full">
            <label class="form-label"><i class="fas fa-laptop-code"></i> Required Software</label>
            <div class="cn-req-picker" id="editSoftwarePicker" data-cn-req-picker="required_software" data-cn-req-kind="software">
              <select class="js-req-select" multiple aria-label="Required software"></select>
              <div class="cn-req-rows js-req-rows"></div>
              <input type="hidden" name="required_software" value="">
            </div>
            <p class="cn-req-hint">Optional — tools candidates should know for this role.</p>
            <span class="cn-req-error" data-cn-error-for="required_software" id="err-edit_required_software"></span>
          </div>

        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;padding:16px 28px 24px;border-top:1px solid var(--theme-border);">
        <button type="button" class="btn-confirm-cancel" onclick="closeEditModal()"><i class="fas fa-xmark"></i> Cancel</button>
        <button type="submit" class="btn-submit" style="margin:0;"><i class="fas fa-floppy-disk"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>
@endsection
@push('styles')
<style>
  /* Keep the edit form and its footer inside the viewport; only the form scrolls. */
  #editModal .edit-job-modal {
    display: flex !important;
    width: min(600px, 100%) !important;
    max-width: 600px !important;
    height: auto !important;
    max-height: calc(100vh - 40px) !important;
    overflow: hidden !important;
  }

  #editModal #editJobForm {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  #editModal #editJobForm > .form-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
  }

  #editModal .cn-req-picker,
  #editModal .cn-req-picker .ts-wrapper,
  #editModal .cn-req-picker .ts-control {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }

  #editModal .cn-req-picker .ts-control {
    flex-wrap: wrap;
    min-width: 0;
  }

  #editModal .cn-req-picker .ts-control > input {
    min-width: 5rem;
  }

  #editModal .cn-req-picker .ts-dropdown {
    max-width: 100%;
  }

  @media (max-width: 480px) {
    #editModal .edit-job-modal {
      max-height: calc(100vh - 16px) !important;
    }

    #editModal #editJobForm > div:last-child {
      padding: 12px 14px !important;
    }
  }
</style>
@endpush
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="{{ asset('assets/js/job-requirements.js') }}?v={{ @filemtime(public_path('assets/js/job-requirements.js')) ?: 1 }}"></script>
<script>
  // ── "Other" qualification field toggle ──
  function initializeJobQualificationInputs() {
    const qualification = document.getElementById('cmbQual');
    const otherInput = document.getElementById('txtOther');
    const otherGroup = document.getElementById('otherGroup');
    if (qualification && otherInput && otherGroup) {
      otherInput.disabled = qualification.value !== 'Other';
      otherGroup.style.opacity = qualification.value === 'Other' ? '1' : '0.5';
    }
  }
  initializeJobQualificationInputs();
  document.addEventListener('cn:region-updated', initializeJobQualificationInputs);

  document.addEventListener('change', function(event) {
    if (event.target.id === 'cmbQual') {
      document.getElementById('otherGroup').style.opacity = event.target.value === 'Other' ? '1' : '0.5';
      document.getElementById('txtOther').disabled = event.target.value !== 'Other';
    }
    if (event.target.id === 'edit_cmbQual') {
      document.getElementById('edit_otherGroup').style.opacity = event.target.value === 'Other' ? '1' : '0.5';
      document.getElementById('edit_txtOther').disabled = event.target.value !== 'Other';
    }
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
  document.addEventListener('input', function(event) {
      const el = event.target;
      if (!el.matches('.form-input,.form-textarea')) return;
      const err = document.getElementById('err-' + el.id);
      if (err) err.classList.remove('visible');
      el.style.borderColor = '';
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

    // Load this job's saved requirement entries into the modal pickers.
    // They are read from the row itself because an AJAX refresh replaces the
    // table, so anything cached outside that region can go stale.
    var trigger = document.querySelector('.btn-edit[data-job-id="' + id + '"]');
    var saved = {};

    try {
      saved = JSON.parse((trigger && trigger.dataset.requirements) || '{}');
    } catch (error) {
      saved = {};
    }

    if (window.CareerNextRequirementPickers) {
      window.CareerNextRequirementPickers.load('#editSkillPicker', saved.skills);
      window.CareerNextRequirementPickers.load('#editSoftwarePicker', saved.software);
    }

    // The classification selects are read from the same row for the same reason.
    var classification = {};

    try {
      classification = JSON.parse((trigger && trigger.dataset.classification) || '{}');
    } catch (error) {
      classification = {};
    }

    setSelectOption('edit_cmbExperience', classification.experience);
    setSelectOption('edit_cmbArrangement', classification.arrangement);
    setSelectOption('edit_cmbEmployment', classification.employment);
  }

  function setSelectOption(id, value) {
    const select = document.getElementById(id);

    if (select) {
      select.value = value || '';
    }
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
