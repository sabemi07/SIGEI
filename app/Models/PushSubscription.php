<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'device_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'expires_at', 'revoked_at'])]
#[Hidden(['endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'session_hash', 'binding_id', 'auth_version', 'active_device_id'])]
class PushSubscription extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['content_encoding' => 'aes128gcm'];

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription): void {
            $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'auth_version' => 'integer',
            'endpoint' => 'encrypted',
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
