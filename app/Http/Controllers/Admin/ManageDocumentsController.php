<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PrivateUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageDocumentsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:all,pending,approved,rejected'],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'in:10,25,50,100'],
        ]);
        $filterStatus = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $perPage = (int) ($filters['per_page'] ?? 10);

        $documents = DB::table('jobseeker_documents as jd')
            ->join('jobseeker_reg as jr', 'jd.JobSeekId', '=', 'jr.JobSeekId')
            ->when($filterStatus != 'all', function ($q) use ($filterStatus) {
                $q->where('jd.VerificationStatus', $filterStatus);
            })
            ->when($search != '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('jr.JobSeekerName', 'like', "%{$search}%")
                        ->orWhere('jd.OriginalFileName', 'like', "%{$search}%");
                });
            })
            ->select('jd.*', 'jr.JobSeekerName', 'jr.JobSeekId')
            ->orderByDesc('jd.UploadDate')
            ->paginate($perPage)
            ->withQueryString();

        $counts = [
            'total' => DB::table('jobseeker_documents')->count(),
            'approved' => DB::table('jobseeker_documents')->where('VerificationStatus', 'approved')->count(),
            'pending' => DB::table('jobseeker_documents')->where('VerificationStatus', 'pending')->count(),
            'rejected' => DB::table('jobseeker_documents')->where('VerificationStatus', 'rejected')->count(),
        ];

        return view('admin.manage_documents', [
            'documents'     => $documents,
            'counts'        => $counts,
            'filterStatus'  => $filterStatus,
            'search'        => $search,
            'perPage'       => $perPage,
        ]);
    }
    
    public function view(int $id)
    {
        $document = DB::table('jobseeker_documents')
            ->where('DocId', $id)
            ->first();

        if (!$document) {
            abort(404, 'Document record not found.');
        }

        return PrivateUpload::response(
            (string) $document->FileName,
            $document->OriginalFileName,
            ['uploads/documents', 'upload']
        );
    }

    public function approve(int $id)
    {
        $updated = DB::table('jobseeker_documents')->where('DocId', $id)->exists();
        if ($updated) DB::table('jobseeker_documents')->where('DocId', $id)->update(['VerificationStatus' => 'approved']);

        if (!$updated) {
            return redirect()
                ->route('admin.manage.documents')
                ->with('error', 'Document not found.');
        }

        return redirect()
            ->route('admin.manage.documents')
            ->with('success', 'Document approved successfully.');
    }

    public function reject(int $id)
    {
        $updated = DB::table('jobseeker_documents')->where('DocId', $id)->exists();
        if ($updated) DB::table('jobseeker_documents')->where('DocId', $id)->update(['VerificationStatus' => 'rejected']);

        if (!$updated) {
            return redirect()
                ->route('admin.manage.documents')
                ->with('error', 'Document not found.');
        }

        return redirect()
            ->route('admin.manage.documents')
            ->with('success', 'Document rejected successfully.');
    }
}
