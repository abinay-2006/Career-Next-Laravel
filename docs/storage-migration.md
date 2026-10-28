# Career Next upload storage and migration

## Current and target layout

The application stores relative keys in its existing database fields. Profile photos use the configured public upload disk (`photos/...`, with historical `registration/photos/...` and `upload(s)/photos/...` forms). Registration resumes and Job Seeker resumes/documents use the configured private upload disk (`registration/resumes/...` and `documents/...`). Private files are streamed by role-protected Laravel routes; private storage must not have a public URL or bucket policy.

Local development defaults remain `public` and `private` local disks. For a cloud rollout, select `r2-public` and `r2-private` independently. Prefer separate buckets: expose only the photo bucket through an owner-controlled custom domain, and leave the private bucket with public access disabled. Laravel builds normal image URLs from the configured public disk; private responses use the configured private disk and `Content-Disposition: inline`, `Cache-Control: private, no-store`, and `X-Content-Type-Options: nosniff`.

R2 uses the S3 API endpoint for the account and region `auto`. The R2 public URL is a separate custom-domain URL; do not use an S3 API endpoint as a browser URL. The private disk intentionally has no URL configured.

## Environment variable names

Local defaults need no object-storage variables. Cloud configuration requires:

- `PUBLIC_UPLOAD_DISK` (`public` locally; `r2-public` only after verification)
- `PRIVATE_UPLOAD_DISK` (`private` locally; `r2-private` only after verification)
- `OBJECT_STORAGE_ENDPOINT`
- `OBJECT_STORAGE_REGION` (R2: `auto`)
- `OBJECT_STORAGE_ACCESS_KEY_ID`
- `OBJECT_STORAGE_SECRET_ACCESS_KEY`
- `OBJECT_STORAGE_PUBLIC_BUCKET`
- `OBJECT_STORAGE_PRIVATE_BUCKET`
- `OBJECT_STORAGE_PUBLIC_URL` (public photo custom domain)
- `OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT`

Keep credentials only in the local secret store or the owner-managed Render environment. Never place them in this file, a Docker build argument, a migration report, or source control. Do not switch either disk until bucket access policy and migration verification are complete.

## Reviewed backup staging and inventory

The inventory and migration commands accept source directories only beneath `storage/app/migration-input`. Place a verified backup there, with separate folders for photos and private documents. This folder and generated reports are Git-ignored. Do not use the live Render filesystem as a source without an owner-approved export; local files are not evidence of the live file set.

Examples from the project root (paths are examples; do not copy private user files into a shared or committed location):

```powershell
New-Item -ItemType Directory -Force storage/app/migration-input/legacy-upload-photos
New-Item -ItemType Directory -Force storage/app/migration-input/private-documents
php artisan storage:inventory storage/app/migration-input/legacy-upload-photos public-photo
php artisan storage:inventory storage/app/migration-input/private-documents private-document
```

Inventory is read-only and writes JSON metadata beneath `storage/app/private/storage-migration/reports`. It records relative source path, category, size, detected MIME type, SHA-256, and `not_checked` reference/destination status. It does not inspect the database, infer ownership, or claim that a source file is referenced. Review the report and prepare a record-to-file mapping from an approved, read-only database export before any production migration.

## Copy and verification commands

Copy commands are dry-run unless `--execute` is supplied. The source is never deleted. Destination keys preserve the chosen prefix and relative source path. Existing destination objects are never deliberately overwritten: matching size and SHA-256 report `already_verified`; mismatches report `skipped`. A separate verification-only run makes no copies.

The command refuses remote S3-compatible storage unless `--allow-remote-storage` is explicitly supplied, because even dry-run and verification must query the destination. If `APP_ENV=production`, it also requires `--allow-production-endpoint`. Those switches only guard the local command; they are not approval to access production under this task. Use local fake disks for tests and only an owner-approved backup and test bucket for manual exercises.

Select destination prefixes from the reviewed original key shape. For example, source files copied from `public/upload/photos` use `upload/photos`; files from `public/uploads/photos` use `uploads/photos`; files from `storage/app/public/photos` use `photos`; private legacy web-root documents use `upload` or `uploads/documents`; and existing private disk files under `documents` or `registration/resumes` retain those prefixes. Keep separate staged source folders and inventory runs where source roots or prefixes differ.

```powershell
php artisan storage:migrate-reviewed storage/app/migration-input/legacy-upload-photos public-photo upload/photos
php artisan storage:migrate-reviewed storage/app/migration-input/private-documents private-document documents
```

After reviewing the dry-run report and configuring a test bucket only, an operator may explicitly copy:

```powershell
php artisan storage:migrate-reviewed storage/app/migration-input/legacy-upload-photos public-photo upload/photos --execute --allow-remote-storage
php artisan storage:migrate-reviewed storage/app/migration-input/private-documents private-document documents --execute --allow-remote-storage
php artisan storage:migrate-reviewed storage/app/migration-input/legacy-upload-photos public-photo upload/photos --verify-only --allow-remote-storage
php artisan storage:migrate-reviewed storage/app/migration-input/private-documents private-document documents --verify-only --allow-remote-storage
```

The report distinguishes `copied`, `already_verified`, `skipped`, `missing_source`, `failed`, and `verification_failed`. The tool does not update database keys, delete files, or establish that a database reference maps to a copied object. Review duplicate basenames and destination-key collisions manually. Run one migration operator at a time and use a destination prefix reserved for this migration.

## Legacy compatibility

The current Docker ignore rules exclude upload trees except for confirmed tracked photos and four confirmed legacy PDFs. The Docker assembly stage copies those PDFs to private `storage/app/private/upload` and `storage/app/private/uploads/documents` keys and removes their public copies. The final image is based on a separate PHP runtime stage and copies the sanitized filesystem snapshot only; it does not inherit the assembly stage's layers. Other live runtime uploads are not included by Docker.

Private legacy resolution checks the configured private disk using the legacy relative directory and basename, then checks approved local legacy roots. Direct public URLs remain denied by Apache. Existing database references are not rewritten. The tracked root-level `public/uploads/Marksheet.pdf` is not included because no code/route mapping was confirmed; include it only after an approved reference review.

## Test and local setup

Ordinary local development needs no R2 credentials. Tests should use Laravel fake disks and temporary folders only. For a safe Docker validation, start Docker Desktop in Linux-container mode, build the image, then run `apache2ctl -t` with the default `PORT=10000` and an alternate port. Finally inspect the image filesystem to confirm the legacy PDFs exist only under `storage/app/private`, and request the equivalent `/upload/...pdf` and `/uploads/documents/...pdf` URLs to confirm denial. A Laravel feature test cannot substitute for testing Apache's actual directory rules.

## Owner-controlled rollout and rollback

1. Review code, tests, dependency lock changes, and Docker validation locally.
2. Export and back up the actual runtime files through an approved process. Generate a read-only inventory and review references; do not assume the Git-tracked files are the production uploads.
3. Create separate test buckets and least-privilege credentials manually. Keep the private bucket private and validate access without production credentials.
4. Copy to test storage, retain originals, and verify all files by size and SHA-256. Resolve collisions and missing references manually.
5. Confirm the application can resolve every existing database key through either the configured destination key or a retained legacy fallback. No database path changes are part of this implementation.
6. Only after a verified backup and approved owner change, configure Render disk selectors and secrets. Do not set a selector to an empty or incomplete bucket.
7. Deploy only with separate approval, then perform authorized photo/document route smoke tests and inspect logs without exposing secrets.
8. For rollback, point selectors back to the original storage only if that source remains available. Keep both source and destination untouched until post-deployment verification and backup-retention requirements are satisfied.

Render's ordinary filesystem is ephemeral. This code does not activate persistence by itself. Cloud account setup, actual production export, migration, Render environment changes, and deployment remain owner-operated actions. Local metadata reports may contain paths that identify users; protect and delete them according to the owner's retention policy.
