<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Closure;

class AccountIdentifierRegistry
{
    /** @var array<int, int> */
    private static array $ownershipLockDepth = [];

    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    public static function isValidUsername(string $value): bool
    {
        $normalized = trim($value);

        return preg_match('/^[A-Za-z0-9._-]+$/D', $normalized) === 1
            || filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Serialize application identifier writes on databases that support advisory locks.
     * SQLite tests run on one isolated connection and do not need a server lock.
     */
    public function withOwnershipLock(Closure $callback): mixed
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $lockName = 'career_next_identifier_owner';
        $connectionId = spl_object_id($connection);

        if ((self::$ownershipLockDepth[$connectionId] ?? 0) > 0) {
            self::$ownershipLockDepth[$connectionId]++;
            try {
                return $callback();
            } finally {
                self::$ownershipLockDepth[$connectionId]--;
            }
        }

        if ($driver === 'mysql') {
            $result = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
            if ((int) ($result->acquired ?? 0) !== 1) {
                throw new \RuntimeException('Could not acquire the account identifier ownership lock.');
            }

            self::$ownershipLockDepth[$connectionId] = 1;
            try {
                return $callback();
            } finally {
                unset(self::$ownershipLockDepth[$connectionId]);
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
        }

        if ($driver === 'pgsql') {
            $connection->selectOne('SELECT pg_advisory_lock(hashtext(?))', [$lockName]);
            self::$ownershipLockDepth[$connectionId] = 1;
            try {
                return $callback();
            } finally {
                unset(self::$ownershipLockDepth[$connectionId]);
                $connection->selectOne('SELECT pg_advisory_unlock(hashtext(?))', [$lockName]);
            }
        }

        return $callback();
    }

    /**
     * Return a read-only report of normalized identifiers owned by multiple accounts.
     * Values are represented by SHA-256 hashes so reports do not disclose identifiers.
     *
     * @return list<array{identifier_hash: string, owners: list<array{account_type: string, account_id: int|string, fields: list<string>}>}>
     */
    public function reportCrossFieldCollisions(): array
    {
        $ownersByValue = [];
        foreach ([
            ['employer_reg', 'EmployerId', 'employer'],
            ['jobseeker_reg', 'JobSeekId', 'jobseeker'],
        ] as [$table, $key, $accountType]) {
            foreach (DB::table($table)->get([$key, 'UserName', 'Email']) as $account) {
                $ownerKey = $accountType.':'.$account->$key;
                foreach (['username' => $account->UserName, 'email' => $account->Email] as $field => $value) {
                    $normalized = self::normalize((string) $value);
                    if ($normalized !== '') {
                        $ownersByValue[$normalized][$ownerKey] ??= [
                            'account_type' => $accountType,
                            'account_id' => $account->$key,
                            'fields' => [],
                        ];
                        $ownersByValue[$normalized][$ownerKey]['fields'][] = $field;
                    }
                }
            }
        }

        $report = [];
        foreach ($ownersByValue as $normalized => $owners) {
            if (count($owners) > 1) {
                $report[] = [
                    'identifier_hash' => hash('sha256', $normalized),
                    'owners' => array_values($owners),
                ];
            }
        }

        return $report;
    }

    public static function existsInAccounts(string $value): bool
    {
        $normalized = self::normalize($value);
        foreach (['employer_reg', 'jobseeker_reg'] as $table) {
            if (DB::table($table)
                ->whereRaw('LOWER(TRIM(UserName)) = ?', [$normalized])
                ->orWhereRaw('LOWER(TRIM(Email)) = ?', [$normalized])
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function sync(string $accountType, int|string $accountId, string $username, string $email): void
    {
        $table = $accountType === 'employer' ? 'employer_reg' : 'jobseeker_reg';
        $key = $accountType === 'employer' ? 'EmployerId' : 'JobSeekId';
        if (!in_array($accountType, ['employer', 'jobseeker'], true)
            || !DB::table($table)->where($key, $accountId)->exists()) {
            throw new \InvalidArgumentException('Unknown account type or account.');
        }

        foreach (['username' => $username, 'email' => $email] as $type => $value) {
            $normalized = self::normalize($value);
            if ($normalized === '') {
                throw ValidationException::withMessages([$type === 'username' ? 'username' : 'email' => 'A username and email are required.']);
            }
            $valid = $type === 'username' ? self::isValidUsername($normalized) : self::isValidEmail($normalized);
            if (!$valid) {
                throw ValidationException::withMessages([$type => 'Please provide a valid username and email address.']);
            }
        }

        $this->withOwnershipLock(function () use ($accountType, $accountId, $username, $email): void {
            DB::transaction(function () use ($accountType, $accountId, $username, $email): void {
                foreach (array_unique([self::normalize($username), self::normalize($email)]) as $normalized) {
                    $conflict = DB::table('account_identifiers')
                        ->where('normalized_value', $normalized)
                        ->where(function ($query) use ($accountType, $accountId): void {
                            $query->where('account_type', '!=', $accountType)
                                ->orWhere('account_id', '!=', $accountId);
                        })
                        ->exists();
                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'username' => 'That username or email is already in use.',
                        ]);
                    }
                }

                // Remove any existing identifiers for this account only after both new
                // reservations have passed the cross-field ownership check.
                $this->release($accountType, $accountId);

                // Insert the new identifiers.
                foreach ([
                    'username' => $username,
                    'email' => $email,
                ] as $type => $value) {
                    $normalized = self::normalize($value);
                    DB::table('account_identifiers')->insert([
                        'identifier_type' => $type,
                        'normalized_value' => $normalized,
                        'account_type' => $accountType,
                        'account_id' => $accountId,
                    ]);
                }
            });
        });
    }

    public function release(string $accountType, int|string $accountId): void
    {
        DB::table('account_identifiers')->where('account_type', $accountType)->where('account_id', $accountId)->delete();
    }

    public static function isUniqueViolation(\Throwable $exception): bool
    {
        return $exception instanceof \Illuminate\Database\QueryException
            && in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true);
    }
}
