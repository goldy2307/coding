<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\SalesRoutePlanning;
use App\Models\Shop;
use App\Models\ShopsPerRoute;
use App\Models\User;
use App\Models\Inventory;
use App\Models\SalesOrder;
use App\Models\Product;
use App\Notifications\ForgotPasswordMail;
use App\Notifications\SalesOrderCreated;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\VillagesImport;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Services\FirebaseNotificationService;
use App\Models\SalesOrderTracking;
use App\Models\Department;
use Carbon\Carbon;


class SalesController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|exists:users,email',
                'password' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['errors' => ['Invalid credentials'], 'message' => 'No user found with this email'], 200);
        } elseif (! Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => ['Invalid credentials'], 'message' => 'Invalid password'], 200);
        } elseif (! $user->hasRole('sales employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have sales employee role'], 200);
        }
        
        $user->tokens()->delete();

        $token = $user->createToken('SalesApp')->plainTextToken;
        $user->auth_token = $token;
        $user->save();

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'phone_no' => 'required|numeric|digits:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = User::where('phone', $request->phone_no)->first();
        if (! $user) {
            return response()->json([
                'errors' => ['User not found'],
                'message' => 'Provided number is not registered with any account.',
            ]);
        } elseif (! $user->hasRole('sales employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have sales employee role'], 200);
        }

        $jar = new CookieJar();


        $client = new Client();

        // Submit phone number to get OTP
        $response = $client->post('https://auth.phone.email/submit-login', [
            'form_params' => [
                'phone_no' => $request->phone_no,
                'phone_country' => '+91',
                'client_id' => config('app.phone_email.client_id'),
            ],
            'cookies' => $jar,
        ]);
        // Store the cookie jar for reuse
        file_put_contents('cookiejar.serialize', serialize($jar));
        $body = $response->getBody()->getContents();
        // Optionally decode if response is JSON
        $json = json_decode($body, true);

        return response()->json([
            'status' => true,
            'data' => $json ?? $body // fallback to raw body if not JSON
        ]);
    }

    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'otp' => 'required|numeric|digits:6',
                'phone_no' => 'required|numeric|digits:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }
        $jar = unserialize(file_get_contents('cookiejar.serialize'));

        $client = new Client();

        // Submit the OTP and verify login
        $response = $client->post('https://auth.phone.email/verify-login', [
            'form_params' => [
                'otp' => $request->otp,
                'client_id' => config('app.phone_email.client_id'),
                'fname' => 'Aayush',
                'lname' => 'Patidar',
            ],
            'cookies' => $jar,
        ]);

        $body = $response->getBody()->getContents();

        // Optionally decode if response is JSON
        $json = json_decode($body, true);
        if ($json['flag'] == 1) {
            $user = User::where('phone', $request->phone_no)->first();
            if ($user && $user->hasRole('sales employee')) {
                $user->tokens()->delete();
                $token = $user->createToken('SalesApp')->plainTextToken;
                $user->auth_token = $token;
                $user->save();

                return response()->json([
                    'status' => true,
                    'verification' => true,
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'message' => 'User verified and logged in successfully.',
                        'verification' => $json ?? $body,
                    ],
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'verification' => true,
                    'data' => [
                        'message' => 'User not found. Please contact HR.',
                        'verification' => $json ?? $body,
                    ],
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'verfication' => false,
                'data' => $json ?? $body, // fallback to raw body if not JSON
            ]);
        }
    }

    public function storeDeviceToken(Request $request)
    {
        try {
            $request->validate([
                'device_token' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = Auth::user();
        $user->device_token = $request->device_token;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Device token stored successfully'
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }
        return response()->json(['errors' => ['User not authorized'], 'message' => 'Unauthorized'], 200);
    }

    public function forgot_password(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        // Generate a password reset token and send it to the user's email
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['errors' => ['User not found'], 'message' => 'Please enter a valid registered email.'], 200);
        } elseif (! $user->hasRole('sales employee')) {
            return response()->json(['errors' => ['User not authorized'], 'message' => 'You are not authorized to perform this action.'], 200);
        }

        $token = Password::createToken($user);
        $user->notify(new ForgotPasswordMail($token));
        return response()->json(['message' => 'Password reset email sent successfully']);
    }

    private function sendNotification($fcmToken, $title, $body, $data)
    {
        $firebase = new FirebaseNotificationService();
        try {
            $firebase->sendNotification($fcmToken, $title, $body, $data);
        } catch (\Throwable $e) {
            // Log::info($e->getMessage());
        }
    }

    public function todayRoutePlan()
    {
        $plan = SalesRoutePlanning::with('shops.shop')->where('user_id', Auth::user()->id)
            ->whereDate('date', today()->format('Y-m-d'))
            ->first()?->toArray();
        if ($plan) {
            $groupedShops = collect($plan['shops'])->groupBy(function ($shopEntry) {
                return $shopEntry['shop']['pincode'];
            })->map(function ($shopsByPincode) {
                return $shopsByPincode->groupBy(function ($shopEntry) {
                    return $shopEntry['shop']['area'];
                });
            });
            $plan['shops'] = $groupedShops;
        }



        return response()->json([
            'status' => true,
            'data' => $plan,
        ]);
    }

    public function dashboardAnalytics()
    {
        $salesRoutePlan = SalesRoutePlanning::with('shops')->where('user_id', Auth::user()->id)->whereDate('date', today()->format('Y-m-d'))->first();

        $pending_shops = $salesRoutePlan->shops->where('visit_status', 'pending')->count();
        $visited_shops = $salesRoutePlan->shops->where('visit_status', 'completed')->count();

        return response()->json([
            'status' => true,
            'data' => [
                'visited_shops' => $visited_shops,
                'pending_shops' => $pending_shops,
            ],
        ]);
    }

    public function addShop(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'gumasta' => 'nullable',
                'gst' => 'nullable',
                'shop_photo' => 'nullable',
                'owner_name' => 'required',
                'owner_contact_no' => 'required',
                'owner_whatsapp_no' => 'required',
                'bank_details' => 'nullable',
                'address' => 'required',
                'latitude' => 'required|string|max:255',
                'longitude' => 'required|string|max:255',
                'pincode' => 'required|numeric|digits:6',
                'area' => 'required',
                'village' => 'required'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        if ($request->hasFile('shop_photo')) {
            $file = $request->shop_photo;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ShopImages/', $filename);
            $data['shop_photo'] = 'ShopImages/' . $filename;
        }

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        // Create a new shop record
        $shop = Shop::create($data);

        $salesRoutePlan = SalesRoutePlanning::where('user_id', Auth::id())->whereDate('date', today()->format('Y-m-d'))->first();
        if ($salesRoutePlan) {
            $shopPerRoute = new ShopsPerRoute();
            $shopPerRoute->sales_route_planning_id = $salesRoutePlan->id;
            $shopPerRoute->shop_id = $shop->id;
            $shopPerRoute->shop_status = 'newly_added';
            $shopPerRoute->created_by = Auth::user()->email;
            $shopPerRoute->last_updated_by = Auth::user()->email;
            $shopPerRoute->save();
        }

        return response()->json(['message' => 'Shop added successfully. Please mark shop visited and attach proof.']);
    }
    
    public function editShop(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'required|exists:shops,id',
                'name' => 'required|string|max:255',
                'gumasta' => 'nullable',
                'gst' => 'nullable',
                'shop_photo' => 'nullable',
                'owner_name' => 'required',
                'owner_contact_no' => 'required',
                'owner_whatsapp_no' => 'required',
                'bank_details' => 'nullable',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        if ($request->hasFile('shop_photo')) {
            $file = $request->shop_photo;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ShopImages/', $filename);
            $data['shop_photo'] = 'ShopImages/' . $filename;
        }

        $data['last_updated_by'] = Auth::user()->email;

        $filteredData = array_filter($data, function ($value) { 
            return !is_null($value); 
            
        }); 
        
        $shop = Shop::find($data['id']); 
        $shop->update($filteredData);

        return response()->json(['message' => 'Shop updated successfully. Please mark shop visited and attach proof.']);
    }

    public function getInventory()
    {
        $inventory = Inventory::where('warehouse_id', Auth::user()->warehouse_id)
            ->whereHas('product', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->with('product')
            ->get()
            ->sortBy(function ($item) {
                return $item->product->name ?? '';
            })
            ->sortByDesc(function ($item) {
                return $item->available_quantity ?? 0;
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $inventory,
        ]);
    }

    public function createSalesOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => ['required', Rule::exists('shops', 'id')],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', Rule::exists('products', 'id')],
            'products.*.quantity' => ['required', 'integer'],
            'products.*.unit' => ['required', 'string', 'max:255'],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        $warehouseId = Auth::user()->warehouse_id;
        $validator->after(function ($validator) use ($request, $warehouseId) {

            foreach ($request->input('products', []) as $index => $product) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $product['product_id'])
                    ->first();

                $availableQty = $inventory?->available_quantity ?? 0;
                $productName = Product::find($product['product_id'])?->name . ' ' . Product::find($product['product_id'])?->variant;

                if($product['unit'] == 'Carton'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
                    if ($product['quantity']*Product::find($product['product_id'])->quantity_per_carton > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif($product['unit'] == 'Box'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
                    if ($product['quantity']*Product::find($product['product_id'])->quantity_per_box > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif($product['unit'] == 'Bundle'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
                    if ($product['quantity']*Product::find($product['product_id'])->quantity_per_bundle > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif($product['unit'] == 'Ladi'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
                    if ($product['quantity']*Product::find($product['product_id'])->quantity_per_ladi > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } else{
                    $quantity = $product['quantity'];
                    if ($product['quantity'] > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                }
                
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 200);
        }

        $data = $validator->validated();

        foreach ($data['products'] as $index => $product) {
            $productModel = Product::find($product['product_id']);

            $data['products'][$index]['rate'] = $productModel?->selling_price ?? 0;
            $data['products'][$index]['hsn'] = $productModel?->hsn ?? '';
            $data['products'][$index]['mrp'] = $productModel?->price ?? 0;
            $data['products'][$index]['weight'] = $productModel?->weight ?? 0;
            $data['products'][$index]['gst'] = $productModel?->gst ?? '18';
        }

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;
        $data['user_id'] = Auth::id();
        $data['date'] = today()->format('Y-m-d');
        $lastOrder = SalesOrder::orderBy('id', 'desc')->first();
        $nextNumber = $lastOrder ? $lastOrder->id + 1 : 1;
        $padLength = max(4, strlen((string)$nextNumber));
        $data['order_number'] = 'SAL-ORD-' . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);

        // Create a new sales order
        $salesOrder = SalesOrder::create($data);

        foreach ($request->input('products', []) as $index => $product) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $product['product_id'])
                ->first();
            if($product['unit'] == 'Carton'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
            }elseif($product['unit'] == 'Box'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
            }elseif($product['unit'] == 'Bundle'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
            }elseif($product['unit'] == 'Ladi'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
            }else{
                $quantity = $product['quantity'];
            }
            $inventory->available_quantity = $inventory->available_quantity - $quantity;
            $inventory->reserved_quantity = $inventory->reserved_quantity + $quantity;

            $productThreshold = Product::find($product['product_id'])?->threshold_limit;

            if ($inventory->available_quantity < $productThreshold) {
                $inventory->status = 'low_stock';
            }

            $inventory->save();
        }

        $backendDepartmentIds = Department::where('name', 'like', '%backend%')->pluck('id');
        $backendUsers = User::whereIn('department_id', $backendDepartmentIds)->get();
        $notification = Notification::create([
            'type' => 'new_sales_order',
            'title' => "New Sales Order #{$salesOrder->order_number}",
            'body' => "New Sales Order is placed, please verify and approve.",
            'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
            'sender_id' => Auth::id(),
        ]);
        foreach ($backendUsers as $user) {

            $notification->users()->attach($user->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new NotificationCreated($notification, $user->id));
            if ($user->device_token) {
                $this->sendNotification($user->device_token, $notification['title'], $notification['body'], $notification->toArray());
            }
            
            // $user->notify(new SalesOrderCreated($salesOrder, Auth::user()));
        }
        if(Auth::user()->device_token){
            $this->sendNotification(Auth::user()->device_token, $notification['title'], 'Your sales order has been submitted successfully.', $notification->toArray());
        }

        return response()->json(['message' => 'Sales order created successfully.', 'data' => $salesOrder]);
    }
    
    public function editSalesOrder(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => ['required', Rule::exists('sales_orders', 'id')],
            'shop_id' => ['required', Rule::exists('shops', 'id')],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', Rule::exists('products', 'id')],
            'products.*.quantity' => ['required', 'integer'],
            'products.*.unit' => ['required', 'string', 'max:255'],
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);
        
        $warehouseId = Auth::user()->warehouse_id;
        $salesOrder = SalesOrder::findOrFail($request->id);
        if($salesOrder->approval_status != 'pending'){
            return response()->json([
                'status' => false,
                'message' => "Sales Order can't be edited as it is already approved or rejected."
            ]);
        }
        
        foreach ($salesOrder->products as $oldProduct) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $oldProduct['product_id'])
                ->first();
            if (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Carton') {
                $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_carton;
            } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Box'){
                $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_box;
            } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Bundle'){
                $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_bundle;
            } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Ladi'){
                $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_ladi;
            } else {
                $oldQuantity = $oldProduct['quantity'];
            }
            $inventory->available_quantity = $inventory->available_quantity + $oldQuantity;
            $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $oldQuantity);

            $productThreshold = Product::find($oldProduct['product_id'])?->threshold_limit;

            if ($inventory->available_quantity > $productThreshold) {
                $inventory->status = 'sufficient_stock';
            }

            $inventory->save();
        }
        
        $validator->after(function ($validator) use ($request, $warehouseId) {

            foreach ($request->input('products', []) as $index => $product) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $product['product_id'])
                    ->first();

                $availableQty = $inventory?->available_quantity ?? 0;
                $productName = Product::find($product['product_id'])?->name . ' ' . Product::find($product['product_id'])?->variant;

                if ($product['unit'] == 'Carton') {
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_carton > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Box'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_box > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Bundle'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Ladi'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } else {
                    $quantity = $product['quantity'];
                    if ($product['quantity'] > $availableQty) {
                        $validator->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                }
            }
        });
        
        if ($validator->fails()) {
            foreach ($salesOrder->products as $oldProduct) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $oldProduct['product_id'])
                    ->first();
                if (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Carton') {
                    $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_carton;
                } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Box') {
                    $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_box;
                } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Bundle') {
                    $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_bundle;
                } elseif (isset($oldProduct['unit']) && $oldProduct['unit'] == 'Ladi') {
                    $oldQuantity = $oldProduct['quantity'] * Product::find($oldProduct['product_id'])->quantity_per_ladi;
                } else {
                    $oldQuantity = $oldProduct['quantity'];
                }
                $inventory->available_quantity = $inventory->available_quantity - $oldQuantity;
                $inventory->reserved_quantity = $inventory->reserved_quantity + $oldQuantity;

                $productThreshold = Product::find($oldProduct['product_id'])?->threshold_limit;

                if ($inventory->available_quantity < $productThreshold) {
                    $inventory->status = 'low_stock';
                }

                $inventory->save();
            }
        }
        
        $validator = $validator->validate();
        
        foreach ($validator['products'] as $index => $product) {
            $productModel = Product::find($product['product_id']);

            $validator['products'][$index]['rate'] = $productModel?->selling_price ?? 0;
            $validator['products'][$index]['hsn'] = $productModel?->hsn ?? '';
            $validator['products'][$index]['mrp'] = $productModel?->price ?? 0;
            $validator['products'][$index]['weight'] = $productModel?->weight ?? 0;
            $validator['products'][$index]['gst'] = $productModel?->gst ?? '18';
        }

        $validator['last_updated_by'] = Auth::user()->email;
        
        $salesOrder->update($validator);
        
        foreach ($request->input('products', []) as $index => $product) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $product['product_id'])
                ->first();
            if ($product['unit'] == 'Carton') {
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
            } elseif ($product['unit'] == 'Box') {
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
            } elseif ($product['unit'] == 'Bundle') {
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
            } elseif ($product['unit'] == 'Ladi') {
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
            } else {
                $quantity = $product['quantity'];
            }
            $inventory->available_quantity = $inventory->available_quantity - $quantity;
            $inventory->reserved_quantity = $inventory->reserved_quantity + $quantity;

            $productThreshold = Product::find($product['product_id'])?->threshold_limit;

            if ($inventory->available_quantity < $productThreshold) {
                $inventory->status = 'low_stock';
            }

            $inventory->save();
        }

        return response()->json(['message' => 'Sales order updated successfully.', 'data' => $salesOrder]);
        
        
    }

    public function salesOrders()
    {
        $salesOrders = SalesOrder::with(['user', 'shop'])->where('user_id', Auth::user()->id)->latest()->get();
        return response()->json([
            'status' => true,
            'data' => $salesOrders
        ]);
    }

    public function geolocationRange(Request $request)
    {
        try {
            $data = $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
                'shop_id' => 'required|exists:shops,id'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $shop = Shop::find($request->shop_id);

        if ($shop->latitude && $shop->longitude) {
            $earthRadius = 6371000;

            $lat1Rad = deg2rad($request->lat);
            $lat2Rad = deg2rad($shop->latitude);
            $deltaLat = deg2rad($shop->latitude - $request->lat);
            $deltaLon = deg2rad($shop->longitude - $request->lon);

            $a = sin($deltaLat / 2) ** 2 +
                cos($lat1Rad) * cos($lat2Rad) *
                sin($deltaLon / 2) ** 2;

            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

            $distance =  round($earthRadius * $c);
            if ($distance < 100) {
                return response()->json([
                    'status' => 'true',
                    'distance' => $distance
                ]);
            } else {
                return response()->json([
                    'status' => 'false',
                    'distance' => $distance
                ]);
            }
        } else {
            return response()->json([
                'errors' => ['Co-ordinates missing'],
                'message' => 'Shop geo co-ordinates are not assigned'
            ]);
        }
    }

    public function markVisited(Request $request)
    {
        try {
            $data = $request->validate([
                'shop_id' => 'required|exists:shops,id',
                'visit_proof' => 'required',
                'remarks' => 'nullable'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        if ($request->hasFile('visit_proof')) {
            $file = $request->visit_proof;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('VisitProofs/', $filename);
            $data['visit_proof'] = 'VisitProofs/' . $filename;
        }

        $data['visit_status'] = 'completed';
        $sales_route_plan = SalesRoutePlanning::where('date', now()->format('Y-m-d'))->where('user_id', Auth::id())->first();
        if (!$sales_route_plan) {
            return response()->json([
                'errors' => ['No Route Plan Exist'],
                'message' => 'No Route Plan exist for todays date.'
            ]);
        }
        $data['sales_route_planning_id'] = $sales_route_plan->id;
        $shop_per_route = ShopsPerRoute::where('sales_route_planning_id', $sales_route_plan->id)
            ->where('shop_id', $data['shop_id'])
            ->first();

        if ($shop_per_route) {
            $shop_per_route->update($data);
            if(Auth::user()->device_token){
                $this->sendNotification(Auth::user()->device_token, 'Visit Status updated as completed', 'Visit completed for shop ' . $shop_per_route->shop->name . ' successfully.', $data);
            }
            return response()->json(['message' => 'Shop marked as visited successfully.']);
        } else {
            return response()->json([
                'errors' => ['Shop not found in today\'s route plan'],
                'message' => 'Shop not found in today\'s route plan.'
            ]);
        }
    }

    public function notifications(){
        $notifications = NotificationUser::with('notification')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $notifications,
        ]);
    }


    public function salesOrderTracking($order_number){
        $order = SalesOrder::with(['user', 'deliveryEmployee', 'shop'])->where('order_number', $order_number)->first();
        $trackings = SalesOrderTracking::where('sales_order_id', $order->id)->get();
        $users = User::whereIn('email', $trackings->pluck('created_by'))->get()->keyBy('email');
        
        if(!$order){
            abort(404, 'Not found the order you are looking for.');
        }else{
            return view('sales-order-tracking', compact('order', 'trackings', 'users'));
        }
    }






















    public function uploadVillages(Request $request)
    {
        ini_set('max_execution_time', 300);
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new VillagesImport, $request->file('file'));

        return response()->json(['message' => 'Villages uploaded successfully']);
    }

    


    public function run_notification(): bool
    {
        $data = [
            'user_id' => 2,
            'date' => '2024-10-10'
        ];
        $this->sendNotification("f7yLojJARYu1IHYEywnRmQ:APA91bEhVHnVgRuuFDMzrbzU_r10g_qae03Jq7oUhQzPkTX8gVC3H6G7SMdhlCATL7Yd36JDDHpYSYcqDH9kBQcg_4vKP3lpKwy9wrRK3nwB25OkGhyffEU", "Title- Notification from Aayush", "Body-Notification for Atul", $data);

        return true;
    }
    
    
    public function getOrdersByProduct(Request $request, $id) { 
        $request->validate([ 'date' => 'required|date', ]); 
        $dateStart = Carbon::parse($request->date)->startOfDay(); 
        $dateEnd = Carbon::parse($request->date)->endOfDay();
    
        // fetch all orders created yesterday
        $orders = SalesOrder::whereBetween('created_at', [$dateStart, $dateEnd])
            ->get(['id','approval_status', 'delivery_status','created_at','products']);
    
        // format response
        $response = $orders->map(function ($order) use ($id) {
            // products is already an array in your schema
            $products = $order->products;
    
            // filter only product_id = $id
            $filtered = collect($products)
                ->filter(function ($p) use ($id) {
                    return $p['product_id'] == $id;
                })
                ->map(function ($p) {
                    return [
                        'product_id' => $p['product_id'],
                        'quantity'   => $p['quantity'],
                        'unit'       => $p['unit'] ?? null,
                    ];
                })
                ->values();
    
            return [
                'sales_order_id' => $order->id,
                'approval_status' => $order->approval_status,
                'status'         => $order->delivery_status,
                'created_at'     => $order->created_at->toDateTimeString(),
                'products'       => $filtered,
            ];
        })->filter(function ($order) {
            // only include orders that actually have the product
            return $order['products']->isNotEmpty();
        })->values();
    
        return response()->json($response, 200);
        
        // $sales_orders = SalesOrder::with(['report'])->where('date', $request->date)->where('delivery_partner', $id)->get();
        // $response = [];
        
        // foreach ($sales_orders as $order) {
        //     // Use products_with_details if available
        //     $products = is_string($order->products) 
        //         ? json_decode($order->products, true) 
        //         : $order->products;
        
        //     if (is_array($products)) {
        //         $total_sales_amount = 0;
        //         foreach ($products as $item) {
        //             $rate = floatval($item['rate'] ?? 0);
        //             $qty  = floatval($item['quantity'] ?? 0);
        
        //             // Unit conversions
        //             if (($item['unit'] ?? '') === 'Carton') {
        //                 $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
        //             } elseif (($item['unit'] ?? '') === 'Box') {
        //                 $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
        //             } elseif (($item['unit'] ?? '') === 'Bundle') {
        //                 $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
        //             } elseif (($item['unit'] ?? '') === 'Ladi') {
        //                 $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
        //             }
                    
        
        
        //             // Net amount (rate × qty)
        //             $net = $rate * $qty;
                    
        //             $cdPer = floatval($item['cd_per'] ?? 0); 
        //             $tdPer = floatval($item['td_per'] ?? 0); 
        //             if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
        //             if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
                    
        //             $total_sales_amount += round($net, 2); 
                    
        
        //         }
        //         $response[$order->order_number] = $total_sales_amount;
        //     }
            
        // }
        
        // return collect($response)->sum();

    }


}
