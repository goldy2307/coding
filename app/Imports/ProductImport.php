<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ProductImport implements OnEachRow, WithHeadingRow
{
    protected $drawings = [];
    protected array $skippedRows = [];

    public function __construct()
    {
        // Load drawings once during import
        $spreadsheet = IOFactory::load(request()->file('file'));
        $this->drawings = $spreadsheet->getActiveSheet()->getDrawingCollection();
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex(); // 2-based index (1 = header)
        $data = $row->toArray();
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Replace backtick with apostrophe if between letters/numbers
                $value = preg_replace("/([a-zA-Z0-9])`([a-zA-Z0-9])/", "$1'$2", $value);
    
                // Remove backticks at start or end
                $value = trim($value, '`');
    
                // Remove any remaining stray backticks
                $value = str_replace('`', '', $value);
    
                $data[$key] = $value;
            }
        }

        $required = ['name', 'price', 'buying_price', 'selling_price', 'quantity_per_carton', 'variant', 'threshold_limit', 'category', 'packaging_type'];

        // Check if any required field is missing or empty
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $this->skippedRows[] = [
                    'row' => $rowIndex,
                    'reason' => "Missing required field: {$field}"
                ];
                return;
            }
        }

        $imagePath = null;

        // Attempt to match drawing to row
        foreach ($this->drawings as $drawing) {
            if ($drawing->getCoordinates()[1] == $rowIndex) {
                if ($drawing instanceof MemoryDrawing) {
                    ob_start();
                    call_user_func($drawing->getRenderingFunction(), $drawing->getImageResource());
                    $imageContents = ob_get_clean();
                }elseif ($drawing instanceof Drawing) {
                    $path = $drawing->getPath();
                    $imageContents = file_get_contents($path);
                } else {
                    continue; // unsupported drawing type
                }

                $extension = pathinfo($drawing instanceof Drawing ? $path : 'image.png', PATHINFO_EXTENSION);

                $filename = 'product_' . time() . '_' . $rowIndex . '.' . $extension;
                $path = public_path('ProductImages/' . $filename);
                file_put_contents($path, $imageContents);
                $imagePath = 'ProductImages/' . $filename;
                break;
            }
        }

        $category = Category::where('name', $data['category'])->first();
        if (!$category) {
            $this->skippedRows[] = [
                'row' => $rowIndex,
                'reason' => "Category not found: {$data['category']}"
            ];
            return;
        }

        Product::updateOrCreate([
            'name' => $data['name'],
            'category_id' => $category->id,
            'variant' => $data['variant'],
            'price' => $data['price'],

        ], [
            'image' => $imagePath,
            'buying_price' => $data['buying_price'],
            'selling_price' => $data['selling_price'],
            'quantity_per_carton' => $data['quantity_per_carton'],
            'threshold_limit' => $data['threshold_limit'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'] ?? null,
            'packaging_type' => $data['packaging_type'],
            'availability' => $data['availability'] ?? 0,
            'created_by' => auth()->user()->email,
            'last_updated_by' => auth()->user()->email
        ]);
    }

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }
}
