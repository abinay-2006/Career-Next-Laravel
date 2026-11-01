# Security audit remediation notes (M1–M4, H1–H3)

This note records the code-level changes and owner checks required before a production rollout. No migration has been run as part of this work.

## Account identifier ownership (M1)

Identifiers are normalized by trimming surrounding whitespace and lowercasing. Within either login account class, an input that matches a username or an email is treated as the same identifier. Registration, profile synchronization, and login apply that rule. An account may retain the same value in its own username and email fields; the registry records those as two field types owned by that same account.

Application registration and profile writes acquire the named database ownership lock before changing account rows and registry rows. MySQL uses `GET_LOCK`/`RELEASE_LOCK`; PostgreSQL uses session advisory locks. The lock serializes only code paths that use this service. Direct SQL writers and older application instances that do not use the lock must be paused or upgraded before identifier writes resume. The registry's current unique indexes remain per identifier type, so cross-field ownership still depends on all writers using this service and lock.

`AccountIdentifierRegistry::reportCrossFieldCollisions()` is a read-only preflight helper. It returns normalized-value hashes, account classes/IDs, and the fields involved; it does not return raw identifiers or modify accounts. Run it only against an owner-approved, access-controlled database copy with the required account tables. Review the affected accounts manually and obtain the account owners' approved identifier changes. Do not auto-rename, merge, or delete accounts. The registry creation migration also aborts on normalized collisions before creating its table.

## Registry migration and MySQL assumptions (M2)

The migration validates and snapshots account usernames/emails, creates the registry, locks existing rows during import, then compares a second snapshot. `lockForUpdate()` cannot be relied on as a universal lock against phantom inserts or writers on all engines, isolation levels, query plans, and table engines. The migration does not acquire a global write lock. Application advisory locks serialize only application code paths that use the registry service; they do not protect the migration from direct SQL writers, integrations that write account tables, background jobs that bypass the service, or older application versions that do not use that lock.

Before any owner-approved migration:

1. On a production-like staging copy, confirm the exact MySQL/MariaDB version; InnoDB engine and row format; database/table character set and collation; and effective index-length support. The migration's `(identifier_type VARCHAR(20), normalized_value VARCHAR(255))` utf8mb4 unique key can require up to 1,100 bytes. A 767-byte index limit is insufficient. Do not infer compatibility from local SQLite tests or generic version assumptions.
2. Take and verify a restorable backup, rehearse on a production-like copy, and run the read-only collision report. Resolve collisions manually before migration.
3. Schedule a maintenance window and pause **all identifier writes** before validation and keep them paused until post-migration ownership checks finish. This includes registration, Employer and Job Seeker profile updates, admin tools, scheduled/background jobs, integrations, older application instances, and direct SQL or other account-table writers. The migration's row locks and repeated snapshots are race detection, not a global write barrier; advisory locks used by current application code do not coordinate direct SQL writers or older code paths.
4. Run the migration only through the normal owner-controlled deployment process after rehearsal. Do not run it concurrently with account writes.

MySQL DDL can implicitly commit. The migration checks for an existing registry table before starting and stops with an actionable error requiring inspection; it does not treat an existing table as success or automatically drop, truncate, or overwrite it. A process termination can still occur after table creation and before import completion or before the migration ledger is updated. On interruption, inspect the migration ledger, table definition, and registry contents on a preserved staging/backup copy, then choose a recovery plan manually. The cleanup handler only attempts to remove the table created by the current process after an import exception; it cannot run after abrupt process termination. No MySQL execution, concurrency test, or rollback rehearsal was performed here.

## Private document HTTP protection (M3)

The supported Docker Apache configuration denies direct access beneath `/public/upload` and `/public/uploads`, then grants access only to the nested `photos` directories. It separately allows the two named legacy root-level image URLs. Public profile-photo directories therefore remain available. `public/.htaccess` provides Laravel rewrites but is not a substitute for these private-directory rules.

Laravel's Job Seeker education-document route binds the document to the authenticated Job Seeker ID. The employer resume route checks that the authenticated employer owns the application before streaming the resume. `PrivateUpload` streams from the configured private disk and has a constrained legacy fallback used only through these authorized controllers. Synthetic private-disk feature tests cover Job Seeker ownership and response headers. Apache's actual HTTP behavior was not exercised because no container/server build was run. Non-Apache deployments are unsupported until equivalent deny rules are configured and tested; for Nginx, deny `/upload/` and `/uploads/` except the explicit public photo paths and named root-level image assets, while keeping authorized Laravel routes available.

## H1 Docker image layers

The final `runtime` stage starts from `php-runtime-base`; it does not inherit `app-assembly` layers and only copies the assembled filesystem. The shared base contains PHP/Apache dependencies and configuration, not application upload documents. `.dockerignore` excludes both legacy upload trees except public photos and two explicit public root images; it also excludes `storage/app` contents. Thus the Dockerfile/context structure excludes private legacy PDFs from the final image ancestry. No Docker daemon/image was used, so final filesystem and image-layer payload absence remain unverified.

## H2 Password recovery limits

Named send and verification limiters are registered in `AppServiceProvider`. Each uses separate hashed normalized-email and request-IP keys. Email keys trim and lowercase input/session email, and account type is not part of those keys. The send route permits 3 per email and 10 per IP per minute. Verification route middleware permits 10 per email and 30 per IP per minute; the controller independently allows at most 5 failed OTP guesses per normalized email per 10 minutes. Resending does not clear that failure counter; successful verification does. These application limits rely on the configured cache store being shared across all production application instances for cluster-wide enforcement.

## H3 Trusted proxies

`config/trustedproxy.php` accepts comma-separated owner-verified IPs/CIDRs through `TRUSTED_PROXIES`. It defaults to an empty list and rejects `*`/`**`. Forwarded client IP, host, protocol, and port are honored only when the immediate peer is in that list. No deployment proxy ranges were inferred or added. The deployment owner must obtain authoritative current ranges and configure them; until then, proxy-derived HTTPS/host URL generation is intentionally not trusted. The upstream proxy must also overwrite/sanitize forwarded headers.
