<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, CanResetPasswordTrait;

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sponsor_id');
    }

    public function sponsoredUsers(): HasMany
    {
        return $this->hasMany(self::class, 'sponsor_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' && $this->isActive();
    }

    public function assignSponsor(?self $sponsor): void
    {
        if ($sponsor?->is($this)) {
            throw new LogicException('A user cannot sponsor itself.');
        }

        $visited = [];
        for ($cursor = $sponsor; $cursor; $cursor = $cursor->sponsor) {
            if (isset($visited[$cursor->getKey()]) || $cursor->is($this)) {
                throw new LogicException('Circular sponsor relationships are not allowed.');
            }
            $visited[$cursor->getKey()] = true;
        }

        $this->sponsor()->associate($sponsor);
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
