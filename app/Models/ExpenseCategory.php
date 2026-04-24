<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class ExpenseCategory extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'expense_categories';
    protected $fillable = ['name', 'terms', 'created_by', 'last_updated_by'];
}
