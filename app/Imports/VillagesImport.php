<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Village;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class VillagesImport implements OnEachRow, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    // public function model(array $row)
    // {
    //     return new Village([
    //         //
    //     ]);
    // }

    /**
     * Handle each row from the import.
     *
     * @param \Maatwebsite\Excel\Row $row
     */
    public function onRow(Row $row)
    {
        $data = $row->toArray();
        Village::updateOrCreate([
            'village' => trim(Str::title($data['village'])),
            'area' => trim(Str::title($data['area'])),
            'pincode' => trim($data['pincode']),
        ]);

        Area::updateOrCreate([
            'area' => trim(Str::title($data['area'])),
            'pincode' => trim($data['pincode']),
        ]);
    }
}
