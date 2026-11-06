<?php

namespace App\Support;

use ArrayObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Table and column existence checks that cost at most one round trip each.
 *
 * The Schema facade answers these with a live query to `information_schema`,
 * which on a hosted database is a full network round trip. A single page asks
 * the same question several times - the resume table from the match service and
 * again from the suitability service, the chat tables from the sidebar badge and
 * again from the card - and the guards together cost seven round trips on the
 * job search alone. Those checks happen again on every click, so they are the
 * majority of the wait rather than the data being asked for.
 *
 * Answers are memoised per request, then per cache window. A migration is
 * detected through the connection that runs it, which starts a new window, so
 * the table that has just been created is visible without a restart; a schema
 * changed outside this application is picked up when the window closes.
 */
class SchemaReady
{
    /** How long one set of answers stays valid without a migration to end it. */
    private const WINDOW = 300;

    private const MEMO = 'cn.schema_ready';

    public static function hasTable(string $table): bool
    {
        return static::once(
            'table '.$table,
            fn (): bool => Schema::hasTable($table)
        );
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return static::once(
            "column {$table}.{$column}",
            fn (): bool => Schema::hasColumn($table, $column)
        );
    }

    /**
     * Start a new window so the next read asks the database again.
     *
     * Called when this application changes the schema itself; a schema changed
     * from outside it waits for the window to close instead.
     */
    public static function invalidate(): void
    {
        Cache::forget(self::MEMO.'.window');
    }

    private static function once(string $key, callable $check): bool
    {
        $memo = static::memo();

        if ($memo === null) {
            return static::windowed($key, $check);
        }

        return $memo[$key] ??= static::windowed($key, $check);
    }

    private static function windowed(string $key, callable $check): bool
    {
        // The window id is part of the key, so invalidating it orphans every
        // answer at once instead of hunting the individual cache entries.
        $window = Cache::remember(
            self::MEMO.'.window',
            self::WINDOW,
            fn (): string => (string) random_int(100000, 999999)
        );

        return Cache::remember(self::MEMO.".$window.$key", self::WINDOW, $check);
    }

    /**
     * The per-request memo lives on the Request object, so its lifetime is
     * exactly one request and it needs nothing to clear it. Outside a request -
     * a console command or a queue worker - there is no such boundary, and those
     * callers simply use the cached answers the window provides.
     */
    private static function memo(): ?ArrayObject
    {
        $request = app()->bound('request') ? app('request') : null;

        if (!$request instanceof SymfonyRequest) {
            return null;
        }

        if (!$request->attributes->has(self::MEMO)) {
            $request->attributes->set(self::MEMO, new ArrayObject());
        }

        return $request->attributes->get(self::MEMO);
    }
}
