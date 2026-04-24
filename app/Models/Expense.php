<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Expense extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = "expenses";
    protected $fillable = [
        'expense_category_id',
        'user_id',
        'amount',
        'date',
        'receipt',
        'notes',
        'status',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone(config('app.timezone')))
                    ->format('Y-m-d');
    }

    public function category(){
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }

    public function user(){
        return $this->belongsTo(User::class)->withTrashed();
    }
}
