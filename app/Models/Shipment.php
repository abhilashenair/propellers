<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'vendor_id', 'created_by', 'origin', 'destination'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function bill(): HasOne
    {
        return $this->hasOne(AirwayBill::class, 'number', 'number');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TrackingUpdate::class, 'shipment_number', 'number')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(TrackingUpdate::class, 'shipment_number', 'number')->ofMany(['occurred_at' => 'max', 'id' => 'max']);
    }

    public function scopeMatchingIdentifier(Builder $query, string $identifier): Builder
    {
        return $query->where(fn (Builder $match) => $match->where('shipments.number', $identifier)->orWhereHas('bill', fn (Builder $bill) => $bill->where('reference', $identifier)));
    }
}
