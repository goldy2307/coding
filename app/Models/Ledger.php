<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;



class Ledger extends Model
{
    
    protected $table = "ledgers";
    protected $fillable = [
        'date',
        'prepared_by',
        'collected_amount'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'prepared_by');
    }

}
