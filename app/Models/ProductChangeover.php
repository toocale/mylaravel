<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductChangeover extends Model
{
    protected $fillable = [
        'production_shift_id',
        'from_product_id',
        'to_product_id',
        'changed_at',
        'recorded_by',
        'notes',
        'batch_number',
        'good_count',
        'reject_count',
        'material_loss_units',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function productionShift()
    {
        return $this->belongsTo(ProductionShift::class);
    }

    public function fromProduct()
    {
        return $this->belongsTo(Product::class, 'from_product_id');
    }

    public function toProduct()
    {
        return $this->belongsTo(Product::class, 'to_product_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
