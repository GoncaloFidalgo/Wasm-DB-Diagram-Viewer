<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['diagram_id','name', 'description', 'visibility', 'is_published','version', 'diagram', 'user_id'])]
class Diagram extends Model
{
    // Passar o JSONB para array
    protected $casts = [
        'diagram' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
