<?php

namespace App\Support;

/**
 * Working set for the Job Offer Comparison Lab.
 *
 * Kept in the session instead of a table because a comparison is a short-lived
 * scratchpad of up to four listings. Every read still re-checks the listing
 * against the database, so nothing stored here is treated as trustworthy.
 */
class JobComparisonState
{
    public const MAX_JOBS = 4;

    public const FACTORS = [
        'salary' => 'Salary',
        'location' => 'Location',
        'work_life_balance' => 'Work-life balance',
        'career_growth' => 'Career growth',
        'skill_match' => 'Skill compatibility',
    ];

    public const MIN_WEIGHT = 0;

    public const MAX_WEIGHT = 100;

    public const DEFAULT_WEIGHT = 50;

    public const ADDED = 'added';

    public const DUPLICATE = 'duplicate';

    public const FULL = 'full';

    private const KEY = 'job_comparison';

    /**
     * @return list<array{id: int, title: string, company: string}>
     */
    public static function entries(): array
    {
        return array_values(self::keyed());
    }

    /**
     * @return array<int, array{id: int, title: string, company: string}>
     */
    private static function keyed(): array
    {
        $stored = session()->get(self::KEY . '.jobs', []);

        if (!is_array($stored)) {
            return [];
        }

        $clean = [];

        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = (int) ($entry['id'] ?? 0);

            if ($id <= 0 || isset($clean[$id]) || count($clean) >= self::MAX_JOBS) {
                continue;
            }

            // The snapshot only labels the replace dialog; the listing itself is
            // always re-read from the database.
            $clean[$id] = [
                'id' => $id,
                'title' => mb_substr(self::text($entry['title'] ?? null), 0, 120),
                'company' => mb_substr(self::text($entry['company'] ?? null), 0, 60),
            ];
        }

        return $clean;
    }

    /**
     * @return list<int>
     */
    public static function jobIds(): array
    {
        return array_map(static fn (array $entry): int => $entry['id'], self::entries());
    }

    public static function count(): int
    {
        return count(self::entries());
    }

    public static function contains(int $jobId): bool
    {
        return in_array($jobId, self::jobIds(), true);
    }

    /**
     * @return self::ADDED|self::DUPLICATE|self::FULL
     */
    public static function add(int $jobId, string $title = '', string $company = ''): string
    {
        $entries = self::keyed();

        if (isset($entries[$jobId])) {
            return self::DUPLICATE;
        }

        if (count($entries) >= self::MAX_JOBS) {
            return self::FULL;
        }

        $entries[$jobId] = ['id' => $jobId, 'title' => $title, 'company' => $company];

        self::write($entries);

        return self::ADDED;
    }

    public static function remove(int $jobId): bool
    {
        $entries = self::keyed();

        if (!isset($entries[$jobId])) {
            return false;
        }

        unset($entries[$jobId]);

        self::write($entries);

        return true;
    }

    /**
     * Swap one slot for another listing, keeping its position in the table.
     */
    public static function replace(int $outId, int $inId, string $title = '', string $company = ''): bool
    {
        $entries = self::keyed();

        if (!isset($entries[$outId]) || isset($entries[$inId]) || $outId === $inId) {
            return false;
        }

        $ordered = [];

        foreach ($entries as $id => $entry) {
            if ($id === $outId) {
                $entry = ['id' => $inId, 'title' => $title, 'company' => $company];
            }

            $ordered[$entry['id']] = $entry;
        }

        self::write($ordered);

        return true;
    }

    /**
     * Shift a listing left or right among the compared columns.
     */
    public static function move(int $jobId, int $offset): bool
    {
        $entries = self::keyed();

        if (!isset($entries[$jobId])) {
            return false;
        }

        $ids = array_keys($entries);
        $position = array_search($jobId, $ids, true);
        $target = $position + max(-1, min(1, $offset));

        if ($target === $position || $target < 0 || $target >= count($ids)) {
            return false;
        }

        // The buttons move a column one step, so a swap with its neighbour is
        // all this needs.
        $ids[$position] = $ids[$target];
        $ids[$target] = $jobId;

        $ordered = [];

        foreach ($ids as $id) {
            $ordered[$id] = $entries[$id];
        }

        self::write($ordered);

        return true;
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Drop the listings that are no longer there to compare.
     *
     * @param  array<int, int|string>  $keep
     */
    public static function retain(array $keep): void
    {
        $keep = array_map(static fn ($id): int => (int) $id, $keep);

        $entries = array_filter(
            self::keyed(),
            static fn (int $id): bool => in_array($id, $keep, true),
            ARRAY_FILTER_USE_KEY
        );

        self::write($entries);
    }

    /**
     * @return array<string, int>
     */
    public static function weights(): array
    {
        $stored = session()->get(self::KEY . '.weights', []);

        $weights = [];

        foreach (array_keys(self::FACTORS) as $factor) {
            $value = is_array($stored) && array_key_exists($factor, $stored)
                ? (int) $stored[$factor]
                : self::DEFAULT_WEIGHT;

            $weights[$factor] = max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, $value));
        }

        return $weights;
    }

    /**
     * Only the five priority weights are read; anything else in the request -
     * including a score - is ignored, so scores stay server-side.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, int>
     */
    public static function setWeights(array $input): array
    {
        $current = self::weights();

        foreach (array_keys(self::FACTORS) as $factor) {
            if (array_key_exists($factor, $input)) {
                $current[$factor] = max(self::MIN_WEIGHT, min(self::MAX_WEIGHT, (int) $input[$factor]));
            }
        }

        session()->put(self::KEY . '.weights', $current);

        return $current;
    }

    /**
     * @return array<string, int>
     */
    public static function resetWeights(): array
    {
        session()->forget(self::KEY . '.weights');

        return self::weights();
    }

    /**
     * @param  array<int, array{id: int, title: string, company: string}>  $entries
     */
    private static function write(array $entries): void
    {
        session()->put(self::KEY . '.jobs', array_values($entries));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '') : '';
    }
}
