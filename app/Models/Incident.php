<?php

namespace App\Models;

use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['emergency_type_id', 'location_id', 'location_details', 'description', 'reporter_user_id', 'status', 'priority', 'reported_at', 'closed_at', 'closed_by'])]
#[Hidden(['submission_key_hash', 'submission_scope_hash', 'submission_payload_hash'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $attributes = ['status' => 'Pendiente', 'priority' => 'Por evaluar', 'lock_version' => 0];

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            $incident->folio = 'SIG-'.Str::ulid();
            $incident->type_name = $incident->emergencyType()->firstOrFail()->name;
            $incident->location_name = $incident->location()->firstOrFail()->name;
            $incident->reported_at ??= now('UTC');
        });

        static::updating(function (Incident $incident): void {
            if ($incident->isDirty('folio')) {
                throw new LogicException('El folio del incidente no se puede modificar.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
            'reported_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    public function emergencyType(): BelongsTo
    {
        return $this->belongsTo(EmergencyType::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IncidentAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(IncidentAssignment::class)->whereNull('ended_at');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class);
    }

    public function internalNotifications(): HasMany
    {
        return $this->hasMany(InternalNotification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
