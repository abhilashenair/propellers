<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirwayBill extends Model
{
    use HasFactory;

    protected $fillable = ['shipper_details', 'reference', 'carrier_number', 'dimensions', 'volumetric_weight', 'declared_value', 'number', 'customer_id', 'vendor_id', 'created_by', 'bill_date', 'origin', 'destination', 'consignee_name', 'consignee_phone', 'consignee_address', 'contents', 'pieces', 'weight', 'chargeable_weight', 'service', 'currency', 'freight', 'other_charges', 'tax_rate', 'total_minor', 'notes'];

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
}
