<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Vendor extends Model
{
    use LogsModelChanges, SoftDeletes;
    protected $table = "vendors";

    protected $fillable = [
        'name',
        'gst_details',
        'bank_details',
        'contact_number',
        'whatsapp_number',
        'delivery_address',
        'status',
        'created_by',
        'last_updated_by'
    ];
}
