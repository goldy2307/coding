<?php

namespace App\Models;

use App\LogsModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;



class SalaryStructure extends Model
{
    use SoftDeletes, LogsModelChanges;

    protected $table = 'salary_structures';
    protected $fillable = [
        'name',
        'earning_components',
        'deduction_components',
        'created_by',
        'last_updated_by'
    ];

    protected $casts = [
        'earning_components' => 'array',
        'deduction_components' => 'array',
    ];

    public function earningSalaryComponents()
    {
        return SalaryComponent::whereIn('id', $this->earning_components ?? [])->get();
    }

    public function deductionSalaryComponents()
    {
        return SalaryComponent::whereIn('id', $this->deduction_components ?? [])->get();
    }

}
