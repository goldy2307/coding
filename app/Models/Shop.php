<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Shop extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'shops';
    protected $fillable = [
        'name',
        'gumasta',
        'gst',
        'shop_photo',
        'owner_name',
        'owner_contact_no',
        'owner_whatsapp_no',
        'bank_details',
        'address',
        'latitude',
        'longitude',
        'pincode',
        'area',
        'village',
        'created_by',
        'last_updated_by'
    ];
}
