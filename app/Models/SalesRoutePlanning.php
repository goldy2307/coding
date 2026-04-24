<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SalesRoutePlanning extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'sales_route_plannings';
    protected $fillable = [
        'date',
        'user_id',
        'pincodes',
        'areas',
        'villages',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'pincodes' => 'array',
        'areas' => 'array',
        'villages' => 'array',
        'date' => 'date',
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
            ->format('Y-m-d');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function shops()
    {
        return $this->hasMany(ShopsPerRoute::class)->withTrashed();
    }
}
