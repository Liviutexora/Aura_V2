<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMembership;

class UserHeaderStatus
{
    public function __construct(private SubscriptionAccess $subscriptionAccess) {}

    /** @return array{label: string, tone: string, linkable: bool, installable: bool} */
    public function for(User $user): array
    {
        if (! $user->isActive()) {
            return [
                'label' => $user->status === 'suspended' ? 'Cont suspendat' : 'Cont inactiv',
                'tone' => 'danger',
                'linkable' => false,
                'installable' => false,
            ];
        }

        $current = $this->subscriptionAccess->current($user);
        if ($current) {
            $secondsRemaining = $current->ends_at->getTimestamp() - now()->getTimestamp();

            return [
                'label' => $current->source === 'trial' ? 'Trial' : $current->terms['name'],
                'tone' => $secondsRemaining <= 72 * 60 * 60 ? 'warning' : 'active',
                'linkable' => true,
                'installable' => true,
            ];
        }

        $expired = UserMembership::whereBelongsTo($user)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '<=', now())
            ->latest('ends_at')
            ->first();

        return [
            'label' => $expired ? 'Abonament expirat' : 'Cont activ',
            'tone' => $expired ? 'danger' : 'basic',
            'linkable' => true,
            'installable' => true,
        ];
    }
}
