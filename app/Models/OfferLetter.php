<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class OfferLetter extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "offer_letters";
    protected $fillable = [
        'user_id',
        'user_address',
        'job_title',
        'reporting_manager',
        'joining_date',
        'employment_type',
        'monthly_gross_salary',
        'annual_ctc',
        'probation_period',
        'notice_period',
        'acceptance_deadline',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'joining_date' => 'date',
        'acceptance_deadline' => 'date',
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
                    ->format('Y-m-d');
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
