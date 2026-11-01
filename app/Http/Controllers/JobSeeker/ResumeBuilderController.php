<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerResume;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ResumeBuilderController extends Controller
{
    private const TEMPLATES = [
        'classic' => 'Professional Classic',
        'modern' => 'Modern Professional',
        'ats' => 'Minimal ATS-Friendly',
        'creative' => 'Creative',
        'corporate' => 'Corporate',
    ];

    public function index(Request $request)
    {
        $seeker = Auth::guard('jobseeker')->user();
        $resumes = JobSeekerResume::where('JobSeekId', $seeker->JobSeekId)->latest()->get();
        $resume = $request->boolean('new')
            ? null
            : ($request->integer('resume') ? $resumes->firstWhere('id', $request->integer('resume')) : $resumes->first());
        $content = $resume ? $this->normalizeContent($resume->content) : $this->profileContent($seeker);

        return view('jobseeker.resumes.index', [
            'resume' => $resume,
            'resumes' => $resumes,
            'content' => $content,
            'templates' => self::TEMPLATES,
            'selectedTemplate' => $resume?->template ?? 'classic',
            'photoUrl' => \App\Support\ProfilePhoto::url($seeker->Photo),
            'photoData' => $this->profilePhotoData($seeker),
        ]);
    }

    public function myResumes(Request $request)
    {
        $seekerId = Auth::guard('jobseeker')->id();
        $query = JobSeekerResume::where('JobSeekId', $seekerId);
        $totalResumes = (clone $query)->count();
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return view('jobseeker.resumes.my-index', [
            'resumes' => $query->orderByDesc('updated_at')->paginate(12)->withQueryString(),
            'totalResumes' => $totalResumes,
            'search' => $search,
            'templates' => self::TEMPLATES,
        ]);
    }

    public function show(JobSeekerResume $resume)
    {
        abort_unless((int) $resume->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);
        $content = $this->normalizeContent($resume->content);

        return view('jobseeker.resumes.show', [
            'resume' => $resume,
            'templates' => self::TEMPLATES,
        ]);
    }

    public function previewSaved(JobSeekerResume $resume)
    {
        abort_unless((int) $resume->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);

        return view('jobseeker.resumes.preview', [
            'content' => $this->normalizeContent($resume->content),
            'template' => $resume->template,
            'photoData' => $this->profilePhotoData(Auth::guard('jobseeker')->user()),
        ]);
    }

    public function store(Request $request)
    {
        $seeker = Auth::guard('jobseeker')->user();
        $data = $this->validated($request);
        $resume = null;
        if (!empty($data['id'])) {
            $resume = JobSeekerResume::where('JobSeekId', $seeker->JobSeekId)->findOrFail($data['id']);
            $resume->update(['title' => $data['title'], 'template' => $data['template'], 'content' => $data['content']]);
        } else {
            $resume = JobSeekerResume::create([
                'JobSeekId' => $seeker->JobSeekId,
                'title' => $data['title'],
                'template' => $data['template'],
                'content' => $data['content'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Resume saved.', 'id' => $resume->id]);
    }

    public function create(Request $request)
    {
        $seeker = Auth::guard('jobseeker')->user();
        $data = $this->validated($request);
        $resume = JobSeekerResume::create([
            'JobSeekId' => $seeker->JobSeekId,
            'title' => $data['title'],
            'template' => $data['template'],
            'content' => $data['content'],
        ]);

        return response()->json(['success' => true, 'message' => 'Resume created.', 'id' => $resume->id]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'content' => ['required', 'string', 'max:200000'],
        ]);
        $content = json_decode($validated['content'], true);
        if (!is_array($content)) {
            return response()->json(['message' => 'Resume content must be valid JSON.'], 422);
        }

        $seeker = Auth::guard('jobseeker')->user();
        return response()->json(['html' => view('jobseeker.resumes.preview', [
            'content' => $this->normalizeContent($content),
            'template' => $validated['template'],
            'photoData' => $this->profilePhotoData($seeker),
        ])->render()]);
    }

    public function download(JobSeekerResume $resume)
    {
        abort_unless((int) $resume->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);
        $resume->content = $this->normalizeContent($resume->content);

        return Pdf::loadView('jobseeker.resumes.pdf', [
            'resume' => $resume,
            'seeker' => Auth::guard('jobseeker')->user(),
            'photoData' => $this->profilePhotoData(Auth::guard('jobseeker')->user()),
        ])->setPaper('a4')->download(str($resume->title)->slug().'.pdf');
    }

    public function destroy(JobSeekerResume $resume)
    {
        abort_unless((int) $resume->JobSeekId === (int) Auth::guard('jobseeker')->id(), 404);
        $resume->delete();
        return response()->json(['success' => true, 'message' => 'Resume deleted.']);
    }

    private function validated(Request $request): array
    {
        $request->merge(['content' => json_decode((string) $request->input('content'), true)]);
        return $request->validate([
            'id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'template' => ['required', Rule::in(array_keys(self::TEMPLATES))],
            'content' => ['required', 'array'],
            'content.name' => ['nullable', 'string', 'max:150'],
            'content.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'content.gender' => ['nullable', 'string', 'max:30'],
            'content.qualification' => ['nullable', 'string', 'max:100'],
            'content.email' => ['nullable', 'email', 'max:150'],
            'content.phone' => ['nullable', 'string', 'max:40'],
            'content.location' => ['nullable', 'string', 'max:255'],
            'content.summary' => ['nullable', 'string', 'max:3000'],
            'content.skills' => ['nullable', 'string', 'max:3000'],
            'content.education' => ['nullable', 'array', 'max:30'],
            'content.education.*.qualification' => ['nullable', 'string', 'max:150'],
            'content.education.*.institution' => ['nullable', 'string', 'max:255'],
            'content.education.*.board' => ['nullable', 'string', 'max:255'],
            'content.education.*.year' => ['nullable', 'string', 'max:20'],
            'content.education.*.result' => ['nullable', 'string', 'max:40'],
            'content.experience' => ['nullable', 'array', 'max:30'],
            'content.experience.*.title' => ['nullable', 'string', 'max:150'],
            'content.experience.*.company' => ['nullable', 'string', 'max:255'],
            'content.experience.*.duration' => ['nullable', 'string', 'max:100'],
            'content.experience.*.responsibilities' => ['nullable', 'string', 'max:3000'],
            'content.certifications' => ['nullable', 'array', 'max:30'],
            'content.certifications.*.name' => ['nullable', 'string', 'max:255'],
            'content.certifications.*.issuer' => ['nullable', 'string', 'max:255'],
            'content.certifications.*.year' => ['nullable', 'string', 'max:20'],
            'content.projects' => ['nullable', 'array', 'max:30'],
            'content.projects.*.name' => ['nullable', 'string', 'max:255'],
            'content.projects.*.description' => ['nullable', 'string', 'max:2000'],
            'content.projects.*.link' => ['nullable', 'string', 'max:500'],
            'content.languages' => ['nullable', 'string', 'max:1000'],
            'content.achievements' => ['nullable', 'string', 'max:2000'],
            'content.internships' => ['nullable', 'string', 'max:2000'],
            'content.sections' => ['nullable', 'array'],
        ]);
    }

    private function profileContent($seeker): array
    {
        $education = $seeker->education->map(fn ($row) => [
            'qualification' => $row->Degree, 'institution' => $row->University,
            'year' => $row->PassingYear, 'result' => $row->Percentage,
        ])->concat($seeker->schoolEducation->map(fn ($row) => [
            'qualification' => $row->Qualification, 'institution' => $row->SchoolName,
            'board' => $row->Board, 'year' => $row->YearOfPassing, 'result' => $row->Percentage,
        ]))->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->sortByDesc(fn ($row) => (int) ($row['year'] ?? 0))->values()->all();

        return [
            'name' => $seeker->JobSeekerName,
            'age' => $seeker->BirthDate ? \Carbon\Carbon::parse($seeker->BirthDate)->age : null,
            'gender' => $seeker->Gender,
            'qualification' => $seeker->Qualification,
            'email' => $seeker->Email,
            'phone' => (string) $seeker->Mobile,
            'location' => collect([$seeker->Address, $seeker->City])->filter()->implode(', '),
            'summary' => '', 'skills' => '', 'education' => $education, 'experience' => [],
            'certifications' => '', 'projects' => '', 'languages' => '', 'achievements' => '', 'internships' => '',
            'sections' => ['summary', 'education', 'experience', 'skills', 'certifications', 'projects', 'languages', 'achievements', 'internships'],
        ];
    }

    private function profilePhotoData($seeker): ?string
    {
        $path = \App\Support\ProfilePhoto::path($seeker->Photo);
        if (!$path || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        try {
            $disk = \App\Support\UploadStorage::publicDisk();
            if ($disk->exists($path)) {
                $bytes = $disk->get($path);
            } else {
                $local = public_path($path);
                $real = realpath($local);
                $root = realpath(public_path());
                if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
                    return null;
                }
                $bytes = file_get_contents($real);
            }

            $mime = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeContent(array $content): array
    {
        foreach (['education', 'experience'] as $key) {
            $content[$key] = is_array($content[$key] ?? null) ? $content[$key] : [];
        }
        foreach (['certifications', 'projects'] as $key) {
            $value = $content[$key] ?? [];
            $content[$key] = is_array($value) ? $value : (filled($value) ? [['name' => $value]] : []);
        }
        $content['sections'] = is_array($content['sections'] ?? null) ? $content['sections'] : array_keys([
            'summary' => true, 'education' => true, 'experience' => true, 'skills' => true,
            'certifications' => true, 'projects' => true, 'languages' => true, 'achievements' => true, 'internships' => true,
        ]);
        return $content;
    }
}
