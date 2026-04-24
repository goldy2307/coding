<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class SalaryComponent extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'salary_components';
    protected $fillable = ['type', 'name', 'created_by', 'last_updated_by'];
}
