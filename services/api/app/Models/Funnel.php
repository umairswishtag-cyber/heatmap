<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funnel extends Model
{
    protected $fillable = ['project_id', 'name', 'steps', 'window_minutes'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
