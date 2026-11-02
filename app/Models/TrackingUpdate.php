<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingUpdate extends Model
{
    use HasFactory;

    protected $fillable = ['shipment_number', 'created_by', 'status', 'location', 'message', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['q'])) {
            $term = $filters['q'];
            $query->where(fn (Builder $match) => $match->where('message', 'like', '%'.$term.'%')->orWhere('location', 'like', '%'.$term.'%')->orWhere('status', 'like', '%'.$term.'%'));
        }
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('occurred_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('occurred_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
