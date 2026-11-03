Career Next — Saved Jobs / Bookmark feature patch

Target project: latest uploaded Career-Next-Laravel(4).zip

Copy the included paths into the matching locations in your existing local project. The patch is additive except for small updates to routes/web.php, JobSearchController.php, the Job Seeker sidebar component, and the two existing job views.

New:
- app/Models/SavedJob.php
- app/Http/Controllers/JobSeeker/SavedJobController.php
- database/migrations/2026_11_01_000000_create_saved_jobs_table.php
- resources/views/jobseeker/saved-jobs/index.blade.php
- tests/Feature/SavedJobsTest.php

Updated:
- routes/web.php
- app/Http/Controllers/JobSeeker/JobSearchController.php
- resources/views/components/dashboard-sidebar.blade.php
- resources/views/jobseeker/jobs/index.blade.php
- resources/views/jobseeker/jobs/show.blade.php

Local setup (after copying):
1. Review the diff and preserve any local edits; do not blindly overwrite newer local work.
2. Run: php artisan migrate:status
3. Confirm the new migration is pending and your local database backup is current.
4. Run only the additive migration locally: php artisan migrate --path=database/migrations/2026_11_01_000000_create_saved_jobs_table.php
5. Run: php artisan test --filter=SavedJobsTest
6. Manually test saving, duplicate save, removal, another jobseeker account, and pending/unapproved jobs.

Production migration is NOT included in this instruction. Do not deploy or run production migrations until separately reviewed and explicitly approved.

No .env, credentials, database data, uploaded files, or Git metadata are included or modified by this patch. No Git staging, commit, push, pull, merge, or branch operation was performed.
