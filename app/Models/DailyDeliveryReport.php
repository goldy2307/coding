<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyDeliveryReport extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'daily_delivery_reports';

    protected $fillable = [
        'sales_order_id',
        'date',
        'shop_id',
        'user_id',
        'status',
        'shipment_weight',
        'delivery_charge',
        'last_activity_at',
        'proof_of_delivery',
        'remarks',
        'sent',
        'approval_status'
    ];

    protected $casts = [
        'date' => 'date',
        'shipment_weight' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'last_activity_at' => 'datetime',
        'sent' => 'boolean',
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
                    ->format('Y-m-d');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }


}
