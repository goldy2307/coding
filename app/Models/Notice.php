<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Notice extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "notices";
    protected $fillable = [
        'user_id',
        'points',
        'date',
        'created_by',
        'last_updated_by',
        'status'
    ];

    protected $casts = [
        'date' => 'date'
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
                    ->format('Y-m-d');
    }

    public function user(){
        return $this->belongsTo(User::class)->withTrashed();
    }

}
