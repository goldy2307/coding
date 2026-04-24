<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Warehouse extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'warehouses';
    protected $fillable = [
        'name', 
        'location', 
        'contact_person',
        'contact_number', 
        'created_by',
        'last_updated_by'
    ];

    public function contactPerson()
    {
        return $this->belongsTo(User::class, 'contact_person')->withTrashed();
    }
    
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }
}
