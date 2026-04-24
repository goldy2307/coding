<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Category extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'created_by',
        'last_updated_by'
    ];

    public function products()
    {
        return $this->hasMany(Product::class)->withTrashed();
    }

    protected $appends = ['product_count'];

    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }
}
