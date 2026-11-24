<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPromoCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'redeemed_at' => 'immutable_datetime'];
    }
}
