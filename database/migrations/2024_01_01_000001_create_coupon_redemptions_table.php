<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->nullableMorphs('redeemer');
            $table->unsignedInteger('amount_discounted');
            $table->string('currency', 3);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
