<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Shop;
use App\Models\SalesRoutePlanning;



class ShopsPerRoute extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'shops_per_routes';

    protected $fillable = [
        'sales_route_planning_id',
        'shop_id',
        'visit_status',
        'visit_proof',
        'shop_status',
        'remarks',
        'created_by',
        'last_updated_by',
    ];

    // Relationship to Shop
    public function shop()
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    // Relationship to SalesRoutePlanning
    public function salesRoutePlanning()
    {
        return $this->belongsTo(SalesRoutePlanning::class)->withTrashed();
    }
}
