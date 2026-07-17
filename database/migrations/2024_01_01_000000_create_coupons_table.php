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
            $table->integer('value');
            $table->string('code')->unique();
            $table->integer('usage')->default(0);
            $table->integer('max_usage')->default(0);
            $table->unsignedInteger('max_usage_per_redeemer')->default(0);
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('minimum_spend')->nullable();
            $table->unsignedInteger('max_discount')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
