<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;



class Department extends Model
{
    use SoftDeletes, LogsModelChanges;
    protected $table = 'departments';

    protected $fillable = [
        'name',
        'roles',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'roles' => 'array',
    ];

    public function roleModels()
    {
        return Role::whereIn('id', $this->roles ?? [])->get();
    }
}
