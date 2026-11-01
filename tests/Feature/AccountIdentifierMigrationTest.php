<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AccountIdentifierMigrationTest extends TestCase
{
    public function test_existing_case_insensitive_collision_aborts_migration_before_creating_registry(): void
    {
        $this->createAccountTables();
        DB::table('employer_reg')->insert(['UserName' => 'Existing.Name', 'Email' => 'one@example.test']);
        DB::table('jobseeker_reg')->insert(['UserName' => 'existing.name', 'Email' => 'two@example.test']);

        $migration = require database_path('migrations/2026_10_30_000000_create_account_identifier_registry.php');
        try {
            $migration->up();
            $this->fail('Expected the migration to stop on a normalized identifier collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('normalized identifier collision', $exception->getMessage());
            $this->assertStringNotContainsString('Existing.Name', $exception->getMessage());
            $this->assertStringNotContainsString('existing.name', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('account_identifiers'));
        }
    }

    public function test_email_style_username_equal_to_email_is_imported_as_two_identifier_types(): void
    {
        $this->createAccountTables();
        DB::table('employer_reg')->insert(['UserName' => 'ABC@Example.test', 'Email' => 'abc@example.test']);

        $this->runMigration();

        $this->assertSame(2, DB::table('account_identifiers')->where('normalized_value', 'abc@example.test')->count());
        $this->assertDatabaseHas('account_identifiers', [
            'identifier_type' => 'username', 'normalized_value' => 'abc@example.test', 'account_type' => 'employer', 'account_id' => 1,
        ]);
        $this->assertDatabaseHas('account_identifiers', [
            'identifier_type' => 'email', 'normalized_value' => 'abc@example.test', 'account_type' => 'employer', 'account_id' => 1,
        ]);
    }

    public function test_missing_required_account_table_fails_before_creating_registry(): void
    {
        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId'); $table->string('UserName'); $table->string('Email');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("required account table 'jobseeker_reg' is missing");
        $this->runMigration();
    }

    public function test_missing_employer_table_fails_before_creating_registry(): void
    {
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId'); $table->string('UserName'); $table->string('Email');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("required account table 'employer_reg' is missing");
        $this->runMigration();
    }

    public function test_invalid_existing_identifier_aborts_migration_without_registry(): void
    {
        $this->createAccountTables();
        DB::table('employer_reg')->insert(['UserName' => 'invalid name', 'Email' => 'valid@example.test']);

        try {
            $this->runMigration();
            $this->fail('Expected invalid username data to stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid username', $exception->getMessage());
            $this->assertStringContainsString('employer_reg.EmployerId=1', $exception->getMessage());
            $this->assertStringNotContainsString('invalid name', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('account_identifiers'));
        }
    }

    public function test_invalid_existing_email_aborts_migration_without_registry(): void
    {
        $this->createAccountTables();
        DB::table('employer_reg')->insert(['UserName' => 'valid-name', 'Email' => 'not-an-email']);

        try {
            $this->runMigration();
            $this->fail('Expected invalid email data to stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid email', $exception->getMessage());
            $this->assertStringContainsString('employer_reg.EmployerId=1', $exception->getMessage());
            $this->assertStringNotContainsString('not-an-email', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('account_identifiers'));
        }
    }

    public function test_existing_empty_registry_table_is_preserved_and_not_treated_as_complete(): void
    {
        $this->createAccountTables();
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
            $this->runMigration();
            $this->fail('Expected an existing registry table to require manual inspection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('registry table already exists', $exception->getMessage());
            $this->assertStringContainsString('migration ledger', $exception->getMessage());
            $this->assertStringContainsString('table schema', $exception->getMessage());
            $this->assertStringContainsString('table contents', $exception->getMessage());
            $this->assertTrue(Schema::hasTable('account_identifiers'));
            $this->assertSame(0, DB::table('account_identifiers')->count());
            $this->assertTrue(Schema::hasColumn('account_identifiers', 'normalized_value'));
        }
    }

    public function test_partially_populated_registry_table_is_preserved_for_manual_recovery(): void
    {
        $this->createAccountTables();
        Schema::create('account_identifiers', function (Blueprint $table): void {
            // Deliberately incomplete: an interrupted attempt may leave a table
            // with only part of its intended schema or contents.
            $table->string('identifier_type', 20);
            $table->string('normalized_value', 255);
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id');
        });
        DB::table('account_identifiers')->insert([
            'identifier_type' => 'username',
            'normalized_value' => 'partial-test-value',
            'account_type' => 'employer',
            'account_id' => 7,
        ]);

        try {
            $this->runMigration();
            $this->fail('Expected a partially populated registry to require manual inspection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('registry table already exists', $exception->getMessage());
            $this->assertTrue(Schema::hasTable('account_identifiers'));
            $this->assertFalse(Schema::hasColumn('account_identifiers', 'id'));
            $this->assertSame(1, DB::table('account_identifiers')->count());
            $this->assertDatabaseHas('account_identifiers', [
                'identifier_type' => 'username',
                'normalized_value' => 'partial-test-value',
                'account_type' => 'employer',
                'account_id' => 7,
            ]);
        }
    }

    public function test_identifier_changes_after_validation_abort_without_importing_stale_values(): void
    {
        $this->createAccountTables();
        DB::table('employer_reg')->insert(['UserName' => 'stable.name', 'Email' => 'stable@example.test']);
        $changed = false;
        DB::listen(function (QueryExecuted $query) use (&$changed): void {
            if (!$changed && str_contains(strtolower($query->sql), 'create table') && str_contains(strtolower($query->sql), 'account_identifiers')) {
                $changed = true;
                DB::table('employer_reg')->where('EmployerId', 1)->update(['UserName' => 'changed.name']);
            }
        });

        try {
            $this->runMigration();
            $this->fail('Expected a concurrent identifier change to stop the migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed during migration validation', $exception->getMessage());
            $this->assertTrue($changed);
            $this->assertFalse(Schema::hasTable('account_identifiers'));
            $this->assertSame('changed.name', DB::table('employer_reg')->value('UserName'));
        }
    }

    private function createAccountTables(): void
    {
        Schema::create('employer_reg', function (Blueprint $table): void {
            $table->increments('EmployerId'); $table->string('UserName'); $table->string('Email');
        });
        Schema::create('jobseeker_reg', function (Blueprint $table): void {
            $table->increments('JobSeekId'); $table->string('UserName'); $table->string('Email');
        });
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_30_000000_create_account_identifier_registry.php');
        $migration->up();
    }
}
