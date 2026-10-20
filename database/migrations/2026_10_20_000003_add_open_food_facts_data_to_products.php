<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('open_food_facts_data')->nullable();
            $table->string('open_food_facts_code', 32)->nullable()->index();
            $table->string('open_food_facts_source', 32)->nullable();
            $table->timestamp('open_food_facts_imported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['open_food_facts_code']);
            $table->dropColumn(['open_food_facts_data', 'open_food_facts_code', 'open_food_facts_source', 'open_food_facts_imported_at']);
        });
    }
};
