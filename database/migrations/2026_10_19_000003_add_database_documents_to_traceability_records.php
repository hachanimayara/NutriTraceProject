<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lots', 'transformations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('document_name')->nullable();
                $blueprint->string('document_mime', 100)->nullable();
                $blueprint->longText('document_contents')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['lots', 'transformations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['document_name', 'document_mime', 'document_contents']);
            });
        }
    }
};
