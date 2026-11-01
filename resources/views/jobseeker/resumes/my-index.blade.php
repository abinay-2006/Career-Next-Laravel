@extends('layouts.jobseeker')
@section('title', 'My Resumes')
@section('content')
<style>
  
</style>
<div class="my-resumes">
  <header class="mr-top">
    <div>
      <h1>My Resumes</h1>
      <p>Manage your created resumes in one place.</p><span class="mr-count"><i class="fas fa-file-lines"></i> {{ $totalResumes }} saved {{ $totalResumes===1?'resume':'resumes' }}</span>
    </div><a class="btn btn-primary" href="{{ route('jobseeker.resumes.index', ['new' => 1]) }}"><i class="fas fa-plus me-1"></i>Create New Resume</a>
  </header>

  @if($totalResumes > 0)
  <div class="mr-toolbar">
    <form class="mr-search" method="GET" action="{{ route('jobseeker.my-resumes.index') }}"><input class="form-control" type="search" name="q" value="{{ $search }}" placeholder="Search resumes by title" aria-label="Search resumes"><button class="btn btn-outline-primary" type="submit" aria-label="Search"><i class="fas fa-magnifying-glass"></i></button>@if($search)<a class="btn btn-outline-secondary" href="{{ route('jobseeker.my-resumes.index') }}">Clear</a>@endif</form><small class="text-muted">{{ $resumes->total() }} result{{ $resumes->total()===1?'':'s' }}</small>
  </div>
  <div class="mr-grid">
    @forelse($resumes as $resume)
    <article class="mr-card" id="resume-{{ $resume->id }}" data-resume-card>
      <div class="mr-card-head"><span class="mr-file-icon"><i class="fas fa-file-lines"></i></span>
        <div class="flex-grow-1 min-w-0">
          <div class="mr-card-title">{{ $resume->title }}</div>
          <div class="mr-template"><i class="fas fa-palette me-1"></i>{{ $templates[$resume->template] ?? \Illuminate\Support\Str::headline($resume->template) }}</div>
        </div>
      </div>
      <div class="mr-meta"><span><i class="fas fa-rotate"></i>Updated {{ $resume->updated_at?->format('M j, Y') ?? '—' }}</span><span><i class="fas fa-calendar-plus"></i>Created {{ $resume->created_at?->format('M j, Y') ?? '—' }}</span></div>
      <div class="mr-actions"><a class="btn btn-outline-secondary" href="{{ route('jobseeker.my-resumes.show', $resume) }}"><i class="fas fa-eye me-1"></i>Preview</a><a class="btn btn-outline-primary" href="{{ route('jobseeker.resumes.index', ['resume'=>$resume->id]) }}"><i class="fas fa-pen me-1"></i>Edit</a><a class="btn btn-primary" href="{{ route('jobseeker.resumes.download', $resume) }}"><i class="fas fa-download me-1"></i>PDF</a>
        <form class="mr-delete" method="POST" action="{{ route('jobseeker.resumes.destroy', $resume) }}" data-delete-resume>@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger" aria-label="Delete {{ $resume->title }}"><i class="fas fa-trash"></i></button></form>
      </div>
    </article>
    @empty
    <div class="mr-none"><i class="fas fa-magnifying-glass me-1"></i>No resumes match “{{ $search }}”.</div>
    @endforelse
  </div>
  <div class="mt-3">{{ $resumes->links() }}</div>
  @else
  <div class="mr-empty">
    <div class="mr-empty-icon"><i class="fas fa-file-circle-plus"></i></div>
    <h2>You haven't created any resumes yet.</h2>
    <p>Build a professional resume using your profile information, then customize and download it for your next opportunity.</p><a class="btn btn-primary" href="{{ route('jobseeker.resumes.index', ['new'=>1]) }}"><i class="fas fa-plus me-1"></i>Create Your First Resume</a>
  </div>
  @endif
</div>
<script>
  (() => {
    document.querySelectorAll('[data-delete-resume]').forEach(form => form.addEventListener('submit', async event => {
      event.preventDefault();
      const card = form.closest('[data-resume-card]'),
        button = form.querySelector('button');
      if (!window.confirm('Delete this resume? This cannot be undone.')) return;
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      try {
        const response = await fetch(form.action, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Could not delete this resume.');
        card.remove();

        const remainingCards = document.querySelectorAll('[data-resume-card]').length;

        if (remainingCards === 0) {
          window.location.reload();
        }
      } catch (error) {
        window.alert(error.message);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-trash"></i>';
      }
    }));
  })();
</script>
@endsection