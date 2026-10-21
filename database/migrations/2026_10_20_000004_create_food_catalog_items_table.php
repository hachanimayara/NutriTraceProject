<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('barcode', 14)->nullable();
            $table->string('source', 32);
            $table->json('data');
            $table->timestamps();
            $table->index(['user_id', 'barcode']);
            $table->index(['user_id', 'name']);
        });

        DB::table('products')->whereNotNull('open_food_facts_data')->orderBy('id')->get()->each(function ($product) {
            $data = json_decode($product->open_food_facts_data, true);
            if (! is_array($data) || $data === []) {
                return;
            }

            DB::table('food_catalog_items')->insert([
                'user_id' => $product->user_id,
                'name' => mb_substr((string) ($data['name'] ?? $data['generic_name'] ?? $product->name), 0, 160),
                'barcode' => $product->open_food_facts_code,
                'source' => $product->open_food_facts_source ?: 'open_food_facts',
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => $product->open_food_facts_imported_at ?: now(),
                'updated_at' => $product->open_food_facts_imported_at ?: now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_catalog_items');
    }
};
