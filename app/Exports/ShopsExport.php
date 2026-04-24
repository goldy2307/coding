<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ShopsExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return DB::table('shops')
            ->select([
                'shops.name',
                'shops.gumasta',
                'shops.gst',
                'shops.owner_name',
                'shops.owner_contact_no',
                'shops.owner_whatsapp_no',
                'shops.bank_details',
                'shops.address',
                'shops.latitude',
                'shops.longitude',
                'shops.pincode',
                'shops.area',
                'shops.village',
                'shops.created_at',
                'shops.updated_at',
                'users.name as created_by_name' // ✅ fetch user name
            ])
            ->leftJoin('users', 'users.email', '=', 'shops.created_by')
            ->whereNull('shops.deleted_at')
            ->get();
    }

    
    public function headings(): array
    {
        return ['Name','Gumasta','GST','Owner Name','Owner Contact No',
                'Owner WhatsApp No','Bank Details','Address','Latitude',
                'Longitude','Pincode','Area','Village', 'Created At', 'Updated At', 'Created By'];
    }
}
