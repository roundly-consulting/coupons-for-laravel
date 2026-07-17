<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned table the suite's redeemer fixture lives in.
 *
 * This was a `Schema::create()` inlined into `TestCase::defineDatabaseMigrations()`. It has
 * to be a real migration now: on a real engine the base case resets state by dropping every
 * table and re-migrating, so anything created outside the migrator exists for exactly one
 * test and the second test redeems against a table that is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
    }
};
