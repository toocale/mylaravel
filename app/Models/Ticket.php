<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'assigned_to',
        'subject',
        'description',
        'priority',
        'status',
        'category',
        'plant_id',
        'machine_id',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at', 'asc');
    }

    // Helper methods
    public function isOpen()
    {
        return $this->status === 'open';
    }

    public function isClosed()
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function canBeEditedBy(User $user)
    {
        return $user->id === $this->created_by || 
               $user->id === $this->assigned_to ||
               $user->groups()->whereIn('name', ['Admin', 'Supervisor', 'Manager'])->exists();
    }
}
