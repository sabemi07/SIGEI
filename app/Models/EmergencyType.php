<?php

namespace App\Models;

use Database\Factories\EmergencyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class EmergencyType extends Model
{
    /** @use HasFactory<EmergencyTypeFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
