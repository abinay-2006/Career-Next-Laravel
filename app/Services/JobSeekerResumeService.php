<?php

namespace App\Services;

use App\Models\JobSeeker;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Coordinates the legacy resume path and the canonical resume document row. */
class JobSeekerResumeService
{
    /**
     * Replace the seeker's resume while serializing all application resume writers
     * on that seeker's account row.
     *
     * @return list<string> Previous paths that may be eligible for post-commit cleanup.
     */
    public function replace(int|string $jobSeekerId, string $path, string $originalFileName, ?string $verificationStatus = null): array
    {
        return DB::transaction(function () use ($jobSeekerId, $path, $originalFileName, $verificationStatus): array {
            $jobSeeker = JobSeeker::query()
                ->where('JobSeekId', $jobSeekerId)
                ->lockForUpdate()
                ->firstOrFail();

            $resumeDocuments = $jobSeeker->documents()
                ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
                ->orderByDesc('DocId')
                ->lockForUpdate()
                ->get();

            $oldPaths = $resumeDocuments->pluck('FileName')->all();
            if ($jobSeeker->Resume) {
                $oldPaths[] = $jobSeeker->Resume;
            }

            $values = [
                'FileName' => $path,
                'OriginalFileName' => $originalFileName,
                'UploadDate' => now(),
                'DocumentType' => 'resume',
            ];
            if ($verificationStatus !== null) {
                $values['VerificationStatus'] = $verificationStatus;
            }

            $existingDocument = $resumeDocuments->first();
            if ($existingDocument) {
                if (!$existingDocument->update($values)) {
                    throw new \RuntimeException('Resume document record was not persisted.');
                }
            } else {
                $created = $jobSeeker->documents()->create($values);
                if (!$created->exists) {
                    throw new \RuntimeException('Resume document record was not persisted.');
                }
            }

            // Repair historical duplicates while the same per-account lock is held.
            foreach ($resumeDocuments->skip(1) as $duplicate) {
                if (!$duplicate->delete()) {
                    throw new \RuntimeException('Duplicate resume document record was not removed.');
                }
            }

            $jobSeeker->Resume = $path;
            if (!$jobSeeker->save()) {
                throw new \RuntimeException('Legacy resume path was not persisted.');
            }

            return $oldPaths;
        });
    }

    /** Remove all resume document rows and clear the legacy path atomically. */
    public function remove(int|string $jobSeekerId): array
    {
        return DB::transaction(function () use ($jobSeekerId): array {
            $jobSeeker = JobSeeker::query()
                ->where('JobSeekId', $jobSeekerId)
                ->lockForUpdate()
                ->firstOrFail();

            $resumeDocuments = $jobSeeker->documents()
                ->whereRaw('LOWER(DocumentType) = ?', ['resume'])
                ->orderByDesc('DocId')
                ->lockForUpdate()
                ->get();

            $paths = $resumeDocuments->pluck('FileName')->all();
            if ($jobSeeker->Resume) {
                $paths[] = $jobSeeker->Resume;
            }

            foreach ($resumeDocuments as $resumeDocument) {
                if (!$resumeDocument->delete()) {
                    throw new \RuntimeException('Resume document record was not removed.');
                }
            }

            $hadResume = $resumeDocuments->isNotEmpty() || !empty($jobSeeker->Resume);
            if ($hadResume) {
                $jobSeeker->Resume = null;
                if (!$jobSeeker->save()) {
                    throw new \RuntimeException('Legacy resume path was not cleared.');
                }
            }

            return ['paths' => $paths, 'had_resume' => $hadResume];
        });
    }

    /** Delete storage objects only after checking both database reference locations. */
    public function cleanupUnreferenced(array $paths, ?string $keepPath, string $operation): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if ($path === $keepPath) {
                continue;
            }

            try {
                $documentReferenceExists = DB::table('jobseeker_documents')->where('FileName', $path)->exists();
                $legacyReferenceExists = DB::table('jobseeker_reg')->where('Resume', $path)->exists();
            } catch (\Throwable $e) {
                // If references cannot be checked, retaining a possible live object is safer.
                Log::warning('Resume storage cleanup skipped because references could not be checked.', [
                    'operation' => $operation,
                    'exception' => $e::class,
                ]);
                continue;
            }

            if (!$documentReferenceExists && !$legacyReferenceExists) {
                UploadStorage::deleteBestEffort(UploadStorage::privateDiskName(), $path, $operation);
            }
        }
    }
}
