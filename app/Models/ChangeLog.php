<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeLog extends Model
{
    protected $fillable = [
        'table_name',
        'row_id',
        'column_name',
        'old_value',
        'new_value',
        'change_type',
        'changed_by',
    ];
}
