<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialLossCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'loss_type',             // Legacy: keep for backward compatibility during migration
        'loss_type_id',          // NEW: Link to dynamic LossType
        // 'unit',               // REMOVED - no longer needed
        'affects_oee',
        'requires_reason',
        'color',
        'active',
    ];

    protected $casts = [
        'affects_oee' => 'boolean',
        'requires_reason' => 'boolean',
        'active' => 'boolean',
    ];

    // Relationships
    public function materialLosses()
    {
        return $this->hasMany(MaterialLoss::class, 'loss_category_id');
    }

    public function lossType()
    {
        return $this->belongsTo(LossType::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeAffectsOee($query)
    {
        return $query->where('affects_oee', true);
    }
    
    public function scopeRawMaterial($query)
    {
        return $query->where('loss_type', 'raw_material');
    }

    public function scopePackaging($query)
    {
        return $query->where('loss_type', 'packaging');
    }

    // Accessors
    public function getFormattedNameAttribute()
    {
        return "{$this->code} - {$this->name}";
    }
}
