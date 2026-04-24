<?php

namespace App\Exports;

use App\Models\SalesOrder as DBSalesOrder;
use App\Models\User;
use App\Models\Shop;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrder implements FromCollection, WithHeadings
{
    
    protected $warehouseId; // Accept warehouseId optionally 
    protected $deliveryPartnerId;
    protected $startDate; 
    protected $endDate;
    
    public function __construct($warehouseId = null, $deliveryPartnerId = null, $startDate = null, $endDate = null) { 
        $this->warehouseId = $warehouseId; 
        $this->deliveryPartnerId = $deliveryPartnerId;
        $this->startDate = $startDate; 
        $this->endDate = $endDate;
    }
    /**
     * Return the collection of data to export.
     *
     * @return \Illuminate\Support\Collection
     */
    // public function collection()
    // {
    //     if ($this->warehouseId) {
    //         $orders = DBSalesOrder::with('user')->where('approval_status', 'approved')
    //             ->whereHas('user', function ($query) {
    //                 $query->where('warehouse_id', $this->warehouseId);
    //             })
    //             ->get();

    //     } elseif($this->deliveryPartnerId){
    //         $orders = DBSalesOrder::where('delivery_partner', $this->deliveryPartnerId)->get();
    //     }else {
    //         $orders = DBSalesOrder::all();
    //     }

    //     return $orders->map(function ($order) {
    //         // Resolve foreign keys
    //         $userName = optional(User::withTrashed()->find($order->user_id))->name;
    //         $shopName = optional(Shop::find($order->shop_id))->name;
    //         $shopContact = optional(Shop::find($order->shop_id))->owner_contact_no;
    //         $shopGst = optional(Shop::find($order->shop_id))->gst;
    //         $address = optional(Shop::find($order->shop_id))->address;
    //         $area = optional(Shop::find($order->shop_id))->area;
    //         $village = optional(Shop::find($order->shop_id))->village;
    //         $deliveryEmployeeName = optional(User::find($order->delivery_employee_id))->name;
    //         $deliveryPartnerName = optional(User::find($order->delivery_partner))->name;
            
    //         $grouped = collect($order->products)->groupBy('hsn');
    //         $hsnList = $grouped->keys()->implode(', ');
    //         $hsnSummary = [];
            
    //         foreach ($grouped as $hsn => $items) {
    //             $net = 0;
    //             $gstRate = null;
    //             $quantity = 0;
    
    //             foreach ($items as $item) {
    //                 $rate = floatval($item['rate'] ?? 0);
    //                 $qty = floatval($item['quantity'] ?? 0);
    //                 $gstRate = floatval($item['gst'] ?? 0); // assuming same GST for all items under same HSN
    
    //                 if (isset($item['unit']) && $item['unit'] == 'Carton') {
    //                     $qty = $qty * (Product::find($item['product_id'])->quantity_per_carton ?? 1);
    //                 } elseif (isset($item['unit']) && $item['unit'] == 'Box') {
    //                     $qty = $qty * (Product::find($item['product_id'])->quantity_per_box ?? 1);
    //                 } elseif (isset($item['unit']) && $item['unit'] == 'Bundle') {
    //                     $qty = $qty * (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
    //                 } elseif (isset($item['unit']) && $item['unit'] == 'Ladi') {
    //                     $qty = $qty * (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
    //                 }
    
    //                 $net += $rate * $qty;
    //                 if(isset($item['cd_per'])){
    //                     $net = $net - ($rate * $qty*$item['cd_per']/100);
    //                 }
    //                 if(isset($item['td_per'])){
    //                     $net = $net - ($rate * $qty*$item['td_per']/100);
    //                 }
    //                 $quantity += $qty;
    //             }
    
    //             $gstAmount = $net - $net * 100 / (100 + $gstRate);
    //             $cgst = $gstAmount / 2;
    //             $sgst = $gstAmount / 2;
    
    //             $hsnSummary[$hsn] = [
    //                 'gross_amount' => round($net - $gstAmount, 2),
    //                 'gst_rate' => $gstRate,
    //                 'gst_amount' => round($gstAmount, 2),
    //                 'cgst' => round($cgst, 2),
    //                 'sgst' => round($sgst, 2),
    //                 'net_amount' => round($net, 2),
    //                 'quantity' => $quantity,
    //             ];
    //         }
            
    //         $net_amount = round(collect($hsnSummary)->sum('net_amount'));
    //         $grand_total = collect($hsnSummary)->sum('net_amount');
    //         $gst_amount = collect($hsnSummary)->sum('gst_amount');
    //         $cgst = collect($hsnSummary)->sum('cgst');
    //         $sgst = collect($hsnSummary)->sum('sgst');

    //         // Decode products JSON and map product_id to product name
    //         $products = [];
    //         foreach ($order->products ?? [] as $p) {
    //             $product = Product::find($p['product_id']);
    //             $products[] = ($product?->name ?? 'Unknown') 
    //                           . (isset($product->variant) ? ' ' . $product->variant : '')
    //                           . ' - Qty: ' . $p['quantity'] . ' ' . $p['unit']
    //                           . ' - GST %: ' . $p['gst']
    //                           . ' - HSN: ' . $p['hsn'];
    //         }

    //         return [
    //             'Order Number'        => str_replace('SAL-ORD', 'INV-BILL', $order->order_number),
    //             'sales Person'        => $userName,
    //             'Shop'                => $shopName,
    //             'Shop Contact'        => $shopContact,
    //             'Shop GST'            => $shopGst,
    //             'Address'             => $address,
    //             'Area'                => $area,
    //             'Village'             => $village,
    //             'Order Date'          => $order->date,
    //             'Expected Delivery'   => $order->expected_delivery_date,
    //             'No. of Products'     => count($products),
    //             'Products'            => implode(", ", $products),
    //             'HSN Numbers'         => $hsnList,
    //             'Approval Status'     => $order->approval_status,
    //             'Packaging Status'    => $order->packaging_status,
    //             'Delivery Status'     => $order->delivery_status,
    //             'Returned Back'       => $order->returned_back,
    //             'Received Back'       => $order->received_back,
    //             'Shipment Weight'     => $order->shipment_weight,
    //             'Delivery Charge'     => $order->delivery_charge,
    //             'Delivery Partner'    => $deliveryPartnerName,
    //             'Delivery Employee'   => $deliveryEmployeeName,
    //             'Created At'          => $order->created_at,
    //             'Updated At'          => $order->updated_at,
    //             'Created By'          => $order->created_by,
    //             'Last Updated By'     => $order->last_updated_by,
    //             'CGST'                => $cgst,
    //             'SGST'                => $sgst,
    //             'Total GST Amount'    => $gst_amount,
    //             'Net Amount'          => $net_amount,
    //             'Grand Total'         => $grand_total,
    //             'Switch It GST'       => '23ABQCS3528P1ZO',
    //             'Rejection Remark'    => $order->rejection_remark,
    //         ];
    //     });
    // }
    
    public function collection()
    {
        $rows = collect();
    
        // Fetch orders
        if ($this->warehouseId) {
            $orders = DBSalesOrder::with('user')->where('approval_status', 'approved')->where('date', '>=', $this->startDate)->where('date', '<=', $this->endDate)
                ->whereHas('user', function ($query) {
                    $query->where('warehouse_id', $this->warehouseId);
                })
                ->get();
        } elseif ($this->deliveryPartnerId) {
            $orders = DBSalesOrder::where('delivery_partner', $this->deliveryPartnerId)->where('date', '>=', $this->startDate)->where('date', '<=', $this->endDate)->get();
        } else {
            $orders = DBSalesOrder::where('date', '>=', $this->startDate)->where('date', '<=', $this->endDate)->get();
        }
    
        foreach ($orders as $order) {
            // resolve order-level info once
            $userName = optional(User::withTrashed()->find($order->user_id))->name;
            $shop = Shop::withTrashed()->find($order->shop_id);
            $deliveryEmployeeName = optional(User::find($order->delivery_employee_id))->name;
            $deliveryPartnerName  = optional(User::find($order->delivery_partner))->name;
        
            $grouped = collect($order->products)->groupBy('hsn');
            $hsnSummary = [];
            
            foreach ($grouped as $hsn => $items) {
                // group again by gst rate inside each HSN
                $gstGrouped = collect($items)->groupBy('gst');
            
                foreach ($gstGrouped as $gstRate => $gstItems) {
                    $net = 0;
                    $quantity = 0;
            
                    foreach ($gstItems as $item) {
                        $rate = floatval($item['rate'] ?? 0);
                        $qty  = floatval($item['quantity'] ?? 0);
            
                        $product = Product::find($item['product_id']);
                        if ($item['unit'] === 'Carton') {
                            $qty *= $product->quantity_per_carton ?? 1;
                        } elseif ($item['unit'] === 'Box') {
                            $qty *= $product->quantity_per_box ?? 1;
                        } elseif ($item['unit'] === 'Bundle') {
                            $qty *= $product->quantity_per_bundle ?? 1;
                        } elseif ($item['unit'] === 'Ladi') {
                            $qty *= $product->quantity_per_ladi ?? 1;
                        }
            
                        $lineNet = $rate * $qty;
                        if (isset($item['cd_per'])) {
                            $lineNet -= ($rate * $qty * $item['cd_per'] / 100);
                        }
                        if (isset($item['td_per'])) {
                            $lineNet -= ($rate * $qty * $item['td_per'] / 100);
                        }
            
                        $net += $lineNet;
                        $quantity += $qty;
                    }
            
                    $gstAmount = $net - $net * 100 / (100 + $gstRate);
                    $cgst = $gstAmount / 2;
                    $sgst = $gstAmount / 2;
            
                    $hsnSummary[] = [
                        'hsn'         => $hsn,
                        'gst_rate'    => $gstRate,
                        'gross_amount'=> round($net - $gstAmount, 2),
                        'gst_amount'  => round($gstAmount, 2),
                        'cgst'        => round($cgst, 2),
                        'sgst'        => round($sgst, 2),
                        'net_amount'  => round($net, 2),
                        'quantity'    => $quantity,
                    ];
                }
            }

            
            $net_amount = round(collect($hsnSummary)->sum('net_amount'));
            $grand_total = collect($hsnSummary)->sum('net_amount');
            $gst_amount = collect($hsnSummary)->sum('gst_amount');
            $cgst = collect($hsnSummary)->sum('cgst');
            $sgst = collect($hsnSummary)->sum('sgst');
            $gross = collect($hsnSummary)->sum('gross_amount');
        
            // First row: order details + first product
            $products = array_values($order->products ?? []);
            $firstProduct = $products[0] ?? null;
            
            $q = $firstProduct['quantity'] ?? 0; 
            if (isset($firstProduct['unit'])) { 
                $product = Product::find($firstProduct['product_id']); 
                if ($firstProduct['unit'] === 'Carton') { 
                    $q *= $product->quantity_per_carton ?? 1; 
                    
                } elseif ($firstProduct['unit'] === 'Box') { 
                    $q *= $product->quantity_per_box ?? 1; 
                    
                } elseif ($firstProduct['unit'] === 'Bundle') { 
                    $q *= $product->quantity_per_bundle ?? 1; 

                } elseif ($firstProduct['unit'] === 'Ladi') { 
                    $q *= $product->quantity_per_ladi ?? 1; 
                    
                } 
                
            }
            
            $r = $firstProduct['rate'] ?? 0;
            $g  = $firstProduct['gst'] ?? 0;
            
            $productGrossAmount = ($r * $q * 100) / (100 + $g);

            
            $rows->push([
                'Order Number'      => str_replace('SAL-ORD', 'INV-BILL', $order->order_number),
                'Sales Person'      => $userName,
                'Shop'              => optional($shop)->name,
                'Shop Contact'      => optional($shop)->owner_contact_no,
                'Shop GST'          => optional($shop)->gst,
                'Address'           => optional($shop)->address,
                'Area'              => optional($shop)->area,
                'Village'           => optional($shop)->village,
                'Order Date'        => $order->date,
                'Expected Delivery' => $order->expected_delivery_date,
                'No. of Products'   => count($order->products ?? []),
                'Product'           => $this->productName($firstProduct),
                'Quantity'          => $firstProduct['quantity'] ?? '',
                'Unit'              => $firstProduct['unit'] ?? '',
                'GST %'             => $firstProduct['gst'] ?? '',
                'HSN'               => $firstProduct['hsn'] ?? '',
                'Product Gross Amount' => round($productGrossAmount, 2),
                'Approval Status'   => $order->approval_status,
                'Packaging Status'  => $order->packaging_status,
                'Delivery Status'   => $order->delivery_status,
                'Returned Back'     => $order->returned_back,
                'Received Back'     => $order->received_back,
                'Shipment Weight'   => $order->shipment_weight,
                'Delivery Charge'   => $order->delivery_charge,
                'Delivery Partner'  => $deliveryPartnerName,
                'Delivery Employee' => $deliveryEmployeeName,
                'Created At'        => $order->created_at,
                'Updated At'        => $order->updated_at,
                'Created By'        => $order->created_by,
                'Last Updated By'   => $order->last_updated_by,
                'CGST'              => $cgst,
                'SGST'              => $sgst,
                'Total GST Amount'  => $gst_amount,
                'Net Amount'        => $net_amount,
                'Grand Total'       => $grand_total,
                'Gross Amount'      => $gross,
                'Switch It GST'     => '23ABQCS3528P1ZO',
                'Rejection Remark'  => $order->rejection_remark,
            ]);
        
            // Subsequent rows: only product details
            foreach (array_slice($products, 1) as $p) {
                $q = $p['quantity'] ?? 0; 
                if (isset($p['unit'])) { 
                    $product = Product::find($p['product_id']); 
                    if ($p['unit'] === 'Carton') { 
                        $q *= $product->quantity_per_carton ?? 1; 
                        
                    } elseif ($p['unit'] === 'Box') { 
                        $q *= $product->quantity_per_box ?? 1; 
                        
                    } elseif ($p['unit'] === 'Bundle') { 
                        $q *= $product->quantity_per_bundle ?? 1; 
    
                    } elseif ($p['unit'] === 'Ladi') { 
                        $q *= $product->quantity_per_ladi ?? 1; 
                        
                    } 
                    
                }
                
                $r = $p['rate'] ?? 0;
                $g  = $p['gst'] ?? 0;
                
                $productGrossAmount = ($r * $q * 100) / (100 + $g);
                
                $rows->push([
                    'Order Number'      => '',
                    'Sales Person'      => '',
                    'Shop'              => '',
                    'Shop Contact'      => '',
                    'Shop GST'          => '',
                    'Address'           => '',
                    'Area'              => '',
                    'Village'           => '',
                    'Order Date'        => '',
                    'Expected Delivery' => '',
                    'No. of Products'   => '',
                    'Product'           => $this->productName($p),
                    'Quantity'          => $p['quantity'],
                    'Unit'              => $p['unit'],
                    'GST %'             => $p['gst'],
                    'HSN'               => $p['hsn'],
                    'Product Gross Amount' => round($productGrossAmount, 2),
                    'Approval Status'   => '',
                    'Packaging Status'  => '',
                    'Delivery Status'   => '',
                    'Returned Back'     => '',
                    'Received Back'     => '',
                    'Shipment Weight'   => '',
                    'Delivery Charge'   => '',
                    'Delivery Partner'  => '',
                    'Delivery Employee' => '',
                    'Created At'        => '',
                    'Updated At'        => '',
                    'Created By'        => '',
                    'Last Updated By'   => '',
                    'CGST'              => '',
                    'SGST'              => '',
                    'Total GST Amount'  => '',
                    'Net Amount'        => '',
                    'Grand Total'       => '',
                    'Gross Amount'      => '',
                    'Switch It GST'     => '',
                    'Rejection Remark'  => '',
                ]);
            }
        
            // Empty row after each order
            $rows->push([]);
        }

    
        return $rows;
    }
    
    protected function productName($p)
    {
        if (!$p) return '';
        $product = Product::find($p['product_id']);
        return ($product?->name ?? 'Unknown') . (isset($product->variant) ? ' ' . $product->variant : '');
    }

    


    /**
     * Define the headings for the Excel sheet.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Order Number',
            'Sales Person',
            'Shop',
            'Shop Contact',
            'Shop GST',
            'Address',
            'Area',
            'Village',
            'Order Date',
            'Expected Delivery',
            'No. of Products',
            // 'Products',
            // 'HSN Numbers',
            'Product',
            'Quantity',
            'Unit',
            'GST %',
            'HSN',
            'Product Gross Amount',
            'Approval Status',
            'Packaging Status',
            'Delivery Status',
            'Returned Back',
            'Received Back',
            'Shipment Weight',
            'Delivery Charge',
            'Delivery Partner',
            'Delivery Employee',
            'Created At',
            'Updated At',
            'Created By',
            'Last Updated By',
            'CGST',
            'SGST',
            'Total GST Amount',
            'Net Amount',
            'Grand Total',
            'Gross Amount',
            'Switch It GST',
            'Rejection Remark',
        ];
    }
}
