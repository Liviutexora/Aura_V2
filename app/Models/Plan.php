<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'seed_key', 'version'];

    /** @return Collection<int, self> */
    public static function activeOptions(): Collection
    {
        return self::where('is_active', true)->orderBy('access_level')->orderBy('id')->get(['id', 'name']);
    }

    protected function casts(): array
    {
        return ['benefits' => 'array', 'is_active' => 'boolean'];
    }

    /** @return array{name:string, benefits:array, version:int, access_level:int, max_projects:?int, max_explorer_projects:?int, max_mentor_projects:?int, monthly_price_minor:int, quarterly_price_minor:int, trial_days:int} */
    public function terms(): array
    {
        return $this->only('name', 'benefits', 'version', 'access_level', 'max_projects',
            'max_explorer_projects', 'max_mentor_projects', 'monthly_price_minor', 'quarterly_price_minor', 'trial_days');
    }
}
