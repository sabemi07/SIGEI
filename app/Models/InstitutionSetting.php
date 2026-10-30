<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scope', 'name', 'abbreviation', 'notify_new_incidents', 'notify_assignments', 'notify_updates', 'updated_by'])]
class InstitutionSetting extends Model
{
    protected $primaryKey = 'scope';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = [
        'scope' => 'institution',
        'lock_version' => 0,
        'notify_new_incidents' => true,
        'notify_assignments' => true,
        'notify_updates' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
            'notify_new_incidents' => 'boolean',
            'notify_assignments' => 'boolean',
            'notify_updates' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
