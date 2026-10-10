<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeekerEducation;
use App\Models\JobSeekerSchoolEducation;
use App\Models\JobSeekerDocument;
use App\Support\PrivateUpload;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EducationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Education Page
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $rows_edu = JobSeekerEducation::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )
            ->orderByDesc('EduId')
            ->get();

        $rows_school = JobSeekerSchoolEducation::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )->get();

        $rows_docs = JobSeekerDocument::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )
            ->orderByDesc('DocId')
            ->get();

        $degrees = [
            'B.C.A',
            'M.C.A',
            'B.Sc.I.T',
            'B.Sc.C.S',
            'M.Sc.I.T',
            'M.Sc.C.S',
            'B.E / B.Tech',
            'M.E / M.Tech',
            'B.Com',
            'M.Com',
            'B.A',
            'M.A',
            'M.B.A',
            'B.B.A',
            'B.Pharm',
            'M.Pharm',
            'MBBS',
            'LLB',
            'LLM',
            'Diploma',
            'Ph.D',
            'Other',
        ];

        $boards = [
            'Mumbai University',
            'Delhi University',
            'Pune University',
            'Gujarat University',
            'Anna University',
            'Osmania University',
            'Bangalore University',
            'Calcutta University',
            'Madras University',
            'Rajasthan University',
            'IGNOU',
            'Symbiosis International',
            'Amity University',
            'Lovely Professional University',
            'Other',
        ];

        $schoolQualifications = [
            'High School',
            'Secondary School Certificate (SSC)',
            'Higher Secondary Certificate (HSC)',
            'Diploma',
            'Other',
        ];

        return view('jobseeker.education.index', [
            'rows_edu' => $rows_edu,
            'rows_school' => $rows_school,
            'rows_docs' => $rows_docs,

            'records_edu' => $rows_edu->count(),
            'records_school' => $rows_school->count(),
            'records_docs' => $rows_docs->count(),

            'degrees' => $degrees,
            'boards' => $boards,
            'schoolQualifications' => $schoolQualifications,

            'higherYears' => range(1998, now()->year),
            'schoolYears' => range(1990, now()->year),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Store Higher Education
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'cmbQual' => 'required|string|max:255',
            'txtOther' => 'nullable|string|max:255',
            'txtBoard' => 'required|string|max:255',
            'cmbYear' => 'required|integer|min:1998|max:' . now()->year,
            'txtPer' => 'required|numeric|min:0|max:100',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $degree = $request->cmbQual;

        if ($degree === 'Other') {
            $degree = trim($request->txtOther);

            if ($degree === '') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'txtOther' => 'Please specify the degree.'
                    ]);
            }
        }

        JobSeekerEducation::create([
            'JobSeekId'   => $jobSeeker->JobSeekId,
            'Degree'      => $degree,
            'University'  => $request->txtBoard,
            'PassingYear' => $request->cmbYear,
            'Percentage'  => $request->txtPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Education added successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Update Higher Education
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $request->validate([
            'cmbQual' => 'required|string|max:255',
            'txtOther' => 'nullable|string|max:255',
            'txtBoard' => 'required|string|max:255',
            'cmbYear' => 'required|integer|min:1998|max:' . now()->year,
            'txtPer' => 'required|numeric|min:0|max:100',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $education = JobSeekerEducation::where('EduId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $degree = $request->cmbQual;

        if ($degree === 'Other') {
            $degree = trim($request->txtOther);

            if ($degree === '') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'txtOther' => 'Please specify the degree.'
                    ]);
            }
        }

        $education->update([
            'Degree'      => $degree,
            'University'  => $request->txtBoard,
            'PassingYear' => $request->cmbYear,
            'Percentage'  => $request->txtPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Education updated successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Higher Education
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $education = JobSeekerEducation::where('EduId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $education->delete();

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Education deleted successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Store School Education
    |--------------------------------------------------------------------------
    */
    public function storeSchool(Request $request)
    {
        $request->validate([
            'txtSchoolName' => 'required|string|max:255',
            'cmbSchoolQual' => 'required|string|max:255',
            'txtSchoolOther' => 'nullable|string|max:255',
            'txtSchoolBoard' => 'required|string|max:255',
            'cmbSchoolYear' => 'required|integer|min:1990|max:' . now()->year,
            'txtSchoolPer' => 'required|numeric|min:0|max:100',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $qualification = $request->cmbSchoolQual;

        if ($qualification === 'Other') {
            $qualification = trim($request->txtSchoolOther);

            if ($qualification === '') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'txtSchoolOther' => 'Please specify the qualification.'
                    ]);
            }
        }

        JobSeekerSchoolEducation::create([
            'JobSeekId'     => $jobSeeker->JobSeekId,
            'SchoolName'    => trim($request->txtSchoolName),
            'Qualification' => $qualification,
            'Board'         => trim($request->txtSchoolBoard),
            'YearOfPassing' => $request->cmbSchoolYear,
            'Percentage'    => $request->txtSchoolPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'School education added successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Update School Education
    |--------------------------------------------------------------------------
    */
    public function updateSchool(Request $request, $id)
    {
        $request->validate([
            'txtSchoolName' => 'required|string|max:255',
            'cmbSchoolQual' => 'required|string|max:255',
            'txtSchoolOther' => 'nullable|string|max:255',
            'txtSchoolBoard' => 'required|string|max:255',
            'cmbSchoolYear' => 'required|integer|min:1990|max:' . now()->year,
            'txtSchoolPer' => 'required|numeric|min:0|max:100',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $school = JobSeekerSchoolEducation::where('id', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $qualification = $request->cmbSchoolQual;

        if ($qualification === 'Other') {
            $qualification = trim($request->txtSchoolOther);

            if ($qualification === '') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'txtSchoolOther' => 'Please specify the qualification.'
                    ]);
            }
        }

        $school->update([
            'SchoolName'    => trim($request->txtSchoolName),
            'Qualification' => $qualification,
            'Board'         => trim($request->txtSchoolBoard),
            'YearOfPassing' => $request->cmbSchoolYear,
            'Percentage'    => $request->txtSchoolPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'School education updated successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Delete School Education
    |--------------------------------------------------------------------------
    */
    public function destroySchool($id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $school = JobSeekerSchoolEducation::where('id', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $school->delete();

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'School education deleted successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Upload Document
    |--------------------------------------------------------------------------
    */
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'cmbDocumentType' => 'required|string|max:50',
            'fileDocument' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $file = $request->file('fileDocument');

        $fileName = time()
            . '_'
            . Str::random(8)
            . '.'
            . $file->getClientOriginalExtension();

        $storedPath = $file->storeAs('documents', $fileName, [
            'disk' => config('filesystems.default'),
            'visibility' => 'private',
        ]);
        if (!$storedPath) {
            return back()->with('error', 'The document could not be saved. Please try again.');
        }

        JobSeekerDocument::create([
            'JobSeekId'        => $jobSeeker->JobSeekId,
            'DocumentType'     => $request->cmbDocumentType,
            'FileName'         => $storedPath,
            'OriginalFileName' => $file->getClientOriginalName(),
            'UploadDate'       => now(),
            'VerificationStatus' => 'pending',
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Document uploaded successfully.');
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Document
    |--------------------------------------------------------------------------
    */
    public function deleteDocument($id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $document = JobSeekerDocument::where('DocId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        Storage::disk(config('filesystems.default'))->delete($document->FileName);
        $legacyPath = public_path('uploads/documents/' . basename($document->FileName));
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }

        $document->delete();

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Document deleted successfully.');
    }

    public function viewDocument(int $id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();
        $document = JobSeekerDocument::where('DocId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        return PrivateUpload::response(
            (string) $document->FileName,
            $document->OriginalFileName,
            ['uploads/documents', 'upload']
        );
    }
}
