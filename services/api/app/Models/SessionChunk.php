<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionChunk extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(RecordingSession::class, 'session_id');
    }
}
