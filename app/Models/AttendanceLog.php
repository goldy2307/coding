<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class AttendanceLog extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "attendance_logs";

    protected $fillable = [
        'user_id',
        'date',
        'punch_in',
        'punch_out'
    ];

    protected $casts = [
        'date' => 'date',
        'punch_in' => 'datetime',
        'punch_out' => 'datetime'
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
