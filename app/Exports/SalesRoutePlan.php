<?php

namespace App\Exports;

use App\Models\SalesRoutePlanning; 
use Maatwebsite\Excel\Concerns\FromCollection; 
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesRoutePlan implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return SalesRoutePlanning::select(
                'sales_route_plannings.date',
                'sales_route_plannings.pincodes',
                'sales_route_plannings.areas',
                'sales_route_plannings.villages',
                'sales_route_plannings.created_by',
                'sales_route_plannings.created_at',
                'sales_route_plannings.updated_at',
                'users.name as user_name' // 👈 add user name
            )
            ->leftJoin('users', 'sales_route_plannings.user_id', '=', 'users.id')
            ->get();
    }
    
    public function headings(): array
    {
        return [
            'Date',
            'Pincodes',
            'Areas',
            'Villages',
            'Created By',
            'Created At',
            'Updated At',
            'User Name', // 👈 new heading
        ];
    }
}
