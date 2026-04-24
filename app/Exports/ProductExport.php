<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with('category')->get()->map(function ($product) {
            return [
                'Name' => $product->name,
                'Category' => $product->category->name ?? '',
                'Variant' => $product->variant,
                'Price' => $product->price,
                'Buying Price' => $product->buying_price,
                'Selling Price' => $product->selling_price,
                'Quantity Per Carton' => $product->quantity_per_carton,
                'Threshold Limit' => $product->threshold_limit,
                'Description' => $product->description,
                'Weight' => $product->weight,
                'Packaging Type' => $product->packaging_type,
                'Image URL' => $product->image ? asset($product->image) : ''
            ];
        });
        
        
    }
    
    public function headings(): array
    {
        return [
            'Name', 'Category', 'Variant', 'Price', 'Buying Price', 'Selling Price',
            'Quantity Per Carton', 'Threshold Limit', 'Description', 'Weight',
            'Packaging Type', 'Image URL'
        ];
    }
}
