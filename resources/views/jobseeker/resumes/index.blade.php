@extends('layouts.jobseeker')
@section('title', 'Resume Builder')
@section('content')

<div class="resume-builder" id="resumeBuilder" data-resume-id="{{ $resume?->id }}" data-template="{{ $selectedTemplate }}" data-save-url="{{ route('jobseeker.resumes.store') }}" data-create-url="{{ route('jobseeker.resumes.create') }}" data-preview-url="{{ route('jobseeker.resumes.preview') }}" data-index-url="{{ route('jobseeker.resumes.index') }}" data-download-base="{{ url('/jobseeker/resume-builder') }}" data-csrf="{{ csrf_token() }}">
  <div class="rb-toolbar">
    <div class="rb-brand"><small><a href="{{ route('jobseeker.index') }}">Workspace</a> / Resume Builder</small><strong><i class="fas fa-file-lines me-1"></i> Resume Builder</strong></div>
    <div class="rb-title-group">
      <label for="resumeTitle">Resume title</label>
      <input
        type="text"
        class="form-control rb-title-input"
        id="resumeTitle"
        maxlength="120"
        placeholder="Enter resume title"
        value="{{ $resume?->title ?? 'My Resume' }}">
    </div>
    <button class="btn btn-outline-secondary" type="button" id="createResume"><i class="fas fa-plus"></i><span class="d-none d-sm-inline"> New</span></button>
    <button class="btn btn-primary" type="button" id="saveResume"><i class="fas fa-floppy-disk"></i> Save</button>
    <button class="btn btn-outline-primary" type="button" id="downloadResume"><i class="fas fa-download"></i> PDF</button>
  </div>
  <div class="rb-mobile-switch"><button type="button" class="btn btn-outline-primary active" data-mode="edit"><i class="fas fa-pen"></i> Edit</button><button type="button" class="btn btn-outline-primary" data-mode="preview"><i class="fas fa-eye"></i> Preview</button></div>
  <div class="rb-workspace" id="rbWorkspace" data-mode="edit">
    <div class="rb-editor">
      <div class="rb-panel-title">
        <div>
          <h1 class="h5 mb-1" style="color:#ffff">Your information</h1><small class="text-muted" style="color:#ffff">Changes here stay with this resume.</small>
        </div>
      </div>
      <details class="rb-template-details">
        <summary><span class="rb-icon"><i class="fas fa-palette"></i></span>Choose a template<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-template-list" id="templateCards">@foreach($templates as $key=>$label)<button type="button" class="rb-template-option {{ ($resume?->template ?? 'classic')===$key?'active':'' }}" data-template-option="{{ $key }}"><span class="rb-mini {{ $key }}"><span></span></span><strong>{{ $label }}</strong></button>@endforeach</div>
        </div>
      </details>
      <details open>
        <summary><span class="rb-icon"><i class="fas fa-user"></i></span>Personal Information<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-fields">
            <div><label for="f-name">Full name</label><input id="f-name" class="form-control" data-field="name"></div>
            <div><label for="f-email">Email</label><input id="f-email" type="email" class="form-control" data-field="email"></div>
            <div><label for="f-phone">Phone</label><input id="f-phone" class="form-control" data-field="phone"></div>
            <div><label for="f-location">Location</label><input id="f-location" class="form-control" data-field="location"></div>
            <div><label for="f-age">Age</label><input id="f-age" type="number" min="0" max="120" class="form-control" data-field="age"></div>
            <div><label for="f-gender">Gender</label><input id="f-gender" class="form-control" data-field="gender"></div>
            <div class="wide"><label for="f-qualification">Highest qualification</label><input id="f-qualification" class="form-control" data-field="qualification"></div>
          </div>
        </div>
      </details>
      <details>
        <summary><span class="rb-icon"><i class="fas fa-align-left"></i></span>Professional Summary<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-fields">
            <div class="wide"><label for="f-summary">Summary or career objective</label><textarea id="f-summary" class="form-control" rows="4" data-field="summary" placeholder="A concise overview of your experience and strengths"></textarea></div>
          </div>
        </div>
      </details>
      <details open>
        <summary><span class="rb-icon"><i class="fas fa-graduation-cap"></i></span>Education<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div id="educationList"></div><button type="button" class="rb-add" data-add="education"><i class="fas fa-plus me-1"></i>Add Education</button>
        </div>
      </details>
      <details>
        <summary><span class="rb-icon"><i class="fas fa-briefcase"></i></span>Work Experience<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div id="experienceList"></div><button type="button" class="rb-add" data-add="experience"><i class="fas fa-plus me-1"></i>Add Experience</button>
        </div>
      </details>
      <details>
        <summary><span class="rb-icon"><i class="fas fa-bolt"></i></span>Skills<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-fields">
            <div class="wide"><label for="f-skills">Skills</label><textarea id="f-skills" class="form-control" rows="3" data-field="skills" placeholder="Add skills, separated by commas or new lines"></textarea></div>
          </div>
        </div>
      </details>
      <details>
        <summary><span class="rb-icon"><i class="fas fa-certificate"></i></span>Certifications<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div id="certificationsList"></div><button type="button" class="rb-add" data-add="certifications"><i class="fas fa-plus me-1"></i>Add Certification</button>
        </div>
      </details>
      <details>
        <summary><span class="rb-icon"><i class="fas fa-lightbulb"></i></span>Projects<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div id="projectsList"></div><button type="button" class="rb-add" data-add="projects"><i class="fas fa-plus me-1"></i>Add Project</button>
        </div>
      </details>
      @foreach(['languages'=>'Languages','achievements'=>'Achievements','internships'=>'Internships'] as $key=>$label)<details>
        <summary><span class="rb-icon"><i class="fas {{ ['languages'=>'fa-language','achievements'=>'fa-trophy','internships'=>'fa-user-graduate'][$key] }}"></i></span>{{ $label }}<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-fields">
            <div class="wide"><label for="f-{{ $key }}">{{ $label }}</label><textarea id="f-{{ $key }}" class="form-control" rows="3" data-field="{{ $key }}"></textarea></div>
          </div>
        </div>
      </details>@endforeach
      <details>
        <summary><span class="rb-icon"><i class="fas fa-sliders"></i></span>Sections to include<i class="fas fa-chevron-down rb-chevron"></i></summary>
        <div class="rb-section-body">
          <div class="rb-section-toggles" id="sectionToggles"></div>
        </div>
      </details>
      <div class="rb-feedback" id="resumeFeedback" role="status" aria-live="polite"></div>
    </div>
    <div class="rb-preview-pane">
      <div class="rb-preview-head">
        <div><strong>Live preview</strong><br><small>A4 page · updates as you edit</small></div><small id="previewTemplateName"></small>
      </div><iframe class="rb-preview-frame" id="resumePreview" title="Live resume preview" sandbox=""></iframe>
    </div>
  </div>
</div>
@endsection
@section('scripts')
<script type="application/json" id="resumeInitialContent">
  @json($content)
</script>
<script>
  (() => {
    const root = document.getElementById('resumeBuilder'),
      workspace = document.getElementById('rbWorkspace'),
      preview = document.getElementById('resumePreview');
    const initial = JSON.parse(document.getElementById('resumeInitialContent').textContent || '{}');
    let selectedTemplate = root.dataset.template || 'classic';
    const templateLabels = {
      classic: 'Professional Classic',
      modern: 'Modern Professional',
      ats: 'Minimal ATS-Friendly',
      creative: 'Creative',
      corporate: 'Corporate'
    };
    const definitions = {
      education: [
        ['qualification', 'Qualification'],
        ['institution', 'Institution'],
        ['board', 'University / Board'],
        ['year', 'Year of completion'],
        ['result', 'Percentage / CGPA']
      ],
      experience: [
        ['title', 'Job title'],
        ['company', 'Company'],
        ['duration', 'Duration'],
        ['responsibilities', 'Responsibilities']
      ],
      certifications: [
        ['name', 'Certification'],
        ['issuer', 'Issuing organization'],
        ['year', 'Year']
      ],
      projects: [
        ['name', 'Project name'],
        ['description', 'Description'],
        ['link', 'Link (optional)']
      ]
    };
    const labels = {
      summary: 'Professional Summary',
      education: 'Education',
      experience: 'Work Experience',
      skills: 'Skills',
      certifications: 'Certifications',
      projects: 'Projects',
      languages: 'Languages',
      achievements: 'Achievements',
      internships: 'Internships'
    };
    let content = {
      ...initial,
      education: Array.isArray(initial.education) ? initial.education : [],
      experience: Array.isArray(initial.experience) ? initial.experience : [],
      certifications: Array.isArray(initial.certifications) ? initial.certifications : (initial.certifications ? [{
        name: initial.certifications
      }] : []),
      projects: Array.isArray(initial.projects) ? initial.projects : (initial.projects ? [{
        name: initial.projects
      }] : []),
      sections: Array.isArray(initial.sections) ? initial.sections : Object.keys(labels)
    };
    let previewSequence = 0,
      previewTimer;

    function field(label, key, value, wide = false) {
      const wrap = document.createElement('div');
      if (wide) wrap.className = 'wide';
      const l = document.createElement('label');
      l.textContent = label;
      const el = (key === 'responsibilities' || key === 'description') ? document.createElement('textarea') : document.createElement('input');
      el.className = 'form-control';
      el.value = value ?? '';
      el.dataset.recordKey = key;
      if (key === 'year') el.inputMode = 'numeric';
      el.addEventListener('input', renderPreview);
      wrap.append(l, el);
      return wrap
    }

    function renderRecords(type) {
      const container = document.getElementById(type + 'List');
      container.replaceChildren();
      (content[type] || []).forEach((record, index) => {
        const card = document.createElement('div');
        card.className = 'rb-repeat-card';
        const head = document.createElement('div');
        head.className = 'rb-repeat-head';
        const title = document.createElement('strong');
        title.textContent = (definitions[type][0][1]) + ' ' + (index + 1);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger';
        remove.setAttribute('aria-label', 'Remove ' + type.slice(0, -1));
        remove.innerHTML = '<i class="fas fa-trash"></i>';
        remove.addEventListener('click', () => {
          content[type].splice(index, 1);
          renderRecords(type);
          renderPreview()
        });
        head.append(title, remove);
        const grid = document.createElement('div');
        grid.className = 'rb-fields';
        definitions[type].forEach(([key, label]) => grid.append(field(label, key, record[key], key === 'responsibilities' || key === 'description')));
        grid.addEventListener('input', () => {
          grid.querySelectorAll('[data-record-key]').forEach(input => record[input.dataset.recordKey] = input.value);
          renderPreview()
        });
        card.append(head, grid);
        container.append(card)
      });
    }

    function renderPreview() {
      const template = selectedTemplate;
      document.getElementById('previewTemplateName').textContent = templateLabels[template] || templateLabels.classic;
      const sequence = ++previewSequence;
      clearTimeout(previewTimer);
      previewTimer = setTimeout(async () => {
        try {
          const response = await fetch(root.dataset.previewUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': root.dataset.csrf
            },
            body: JSON.stringify({
              template,
              content: JSON.stringify(collect())
            })
          });
          const result = await response.json();
          if (!response.ok) throw new Error(result.message || 'Preview could not be refreshed.');
          if (sequence === previewSequence) preview.srcdoc = result.html;
        } catch (error) {
          if (sequence === previewSequence) document.getElementById('previewTemplateName').textContent = error.message;
        }
      }, 100);
    }
    document.querySelectorAll('[data-field]').forEach(input => {
      input.value = content[input.dataset.field] ?? '';
      input.addEventListener('input', () => {
        content[input.dataset.field] = input.value;
        renderPreview()
      })
    });
    Object.keys(definitions).forEach(renderRecords);
    document.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => {
      const type = button.dataset.add;
      content[type].push({});
      renderRecords(type);
      renderPreview()
    }));
    const toggles = document.getElementById('sectionToggles');
    Object.entries(labels).forEach(([key, title]) => {
      const label = document.createElement('label');
      const input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = content.sections.includes(key);
      input.addEventListener('change', () => {
        content.sections = input.checked ? [...new Set([...content.sections, key])] : content.sections.filter(x => x !== key);
        renderPreview()
      });
      label.append(input, document.createTextNode(title));
      toggles.append(label)
    });
    document.querySelectorAll('[data-template-option]').forEach(card => card.addEventListener('click', () => {
      selectedTemplate = card.dataset.templateOption;
      root.dataset.template = selectedTemplate;
      document.querySelectorAll('[data-template-option]').forEach(option => option.classList.toggle('active', option === card));
      renderPreview()
    }));
    const status = document.getElementById('resumeFeedback');

    function collect() {
      document.querySelectorAll('[data-field]').forEach(i => content[i.dataset.field] = i.value);
      for (const type of Object.keys(definitions)) {
        const cards = document.querySelectorAll('#' + type + 'List .rb-repeat-card');
        content[type] = [...cards].map(card => Object.fromEntries([...card.querySelectorAll('[data-record-key]')].map(i => [i.dataset.recordKey, i.value])))
      }
      return content
    }
    async function save(create = false) {
      const button = document.activeElement;
      const original = button?.innerHTML;
      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving';
      }
      status.textContent = 'Saving your resume…';
      try {
        const payload = {
          id: create ? '' : document.getElementById('resumeBuilder').dataset.resumeId || '',
          title: document.getElementById('resumeTitle').value.trim(),
          template: selectedTemplate,
          content: JSON.stringify(collect())
        };
        const response = await fetch(create ? root.dataset.createUrl : root.dataset.saveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': root.dataset.csrf
          },
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Unable to save the resume.');
        root.dataset.resumeId = result.id;
        status.textContent = result.message || 'Resume saved.';
        return result.id
      } catch (error) {
        status.textContent = error.message;
        throw error
      } finally {
        if (button) {
          button.disabled = false;
          button.innerHTML = original
        }
      }
    }
    document.getElementById('saveResume').addEventListener('click', () => save().catch(() => {}));
    document.getElementById('createResume').addEventListener('click', () => save(true).then(() => location.href = root.dataset.indexUrl + '?resume=' + encodeURIComponent(root.dataset.resumeId)).catch(() => {}));
    document.getElementById('downloadResume').addEventListener('click', () => save().then(id => {
      const button = document.getElementById('downloadResume');
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing';
      location.href = root.dataset.downloadBase + '/' + encodeURIComponent(id) + '/download';
      setTimeout(() => {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-download"></i> PDF'
      }, 1600)
    }).catch(() => {}));

    document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', () => {
      workspace.dataset.mode = button.dataset.mode;
      document.querySelectorAll('[data-mode]').forEach(x => x.classList.toggle('active', x === button))
    }));
    renderPreview();
  })();
</script>
@endsection