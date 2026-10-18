@extends('layouts.jobseeker')

@section('title', 'Education')

@section('content')

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-icon"><i class="fas fa-pen-to-square"></i></div>
            <div>
                <div class="modal-title">Edit Qualification</div>
                <div class="modal-subtitle">Update your education record below</div>
            </div>
        </div>

        <form method="POST" id="editEducationForm" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="eduId" id="modal_eduId">
            <div class="form-grid">

                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> Select Degree</label>
                    <select name="cmbQual"
                        id="modal_cmbQual"
                        onchange="toggleOtherInput(this,'modal_txtOther')">

                        @foreach($degrees as $degree)
                        <option value="{{ $degree }}">
                            {{ $degree }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-pen"></i> Other Degree</label>
                    <input type="text" name="txtOther" id="modal_txtOther" placeholder="If Other selected" disabled>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-university"></i> University / Board <span style="color:#fca5a5">*</span></label>
                    <select name="txtBoard" id="modal_txtBoard">
                        @foreach($boards as $board)
                        <option value="{{ $board }}">{{ $board }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Passing Year</label>
                    <select name="cmbYear" id="modal_cmbYear">
                        @foreach($higherYears as $year)
                        <option value="{{ $year }}">
                            {{ $year }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group full">
                    <label><i class="fas fa-percent"></i> Percentage (%) <span style="color:#fca5a5">*</span></label>
                    <input type="text" name="txtPer" id="modal_txtPer" placeholder="e.g. 78.50" required>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()"><i class="fas fa-xmark"></i> Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT SCHOOL MODAL ══ -->
<div class="modal-overlay" id="editSchoolModal">
    <div class="modal">
        <button class="modal-close" onclick="closeSchoolModal()"><i class="fas fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-icon"><i class="fas fa-school"></i></div>
            <div>
                <div class="modal-title">Edit School Education</div>
                <div class="modal-subtitle">Update your school education record below</div>
            </div>
        </div>

        <form method="POST" id="editSchoolForm" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="update_school">
            <input type="hidden" name="schoolId" id="modal_schoolId">
            <div class="form-grid">

                <div class="form-group">
                    <label><i class="fas fa-school"></i> School Name <span style="color:#fca5a5">*</span></label>
                    <input type="text" name="txtSchoolName" id="modal_txtSchoolName" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> Qualification</label>
                    <select name="cmbSchoolQual" id="modal_cmbSchoolQual" onchange="toggleOtherInput(this, 'modal_txtSchoolOther')">
                        @foreach($schoolQualifications as $qualification)
                        <option value="{{ $qualification }}">
                            {{ $qualification }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-pen"></i> Other Qualification</label>
                    <input type="text" name="txtSchoolOther" id="modal_txtSchoolOther" placeholder="Specify if Other selected" disabled>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-university"></i> Board Name <span style="color:#fca5a5">*</span></label>
                    <input type="text" name="txtSchoolBoard" id="modal_txtSchoolBoard" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Passing Year</label>
                    <select name="cmbSchoolYear" id="modal_cmbSchoolYear">
                        @foreach($schoolYears as $year)
                        <option value="{{ $year }}">
                            {{ $year }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Percentage (%) <span style="color:#fca5a5">*</span></label>
                    <input type="text" name="txtSchoolPer" id="modal_txtSchoolPer" required>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeSchoolModal()"><i class="fas fa-xmark"></i> Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MAIN ══ -->
<div class="main-content">

    <div class="page-header">
        <div class="page-header-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="page-header-text">
            <h1>Education Qualification</h1>
            <p>Manage your academic credentials and qualifications</p>
        </div>
    </div>

    <!-- Add Form -->
    <div class="section-title">
        <i class="fas fa-plus-circle"></i> Add Higher Education
    </div>

    <div class="glass-card">
        <form method="POST" action="{{ route('jobseeker.education.store') }}">
            @csrf

            <div class="form-grid">

                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> Select Degree</label>

                    <select name="cmbQual" id="cmbQual"
                        onchange="toggleOtherInput(this,'txtOther')">

                        @foreach($degrees as $degree)
                        <option value="{{ $degree }}">
                            {{ $degree }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-pen"></i> Other Degree</label>

                    <input type="text"
                        name="txtOther"
                        id="txtOther"
                        placeholder="Specify if Other selected"
                        disabled>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-university"></i> University / Board</label>

                    <select name="txtBoard" id="txtBoard">

                        @foreach($boards as $board)
                        <option value="{{ $board }}">
                            {{ $board }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Passing Year</label>

                    <select name="cmbYear">

                        @foreach($higherYears as $year)
                        <option value="{{ $year }}">
                            {{ $year }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Percentage</label>

                    <input type="text"
                        name="txtPer"
                        placeholder="78.50"
                        required>
                </div>



            </div>
            <div class="form-group" style="justify-content:flex-end;align-items:flex-end">
                <button class="btn-submit">
                    <i class="fas fa-plus"></i>
                    Add Qualification
                </button>
            </div>
        </form>
    </div>

    <!-- Add Form (School Education) -->
    <div class="section-title" style="margin-top:40px;">
        <i class="fas fa-school"></i>
        Add School Education
    </div>

    <div class="glass-card">

        <form method="POST" action="{{ route('jobseeker.school.store') }}">
            @csrf

            <div class="form-grid">

                <div class="form-group">
                    <label><i class="fas fa-school"></i> School Name</label>

                    <input
                        type="text"
                        name="txtSchoolName"
                        placeholder="School Name"
                        required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-user-graduate"></i> Qualification</label>

                    <select
                        name="cmbSchoolQual"
                        onchange="toggleOtherInput(this,'txtSchoolOther')">

                        @foreach($schoolQualifications as $qualification)
                        <option value="{{ $qualification }}">
                            {{ $qualification }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-pen"></i> Other Qualification</label>

                    <input
                        type="text"
                        name="txtSchoolOther"
                        id="txtSchoolOther"
                        disabled>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-university"></i> Board</label>

                    <input
                        type="text"
                        name="txtSchoolBoard"
                        placeholder="CBSE / ICSE / State Board"
                        required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Passing Year</label>

                    <select name="cmbSchoolYear">

                        @foreach($schoolYears as $year)
                        <option value="{{ $year }}">
                            {{ $year }}
                        </option>
                        @endforeach

                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-percent"></i> Percentage</label>

                    <input
                        type="text"
                        name="txtSchoolPer"
                        placeholder="85.00"
                        required>
                </div>



            </div>
            <div class="form-group" style="justify-content:flex-end;align-items:end">
                <button class="btn-submit">
                    <i class="fas fa-plus"></i>

                    Add School Record
                </button>
            </div>
        </form>

    </div>

    <!-- Add Form (Document Upload) -->
    <div class="section-title" style="margin-top: 40px;"><i class="fas fa-upload"></i> Upload Documents</div>

    <div class="glass-card">
        <form method="post" action="{{ route('jobseeker.documents.store') }}" enctype="multipart/form-data" onsubmit="return validateDocumentUpload(this)">
            @csrf

            <div class="form-grid">

                <div class="form-group">
                    <label><i class="fas fa-file-alt"></i> Document Type <span style="color:#fca5a5">*</span></label>
                    <select name="cmbDocumentType" id="cmbDocumentType" required>
                        <option value="">Select Document Type</option>
                        <option value="resume">Resume</option>
                        <option value="degree_certificate">Degree Certificate</option>
                        <option value="transcript">Transcript</option>
                        <option value="mark_sheet">Mark Sheet</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-file-upload"></i> Choose File <span style="color:#fca5a5">*</span></label>
                    <input type="file" name="fileDocument" id="fileDocument" accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocument(this)" required>
                    <small style="color:rgba(255,255,255,.6);font-size:.75rem;margin-top:4px;">PDF, JPG, PNG only (Max 5MB)</small>
                </div>

                <div class="form-group full">
                    <label><i class="fas fa-eye"></i> File Preview</label>
                    <div id="documentPreview" style="background:rgba(255,255,255,.08);border:1px dashed rgba(255,255,255,.2);border-radius:8px;min-height:100px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:.9rem;">
                        No file selected
                    </div>
                </div>

                <div class="form-group full" style="justify-content:flex-end;align-items:flex-end">
                    <button type="submit" class="btn-submit" id="uploadDocumentBtn">
                        <i class="fas fa-cloud-upload-alt"></i> Upload Document
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- Documents Table -->


    <!-- Records Table -->
    <div class="section-title" style="margin-top: 40px;"><i class="fas fa-list"></i> Higher Education Records</div>

    <div class="glass-card" style="padding:0;overflow:hidden">
        @if($rows_edu->count())
        <div class="edu-table-wrap">
            <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="edu-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-user-graduate" style="margin-right:6px;opacity:.7"></i>Degree</th>
                        <th><i class="fas fa-university" style="margin-right:6px;opacity:.7"></i>University / Board</th>
                        <th><i class="fas fa-calendar" style="margin-right:6px;opacity:.7"></i>Passing Year</th>
                        <th><i class="fas fa-percent" style="margin-right:6px;opacity:.7"></i>Percentage</th>
                        <th style="text-align:center"><i class="fas fa-sliders" style="margin-right:6px;opacity:.7"></i>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows_edu as $row)
                    <tr>
                        <td>
                            <span class="degree-badge">
                                <i class="fas fa-certificate" style="font-size:.7rem;opacity:.7"></i>
                                {{ $row->Degree }}
                            </span>
                        </td>
                        <td>{{ $row['University'] }}</td>
                        <td>{{ $row['PassingYear'] }}</td>
                        <td><span class="percent-pill">{{ $row['Percentage'] }}%</span></td>
                        <td style="text-align:center">
                            <div style="display: flex; justify-content: center; gap: 4px;">
                                <button class="btn-edit" onclick="openEditModal(
                      '{{ $row['EduId'] ?? $row['Id'] ?? '' }}',
                      '{{ $row->Degree }}',
                      '{{ $row['University'] }}',
                      '{{ $row['PassingYear'] }}',
                      '{{ $row['Percentage'] }}'
                    )">
                                    <i class="fas fa-pen-to-square"></i> Edit
                                </button>
                                <button class="btn-edit" onclick="confirmDelete(
'{{ route('jobseeker.education.destroy',$row->EduId) }}',
'{{ $row->Degree }}'
)" style="background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.3);color:#fca5a5;">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
            <div class="table-footer">
                <i class="fas fa-info-circle"></i> Total {{ $records_edu }} record{{ $records_edu !== 1 ? 's' : '' }} found
            </div>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No educational records found.<br>Add your first qualification using the form above.</p>
        </div>
        @endif
    </div>

    <div class="section-title" style="margin-top: 40px;">
        <i class="fas fa-school"></i> School Education Records
    </div>

    <div class="glass-card" style="padding:0;overflow:hidden">
        @if (count($rows_school) > 0)
        <div class="edu-table-wrap">
            <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="edu-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-school" style="margin-right:6px;opacity:.7"></i>School Name</th>
                        <th><i class="fas fa-user-graduate" style="margin-right:6px;opacity:.7"></i>Qualification</th>
                        <th><i class="fas fa-university" style="margin-right:6px;opacity:.7"></i>Board</th>
                        <th><i class="fas fa-calendar" style="margin-right:6px;opacity:.7"></i>Passing Year</th>
                        <th><i class="fas fa-percent" style="margin-right:6px;opacity:.7"></i>Percentage</th>
                        <th style="text-align:center"><i class="fas fa-sliders" style="margin-right:6px;opacity:.7"></i>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows_school as $s_row)

                    <tr>
                        <td>{{ $s_row['SchoolName'] }}</td>
                        <td>
                            <span class="degree-badge">
                                <i class="fas fa-certificate" style="font-size:.7rem;opacity:.7"></i>
                                {{ $s_row['Qualification'] }}
                            </span>
                        </td>
                        <td>{{ $s_row['Board'] }}</td>
                        <td>{{ $s_row['YearOfPassing'] }}</td>
                        <td>
                            <span class="percent-pill">
                                {{ $s_row['Percentage'] }}%
                            </span>
                        </td>
                        <td style="text-align:center">
                            <div style="display: flex; justify-content: center; gap: 4px;">
                                <button class="btn-edit"
                                    onclick="openSchoolEditModal(
'{{ $s_row->id }}',
'{{ $s_row->SchoolName }}',
'{{ $s_row->Qualification }}',
'{{ $s_row->Board }}',
'{{ $s_row->YearOfPassing }}',
'{{ $s_row->Percentage }}'
)">
                                    <i class="fas fa-pen-to-square"></i> Edit
                                </button>
                                <button class="btn-edit" onclick="confirmDelete(
'{{ route('jobseeker.school.destroy',$s_row->id) }}',
'{{ $s_row->SchoolName }}'
)" style="background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.3);color:#fca5a5;">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
            <div class="table-footer">
                <i class="fas fa-info-circle"></i>
                Total {{ $records_school }} record{{ $records_school !== 1 ? 's' : '' }}found
            </div>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-school"></i>
            <p>No school education records found.<br>Add your SSC/HSC details using the form above.</p>
        </div>
        @endif
    </div>
    <div class="section-title" style="margin-top: 40px;"><i class="fas fa-folder-open"></i> My Documents</div>

    <div class="glass-card" style="padding:0;overflow:hidden">
        @if (count($rows_docs) > 0)
        <div class="edu-table-wrap">
            <div class="responsive-table-wrapper" role="region" aria-label="Scrollable data table" tabindex="0">
<table class="edu-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-file-alt" style="margin-right:6px;opacity:.7"></i>Document Type</th>
                        <th><i class="fas fa-file" style="margin-right:6px;opacity:.7"></i>File Name</th>
                        <th><i class="fas fa-calendar-alt" style="margin-right:6px;opacity:.7"></i>Upload Date</th>
                        <th><i class="fas fa-check-circle" style="margin-right:6px;opacity:.7"></i>Status</th>
                        <th style="text-align:center"><i class="fas fa-cogs" style="margin-right:6px;opacity:.7"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows_docs as $doc_row)
                    <tr>
                        <td>{{ ucwords(str_replace('_', ' ', $doc_row['DocumentType'])) }}</td>
                        <td>{{ $doc_row['OriginalFileName'] }}</td>
                        <td>{{ $doc_row['UploadDate'] }}</td>
                        <td>{{ $doc_row['VerificationStatus'] }}</td>
                        <td style="text-align:center">
                            <div style="display: flex; justify-content: center; gap: 4px;">
                                <a href="{{ route('jobseeker.documents.view', $doc_row->DocId) }}"
                                    target="_blank"
                                    class="btn-edit"
                                    style="background:rgba(74,222,128,.2);border-color:rgba(74,222,128,.3);color:#86efac;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button class="btn-edit"
                                onclick="confirmDelete(
'{{ route('jobseeker.documents.destroy',$doc_row->DocId) }}',
'{{ addslashes($doc_row->OriginalFileName) }}'
)"
                                    style="background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.3);color:#fca5a5;">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
</div>
            <div class="table-footer">
                <i class="fas fa-info-circle"></i>
                Total {{ $records_docs }} document{{ $records_docs !== 1 ? 's' : '' }} found
            </div>
        </div>
        @else
        <div class="empty-state">
            <i class="fas fa-file-upload"></i>
            <p>No documents uploaded yet.<br>Upload your degree certificates, transcripts, or mark sheets.</p>
        </div>
        @endif
    </div>
</div><!-- /main-content -->

<!-- ══ DELETE MODAL ══ -->
<div class="modal-overlay" id="deleteModal" onclick="handleOverlayClick(event)">
    <div class="modal delete-modal">

        <div class="delete-header">
            <div class="delete-icon">
                <i class="fas fa-trash"></i>
            </div>
            <h2>Delete Record</h2>
        </div>

        <p class="delete-text" id="deleteModalText">
            This will permanently delete the record. This cannot be undone.
        </p>

        <div class="delete-actions">
            <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form id="deleteForm" method="POST">
                @csrf
                @method('DELETE')

                <button type="submit" class="btn-delete">
                    Delete
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
@section('scripts')
<script>
    /* ── Toggle "Other" Qualification Inputs ── */
    function toggleOtherInput(selectElement, targetInputId) {
        const targetInput = document.getElementById(targetInputId);
        if (selectElement.value === 'Other') {
            targetInput.disabled = false;
            targetInput.required = true;
            targetInput.focus();
        } else {
            targetInput.disabled = true;
            targetInput.required = false;
            targetInput.value = '';
        }
    }


    /* ── Edit modal ── */
    function openEditModal(id, degree, university, year, percentage) {
        document.getElementById('editEducationForm').action =
            "{{ url('jobseeker/education') }}/" + id;
        document.getElementById('modal_eduId').value = id;
        document.getElementById('modal_txtPer').value = percentage;

        // Set degree select
        const degSel = document.getElementById('modal_cmbQual');
        let matched = false;
        for (let opt of degSel.options) {
            if (opt.value.trim().toLowerCase() === degree.trim().toLowerCase()) {
                opt.selected = true;
                matched = true;
                break;
            }
        }
        if (!matched) {
            degSel.value = 'Other';
            document.getElementById('modal_txtOther').value = degree;
            document.getElementById('modal_txtOther').disabled = false;
            document.getElementById('modal_txtOther').required = true;
        } else {
            document.getElementById('modal_txtOther').value = '';
            document.getElementById('modal_txtOther').disabled = true;
            document.getElementById('modal_txtOther').required = false;
        }

        // Set board select
        const boardSel = document.getElementById('modal_txtBoard');
        let boardMatched = false;
        for (let opt of boardSel.options) {
            if (opt.value.trim().toLowerCase() === university.trim().toLowerCase()) {
                opt.selected = true;
                boardMatched = true;
                break;
            }
        }
        if (!boardMatched && university.trim() !== '') {
            const newOpt = document.createElement('option');
            newOpt.value = university;
            newOpt.textContent = university;
            newOpt.selected = true;
            boardSel.insertBefore(newOpt, boardSel.lastElementChild);
        }

        // Set year select
        const yearSel = document.getElementById('modal_cmbYear');
        for (let opt of yearSel.options) {
            if (opt.value === year) {
                opt.selected = true;
                break;
            }
        }

        document.getElementById('editModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Close modal on overlay click
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    /* ── Edit school modal ── */
    function openSchoolEditModal(id, name, qual, board, year, percentage) {

        document.getElementById('editSchoolForm').action =
            "{{ url('jobseeker/school') }}/" + id;
        document.getElementById('modal_schoolId').value = id;

        document.getElementById('modal_txtSchoolName').value = name;
        document.getElementById('modal_txtSchoolBoard').value = board;
        document.getElementById('modal_txtSchoolPer').value = percentage;

        const qualSel = document.getElementById('modal_cmbSchoolQual');
        let matched = false;
        for (let opt of qualSel.options) {
            if (opt.value.trim().toLowerCase() === qual.trim().toLowerCase()) {
                opt.selected = true;
                matched = true;
                break;
            }
        }
        if (!matched) {
            qualSel.value = 'Other';
            document.getElementById('modal_txtSchoolOther').value = qual;
            document.getElementById('modal_txtSchoolOther').disabled = false;
            document.getElementById('modal_txtSchoolOther').required = true;
        } else {
            document.getElementById('modal_txtSchoolOther').value = '';
            document.getElementById('modal_txtSchoolOther').disabled = true;
            document.getElementById('modal_txtSchoolOther').required = false;
        }

        const yearSel = document.getElementById('modal_cmbSchoolYear');
        for (let opt of yearSel.options) {
            if (opt.value === year) {
                opt.selected = true;
                break;
            }
        }

        document.getElementById('editSchoolModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSchoolModal() {
        document.getElementById('editSchoolModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Close school modal on overlay click
    document.getElementById('editSchoolModal').addEventListener('click', function(e) {
        if (e.target === this) closeSchoolModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeSchoolModal();
            closeDeleteModal();
        }
    });
    /* ── Document Upload Preview and Validation ── */
    function previewDocument(input) {
        const previewDiv = document.getElementById('documentPreview');
        const file = input.files[0];
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        const maxSize = 5 * 1024 * 1024; // 5 MB

        previewDiv.innerHTML = 'No file selected'; // Reset preview
        previewDiv.style.color = 'rgba(255,255,255,.5)';
        previewDiv.style.fontSize = '.9rem';

        if (file) {
            if (!allowedTypes.includes(file.type)) {
                previewDiv.innerHTML = '<i class="fas fa-times-circle" style="color:#fca5a5;margin-right:8px;"></i> Invalid file type. Only PDF, JPG, PNG allowed.';
                previewDiv.style.color = '#fca5a5';
                input.value = ''; // Clear the file input
                return;
            }
            if (file.size > maxSize) {
                previewDiv.innerHTML = '<i class="fas fa-times-circle" style="color:#fca5a5;margin-right:8px;"></i> File size exceeds 5MB limit.';
                previewDiv.style.color = '#fca5a5';
                input.value = ''; // Clear the file input
                return;
            }

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="File Preview" style="max-width:100%;max-height:150px;border-radius:4px;object-fit:contain;">`;
                    previewDiv.style.color = 'initial';
                    previewDiv.style.fontSize = 'initial';
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                previewDiv.innerHTML = `<i class="fas fa-file-pdf" style="font-size:3rem;color:#fca5a5;"></i><br>${file.name}`;
                previewDiv.style.color = 'rgba(255,255,255,.8)';
                previewDiv.style.fontSize = '.8rem';
            } else {
                previewDiv.innerHTML = `<i class="fas fa-file" style="font-size:3rem;color:rgba(255,255,255,.5);"></i><br>${file.name}`;
                previewDiv.style.color = 'rgba(255,255,255,.8)';
                previewDiv.style.fontSize = '.8rem';
            }
        }
    }

    function validateDocumentUpload(form) {
        const docType = form.cmbDocumentType.value;
        const fileInput = form.fileDocument;

        if (!docType) {
            window.showAlert ? window.showAlert('warning', 'Please select a document type.') : alert('Please select a document type.');
            return false;
        }
        if (!fileInput.files.length) {
            window.showAlert ? window.showAlert('warning', 'Please select a file to upload.') : alert('Please select a file to upload.');
            return false;
        }
        return true; // Client-side validation passed, server-side will re-validate
    }

    // ── Delete confirm modal ──
    function confirmDelete(deleteUrl, itemName) {

        document.getElementById('deleteModalText').innerHTML =
            'This will permanently delete <b>"' + itemName + '"</b>. This cannot be undone.';

        document.getElementById('deleteForm').action = deleteUrl;

        document.getElementById('deleteModal').classList.add('show');

        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        document.body.style.overflow = '';
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('deleteModal')) closeDeleteModal();
    }
</script>
@endsection
