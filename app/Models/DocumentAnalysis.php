<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAnalysis extends Model
{
    protected $fillable = [
        'uploaded_by', 'original_filename', 'storage_path', 'mime_type', 'status', 'external_processing_consented_at',
        'extracted_data', 'summary', 'anomalies', 'ai_model', 'failure_code',
        'admin_decision', 'review_comment', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'anomalies' => 'array',
            'reviewed_at' => 'datetime',
            'external_processing_consented_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
