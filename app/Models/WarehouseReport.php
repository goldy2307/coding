<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseReport extends Model
{
    protected $table = "warehouse_reports";
    
    protected $fillable = [
        'date',
        'prepared_by',
        'shift',
        'stock_in',
        'stock_out',
        'closing_stock',
        'vehicle_in',
        'vehicle_out',
        'first_vehicle_in',
        'last_vehicle_out',
        'overstays',
        'total_purchase_amount',
        'total_sales_amount',
        'total_deliveries_completed',
        'dispatch_orders',
        'employees_present',
        'warehouse_id',
        'loading_unloading',
        'drivers',
        'vehicle_driver_info',
        'notes'
    ];
    
    public function warehouse(){
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    
    public function user(){
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
