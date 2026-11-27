<?php

namespace App\Models;

use App\Models\Enums\PushDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['notification_id', 'subscription_id', 'binding_id', 'status'])]
class PushDelivery extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['status' => 'queued', 'attempts' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PushDeliveryStatus::class,
            'attempts' => 'integer',
            'provider_status' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(InternalNotification::class, 'notification_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'subscription_id');
    }

    /** @return array{id: int, status: string, attempts: int, error_code: ?string} */
    public function summary(): array
    {
        return ['id' => $this->id, 'status' => $this->status->value, 'attempts' => $this->attempts, 'error_code' => $this->error_code];
    }
}
