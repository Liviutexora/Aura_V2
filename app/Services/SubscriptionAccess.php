<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Validation\ValidationException;

class SubscriptionAccess
{
    public function current(User $user): ?UserMembership
    {
        return UserMembership::whereBelongsTo($user)->whereRelation('user', 'status', 'active')->effectiveAt(now())->first();
    }

    /** Called inside ProjectEnrollment's transaction while the owner's row is locked. */
    public function authorizeEnrollment(User $user, Opportunity $offer, string $type): void
    {
        $period = $this->current($user);
        if (! $period || $period->terms['access_level'] < $offer->min_access_level) {
            throw ValidationException::withMessages(['membership' => 'Este necesar un abonament activ cu nivelul de acces corespunzător.']);
        }
        if (! in_array($type, ['explorer', 'mentor'], true) || ! in_array($offer->participation_mode, ['both', $type], true)) {
            throw ValidationException::withMessages(['participation_type' => 'Tipul de participare nu este disponibil.']);
        }
        if ($type === 'mentor' && ! $user->projects()->where('participation_type', 'explorer')->where('status', 'completed')->exists()) {
            throw ValidationException::withMessages(['participation_type' => 'Finalizează un proiect Explorator înainte de a deveni Mentor.']);
        }
        $projects = $user->projects()->whereIn('status', ['accepted', 'active']);
        $totalLimit = $period->terms['max_projects'];
        $typeLimit = $period->terms['max_'.$type.'_projects'];
        if (($totalLimit !== null && $projects->count() >= $totalLimit)
            || ($typeLimit !== null && $projects->where('participation_type', $type)->count() >= $typeLimit)) {
            throw ValidationException::withMessages(['membership' => 'Limita proiectelor simultane a fost atinsă.']);
        }
    }

    public function canDistributeMentorReferral(User $mentor, Project $project): bool
    {
        if ($project->user_id !== $mentor->id || $project->participation_type !== 'mentor'
            || ! in_array($project->status, ['accepted', 'active'], true)) {
            return false;
        }

        $period = $this->current($mentor);

        return $period !== null && $period->terms['max_mentor_projects'] !== 0
            && in_array($project->opportunity->participation_mode, ['mentor', 'both'], true)
            && $period->terms['access_level'] >= $project->opportunity->min_access_level;
    }
}
