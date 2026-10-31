<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingOption extends Model
{
    use HasFactory;

    public const TYPES = ['origin' => 'Origins', 'destination' => 'Destinations', 'service' => 'Services'];

    protected $fillable = ['type', 'name', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
