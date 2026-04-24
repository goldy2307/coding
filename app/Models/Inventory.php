<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Inventory extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "inventories";

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'available_quantity',
        'reserved_quantity',
        'location',
        'status',
        'created_by',
        'last_updated_by'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }
}
