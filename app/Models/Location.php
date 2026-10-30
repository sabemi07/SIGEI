<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'requires_details', 'is_active'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $attributes = ['requires_details' => false, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requires_details' => 'boolean', 'is_active' => 'boolean'];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
