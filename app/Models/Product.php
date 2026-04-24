<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Product extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'description',
        'price',
        'weight',
        'packaging_type',
        'buying_price',
        'selling_price',
        'quantity_per_carton',
        'quantity_per_ladi',
        'quantity_per_box',
        'quantity_per_bundle',
        'variant',
        'image',
        'hsn',
        'gst',
        'availability',
        'threshold_limit',
        'category_id',
        'created_by',
        'last_updated_by'
    ];

    public function category(){
        return $this->belongsTo(Category::class)->withTrashed();
    }
}
