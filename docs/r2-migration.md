# Cloudflare R2 Storage Migration Guide

This document outlines how to migrate local file storage to Cloudflare R2 (or any S3-compatible provider). This is a manual, multi-step process that requires careful verification at each stage.

## 1. Storage Architecture

The application uses three independent settings to determine where files are stored. Configuring R2 credentials does **not** automatically switch the application to use R2, nor does it migrate existing files.

| Setting | Purpose | Default |
|---------|---------|---------|
| `FILESYSTEM_DISK` | Default disk for general Laravel storage. | `private` |
| `PUBLIC_UPLOAD_DISK` | Disk for public profile photos. | `public` |
| `PRIVATE_UPLOAD_DISK` | Disk for private resumes and documents. | `private` |

### Local Disks (Default)
- **public**: Points to `storage/app/public`, symlinked to `public/storage`.
- **private**: Points to `storage/app/private`, inaccessible from the web.

### R2 Disks (Optional)
- **r2-public**: S3-compatible disk for public assets.
- **r2-private**: S3-compatible disk for private assets (streamed via Laravel).

## 2. Environment Configuration

To use R2, you must define the following variables in your `.env` file or Render dashboard.

### Required Variables
- `OBJECT_STORAGE_ENDPOINT`: Your R2 endpoint (e.g., `https://<account-id>.r2.cloudflarestorage.com`).
- `OBJECT_STORAGE_REGION`: Usually `auto`.
- `OBJECT_STORAGE_ACCESS_KEY_ID`: Your R2 Access Key.
- `OBJECT_STORAGE_SECRET_ACCESS_KEY`: Your R2 Secret Key.
- `OBJECT_STORAGE_PUBLIC_BUCKET`: The bucket for public photos.
- `OBJECT_STORAGE_PRIVATE_BUCKET`: The bucket for private documents.
- `OBJECT_STORAGE_PUBLIC_URL`: The public URL/CDN for the public bucket (e.g., `https://pub-xxx.r2.dev`).
- `OBJECT_STORAGE_USE_PATH_STYLE_ENDPOINT`: Set to `false` for R2.

> **Warning:** Do not use `OBJECT_STORAGE_BUCKET`, `OBJECT_STORAGE_NAME`, or `OBJECT_STORAGE_DATA_PERSISTER`. These are not implemented and will be ignored.

## 3. Inventorying Local Files

Before migrating, you must create a JSON inventory of the files you intend to move.

**Requirement:** Files to be inventoried must be placed in a subdirectory under `storage/app/migration-input/`.

### Command
```bash
php artisan storage:inventory <source-directory> <category>
```

- `<source-directory>`: The name of the folder inside `storage/app/migration-input/`.
- `<category>`: Either `public-photo` or `private-document`.

### Example
```bash
# Inventory public photos from storage/app/migration-input/backup-2023/
php artisan storage:inventory backup-2023 public-photo
```

The command generates a JSON report in `storage/app/private/storage-migration/reports/` containing file paths, sizes, and SHA-256 hashes.

## 4. Migrating Files to R2

The migration process copies files from a reviewed local source to the configured R2 bucket.

### Command
```bash
php artisan storage:migrate-reviewed <source-directory> <category> <destination-prefix> [options]
```

### Arguments
- `<source-directory>`: The folder inside `storage/app/migration-input/`.
- `<category>`: `public-photo` or `private-document`.
- `<destination-prefix>`: The top-level folder name in the R2 bucket (e.g., `photos` or `resumes`).

### Options
- `--execute`: Actually perform the copy. Without this, the command runs a **dry run**.
- `--verify-only`: Compare existing files in R2 against the local source without copying anything.
- `--allow-remote-storage`: Required to permit the command to contact the R2 endpoint.
- `--allow-production-endpoint`: Required if running in `APP_ENV=production`.

### Safety Features & Behavior
- **Copy-Only**: The command only copies files. It **never deletes** source files.
- **No Overwrites**: If a file already exists at the destination, it is compared by size and SHA-256. If they match, it is skipped (`already_verified`). If they don't match, it is skipped (`verification_failed`). The command never overwrites existing objects.
- **No Database Changes**: This command **does not update database references**. You must ensure your database records match the `destination-prefix` used during migration.
- **Manual Execution**: There is no automatic migration during deployment or startup.

### Examples
```bash
# Dry run for public photos
php artisan storage:migrate-reviewed reviewed-uploads public-photo photos --allow-remote-storage

# Execute migration for private documents
php artisan storage:migrate-reviewed reviewed-docs private-document documents --execute --allow-remote-storage --allow-production-endpoint
```

## 2.5 Storage Verification Command

Before proceeding with any migration, validate your storage configuration using the `storage:verify` command.

```bash
php artisan storage:verify
```

This command checks:
- Validity of the configured public and private disks.
- For remote disks (R2/B2), it verifies required environment variables are set.

**Optional Remote Write Test**

To test actual connectivity to a remote private disk, use:

```bash
php artisan storage:verify --test-remote-write --allow-remote
```

This performs a full write-read-delete cycle with a unique test object in your private bucket. It requires the `--allow-remote` flag to permit network access.

**Notes**
- Runs in **dry-run mode by default**; no objects are created unless `--test-remote-write` is used.
- Test objects are automatically cleaned up, even on failure.
- Never stores sensitive data in test objects.
- Must be run before any migration to catch configuration errors early.

After running a migration, a JSON report is saved in `storage/app/private/storage-migration/reports/`. Each file will have one of the following statuses:

- `already_verified`: File already exists in R2 with matching hash and size.
- `copied`: File was successfully uploaded and verified.
- `skipped`: File exists but hashes/size don't match, or `--execute` was not passed.
- `failed`: An error occurred during upload.
- `verification_failed`: Uploaded file hash did not match local file hash.
- `missing_source`: Local file could not be read.

## 6. Security Requirements

Public photos and private documents require different access policies in Cloudflare R2:

1.  **Public Bucket (`r2-public`)**: Should have a Public Bucket URL or Custom Domain enabled in Cloudflare. Files are served directly via `OBJECT_STORAGE_PUBLIC_URL`.
2.  **Private Bucket (`r2-private`)**: **Must remain private.** No public URL or custom domain should be enabled. Files are streamed through the Laravel application via `App\Support\PrivateUpload`, ensuring only authorized users can download them.

## 7. Migration Checklist

Follow these steps in order:

1.  [ ] **Secure Credentials**: Create R2 API tokens with appropriate permissions (Read/Write for the specific buckets).
2.  [ ] **Confirm Locations**: Identify exactly where files are currently stored on the server.
3.  [ ] **Back Up Everything**: Create a full backup of all production files and the database.
4.  [ ] **Prepare Migration Input**: Copy the files you want to migrate into `storage/app/migration-input/<name>`.
5.  [ ] **Inventory**: Run `storage:inventory` and review the generated JSON report.
6.  [ ] **Verify R2**: Manually upload a test file to both buckets to confirm credentials and policies work.
7.  [ ] **Dry Run**: Run `storage:migrate-reviewed` without `--execute` and review the report.
8.  [ ] **Staging Test**: Perform the full migration and cut-over in a staging environment first.
9.  [ ] **Execute Migration**: Run with `--execute` and `--allow-remote-storage`.
10. [ ] **Verify Hashes**: Review the final migration report for any `failed` or `verification_failed` entries.
11. [ ] **App Verification**: Log in to the application and confirm photos load and resumes can be downloaded.
12. [ ] **Cut-Over**: Update `PUBLIC_UPLOAD_DISK` and `PRIVATE_UPLOAD_DISK` in your environment settings to `r2-public` and `r2-private`.

## 8. Render & Persistence

Render's "Web Service" disks are ephemeral unless you use **Render Blueprints with Disks** or a **Persistent Disk**. Do not assume local storage is persistent across restarts on Render unless explicitly configured. Using R2 is the recommended way to achieve persistent, scalable storage on Render.
