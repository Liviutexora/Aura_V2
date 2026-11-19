<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'due_on' => 'immutable_date',
            'offer_snapshot' => 'array',
            'eligibility_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(ProjectMission::class, 'project_id')->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(ExternalEarning::class);
    }

    public function progress(): int
    {
        $steps = $this->missions->flatMap->steps;

        return $steps->isEmpty() ? 0 : (int) round(100 * $steps->where('status', 'completed')->count() / $steps->count());
    }
}
