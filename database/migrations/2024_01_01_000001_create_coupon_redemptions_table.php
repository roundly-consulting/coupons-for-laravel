<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('coupons.key_type');

        Schema::create('coupon_redemptions', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->morphKey('redeemer', $keyType, nullable: true);
            $table->unsignedInteger('amount_discounted');
            $table->string('currency', 3);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
