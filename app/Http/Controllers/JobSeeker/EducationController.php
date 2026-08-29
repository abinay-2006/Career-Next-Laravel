<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\JobSeekerEducation;
use App\Models\JobSeekerSchoolEducation;
use App\Models\JobSeekerDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EducationController extends Controller
{
    public function index()
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $rows_edu = JobSeekerEducation::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )->orderByDesc('EduId')->get();

        $rows_school = JobSeekerSchoolEducation::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )->get();

        $rows_docs = JobSeekerDocument::where(
            'JobSeekId',
            $jobSeeker->JobSeekId
        )->orderByDesc('DocId')->get();

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
    public function store(Request $request)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $degree = $request->cmbQual;

        if ($degree === 'Other') {
            $degree = $request->txtOther;
        }

        JobSeekerEducation::create([
            'JobSeekId'    => $jobSeeker->JobSeekId,
            'Degree'       => $degree,
            'University'   => $request->txtBoard,
            'PassingYear'  => $request->cmbYear,
            'Percentage'   => $request->txtPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Education added successfully.');
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'cmbQual' => 'required',
            'txtBoard' => 'required|string|max:255',
            'cmbYear' => 'required',
            'txtPer' => 'required|numeric|min:0|max:100',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $education = JobSeekerEducation::where('EduId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $degree = $request->cmbQual;

        if ($degree === 'Other') {
            $degree = $request->txtOther;
        }

        $education->update([
            'Degree'       => $degree,
            'University'   => $request->txtBoard,
            'PassingYear'  => $request->cmbYear,
            'Percentage'   => $request->txtPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Education updated successfully.');
    }
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

    public function storeSchool(Request $request)
    {

        $jobSeeker = Auth::guard('jobseeker')->user();

        $qualification = $request->cmbSchoolQual;

        if ($qualification === 'Other') {
            $qualification = $request->txtSchoolOther;
        }

        JobSeekerSchoolEducation::create([
            'JobSeekId'      => $jobSeeker->JobSeekId,
            'SchoolName'     => $request->txtSchoolName,
            'Qualification'  => $qualification,
            'Board'          => $request->txtSchoolBoard,
            'YearOfPassing'  => $request->cmbSchoolYear,
            'Percentage'     => $request->txtSchoolPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'School education added successfully.');
    }
    public function updateSchool(Request $request, $id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $school = JobSeekerSchoolEducation::where('id', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $qualification = $request->cmbSchoolQual;

        if ($qualification === 'Other') {
            $qualification = $request->txtSchoolOther;
        }

        $school->update([
            'SchoolName'     => $request->txtSchoolName,
            'Qualification'  => $qualification,
            'Board'          => $request->txtSchoolBoard,
            'YearOfPassing'  => $request->cmbSchoolYear,
            'Percentage'     => $request->txtSchoolPer,
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'School education updated successfully.');
    }
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

    public function uploadDocument(Request $request)
    {
        $request->validate([
            'cmbDocumentType' => 'required',
            'fileDocument' => 'required|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $jobSeeker = Auth::guard('jobseeker')->user();

        $file = $request->file('fileDocument');

        $fileName = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();

        $file->move(public_path('uploads/documents'), $fileName);

        JobSeekerDocument::create([
            'JobSeekId' => $jobSeeker->JobSeekId,
            'DocumentType' => $request->cmbDocumentType,
            'FileName' => $fileName,
            'OriginalFileName' => $file->getClientOriginalName(),
            'UploadDate' => now(),
            'VerificationStatus' => 'Pending',
        ]);

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Document uploaded successfully.');
    }
    public function deleteDocument($id)
    {
        $jobSeeker = Auth::guard('jobseeker')->user();

        $document = JobSeekerDocument::where('DocId', $id)
            ->where('JobSeekId', $jobSeeker->JobSeekId)
            ->firstOrFail();

        $path = public_path('uploads/documents/' . $document->FileName);

        if (file_exists($path)) {
            unlink($path);
        }

        $document->delete();

        return redirect()
            ->route('jobseeker.education')
            ->with('success', 'Document deleted successfully.');
    }
}
