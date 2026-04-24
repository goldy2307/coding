<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class SalesOrderTracking extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "sales_order_trackings";

    protected $fillable = [
        'sales_order_id',
        'checkpoint',
        'remarks',
        'checkpoint_time',
        'proof_of_delivery',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'checkpoint_time' => 'datetime',
    ];

    public function salesOrder(){
        return $this->belongsTo(SalesOrder::class, 'sales_order_id')->withTrashed();
    }
}
