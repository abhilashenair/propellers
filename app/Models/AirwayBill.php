<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class AirwayBill extends Model
{
    use HasFactory;

    protected $fillable = ['consignee_postal_code', 'terms_and_conditions', 'shipper_details', 'reference', 'carrier_number', 'dimensions', 'volumetric_weight', 'declared_value', 'number', 'customer_id', 'vendor_id', 'created_by', 'bill_date', 'origin', 'destination', 'consignee_name', 'consignee_phone', 'consignee_address', 'contents', 'pieces', 'weight', 'chargeable_weight', 'service', 'currency', 'freight', 'other_charges', 'tax_rate', 'total_minor', 'notes'];

    protected static function booted(): void
    {
        static::creating(function (AirwayBill $bill): void {
            if (blank($bill->reference)) {
                do {
                    $reference = 'REF-'.strtoupper((string) Str::ulid());
                } while (self::where('reference', $reference)->exists() || Shipment::where('number', $reference)->exists() || $reference === $bill->number);
                $bill->reference = $reference;
            }
        });
    }

    protected function casts(): array
    {
        return ['shipper_details' => 'array', 'bill_date' => 'date', 'freight' => 'decimal:2', 'other_charges' => 'decimal:2', 'tax_rate' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TrackingUpdate::class, 'shipment_number', 'number')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(TrackingUpdate::class, 'shipment_number', 'number')->ofMany(['occurred_at' => 'max', 'id' => 'max']);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereHas('latestUpdate', fn (Builder $update) => $update->where('status', 'Delivered'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereDoesntHave('latestUpdate', fn (Builder $update) => $update->where('status', 'Delivered'));
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['q'])) {
            $term = $filters['q'];
            $query->where(function (Builder $match) use ($term): void {
                foreach (['number', 'reference', 'carrier_number', 'origin', 'destination', 'service', 'consignee_name'] as $field) {
                    $match->orWhere('airway_bills.'.$field, 'like', '%'.$term.'%');
                }
                $match->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$term.'%'));
                $match->orWhereHas('vendor', fn (Builder $vendor) => $vendor->where('name', 'like', '%'.$term.'%'));
            });
        }
        if (isset($filters['number'])) {
            $term = $filters['number'];
            $query->where(fn (Builder $match) => $match->where('airway_bills.number', 'like', '%'.$term.'%')->orWhere('airway_bills.reference', 'like', '%'.$term.'%'));
        }
        foreach (['origin', 'destination', 'service'] as $field) {
            if (isset($filters[$field])) {
                $query->where('airway_bills.'.$field, 'like', '%'.$filters[$field].'%');
            }
        }
        foreach (['customer', 'vendor'] as $relation) {
            if (isset($filters[$relation])) {
                $term = $filters[$relation];
                $query->whereHas($relation, fn (Builder $related) => $related->where('name', 'like', '%'.$term.'%'));
            }
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('bill_date', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('bill_date', '<=', $filters['date_to']);
        }
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            if ($status === 'Booked') {
                $query->where(fn (Builder $query) => $query->whereDoesntHave('latestUpdate')->orWhereHas('latestUpdate', fn (Builder $update) => $update->where('status', 'Booked')));
            } else {
                $query->whereHas('latestUpdate', fn (Builder $update) => $update->where('status', $status));
            }
        }
        if (isset($filters['delivered_from']) || isset($filters['delivered_to'])) {
            $query->whereHas('latestUpdate', function (Builder $update) use ($filters): void {
                $update->where('status', 'Delivered');
                if (isset($filters['delivered_from'])) {
                    $update->whereDate('occurred_at', '>=', $filters['delivered_from']);
                }
                if (isset($filters['delivered_to'])) {
                    $update->whereDate('occurred_at', '<=', $filters['delivered_to']);
                }
            });
        }

        return $query;
    }
}
