<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\DailyDeliveryReport;



class SalesOrder extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'sales_orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'shop_id',
        'date',
        'expected_delivery_date',
        'products',
        'approval_status',
        'packaging_status',
        'delivery_status',
        'notes',
        'created_by',
        'last_updated_by',
        'returned_back',
        'received_back',
        'shipment_weight',
        'delivery_charge',
        'dispatched',
        'delivery_employee_id',
        'delivery_partner',
        'rejection_remark'
    ];

    protected $casts = [
        'products' => 'array',
        'date' => 'date',
        'expected_delivery_date' => 'date',
        'returned_back' => 'boolean',
        'received_back' => 'boolean',
        'dispatched' => 'boolean',
    ];

    protected $appends = ['products_with_details'];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
            ->format('Y-m-d');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
    
    public function partner()
    {
        return $this->belongsTo(User::class, 'delivery_partner')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function getProductsWithDetailsAttribute()
    {
        $rawProducts = $this->products ?? [];

        return collect($rawProducts)->map(function ($item) {
            $product = Product::find($item['product_id']);

            return array_merge($item, [
                'product' => $product,
            ]);
        });
    }

    public function deliveryEmployee()
    {
        return $this->belongsTo(User::class, 'delivery_employee_id')->withTrashed();
    }

    public function trackings()
    {
        return $this->hasMany(SalesOrderTracking::class)->withTrashed();
    }

    public function latestTracking()
    {
        return $this->hasOne(SalesOrderTracking::class)->latestOfMany('checkpoint_time')->withTrashed();
    }

    public function report(){
        return $this->hasOne(DailyDeliveryReport::class)->withTrashed();
    }
}
