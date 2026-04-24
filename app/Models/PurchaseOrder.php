<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class PurchaseOrder extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "purchase_orders";

    protected $fillable = [
        'order_number',
        'date',
        'expected_delivery_date',
        'vendor_id',
        'warehouse_id',
        'products',
        'approval_status',
        'delivery_status',
        'notes',
        'created_by',
        'last_updated_by',
    ];

    protected $casts = [
        'products' => 'array',
        'date' => 'date',
        'expected_delivery_date' => 'date',
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
                    ->format('Y-m-d');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }
}

