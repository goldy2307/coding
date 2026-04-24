<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class SalarySlip extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "salary_slips";

    protected $fillable = [
        'user_id',
        'month',
        'earnings',
        'deductions',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'earnings' => 'array',
        'deductions' => 'array'
    ];

    public function user(){
        return $this->belongsTo(User::class)->withTrashed();
    }
}
