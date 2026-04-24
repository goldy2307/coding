<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class Notification extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'notifications';
    protected $fillable = ['type', 'title', 'body', 'data', 'sender_id'];

    protected $casts = [
        'data' => 'array'
    ];

    public function recipients()
    {
        return $this->hasMany(NotificationUser::class)->withTrashed();
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'notification_users')->withTrashed();
    }
}
