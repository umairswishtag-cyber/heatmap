<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectDomain extends Model
{
    protected $fillable = ['domain'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
