<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Train extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'from_station',
        'to_station',
        'departure_time',
        'arrival_time',
        'total_seats',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
