<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_ratings', function (Blueprint $table): void {
            $table->foreignId('consumer_id')->nullable()->change();
            $table->string('consumer_name', 80)->nullable()->after('consumer_id');
            $table->char('guest_token', 64)->nullable()->after('consumer_name');
            $table->unique(['product_id', 'guest_token'], 'product_ratings_product_guest_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_ratings', function (Blueprint $table): void {
            $table->dropUnique('product_ratings_product_guest_unique');
            $table->dropColumn(['consumer_name', 'guest_token']);
            $table->foreignId('consumer_id')->nullable(false)->change();
        });
    }
};
