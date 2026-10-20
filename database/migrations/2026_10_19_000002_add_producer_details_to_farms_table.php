<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->date('started_at')->nullable()->after('area_hectares');
            $table->string('document_name')->nullable()->after('started_at');
            $table->string('document_mime', 100)->nullable()->after('document_name');
            $table->longText('document_contents')->nullable()->after('document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'document_name', 'document_mime', 'document_contents']);
        });
    }
};
