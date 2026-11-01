<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\AccountIdentifierRegistry;

return new class extends Migration
{
    private const ACCOUNT_TABLES = [
        ['employer_reg', 'EmployerId', 'employer'],
        ['jobseeker_reg', 'JobSeekId', 'jobseeker'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('account_identifiers')) {
            throw new RuntimeException(
                'Cannot build account identifier registry: the registry table already exists. '
                .'Inspect the migration ledger, table schema, and table contents on a preserved database copy; '
                .'do not rerun, drop, or overwrite the table automatically.'
            );
        }

        // Validate a stable snapshot before making any schema changes.
        [$snapshot, $reservations] = $this->readAndValidateAccounts();

        Schema::create('account_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_type', 20);
            $table->string('normalized_value', 255);
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id');
            $table->unique(['identifier_type', 'normalized_value'], 'account_identifiers_value_unique');
            $table->unique(['account_type', 'account_id', 'identifier_type'], 'account_identifiers_owner_unique');
        });

        try {
            DB::transaction(function () use ($snapshot, $reservations): void {
                // Lock existing account rows and compare again after the registry table exists.
                // Any edits or additions since validation abort before importing identifiers.
                [$currentSnapshot] = $this->readAndValidateAccounts(lockRows: true);
                if ($currentSnapshot !== $snapshot) {
                    throw new RuntimeException('Cannot build account identifier registry: account identifiers changed during migration validation. Retry the migration after account updates stop.');
                }

                // Import the exact normalized values that passed the collision and validity checks.
                DB::table('account_identifiers')->insert($reservations);

                // Detect concurrent inserts or edits that occurred while the registry was populated.
                [$finalSnapshot] = $this->readAndValidateAccounts(lockRows: true);
                if ($finalSnapshot !== $snapshot) {
                    throw new RuntimeException('Cannot build account identifier registry: account identifiers changed while the registry was populated. Retry the migration after account updates stop.');
                }
            });
        } catch (Throwable $exception) {
            // MySQL may implicitly commit DDL; explicitly remove an incomplete registry on failure.
            Schema::dropIfExists('account_identifiers');
            throw $exception;
        }
    }

    /**
     * @return array{array<string, array{username: string, email: string}>, list<array{identifier_type: string, normalized_value: string, account_type: string, account_id: int|string}>}
     */
    private function readAndValidateAccounts(bool $lockRows = false): array
    {
        foreach (self::ACCOUNT_TABLES as [$table]) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("Cannot build account identifier registry: required account table '{$table}' is missing. Restore or migrate that table, then retry.");
            }
        }

        $snapshot = [];
        $reservations = [];
        $seen = [];

        foreach (self::ACCOUNT_TABLES as [$table, $key, $accountType]) {
            $query = DB::table($table)->orderBy($key);
            if ($lockRows) {
                $query->lockForUpdate();
            }

            foreach ($query->get([$key, 'UserName', 'Email']) as $account) {
                $normalizedIdentifiers = [];
                foreach (['username' => $account->UserName, 'email' => $account->Email] as $type => $value) {
                    $normalized = AccountIdentifierRegistry::normalize((string) $value);
                    if ($normalized === '') {
                        throw new RuntimeException("Cannot build account identifier registry: {$table}.{$key}={$account->$key} has an empty {$type}. Correct the account data and retry.");
                    }

                    $valid = $type === 'username'
                        ? AccountIdentifierRegistry::isValidUsername($normalized)
                        : AccountIdentifierRegistry::isValidEmail($normalized);
                    if (!$valid) {
                        throw new RuntimeException("Cannot build account identifier registry: {$table}.{$key}={$account->$key} has an invalid {$type}. Correct the account data and retry.");
                    }

                    $collisionKey = $normalized;
                    $owner = ['table' => $table, 'id' => $account->$key, 'account_type' => $accountType];
                    if (isset($seen[$collisionKey])
                        && ($seen[$collisionKey]['account_type'] !== $accountType
                            || (string) $seen[$collisionKey]['id'] !== (string) $account->$key)) {
                        $existing = $seen[$collisionKey];
                        throw new RuntimeException("Cannot build account identifier registry: normalized identifier collision between {$existing['table']} {$existing['id']} ({$existing['field']}) and {$table} {$account->$key} ({$type}). Resolve the collision manually and retry.");
                    }
                    $seen[$collisionKey] ??= $owner + ['field' => $type];
                    $normalizedIdentifiers[$type] = $normalized;
                    $reservations[] = [
                        'identifier_type' => $type,
                        'normalized_value' => $normalized,
                        'account_type' => $accountType,
                        'account_id' => $account->$key,
                    ];
                }

                $snapshot[$accountType . ':' . $account->$key] = $normalizedIdentifiers;
            }
        }

        ksort($snapshot);

        return [$snapshot, $reservations];
    }

    public function down(): void
    {
        Schema::dropIfExists('account_identifiers');
    }
};
