<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPassword
{
    /** @use HasFactory<UserFactory> */
    use CanResetPasswordTrait, HasFactory, Notifiable;

    public const REFERRAL_CODE_PATTERN = '/\A[0-9a-f]{32}\z/';

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->getKey() !== null && (string) $user->getKey() === (string) $user->sponsor_id) {
                throw new LogicException('Un cont nu poate fi propriul sponsor.');
            }

            $user->referral_code ??= bin2hex(random_bytes(16));
        });

        static::updating(function (self $user): void {
            if ($user->isDirty('sponsor_id')) {
                throw new LogicException('Sponsorul poate fi atribuit numai la înregistrare.');
            }
        });
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
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

    public function referralDisplayName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), 2);
        $firstName = $parts[0] ?? '';

        return preg_match("/\\A\\p{L}[\\p{L}\\p{M}'’-]*\\z/u", $firstName) ? $firstName : 'Membru AURA';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' && $this->isActive();
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
