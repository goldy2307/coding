<?php

namespace App\Exports;

use App\Models\Village; 
use Maatwebsite\Excel\Concerns\FromCollection; 
use Maatwebsite\Excel\Concerns\WithHeadings;

class VillageExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Village::select(['pincode', 'area', 'village'])->get();
    }
    
    public function headings(){
        return ['Pincode', 'Area', 'Village'];
    }
}
