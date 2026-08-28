<?php

namespace Tests;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the Spatie roles and permissions after the database is refreshed.
     *
     * Tests that use the RefreshDatabase trait rely on application roles
     * (e.g. patient, clinician) and permissions existing in the database.
     * Without seeding, role-based and permission-based middleware would
     * fail closed on every feature test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(PermissionSeeder::class);
        }
    }
}
