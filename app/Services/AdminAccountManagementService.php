<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Shared Admin account lookup and status operation for the protected UI and Copilot. */
class AdminAccountManagementService
{
    private const ACCOUNT_TABLES = [
        'jobseeker' => ['table' => 'jobseeker_reg', 'key' => 'JobSeekId', 'name' => 'JobSeekerName'],
        'employer' => ['table' => 'employer_reg', 'key' => 'EmployerId', 'name' => 'CompanyName'],
    ];

    /** @return list<array{account_type: string, id: int, name: string, status: string}> */
    public function findExactTargets(Admin $actor, string $identifier, ?string $accountType = null): array
    {
        $this->assertCurrentAdmin($actor);
        $identifier = trim($identifier);
        if ($identifier === '') return [];

        $types = $accountType === null ? array_keys(self::ACCOUNT_TABLES) : [$accountType];
        $matches = [];
        foreach ($types as $type) {
            $definition = self::ACCOUNT_TABLES[$type] ?? null;
            if ($definition === null || !Schema::hasTable($definition['table'])) continue;

            $query = DB::table($definition['table']);
            if (ctype_digit($identifier)) {
                $query->where($definition['key'], (int) $identifier);
            } else {
                $columns = array_values(array_filter(
                    [$definition['name'], 'UserName', 'Email'],
                    fn (string $column): bool => Schema::hasColumn($definition['table'], $column),
                ));
                if ($columns === []) continue;
                $query->where(function ($subquery) use ($columns, $identifier): void {
                    foreach ($columns as $column) {
                        $subquery->orWhereRaw('LOWER(TRIM(`'.$column.'`)) = ?', [mb_strtolower($identifier)]);
                    }
                });
            }

            $nameColumn = Schema::hasColumn($definition['table'], $definition['name']) ? $definition['name'] : $definition['key'];
            $statusColumn = Schema::hasColumn($definition['table'], 'Status') ? 'Status' : null;
            $selected = [$definition['key'], $nameColumn];
            if ($statusColumn !== null) $selected[] = $statusColumn;

            foreach ($query->limit(6)->get($selected) as $record) {
                $matches[] = [
                    'account_type' => $type,
                    'id' => (int) $record->{$definition['key']},
                    'name' => (string) $record->{$nameColumn},
                    'status' => $statusColumn === null ? '' : (string) $record->{$statusColumn},
                ];
            }
        }

        return $matches;
    }

    /** @return array{success: bool, message: string, status?: string} */
    public function suspend(Admin $actor, string $accountType, int $accountId): array
    {
        $this->assertCurrentAdmin($actor);
        if ($accountId < 1) {
            return ['success' => false, 'message' => 'Account not found.'];
        }
        $definition = self::ACCOUNT_TABLES[$accountType] ?? null;
        if ($definition === null) {
            return ['success' => false, 'message' => 'Unsupported account type.'];
        }

        $result = DB::transaction(function () use ($definition, $accountType, $accountId): array {
            $record = DB::table($definition['table'])
                ->where($definition['key'], $accountId)
                ->lockForUpdate()
                ->first();
            if (!$record) return ['success' => false, 'message' => 'Account not found.'];

            if (strtolower(trim((string) ($record->Status ?? ''))) === 'suspended') {
                return ['success' => true, 'changed' => false, 'message' => 'Account was already suspended.', 'status' => 'Suspended'];
            }

            DB::table($definition['table'])->where($definition['key'], $accountId)->update(['Status' => 'Suspended']);
            app(ActivityLogger::class)->record('account_suspended', $accountType, $accountId);

            return ['success' => true, 'changed' => true, 'message' => 'Account suspended.', 'status' => 'Suspended'];
        });

        return $result;
    }

    private function assertCurrentAdmin(Admin $actor): void
    {
        $current = Auth::guard('admin')->user();
        abort_unless($current instanceof Admin && (int) $current->getAuthIdentifier() === (int) $actor->getAuthIdentifier(), 403);
    }
}
