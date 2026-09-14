<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordingSession extends Model
{
    protected $table = 'sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'converted' => 'boolean'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }

    public function chunks()
    {
        return $this->hasMany(SessionChunk::class, 'session_id')->orderBy('sequence');
    }
}
