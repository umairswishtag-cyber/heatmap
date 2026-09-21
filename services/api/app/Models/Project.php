<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'public_key', 'recording_enabled', 'sampling_rate', 'privacy_settings', 'last_event_at'];

    protected function casts(): array
    {
        return ['recording_enabled' => 'boolean', 'privacy_settings' => 'array', 'last_event_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function domains()
    {
        return $this->hasMany(ProjectDomain::class);
    }

    public function sessions()
    {
        return $this->hasMany(RecordingSession::class);
    }

    public function visitors()
    {
        return $this->hasMany(Visitor::class);
    }

    public function funnels()
    {
        return $this->hasMany(Funnel::class)->latest('updated_at');
    }
}
