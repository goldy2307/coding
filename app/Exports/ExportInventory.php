<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\Models\Product;
use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportInventory implements FromCollection, WithHeadings
{
    protected $warehouseId;
    
     public function __construct($warehouseId) { 
        $this->warehouseId = $warehouseId;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $inventories = Inventory::where('warehouse_id', $this->warehouseId)->get();
        
        return $inventories->map(function ($inventory) {
            $product = Product::find($inventory->product_id);
            
            $available = $inventory->available_quantity;
            $perCarton = $product->quantity_per_carton ?? 1;
            
            if($perCarton == 0){
                $perCarton = 1;
            }
            
            $cartons = intdiv($available, $perCarton);
            $pieces  = $available % $perCarton;
            
            return [
                'Product Name'     => $product->name,
                'Product Weight'   => $product->weight,
                'Price'            => $product->buying_price,
                'Available'        => $available,
                'Available in Carton' => "{$cartons} Carton(s) {$pieces} Piece(s)",
                'Reserved'         => $inventory->reserved_quantity,
            ];

        });
    }
    
    public function headings(): array
    {
        return [
            'Product Name',
            'Product Weight',
            'Price',
            'Available',
            'Available in Carton',
            'Reserved'
        ];
    }
}
