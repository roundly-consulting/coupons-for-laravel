<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Coupons\Enums\DiscountType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default(DiscountType::Fixed->value);
            // Fixed: minor units of `currency`; Percentage: basis points. An int, not a money column.
            $table->bigInteger('value');
            // Stored trimmed and upper-cased (see Coupon::code()); unique among live rows below.
            $table->string('code')->index();
            $table->integer('usage')->default(0);
            $table->integer('max_usage')->default(0);
            $table->unsignedInteger('max_usage_per_redeemer')->default(0);
            $table->currencyCode('currency', nullable: true);
            // Both share the coupon's currency column (added above, so the macro skips it).
            $table->money('minimum_spend', currency: 'currency', nullable: true);
            $table->money('max_discount', currency: 'currency', nullable: true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // A code is unique among coupons that are not soft-deleted: this column mirrors the
            // code while the row is live and is NULL once it is trashed, and a unique index lets
            // NULLs repeat. So a pruned code can be issued again while the old row keeps its code
            // and redemption history. (SQL Server's unique indexes treat NULLs as equal, so this
            // shape needs MySQL/MariaDB, PostgreSQL or SQLite.)
            $table->string('undeleted_code')->nullable()
                ->storedAs('case when deleted_at is null then code end')
                ->unique();
        });
    }
};
