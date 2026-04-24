<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\Department;
use App\Models\Product;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Notifications\OfferLetterNotification;
use App\Notifications\SalarySlipNotification;
use App\Models\SalesRoutePlanning;
use App\Models\Shop;
use App\Models\ShopsPerRoute;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\NewVendorNotification;
use App\Models\Warehouse;
use App\Models\Area;
use App\Models\Inventory;
use App\Models\OfferLetter;
use App\Models\PurchaseOrder;
use App\Notifications\PurchaseOrderStatusNotification;
use App\Models\Village;
use App\Models\SalarySlip;
use App\Models\SalesOrder;
use App\Models\SalesOrderTracking;
use App\Models\ChangeLog;
use App\Models\WarehouseReport;
use App\Models\Ledger;
use App\Notifications\PurchaseOrderDeliveryCancelled;
use App\Notifications\PurchaseOrderWarehouse;
use App\Notifications\PurchaseOrderDelivered;
use App\Models\Notice;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Events\NotificationCreated;
use App\Models\AttendanceLog;
use App\Notifications\SalesOrderStatusNotification;
use App\Notifications\SalesOrderToWarehouse;
use App\Notifications\SalesOrderToDelivery;
use App\Notifications\SalesOrderBackToWarehouse;
use App\Notifications\NoticeNotification;
use App\Notifications\EODReport;
use App\Notifications\ReportUpdate;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Notifications\WelcomeResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Http;
use Mpdf\Mpdf;
use NumberToWords\NumberToWords;
use App\Jobs\StoreAreaFromPostOffice;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use App\Imports\ProductImport;
use App\Exports\ProductExport;
use App\Exports\ShopsExport;
use App\Exports\SalesRoutePlan;
use App\Exports\VillageExport;
use App\Exports\SalesOrder as ExportSalesOrder;
use App\Exports\ExportInventory;
use App\Notifications\SalesOrderCreated;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DailyDeliveryReport;
use App\Jobs\DeleteTempFile;
use Maatwebsite\Excel\Concerns\FromCollection; 
use Maatwebsite\Excel\Concerns\WithHeadings; 
use Illuminate\Support\Collection;



class HomeController extends Controller implements HasMiddleware
{

    public static function middleware(): array
    {
        return [
            'auth'
        ];
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

    public function index()
    {

       return $this->dashboard();
    }

    public function uploadProfilePic(Request $request)
{
    $request->validate([
        'profile_pic' => 'required|image|mimes:jpg,jpeg,png|max:2048'
    ]);

    $user = Auth::user();

    // delete old pic
    if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
        Storage::disk('public')->delete($user->profile_pic);
    }

    // store new pic
    $path = $request->file('profile_pic')->store('profiles', 'public');

    $user->update(['profile_pic' => $path]);

    return back()->with('success', 'Profile picture updated.');
}

public function dashboard()
{
    $user = Auth::user();
    $role = $user->roles()->first()?->name;

    return match(true) {
        in_array($role, ['admin', 'System Administrator', 'Full Stack Developer']) 
            => $this->adminDashboard(),
        in_array($role, ['hr', 'HR & Floor Manager (Jaora Warehouse)']) 
            => $this->hrDashboard(),
        in_array($role, ['sales', 'sales employee', 'Sales Manager', 'Zonal  Sales Manager', 'product sales representative', 'Distributor Relationship Executive', 'Sales Manager (Jaora Warehouse)']) 
            => $this->salesDashboard(),
        in_array($role, ['warehouse', 'Warehouse Manager', 'logistics manager', 'logistics  Manager Assistant', 'Logistic Manager (Warehouse)', 'Computer Operator ( Jaora Warehouse )', 'Supervisor', 'Supervisor (Jaora Warehouse)']) 
            => $this->warehouseDashboard(),
        in_array($role, ['accounts', 'purchase']) 
            => $this->accountsDashboard(),
        in_array($role, ['delivery', 'delivery employee']) 
            => $this->deliveryDashboard(),
        default => $this->defaultDashboard()
    };
}

public function profile()
{
    $user = Auth::user()->load('department', 'warehouse', 'salaryStructure');
    return view('profile', compact('user'));
}

public function updateProfile(Request $request)
{
    $user = Auth::user();

    $request->validate([
        'name'        => 'required|string|max:255',
        'phone'       => 'nullable|string|max:20',
        'birthdate'   => 'nullable|date',
        'designation' => 'nullable|string|max:255',
        'profile_pic' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $data = $request->only(['name', 'phone', 'birthdate', 'designation']);

    if ($request->hasFile('profile_pic')) {
        if ($user->profile_pic && Storage::disk('public')->exists($user->profile_pic)) {
            Storage::disk('public')->delete($user->profile_pic);
        }
        $data['profile_pic'] = $request->file('profile_pic')->store('profiles', 'public');
    }

    $user->update($data);

    return back()->with('success', 'Profile updated successfully.');
}
  public function adminDashboard()
{
    $user = Auth::user()->load('department');
    $today = now()->toDateString();
    $weekStart = now()->startOfWeek()->toDateString();

    // ── Sales Orders ──
    $todaySalesOrders = SalesOrder::whereDate('created_at', $today)->get();
    $todaySalesAmount = $todaySalesOrders->sum(
        fn($o) => collect($o->products)->sum(fn($p) => $p['rate'] * $p['quantity'])
    );

    

    $recentSalesOrders = SalesOrder::with('shop', 'user')
        ->whereDate('created_at', $today)
        ->latest()->take(5)->get();

    // ── Purchase Orders ──
    $todayPurchaseOrders = PurchaseOrder::whereDate('created_at', $today)->count();
    $weekPurchaseOrders  = PurchaseOrder::whereBetween('created_at', [$weekStart, $today])->get();
    $weekPurchaseAmount  = $weekPurchaseOrders->sum(
        fn($o) => collect($o->products)->sum(fn($p) => $p['rate'] * $p['quantity'])
    );

    $recentPurchaseOrders = PurchaseOrder::with('vendor', 'warehouse')
        ->whereDate('created_at', $today)
        ->latest()->take(5)->get();

    // ── Deliveries ──
    $todayDeliveries   = SalesOrder::whereDate('created_at', $today)
        ->where('delivery_status', 'delivered')->count();
    $pendingDeliveries = SalesOrder::whereDate('created_at', $today)
        ->where('delivery_status', 'pending')->count();

    $recentDeliveries = SalesOrder::with('shop', 'deliveryEmployee')
        ->whereDate('created_at', $today)
        ->where('dispatched', true)
        ->latest()->take(5)->get();

    // ── Warehouse Reports ──
    $todayWarehouseReports = WarehouseReport::whereDate('created_at', $today)->count();
    $weekWarehouseReports  = WarehouseReport::whereBetween('created_at', [$weekStart, $today])->count();

    $recentWarehouseReports = WarehouseReport::whereDate('created_at', $today)
        ->latest()->take(5)->get();

    return view('admin.dashboard', compact(
        'user',
        'todaySalesOrders', 'todaySalesAmount',
       
        'todayPurchaseOrders', 'weekPurchaseOrders', 'weekPurchaseAmount',
        'recentSalesOrders',
        'recentPurchaseOrders',
        'todayDeliveries', 'pendingDeliveries', 'recentDeliveries',
        'todayWarehouseReports', 'weekWarehouseReports', 'recentWarehouseReports'
    ));
}
private function hrDashboard()
{
    $today = now()->toDateString();
    $totalEmployees    = \App\Models\User::count();
    $todayAttendance   = \App\Models\AttendanceLog::whereDate('created_at', $today)->count();
    $pendingSlips = \App\Models\SalarySlip::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();
    $recentNotices     = \App\Models\Notice::latest()->take(5)->get();

    return view('hr.dashboard', compact(
        'totalEmployees', 'todayAttendance', 'pendingSlips', 'recentNotices'
    ));
}

private function salesDashboard()
{
    $user  = Auth::user();
    $today = now()->toDateString();

    // sales employees see only their own orders
    $query = \App\Models\SalesOrder::whereDate('created_at', $today);
    if (!$user->hasRole(['admin', 'Sales Manager', 'Zonal  Sales Manager'])) {
        $query->where('user_id', $user->id);
    }

    $todayOrders  = $query->count();
    $recentOrders = $query->latest()->take(5)->get();

    return view('sales.dashboard', compact('todayOrders', 'recentOrders'));
}

private function warehouseDashboard()
{
    $today = now()->toDateString();
    $pendingPurchaseOrders  = \App\Models\PurchaseOrder::where('delivery_status', 'pending')->count();
    $todayWarehouseReports  = \App\Models\WarehouseReport::whereDate('created_at', $today)->count();
    $lowInventory = \App\Models\Inventory::where('available_quantity', '<', 10)->count();
    $recentWarehouseReports = \App\Models\WarehouseReport::latest()->take(5)->get();

    return view('warehouse.dashboard', compact(
        'pendingPurchaseOrders', 'todayWarehouseReports', 'lowInventory', 'recentWarehouseReports'
    ));
}

private function accountsDashboard()
{
    $weekStart = now()->startOfWeek()->toDateString();
    $today     = now()->toDateString();

    $weekPurchaseOrders = \App\Models\PurchaseOrder::whereBetween('created_at', [$weekStart, $today])->get();
    $weekPurchaseAmount = $weekPurchaseOrders->sum(
        fn($o) => collect($o->products)->sum(fn($p) => $p['rate'] * $p['quantity'])
    );
    $recentExpenses     = \App\Models\Expense::latest()->take(5)->get();

    return view('accounts.dashboard', compact(
        'weekPurchaseOrders', 'weekPurchaseAmount', 'recentExpenses'
    ));
}

private function deliveryDashboard()
{
    $user  = Auth::user();
    $today = now()->toDateString();

    $query = \App\Models\SalesOrder::whereDate('created_at', $today)->where('dispatched', true);
    if (!$user->hasRole(['admin', 'delivery'])) {
        $query->where('delivery_employee_id', $user->id);
    }

    $todayDeliveries    = $query->count();
    $deliveredToday     = (clone $query)->where('delivery_status', 'delivered')->count();
    $pendingDeliveries  = (clone $query)->where('delivery_status', 'pending')->count();
    $recentDeliveries   = $query->with('shop')->latest()->take(5)->get();

    return view('delivery.dashboard', compact(
        'todayDeliveries', 'deliveredToday', 'pendingDeliveries', 'recentDeliveries'
    ));
}

public function roles()
    {
        $roles = Role::all();
        return view('admin.roles', compact('roles'));
    }
private function defaultDashboard()
{
    $user = Auth::user();
    return view('dashboard', compact('user'));
}

    public function permissions(Request $request)
    {
        $request->validate([
            'role' => 'required'
        ]);

        $role = Role::findByName($request->role);

        // Fetch all permissions
        $allPermissions = Permission::all();

        $allPermissions = $allPermissions->reject(function ($permission) {
            $parts = explode(' ', $permission->name);
            return isset($parts[1]) && in_array($parts[1], ['role', 'changelog']);
        });

        // Define standard actions
        $standardActions = ['edit', 'view', 'create', 'delete'];

        // Separate special permissions
        $specialPermissions = $allPermissions->filter(function ($permission) use ($standardActions) {
            $action = explode(' ', $permission->name)[0];
            return !in_array($action, $standardActions);
        });


        // Remaining are standard CRUD permissions
        $standardPermissions = $allPermissions->diff($specialPermissions);

        // Group both sets
        $groupedPermissions = $standardPermissions->groupBy(function ($permission) {
            return explode(' ', $permission->name)[1]; // e.g., 'department'
        });


        // Get assigned permission names for the role
        $assigned = $role->permissions->pluck('name')->toArray();

        $groupedAssigned = collect($assigned)->groupBy(function ($perm) {
            return explode(' ', $perm)[1]; // 'edit department' → 'department'
        });

        return view('admin.permissions', [
            'permissions' => $groupedPermissions,
            'role' => $role,
            'assigned' => $assigned,
            'groupedAssigned' => $groupedAssigned,
            'specialPermissions' => $specialPermissions
        ]);
    }


    public function update_permission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required',
            'permission' => 'required|string',
            'assign' => 'required',
        ]);

        // Basic validation
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for restricted permission
        $restrictedPermissions = ['edit role', 'delete role'];

        if (in_array(strtolower($request->permission), $restrictedPermissions)) {
            return response()->json([
                'status' => false,
                'message' => 'Standard module "Role" is restricted from edit or delete permissions.'
            ], 403);
        }

        $role = Role::findByName($request->role);
        $permission = Permission::firstOrCreate(['name' => $request->permission]);



        if ($request->assign == 'true') {
            $role->givePermissionTo($permission);
            return response()->json(['message' => $permission->name . ' permission assigned.']);
        } else {
            $role->revokePermissionTo($permission);
            return response()->json(['message' => $permission->name . ' permission revoked.']);
        }
    }

    public function departments()
    {
        $departments = Department::all();
        foreach ($departments as $department) {
            $department->roles = $department->roleModels();
        }
        $roles = Role::all();
        return view('admin.departments', compact('departments', 'roles'));
    }

    public function add_department(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'roles' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $invalidRoles = collect($value)->filter(function ($roleId) {
                        return !Role::find($roleId);
                    });

                    if ($invalidRoles->isNotEmpty()) {
                        $fail("Invalid role IDs: " . $invalidRoles->implode(', '));
                    }

                    $conflictingDepartments = Department::whereNotNull('roles')
                        ->where(function ($query) use ($value) {
                            foreach ($value as $roleId) {
                                $query->orWhereJsonContains('roles', $roleId);
                            }
                        })->get();

                    if ($conflictingDepartments->isNotEmpty()) {
                        $conflictedRoles = collect($value)->filter(function ($roleId) use ($conflictingDepartments) {
                            return $conflictingDepartments->contains(function ($dept) use ($roleId) {
                                return in_array($roleId, $dept->roles);
                            });
                        });

                        if ($conflictedRoles->isNotEmpty()) {
                            $roleNames = Role::whereIn('id', $conflictedRoles)->pluck('name')->implode(', ');
                            $fail("Roles already assigned to other departments: " . $roleNames);
                        }
                    }
                },
            ],
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $department = Department::create($data);
        $department->roles = $department->roleModels();


        return response()->json([
            'status' => true,
            'message' => $request->name . ' departmet added successfully!',
            'data' => $department

        ]);
    }

    public function edit_department(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255|unique:departments,name,' . $request->id,
            'roles' => [
                'required',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    $invalidRoles = collect($value)->filter(function ($roleId) {
                        return !Role::find($roleId);
                    });

                    if ($invalidRoles->isNotEmpty()) {
                        $fail("Invalid role IDs: " . $invalidRoles->implode(', '));
                    }

                    $conflictingDepartments = Department::where('id', '!=', $request->id)
                        ->whereNotNull('roles')
                        ->where(function ($query) use ($value) {
                            foreach ($value as $roleId) {
                                $query->orWhereJsonContains('roles', $roleId);
                            }
                        })->get();

                    if ($conflictingDepartments->isNotEmpty()) {
                        $conflictedRoles = collect($value)->filter(function ($roleId) use ($conflictingDepartments) {
                            return $conflictingDepartments->contains(function ($dept) use ($roleId) {
                                return in_array($roleId, $dept->roles);
                            });
                        });

                        if ($conflictedRoles->isNotEmpty()) {
                            $roleNames = Role::whereIn('id', $conflictedRoles)->pluck('name')->implode(', ');
                            $fail("Roles already assigned to other departments: " . $roleNames);
                        }
                    }
                },
            ],
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $department = Department::findOrFail($data['id']);
        $department->name = $data['name'];
        $department->roles = $data['roles'];
        $department->last_updated_by = $data['last_updated_by'];
        $department->save();

        $department->roles = $department->roleModels();

        return response()->json([
            'status' => true,
            'message' => 'Department updated successfully.',
            'data' => $department
        ]);
    }

    public function delete_department(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:departments,id'
        ]);

        $department = Department::find($data['id']);
        $department->delete();

        return response()->json([
            'status' => true,
            'message' => 'Department deleted successfully!',
            'data' => $data
        ]);
    }

    public function vendors()
    {
        $vendors = Vendor::all()->groupBy('status');
        return view('admin.vendors', compact('vendors'));
    }

    public function add_vendor(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:vendors,name',
            'contact_number' => 'required|numeric|digits:10',
            'whatsapp_number' => 'required|numeric|digits:10',
            'gst_details' => 'nullable',
            'bank_details' => 'nullable',
            'delivery_address' => 'nullable',
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            $data['status'] = 'active';
        }

        $vendor = Vendor::create($data);
        if (!$user->hasRole('admin')) {
            $admins = User::role('admin')->get();
            $notification = Notification::create([
                'type' => 'new_vendor_created',
                'title' => "🆕 Vendor Created: {$vendor->name} — Activation Pending",
                'body' => "A new vendor <strong>{$vendor->name}</strong> has been added by the team. The vendor is currently inactive and cannot be used for purchase orders until approved.",
                'data' => ['vendor' => $vendor->id, 'link' => Route('vendors')],
                'sender_id' => Auth::id(),
            ]);
            foreach ($admins as $admin) {


                // Link to user
                NotificationUser::create([
                    'notification_id' => $notification->id,
                    'user_id' => $admin->id,
                ]);

                // Fire event
                event(new NotificationCreated($notification, $admin->id));

                // $admin->notify(new NewVendorNotification($vendor));
            }
        }
        return redirect()->back()->with('success', 'Vendor created successfully!');
    }

    public function edit_vendor(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:vendors,id',
            'name' => 'required|unique:vendors,name,' . $request->id,
            'contact_number' => 'required|numeric|digits:10',
            'whatsapp_number' => 'required|numeric|digits:10',
            'gst_details' => 'nullable',
            'bank_details' => 'nullable',
            'delivery_address' => 'nullable',
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $vendor = Vendor::findOrFail($data['id']);
        $vendor->name = $data['name'];
        $vendor->contact_number = $data['contact_number'];
        $vendor->whatsapp_number = $data['whatsapp_number'];
        $vendor->gst_details = $data['gst_details'];
        $vendor->bank_details = $data['bank_details'];
        $vendor->delivery_address = $data['delivery_address'];
        $vendor->last_updated_by = $data['last_updated_by'];
        $vendor->save();

        return redirect()->back()->with('success', 'Vendor updated successfully!');
    }

    public function delete_vendor(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:vendors,id',
            'status' => 'required|in:active,inactive'
        ]);

        $vendor = Vendor::find($data['id']);
        $vendor->status = $data['status'];
        $vendor->save();

        return redirect()->back()->with('success', 'Vendor status updated successfully!');
    }

    public function warehouses()
    {
        $warehouses = Warehouse::all();
        $warehouse_users = User::all();
        return view('admin.warehouses', compact('warehouses', 'warehouse_users'));
    }

    public function add_warehouse(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:warehouses,name',
            'location' => 'required',
            'contact_number' => 'required|numeric|digits:10',
            'contact_person' => 'required|exists:users,id'
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $warehouse = Warehouse::create($data);
        $products = Product::all();
        foreach ($products as $product) {
            $inventory = [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email
            ];
            Inventory::create($inventory);
        }

        return redirect()->back()->with('success', 'Warehouse created successfully!');
    }

    public function edit_warehouse(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:warehouses,name,' . $request->id,
            'location' => 'required',
            'contact_number' => 'required|numeric|digits:10',
            'contact_person' => 'required|exists:users,id'
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $warehouse = Warehouse::findOrFail($request->id);
        $warehouse->update($data);

        return redirect()->back()->with('success', 'Warehouse updated successfully!');
    }

    public function delete_warehouse(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:warehouses,id'
        ]);

        $warehouse = Warehouse::find($data['id']);
        $warehouse->delete();

        return redirect()->back()->with('success', 'Warehouse deleted successfully!');
    }

    public function employees()
    {
        $employees = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->with(['roles', 'department', 'offerLetter', 'salaryStructure'])->get();

        foreach ($employees as $employee) {
            $employee->earning_components = $employee->salaryStructure?->earningSalaryComponents();
            $employee->deduction_components = $employee->salaryStructure?->deductionSalaryComponents();
        }
        $departments = Department::all();
        $roles = Role::where('name', '!=', 'admin')->get();

        foreach ($roles as $role) {
            $department = Department::whereJsonContains('roles', (string)$role->id)->first();
            $role->department = $department ? $department->name : null;
        }
        $warehouses = Warehouse::all();
        $salaryStructures = SalaryStructure::all();
        
        $inactive_employees = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->onlyTrashed()->with(['roles', 'department'])->get();

        return view('employees', compact('employees', 'inactive_employees', 'departments', 'roles', 'warehouses', 'salaryStructures'));
    }

    public function add_employee(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'department_id' => 'required|exists:departments,id',
            'role' => 'required|exists:roles,id',
            'warehouse_id' => [
                Rule::requiredIf(function () use ($request) {
                    $role = Role::find($request->role);
                    return in_array(strtolower($role?->name), ['sales', 'warehouse', 'delivery']);
                }),
                'exists:warehouses,id',
            ],
            'birthdate' => 'required|date_format:Y-m-d',
            'phone' => 'required|numeric|digits:10',
            'salary_structure_id' => 'nullable|exists:salary_structures,id',
            'designation' => 'nullable'
        ]);

        $data['password'] = Hash::make($data['birthdate']);
        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $user = User::create($data);
        $role = Role::find($request->role);
        $user->assignRole($role->name);

        $token = Password::createToken($user);
        $user->notify(new WelcomeResetPassword($token));
        return redirect()->back()->with('success', 'Employee added successfully!');
    }

    public function edit_employee(Request $request)
    {
        
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $request->id,
            'department_id' => 'required|exists:departments,id',
            'role' => 'required|exists:roles,id',
            'warehouse_id' => [
                Rule::requiredIf(function () use ($request) {
                    $role = Role::find($request->role);
                    return in_array(strtolower($role?->name), ['sales', 'warehouse', 'delivery']);
                }),
                'exists:warehouses,id',
            ],
            'birthdate' => 'required|date_format:Y-m-d',
            'phone' => 'required|numeric|digits:10',
            'salary_structure_id' => 'nullable|exists:salary_structures,id',
            'designation' => 'nullable'
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $user = User::findOrFail($request->id);
        $old_role = $user->roles->first();
        $user->update($data);
        $role = Role::find($request->role);

        if ($old_role && $old_role->id !== $data['role']) {
            $user->removeRole($old_role->name);
            $user->assignRole($role->name);
        }else{
            $user->assignRole($role->name);
        }

        return redirect()->back()->with('success', 'Employee updated successfully!');
    }

    public function delete_employee(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:users,id'
        ]);

        $user = User::find($data['id']);
        $user->delete();

        return redirect()->back()->with('success', 'Employee deleted successfully!');
    }
    
    public function restore_employee(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:users,id'
        ]);
    
        // Find the user including trashed ones
        $user = User::withTrashed()->find($data['id']);
    
        if ($user && $user->trashed()) {
            $user->restore();
            return redirect()->back()->with('success', 'Employee restored successfully!');
        }
    
        return redirect()->back()->with('error', 'Employee is not deleted or not found.');
    }


    

    public function create_offerletter(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required',
            'job_title' => 'required',
            'reporting_manager' => 'required',
            'joining_date' => 'required',
            'employment_type' => 'required',
            'monthly_gross_salary' => 'required',
            'annual_ctc' => 'required',
            'probation_period' => 'required',
            'notice_period' => 'required',
            'acceptance_deadline' => 'required',
            'user_address' => 'required'
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $offer_letter = OfferLetter::with(['user'])->where('user_id', $data['user_id'])->first();

        if (!$offer_letter) {
            $data['created_by'] = Auth::user()->email;
            $offer_letter = OfferLetter::create($data);
        } else {
            $offer_letter->update($data);
        }

        return view('offer-letter', compact('offer_letter'));
    }

    public function send_offerletter($user_id)
    {
        $offer_letter = OfferLetter::with(['user'])->where('user_id', $user_id)->first();
        $mpdf = new Mpdf([
            'tempDir' => public_path('app/mpdf'),
        ]);
        $html = view('offer-letter-pdf', compact('offer_letter'))->render();
        $mpdf->setBasePath(public_path());
        $mpdf->WriteHTML($html);
        // dd('hey there');

        $pdfPath = public_path('Offer Letters/offer-letter-' . $offer_letter->user_id . '.pdf');

        $mpdf->Output($pdfPath, 'F');

        $user = User::find($user_id);

        $user->notify(new OfferLetterNotification($offer_letter));

        return redirect()->route('employees');
    }

    public function salarycomponents()
    {
        $salaryComponents = SalaryComponent::all();
        return view('salarycomponents', compact('salaryComponents'));
    }

    public function add_salarycomponent(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:Earning,Deduction',
            'name' => 'required|string|max:255|unique:salary_components,name',
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        SalaryComponent::create($data);
        return redirect()->back()->with('success', 'Salary Component added successfully!');
    }

    public function edit_salarycomponent(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:salary_components,id',
            'type' => 'required|in:Earning,Deduction',
            'name' => 'required|string|max:255|unique:salary_components,name,' . $request->id,
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $salaryComponent = SalaryComponent::findOrFail($request->id);
        $salaryComponent->update($data);

        return redirect()->back()->with('success', 'Salary Component updated successfully!');
    }

    public function delete_salarycomponent(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:salary_components,id'
        ]);

        $salaryComponent = SalaryComponent::findOrFail($data['id']);
        $salaryComponent->delete();

        return redirect()->back()->with('success', 'Salary Component deleted successfully!');
    }

    public function salarystructures()
    {
        $salaryStructures = SalaryStructure::all();
        foreach ($salaryStructures as $structure) {
            $structure->earning_components = $structure->earningSalaryComponents();
            $structure->deduction_components = $structure->deductionSalaryComponents();
        }
        $earning_components = SalaryComponent::where('type', 'Earning')->get();
        $deduction_components = SalaryComponent::where('type', 'Deduction')->get();
        return view('salarystructures', compact('salaryStructures', 'earning_components', 'deduction_components'));
    }

    public function add_salarystructure(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:salary_structures,name',
            'earning_components' => 'required|array',
            'deduction_components' => 'required|array',
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        SalaryStructure::create($data);
        return redirect()->back()->with('success', 'Salary Structure added successfully!');
    }

    public function edit_salarystructure(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:salary_structures,id',
            'name' => 'required|string|max:255|unique:salary_structures,name,' . $request->id,
            'earning_components' => 'required|array',
            'deduction_components' => 'required|array',
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $salaryStructure = SalaryStructure::findOrFail($request->id);
        $salaryStructure->update($data);

        return redirect()->back()->with('success', 'Salary Structure updated successfully!');
    }

    public function delete_salarystructure(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:salary_structures,id'
        ]);

        $salaryStructure = SalaryStructure::findOrFail($data['id']);
        $salaryStructure->delete();

        return redirect()->back()->with('success', 'Salary Structure deleted successfully!');
    }

    public function salaryslips()
    {
        $salaryslips = SalarySlip::with(['user.department'])->orderBy('month', 'desc')->get()->groupBy('month');
        $employees = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->with(['department'])->get();
        // dd($salaryslips);
        return view('salary-slips', compact('salaryslips', 'employees'));
    }

    public function add_salaryslip(Request $request)
    {
        $data = $request->validate([
            'month' => 'required',
            'user_id' => 'required|exists:users,id',
            'earnings' => 'required|array',
            'earnings.*' => 'required|numeric|min:0',
            'deductions' => 'required|array',
            'deductions.*' => 'required|numeric|min:0',
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $salary_slip = SalarySlip::where('month', $data['month'])->where('user_id', $data['user_id'])->first();

        if ($salary_slip) {
            $salary_slip->update($data);
        } else {
            $data['created_by'] = Auth::user()->email;
            $salary_slip = SalarySlip::create($data);
        }

        return redirect()->route('salary-slip', ['month' => $data['month'], 'user_id' => $data['user_id']]);
    }

    public function fetch_salaryslip(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required'
        ]);

        $salary_slip = SalarySlip::where('user_id', $request->user_id)->where('month', $request->month)->first();

        return response()->json([
            'data' => $salary_slip,
            'success' => true,
            'message' => 'Salary Slip fetched successfully'
        ]);
    }

    public function salary_slip($user_id, $month)
    {
        $salary_slip = SalarySlip::with(['user'])->where('month', $month)->where('user_id', $user_id)->first();

        $roundOff = array_sum($salary_slip->earnings) - array_sum($salary_slip->deductions);
        $numberToWords = new NumberToWords();
        $amountInWords = ucfirst($numberToWords->getNumberTransformer('en')->toWords($roundOff)) . ' rupees only';
        return view('salary-slip', compact('salary_slip', 'amountInWords'));
    }
    
    public function send_salaryslip($user_id, $month){
        $salary_slip = SalarySlip::with(['user'])->where('month', $month)->where('user_id', $user_id)->first();

        $roundOff = array_sum($salary_slip->earnings) - array_sum($salary_slip->deductions);
        $numberToWords = new NumberToWords();
        $amountInWords = ucfirst($numberToWords->getNumberTransformer('en')->toWords($roundOff)) . ' rupees only';
        
        $mpdf = new Mpdf([
            'tempDir' => public_path('app/mpdf'),
        ]);
        $html = view('salary-slip-pdf', compact('salary_slip', 'roundOff', 'amountInWords'))->render();
        $mpdf->setBasePath(public_path());
        $mpdf->WriteHTML($html);

        $pdfPath = public_path('Salary Slip/salary-slip-' . $salary_slip->user_id . '-'. $salary_slip->month . '.pdf');

        $mpdf->Output($pdfPath, 'F');

        $user = User::find($user_id);

        $user->notify(new SalarySlipNotification($salary_slip));

        return redirect()->route('salaryslips');
    }

    public function generate_notice(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required',
            'points' => 'required'
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $notice = Notice::create($data);

        return redirect()->route('notice', $notice->id);
    }

    public function notices()
    {
        $notices = Notice::with(['user.department'])->get();
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->with(['department'])->get();
        return view('notices', compact('notices', 'users'));
    }

    public function notice($id)
    {
        $notice = Notice::with(['user.department'])->find($id);
        return view('notice', compact('notice'));
    }

    public function send_notice($id)
    {
        $notice = Notice::with(['user.department'])->find($id);
        $mpdf = new Mpdf([
            'tempDir' => public_path('app/mpdf'),
        ]);
        $html = view('notice-pdf', compact('notice'))->render();
        $mpdf->setBasePath(public_path());
        $mpdf->WriteHTML($html);

        $pdfPath = public_path('Notice/notice-' . $notice->id . '.pdf');

        $mpdf->Output($pdfPath, 'F');

        $user = User::find($notice->user_id);

        $user->notify(new NoticeNotification($notice));
        $notice->status = 'sent';
        $notice->save();

        return redirect()->route('employees');
    }

    public function delete_notice(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:notices,id'
        ]);

        $notice = Notice::find($request->id);
        $notice->delete();

        return redirect()->back()->with('success', 'Notice deleted successfully!');
    }

  

    public function salesrouteplannings()
    {
        if (Auth::user()->hasRole('sales')) {
            $salesRoutePlannings = SalesRoutePlanning::where('user_id', Auth::user()->id)->latest('date')->get();
        }
        // $salesRoutePlannings = SalesRoutePlanning::latest('date')->get();
        $salesRoutePlannings = SalesRoutePlanning::where('created_at', '>=', now()->subDays(10))->latest('date')->get();

        foreach ($salesRoutePlannings as $plan) {
            $areas = Area::whereIn('area', $plan->areas)->whereIn('pincode', $plan->pincodes)
                ->get(['area', 'pincode'])
                ->map(fn($area) => ['name' => $area->area, 'pincode' => $area->pincode]);

            $villages = Village::whereIn('village', (array) $plan->villages)->whereIn('area', (array) $plan->areas)->whereIn('pincode', (array) $plan->pincodes)
                ->get(['village', 'area', 'pincode'])
                ->map(fn($village) => ['village' => $village->village, 'area' => $village->area, 'pincode' => $village->pincode]);

            $plan->villages = $villages;
            $plan->areas = $areas;
        }

        $sales_users = User::whereHas('roles', function ($query) {
            $query->where('name', 'like', '%sale%');
        })->get();
        
        $areas = Area::all();
        
        return view('salesrouteplannings', compact('salesRoutePlannings', 'sales_users', 'areas'));
    }

    public function getAreasByPincode($pincode)
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                    'Accept' => 'application/json',
                ])
                ->get("https://api.postalpincode.in/pincode/{$pincode}");

            $data = $response->json();

            if (!empty($data) && $data[0]['Status'] === 'Success') {
                // Filter PostOffice array without changing the outer structure
                $data[0]['PostOffice'] = array_values(array_filter(
                    $data[0]['PostOffice'] ?? [],
                    fn($office) => in_array($office['BranchType'], ['Sub Post Office', 'Head Post Office'])
                ));
                foreach ($data[0]['PostOffice'] as $office) {
                    dispatch(new StoreAreaFromPostOffice($office));
                }

                return response()->json($data[0]);
            }

            return response()->json([
                [
                    'Status' => 'Error',
                    'Message' => 'Invalid Pincode or No Data Found'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                [
                    'Status' => 'Error',
                    'Message' => 'API Request Failed'
                ]
            ]);
        }
    }

    public function getVillagesByArea(Request $request)
    {
        $areaName = $request->input('area');
        $pincode = $request->input('pincode');

        $villages = Village::where('pincode', $pincode)->where('area', $areaName)->pluck('village'); // adjust column names as needed

        return response()->json([
            'status' => 'Success',
            'villages' => $villages
        ]);
    }

    public function add_salesrouteplanning(Request $request)
    {
        $rawPincodes = $request->input('pincodes.0'); // JSON string like '[{"value":"452001"},{"value":"452002"}]'
        $decodedPincodes = collect(json_decode($rawPincodes, true))
            ->pluck('value')
            ->filter(fn($val) => preg_match('/^\d{6}$/', $val)) // Optional: ensure valid 6-digit format
            ->values()
            ->toArray();

        $request->merge(['pincodes' => $decodedPincodes]);

        $data = $request->validate([
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'pincodes' => 'required|array|min:1',
            'areas' => 'required|array|min:1',
            'villages' => 'required|array|min:1',
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $plan = SalesRoutePlanning::create($data);

        $shops = Shop::whereIn('pincode', $data['pincodes'])->whereIn('area', $data['areas'])->get();
        $shopperroute = [];
        $shopperroute['sales_route_planning_id'] = $plan->id;
        foreach ($shops as $shop) {
            $shopperroute['shop_id'] = $shop->id;
            $shopperroute['created_by'] = Auth::user()->email;
            $shopperroute['last_updated_by'] = Auth::user()->email;
            ShopsPerRoute::create($shopperroute);
        }
        $sales_person = User::find($data['user_id']);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->format('d M, Y');
        if ($sales_person->device_token) {
            $this->sendNotification($sales_person->device_token, 'New Route Plan assigned for ' . $date, 'Route Plan assigned for date ' . $date, $plan->toArray());
        }
        return redirect()->back()->with('success', 'Sales Route Planning added successfully!');
    }

    public function edit_salesrouteplanning(Request $request)
    {

        $rawPincodes = $request->input('pincodes.0'); // JSON string like '[{"value":"452001"},{"value":"452002"}]'

        $decodedPincodes = collect(json_decode($rawPincodes, true))
            ->pluck('value')
            ->filter(fn($val) => preg_match('/^\d{6}$/', $val)) // Optional: ensure valid 6-digit format
            ->values()
            ->toArray();

        $request->merge(['pincodes' => $decodedPincodes]);

        $data = $request->validate([
            'id' => 'required|exists:sales_route_plannings,id',
            'date' => 'required|date',
            'user_id' => 'required|exists:users,id',
            'pincodes' => 'required|array|min:1',
            'areas' => 'required|array|min:1',
            'villages' => 'nullable|array|min:1',
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $planning = SalesRoutePlanning::findOrFail($request->id);
        $planning->update($data);

        $shops = Shop::whereIn('pincode', $data['pincodes'])
            ->whereIn('area', $data['areas'])
            ->get();

        $currentShopIds = $shops->pluck('id')->toArray();

        // Get existing shop IDs for this planning
        $existingShopIds = ShopsPerRoute::where('sales_route_planning_id', $planning->id)
            ->pluck('shop_id')
            ->toArray();

        // 1️⃣ Add or update selected shops
        foreach ($currentShopIds as $shopId) {
            $shopperroute = ShopsPerRoute::withTrashed()
                ->where('sales_route_planning_id', $planning->id)
                ->where('shop_id', $shopId)
                ->first();

            if (!$shopperroute) {
                // Create new record
                $shopperroute = new ShopsPerRoute();
                $shopperroute->sales_route_planning_id = $planning->id;
                $shopperroute->shop_id = $shopId;
                $shopperroute->created_by = Auth::user()->email;
                $shopperroute->last_updated_by = Auth::user()->email;
            } else {
                // Restore if soft-deleted
                if ($shopperroute->trashed()) {
                    $shopperroute->restore();
                }
                $shopperroute->last_updated_by = Auth::user()->email;
            }

            $shopperroute->save();
        }

        // 2️⃣ Delete shops that were previously linked but are now removed
        $shopsToDelete = array_diff($existingShopIds, $currentShopIds);

        ShopsPerRoute::where('sales_route_planning_id', $planning->id)
            ->whereIn('shop_id', $shopsToDelete)
            ->delete();

        return redirect()->back()->with('success', 'Sales Route Planning updated successfully!');
    }

    public function delete_salesrouteplanning(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:sales_route_plannings,id'
        ]);

        $planning = SalesRoutePlanning::findOrFail($data['id']);
        $planning->delete();

        return redirect()->back()->with('success', 'Sales Route Planning deleted successfully!');
    }

    public function shopsperroutes($route_id)
    {
        $shops = ShopsPerRoute::with(['shop', 'salesRoutePlanning.user'])->where('sales_route_planning_id', $route_id)->get();
        return view('shopsperroute', compact('shops', 'route_id'));
    }

    public function shops(Request $request)
    {
        // $shops = Shop::where('created_at', '>=', now()->subDays(7))->get();
        $areas = Area::all()->pluck('area')->unique();
        
        $query = Shop::query();
        
        $filter = false;
        
        if ($request->filled('customer_area')) { 
            $query->whereIn('area', $request->customer_area);
            $filter = true;
        }
        
        if ($request->filled('customer_village')) { 
            $query->where('village', 'like', "%{$request->customer_village}%");
            $filter = true;
        }
        
        if ($request->filled('customer_number')) { 
            $query->where('owner_contact_no', 'like', "%{$request->customer_number}%");
            $filter = true;
        }
        
        if ($request->filled('shop_name')) { 
            $query->where('name', 'like', "%{$request->shop_name}%");
            $filter = true;
        }
        
        if ($request->filled('owner_name')) { 
            $query->where('owner_name', 'like', "%{$request->owner_name}%");
            $filter = true;
        }
        
        if(!$filter){
            $query->where('created_at', '>=', now()->subDays(5)); 
        }
        
        $shops = $query->get();
        
        return view('shops', compact('shops', 'areas', 'request'));
    }

    public function add_shop(Request $request)
    {
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
        if ($request->hasFile('shop_photo')) {
            $file = $request->shop_photo;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ShopImages/', $filename);
            $data['shop_photo'] = 'ShopImages/' . $filename;
        }

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        Shop::create($data);
        return redirect()->back()->with('success', 'Shop added successfully!');
    }

    public function edit_shop(Request $request)
    {
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
            'address' => 'required',
            'latitude' => 'required|string|max:255',
            'longitude' => 'required|string|max:255',
            'pincode' => 'required|numeric|digits:6',
            'area' => 'required',
            'village' => 'required'
        ]);

        if ($request->hasFile('shop_photo')) {
            $file = $request->shop_photo;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ShopImages/', $filename);
            $data['shop_photo'] = 'ShopImages/' . $filename;
        }

        $data['last_updated_by'] = Auth::user()->email;

        $shop = Shop::find($request->id);
        $shop->update($data);
        return redirect()->back()->with('success', 'Shop updated successfully!');
    }

    public function delete_shop(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:shops,id'
        ]);

        $shop = Shop::findOrFail($data['id']);
        $shop->delete();

        return redirect()->back()->with('success', 'Shop deleted successfully!');
    }

    public function categories()
    {
        $categories = Category::withCount('products')->get();
        return view('categories', compact('categories'));
    }

    public function add_category(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:categories,name'
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        Category::create($data);

        return redirect()->back()->with('success', 'Category added successfully!');
    }

    public function edit_category(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:categories,id',
            'name' => 'required|unique:categories,name,' . $request->id
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $category = Category::findOrFail($data['id']);
        $category->update($data);

        return redirect()->back()->with('success', 'Category updated successfully!');
    }

    public function delete_category(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:categories,id'
        ]);

        $category = Category::findOrFail($data['id']);
        $category->delete();
        
        Product::where('category_id', $category->id)->update(['deleted_at' => $category->deleted_at]);

        return redirect()->back()->with('success', 'Category deleted successfully!');
    }

    public function products($category_id)
    {
        $products = Product::where('category_id', $category_id)->with('category')->get();
        return view('products', compact('products', 'category_id'));
    }

    public function add_product(Request $request)
    {
        $cleaned = $request->all();

        foreach ($cleaned as $key => $value) {
            if (is_string($value)) {
                // Replace backtick with apostrophe if between letters/numbers
                $value = preg_replace("/([a-zA-Z0-9])`([a-zA-Z0-9])/", "$1'$2", $value);
    
                // Remove backticks at start or end
                $value = trim($value, '`');
    
                // Remove any remaining stray backticks
                $value = str_replace('`', '', $value);
    
                $cleaned[$key] = $value;
            }
        }
    
        // Now validate cleaned data
        $data = validator($cleaned, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) use ($cleaned) {
                    return $query->where('variant', $cleaned['variant'] ?? null)
                                 ->where('weight', $cleaned['weight'] ?? null)
                                 ->where('category_id', $cleaned['category_id']);
                }),
            ],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'weight' => 'nullable',
            'packaging_type' => 'nullable',
            'buying_price' => 'required',
            'selling_price' => 'required',
            'quantity_per_carton' => 'required',
            'quantity_per_ladi' => 'required',
            'quantity_per_box' => 'required',
            'quantity_per_bundle' => 'required',
            'variant' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,avif,gif|max:2048',
            'hsn' => 'required',
            'gst' => 'required',
            'availability' => 'nullable',
            'threshold_limit' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ],
        [
            'name.unique' => 'This product with the same name, variant, weight, and category already exists.',
        ])->validate();

        if ($request->hasFile('image')) {
            $file = $request->image;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ProductImages/', $filename);
            $data['image'] = 'ProductImages/' . $filename;
        }

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        $product = Product::create($data);

        $warehouses = Warehouse::all();
        foreach ($warehouses as $warehouse) {
            $inventory = new Inventory();
            $inventory->product_id = $product->id;
            $inventory->warehouse_id = $warehouse->id;
            $inventory->available_quantity = 0;
            $inventory->reserved_quantity = 0;
            $inventory->location = '-';
            $inventory->status = 'low_stock';
            $inventory->created_by = Auth::user()->email;
            $inventory->last_updated_by = Auth::user()->email;
            $inventory->save();
        }

        return redirect()->back()->with('success', 'Product added successfully!');
    }

    public function uploadProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $import = new ProductImport;
        Excel::import($import, $request->file('file'));

        $skippedRows = $import->getSkippedRows();

        if (!empty($skippedRows)) {
            $messages = collect($skippedRows)
                ->map(fn($row) => "Row {$row['row']}: {$row['reason']}")
                ->map(fn($msg) => "<li>{$msg}</li>")
                ->implode('');

            return redirect()->back()->with('success', 'Products imported with some skipped rows.')
                ->withErrors(
                    ['<ul style="list-style: disc;">' . $messages . '</ul>'],
                );
        }

        return redirect()->back()->with('success', 'Products uploaded successfully.');
    }
    
    public function exportProducts()
    {
        Log::info('function called');
        return Excel::download(new ProductExport, 'products.xlsx');
    }

    public function edit_product(Request $request)
    {
        $cleaned = $request->all();

        foreach ($cleaned as $key => $value) {
            if (is_string($value)) {
                // Replace backtick with apostrophe if between letters/numbers
                $value = preg_replace("/([a-zA-Z0-9])`([a-zA-Z0-9])/", "$1'$2", $value);
    
                // Remove backticks at start or end
                $value = trim($value, '`');
    
                // Remove any remaining stray backticks
                $value = str_replace('`', '', $value);
    
                $cleaned[$key] = $value;
            }
        }
    
        // Now validate cleaned data
        $data = validator($cleaned, [
            'id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'weight' => 'nullable',
            'packaging_type' => 'nullable',
            'buying_price' => 'required',
            'selling_price' => 'required',
            'quantity_per_carton' => 'required',
            'quantity_per_ladi' => 'required',
            'quantity_per_box' => 'required',
            'quantity_per_bundle' => 'required',
            'variant' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,avif,gif|max:2048',
            'hsn' => 'required',
            'gst' => 'required',
            'availability' => 'nullable',
            'threshold_limit' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ])->validate();

        if ($request->hasFile('image')) {
            $file = $request->image;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ProductImages/', $filename);
            $data['image'] = 'ProductImages/' . $filename;
        }

        $data['last_updated_by'] = Auth::user()->email;

        $product = Product::find($data['id']);
        $product->update($data);

        return redirect()->back()->with('success', 'Product updated successfully!');
    }

    public function delete_product(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:products,id'
        ]);

        $product = Product::findOrFail($data['id']);
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully!');
    }

    public function all_products()
    {
        $products = Product::with('category')->get();
        $categories = Category::all();
        return view('all-products', compact('products', 'categories'));
    }
    
    public function productsByUser($user_id){
        $user = User::find($user_id);
        $products = Product::query()
            ->join('inventories', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.warehouse_id', $user->warehouse_id)
            ->where('inventories.available_quantity', '>', 0)
            ->select('products.*', 'inventories.available_quantity')
            ->get();
    
        return response()->json($products);
    }

    public function inventories()
    {
        // $inventories = Inventory::with(['product', 'warehouse'])->get()->groupBy('warehouse_id');
        $warehouses = Warehouse::has('inventories')->get();
        foreach($warehouses as $warehouse){
            $warehouse->total_quantity = Inventory::where('warehouse_id', $warehouse->id)->get()->sum('available_quantity');
        }
        return view('inventories', compact('warehouses'));
    }
    
    public function warehouseInventories($id)
    {
        $inventories = Inventory::with('product')
            ->where('warehouse_id', $id)
            ->select('id', 'product_id', 'available_quantity', 'reserved_quantity', 'location', 'status', 'created_at', 'created_by', 'last_updated_by')
            ->get();
    
        return response()->json([
            'data' => $inventories
        ]);
    }

    public function edit_inventory(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:inventories,id',
            'location' => 'required|string|max:255'
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        $inventory = Inventory::find($data['id']);
        $inventory->update($data);

        return redirect()->back()->with('success', 'Inventory updated successfully!');
    }

    public function purchaseorders()
    {
        $purchaseorders = PurchaseOrder::with(['vendor', 'warehouse'])->get();
        $warehouses = Warehouse::all();
        $vendors = Vendor::where('status', 'active')->get();
        $products = Product::all();
        return view('purchase-orders', compact('purchaseorders', 'warehouses', 'vendors', 'products'));
    }

    public function add_purchaseorder(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'vendor_id' => 'required|exists:vendors,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.rate' => 'required|numeric|min:0',
            'products.*.rate_per_carton' => 'required|numeric|min:0',
            'products.*.scheme' => 'nullable|string|max:255',
            'products.*.weight' => 'nullable|string|max:255',
            'products.*.packaging_type' => 'nullable|string|max:255',
            'products.*.quantity_per_carton' => 'required|integer|min:1',
            'products.*.cartons' => 'required|numeric|min:1',
            'date' => 'required|date_format:Y-m-d',
            'expected_delivery_date' => 'required|date_format:Y-m-d|after:date',
            'notes' => 'nullable'
        ]);
        // Generate order_number in format PUR-ORD-xxxx
        $lastOrder = PurchaseOrder::withTrashed()->orderBy('id', 'desc')->first();
        $nextNumber = $lastOrder ? $lastOrder->id + 1 : 1;
        $padLength = max(4, strlen((string)$nextNumber));
        $data['order_number'] = 'PUR-ORD-' . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        // Create the purchase order
        $purchaseOrder = PurchaseOrder::create($data);

        return redirect()->route('purchaseorders')->with('success', 'Purchase Order created successfully!');
    }

    public function edit_purchaseorder(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:purchase_orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'vendor_id' => 'required|exists:vendors,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.rate' => 'required|numeric|min:0',
            'products.*.rate_per_carton' => 'required|numeric|min:0',
            'products.*.scheme' => 'nullable|string|max:255',
            'products.*.weight' => 'nullable|string|max:255',
            'products.*.packaging_type' => 'nullable|string|max:255',
            'products.*.quantity_per_carton' => 'required|integer|min:1',
            'products.*.cartons' => 'required|numeric|min:1',
            'date' => 'required|date_format:Y-m-d',
            'expected_delivery_date' => 'required|date_format:Y-m-d|after:date',
            'notes' => 'nullable'
        ]);

        $data['last_updated_by'] = Auth::user()->email;

        // Update the purchase order
        $purchaseOrder = PurchaseOrder::find($data['id']);
        if($purchaseOrder->approval_status == 'approved'){
            return redirect()->back()->with('error', 'Purchase Order is already Approved! No changes can be done.');
        }
        $purchaseOrder->update($data);

        return redirect()->route('purchaseorders')->with('success', 'Purchase Order updated successfully!');
    }
    
    public function add_purchase_order(){
        $warehouses = Warehouse::all();
        $vendors = Vendor::where('status', 'active')->get();
        $products = Product::all();
        return view('add-purchase-order', compact('warehouses', 'vendors', 'products'));
    }
    
    public function edit_purchase_order($id){
        $order = PurchaseOrder::find($id);
        if(!$order){
            return redirect()->back()->with('error', 'No Purchase order found. Refresh and Try Again!');
        }
        $warehouses = Warehouse::all();
        $vendors = Vendor::where('status', 'active')->get();
        $products = Product::all();
        return view('edit-purchase-order', compact('order', 'warehouses', 'vendors', 'products'));
    }

    public function purchase_order($order_number)
    {
        $purchaseOrder = PurchaseOrder::where('order_number', $order_number)->with(['vendor', 'warehouse'])->firstOrFail();
        return view('purchase-order', compact('purchaseOrder'));
    }

    public function purchase_order_pdf($order_number)
    {
        $purchaseOrder = PurchaseOrder::where('order_number', $order_number)
            ->with(['vendor', 'warehouse'])
            ->firstOrFail();
            
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults(); 
        $fontDirs = $defaultConfig['fontDir']; 
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults(); 
        $fontData = $defaultFontConfig['fontdata'];
        
        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 5,
            'margin_right' => 5,
            'margin_bottom' => 5,
            'margin_left' => 5,
            'tempDir' => public_path('app/mpdf'),
            'fontDir' => array_merge($fontDirs, [ 
                public_path('assets/fonts'), 
            ]), 
            'fontdata' => $fontData + [ 
                'noto' => [ 
                    'R' => 'NotoSansDevanagari-Regular.ttf', 
                    'B' => 'NotoSansDevanagari-Bold.ttf', 
                ], 
            ], 
            'default_font' => 'noto'
        ]);
        $html = view('purchase-order-pdf', compact('purchaseOrder'))->render();
        $mpdf->setBasePath(public_path());
        $mpdf->WriteHTML($html);
        // dd('hey there');

        $pdfPath = public_path('Purchase Orders/purchase-order-' . $order_number . '.pdf');

        $mpdf->Output($pdfPath, 'F');

        if (file_exists($pdfPath)) {
            return response()->download($pdfPath);
        }

        abort(500, 'PDF generation failed.');
    }

    public function update_purchaseorder(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:purchase_orders,id',
            'action' => 'required|string|in:approved,rejected,cancelled,notify'
        ]);
        $purchaseOrder = PurchaseOrder::find($data['id']);

        if ($request->action == 'approved') {
            if ($purchaseOrder->approval_status == 'approved' || $purchaseOrder->delivery_status == 'delivered') {
                return redirect()->back()->with('error', 'Purchase Order already approved or delivered!');
            }
            $data['approval_status'] = 'approved';
        } elseif ($request->action == 'rejected') {
            if ($purchaseOrder->approval_status == 'rejected' || $purchaseOrder->delivery_status == 'delivered') {
                return redirect()->back()->with('error', 'Purchase Order already rejected or delivered!');
            }
            $data['approval_status'] = 'rejected';
        } elseif ($request->action == 'cancelled') {
            if ($purchaseOrder->approval_status == 'rejected' || $purchaseOrder->delivery_status == 'delivered' || $purchaseOrder->delivery_status == 'cancelled' || $purchaseOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Purchase Order is already either cancelled, delivered, rejected or not approved!');
            }
            $data['delivery_status'] = 'cancelled';
        } elseif ($request->action == 'notify') {
            $warehouse_users = User::where('warehouse_id', $purchaseOrder->warehouse_id)->get();
            $notification = Notification::create([
                'type' => 'purchase_order_status',
                'title' => "New Purchase Order #{$purchaseOrder->order_number}",
                'body' => "New Purchase Order is placed and will be delivered on the expected delivery date.",
                'data' => ['order_id' => $purchaseOrder->id, 'link' => Route('purchase-order', $purchaseOrder->order_number)],
                'sender_id' => Auth::id(),
            ]);
            foreach ($warehouse_users as $warehouse_user)
                if (Auth::id() != $warehouse_user->id) {

                    $notification->users()->attach($warehouse_user->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    event(new NotificationCreated($notification, $warehouse_user->id));
                    // $warehouse_user->notify(new PurchaseOrderWarehouse($purchaseOrder));

                    return redirect()->back()->with('success', 'Notified warehouse successfully!');
                }
        }

        $data['last_updated_by'] = Auth::user()->email;



        $purchaseOrder->update($data);

        $creator = User::withTrashed()->where('email', $purchaseOrder->created_by)->first();
        if (in_array($request->action, ['approved', 'rejected'])) {
            if (Auth::id() != $creator->id) {
                // Create notification
                $notification = Notification::create([
                    'type' => 'purchase_order_status',
                    'title' => "Purchase Order #{$purchaseOrder->order_number} {$data['approval_status']}",
                    'body' => "Your purchase order has been {$data['approval_status']}.",
                    'data' => ['order_id' => $purchaseOrder->id, 'link' => Route('purchase-order', $purchaseOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                // Link to user
                NotificationUser::create([
                    'notification_id' => $notification->id,
                    'user_id' => $creator->id,
                ]);

                // Fire event
                event(new NotificationCreated($notification, $creator->id));

                // $creator->notify(new PurchaseOrderStatusNotification($purchaseOrder));
            }
        } elseif ($request->action == 'cancelled') {
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'purchase_order_status',
                    'title' => "Delivery Cancelled for Purchase Order #{$purchaseOrder->order_number}",
                    'body' => "Your purchase order has been cancelled.",
                    'data' => ['order_id' => $purchaseOrder->id, 'link' => Route('purchase-order', $purchaseOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                event(new NotificationCreated($notification, $creator->id));
                // $creator->notify(new PurchaseOrderDeliveryCancelled($purchaseOrder));
            }
        }

        return redirect()->back()->with('success', 'Purchase Order updated successfully!');
    }

    public function confirm_delivery(Request $request)
    {
        $data = $request->validate([
            'id' => 'required',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.received_quantity' => 'required|integer|min:1',
            'products.*.rate' => 'required|numeric|min:0',
            'products.*.rate_per_carton' => 'required|numeric|min:0',
            'products.*.scheme' => 'nullable|string|max:255',
            'products.*.weight' => 'nullable|string|max:255',
            'products.*.packaging_type' => 'nullable|string|max:255',
            'products.*.quantity_per_carton' => 'required|integer|min:1',
            'products.*.cartons' => 'required|numeric|min:1',
        ]);

        $data['delivery_status'] = 'delivered';

        $purchaseOrder = PurchaseOrder::findOrFail($data['id']);
        if ($purchaseOrder->approval_status != 'approved' || $purchaseOrder->delivery_status == 'delivered') {
            return redirect()->back()->with('error', 'Purchase Order not approved or already delivered!');
        }
        $purchaseOrder->update($data);

        foreach ($request->products as $product) {
            $inventory = Inventory::where('product_id', $product['product_id'])->where('warehouse_id', $purchaseOrder->warehouse_id)->first();
            $productData = Product::find($product['product_id']);
            if ($inventory && $productData) {
                $inventory->available_quantity += $product['received_quantity'];
                if ($inventory->available_quantity > $productData->threshold_limit) {
                    $inventory->status = 'sufficient_stock';
                }
                $inventory->save();
            }
        }

        $creator = User::withTrashed()->where('email', $purchaseOrder->created_by)->first();

        if (Auth::id() != $creator->id) {
            // Create notification
            $notification = Notification::create([
                'type' => 'purchase_order_status',
                'title' => "Delivery Confirmed for Purchase Order #{$purchaseOrder->order_number}",
                'body' => "Purchase order has been delivered and inventory updated.",
                'data' => ['order_id' => $purchaseOrder->id, 'link' => Route('purchase-order', $purchaseOrder->order_number)],
                'sender_id' => Auth::id(),
            ]);

            // Link to user
            NotificationUser::create([
                'notification_id' => $notification->id,
                'user_id' => $creator->id,
            ]);

            // Fire event
            event(new NotificationCreated($notification, $creator->id));

            $creator->notify(new PurchaseOrderDelivered($purchaseOrder));
        }

        return redirect()->back()->with('success', 'Purchase Order updated successfully and inventory updated!');
    }

    public function delete_purchaseorder(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:purchase_orders,id',
        ]);

        $purchaseOrder = PurchaseOrder::find($data['id']);
        $purchaseOrder->delete();

        return redirect()->back()->with('success', 'Purchase Order deleted successfully!');
    }

    public function getProductById($id)
    {
        $product = Product::find($id);
        return response()->json($product);
    }

    public function salesorders(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->department && preg_match('/delivery/i', $user->department->name)) {
                return redirect()->route('delivery-salesorders');
            } elseif ($user->department && preg_match('/warehouse/i', $user->department->name)) {
                return redirect()->route('warehouse-salesorders');
            }
        }
        if ($request->filled('date')) { 
            $dates = explode(' - ', $request->date); 
            $filters['start_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[0]))->format('Y-m-d'); 
            $filters['end_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[1]))->format('Y-m-d'); 
        }
        $query = SalesOrder::with(['user.warehouse', 'shop']);
        
        $filter = false;
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
            $filter = true;
        }
        
        // Apply other filters (examples) 
        if ($request->filled('approval_status')) { 
            $query->where('approval_status', $request->approval_status); 
            $filter = true;
        } 
        
        if ($request->filled('delivery_status')) { 
            $query->where('delivery_status', $request->delivery_status); 
        } 
        
        if ($request->filled('packaging_status')) { 
            $query->where('packaging_status', $request->packaging_status);
        } 
        
        if ($request->filled('warehouse_id')) { 
            $query->whereHas('user.warehouse', fn($q) => $q->where('id', $request->warehouse_id));
        } 
        
        if ($request->filled('sales_person_id')) { 
            $query->where('user_id', $request->sales_person_id);
        } 
            
        if ($request->filled('customer_area')) { 
            $query->whereHas('shop', fn($q) => $q->whereIn('area', $request->customer_area));
            $filter = true;
        }
        
        if ($request->filled('customer_id')) { 
            $query->whereHas('shop', fn($q) => $q->where('id', $request->customer_id));
            $filter = true;
        }
        
        if ($request->filled('customer_village')) { 
            $query->whereHas('shop', fn($q) => $q->where('village', 'like', "%{$request->customer_village}%"));
            $filter = true;
        }
        
        if(!$filter){
            $query->where('created_at', '>=', now()->subDays(5)); 
        }

        if ($user->hasRole('admin') || ($user->department && preg_match('/backend/i', $user->department->name))) {
            $salesOrders = (clone $query)->where('approval_status', 'pending')->get()->map(function ($order) {
                $order->products = $order->products_with_details;
                return $order;
            });

            $otherOrders = (clone $query)->whereNot('approval_status', 'pending')->orderBy('created_at', 'desc')->get()->map(function ($order) {
                $order->products = $order->products_with_details;
                return $order;
            });
            $salesDepartmentIds = Department::where('name', 'like', '%sales%')->pluck('id');
            $sales_employees = User::whereIn('department_id', $salesDepartmentIds)->get();
        } else {
            $salesOrders = (clone $query)->where('user_id', $user->id)->where('approval_status', 'pending')->get()->map(function ($order) {
                $order->products = $order->products_with_details;
                return $order;
            });
            

            $otherOrders = (clone $query)->where('user_id', $user->id)->whereNot('approval_status', 'pending')->orderBy('created_at', 'desc')->get()->map(function ($order) {
                $order->products = $order->products_with_details;
                return $order;
            });

            $sales_employees = User::where('id', $user->id)->get();
        }

        $products = Product::all();
        $shops = Shop::all();
        $warehouses = Warehouse::all();
        $areas = $shops->pluck('area')->unique();
        
        return view('sales-orders', compact('salesOrders', 'otherOrders', 'products', 'shops', 'sales_employees', 'warehouses', 'areas', 'request'));
    }
    
    public function add_sales_order(){
        $user = Auth::user();
        if ($user->hasRole('admin') || ($user->department && preg_match('/backend/i', $user->department->name))) {
            $salesDepartmentIds = Department::where('name', 'like', '%sales%')->pluck('id');
            $sales_employees = User::with(['warehouse'])->whereIn('department_id', $salesDepartmentIds)->get();
        }else{
            $sales_employees = User::with(['warehouse'])->where('id', $user->id)->get();
        }
        
        $products = Product::all();
        $shops = Shop::all();
        return view('add-sales-order', compact('products', 'shops', 'sales_employees'));
    }
    
    public function edit_sales_order($id){
        $user = Auth::user();
        $order = SalesOrder::find($id);
        if(!$order){
            return redirect()->back()->with('error', 'No sales order found. Refresh and Try Again!');
        }
        if ($user->hasRole('admin') || ($user->department && preg_match('/backend/i', $user->department->name))) {
            $salesDepartmentIds = Department::where('name', 'like', '%sales%')->pluck('id');
            $sales_employees = User::whereIn('department_id', $salesDepartmentIds)->get();
        }else{
            $sales_employees = User::where('id', $user->id)->get();
        }
        
        $sales_user = User::find($order->user_id);
        $products = Product::query()
            ->join('inventories', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.warehouse_id', $sales_user->warehouse_id)
            // ->where('inventories.available_quantity', '>', 0)
            ->select('products.*', 'inventories.available_quantity')
            ->get();
        $shops = Shop::all();
        return view('edit-sales-order', compact('products', 'shops', 'sales_employees', 'order'));
    }

    public function add_salesorder(Request $request)
    {
        $data = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'shop_id' => 'required|exists:shops,id',
            'products' => 'required|array',
            'products.*.hsn' => 'required|numeric|min:0',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.weight' => 'required',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit' => 'required|in:Piece,Carton,Box,Bundle,Ladi',
            'products.*.mrp' => 'required|numeric|min:0',
            'products.*.rate' => 'required|numeric|min:0',
            'products.*.cd_per' => 'nullable|numeric|min:0',
            'products.*.td_per' => 'nullable|numeric|min:0',
            'products.*.gst' => 'nullable|numeric|min:0',
            'notes' => 'nullable',
            'date' => 'required|date',
            'expected_delivery_date' => 'required|date|after_or_equal:date',
        ]);

        $warehouseId = User::find($request->user_id)->warehouse_id;

        $data->after(function ($data) use ($request, $warehouseId) {

            foreach ($request->input('products', []) as $index => $product) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $product['product_id'])
                    ->first();

                $availableQty = $inventory?->available_quantity ?? 0;
                $productName = Product::find($product['product_id'])?->name . ' ' . Product::find($product['product_id'])?->variant;

                if ($product['unit'] == 'Carton') {
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_carton > $availableQty) {
                        $data->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Box'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_box > $availableQty) {
                        $data->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Ladi'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi > $availableQty) {
                        $data->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } elseif ($product['unit'] == 'Bundle'){
                    $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
                    if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle > $availableQty) {
                        $data->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                } else {
                    $quantity = $product['quantity'];
                    if ($product['quantity'] > $availableQty) {
                        $data->errors()->add(
                            "products.$index.quantity",
                            "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                        );
                    }
                }
            }
        });

        $data = $data->validate();
        
        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;
        $lastOrder = SalesOrder::orderBy('id', 'desc')->first();
        $nextNumber = $lastOrder ? $lastOrder->id + 1 : 1;
        $padLength = max(4, strlen((string)$nextNumber));
        $data['order_number'] = 'SAL-ORD-' . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);

        $salesOrder = SalesOrder::create($data);

        foreach ($request->input('products', []) as $index => $product) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $product['product_id'])
                ->first();
            if ($product['unit'] == 'Carton') {
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
            } elseif ($product['unit'] == 'Box'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
            } elseif ($product['unit'] == 'Bundle'){
                $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
            } elseif ($product['unit'] == 'Ladi'){
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
        if (Auth::user()->device_token) {
            $this->sendNotification(Auth::user()->device_token, $notification['title'], 'Your sales order has been submitted successfully.', $notification->toArray());
        }

        return redirect()->route('salesorders')->with('success', 'Sales Order created successfully!');
    }

    public function edit_salesorder(Request $request)
    {
            $data = Validator::make($request->all(), [
                'id' => 'required|exists:sales_orders,id',
                'user_id' => 'required|exists:users,id',
                'shop_id' => 'required|exists:shops,id',
                'products' => 'required|array',
                'products.*.hsn' => 'required|numeric|min:0',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.weight' => 'required',
                'products.*.quantity' => 'required|numeric|min:1',
                'products.*.unit' => 'required|in:Piece,Carton,Box,Bundle,Ladi',
                'products.*.mrp' => 'required|numeric|min:0',
                'products.*.rate' => 'required|numeric|min:0',
                'products.*.cd_per' => 'nullable|numeric|min:0',
                'products.*.td_per' => 'nullable|numeric|min:0',
                'products.*.gst' => 'nullable|numeric|min:0',
                'notes' => 'nullable',
                'date' => 'required|date',
                'expected_delivery_date' => 'required|date|after_or_equal:date',
            ]);
            
    
            
            $warehouseId = User::find($request->user_id)->warehouse_id;
    
            $salesOrder = SalesOrder::findOrFail($request->id);
            if($salesOrder->approval_status != 'pending'){
                return redirect()->route('salesorders')->with('error', 'Sales Order can`t be edited as it is already approved or rejected.');
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
    
            $data->after(function ($data) use ($request, $warehouseId) {
    
                foreach ($request->input('products', []) as $index => $product) {
                    $inventory = Inventory::where('warehouse_id', $warehouseId)
                        ->where('product_id', $product['product_id'])
                        ->first();
    
                    $availableQty = $inventory?->available_quantity ?? 0;
                    $productName = Product::find($product['product_id'])?->name . ' ' . Product::find($product['product_id'])?->variant;
    
                    if ($product['unit'] == 'Carton') {
                        $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_carton;
                        if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_carton > $availableQty) {
                            $data->errors()->add(
                                "products.$index.quantity",
                                "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                            );
                        }
                    } elseif ($product['unit'] == 'Box'){
                        $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_box;
                        if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_box > $availableQty) {
                            $data->errors()->add(
                                "products.$index.quantity",
                                "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                            );
                        }
                    } elseif ($product['unit'] == 'Bundle'){
                        $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle;
                        if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_bundle > $availableQty) {
                            $data->errors()->add(
                                "products.$index.quantity",
                                "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                            );
                        }
                    } elseif ($product['unit'] == 'Ladi'){
                        $quantity = $product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi;
                        if ($product['quantity'] * Product::find($product['product_id'])->quantity_per_ladi > $availableQty) {
                            $data->errors()->add(
                                "products.$index.quantity",
                                "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                            );
                        }
                    } else {
                        $quantity = $product['quantity'];
                        if ($product['quantity'] > $availableQty) {
                            $data->errors()->add(
                                "products.$index.quantity",
                                "Requested quantity ({$product['quantity']}) for product {$productName} exceeds available inventory ({$availableQty})."
                            );
                        }
                    }
                }
            });
    
            if ($data->fails()) {
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
    
            $data = $data->validate();
    
            $data['last_updated_by'] = Auth::user()->email;
    
    
            $salesOrder->update($data);
    
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
    
            return redirect()->route('salesorders')->with('success', 'Sales Order updated successfully!');
        }

    // public function sales_order($order_number)
    // {
    //     $salesOrder = SalesOrder::where('order_number', $order_number)->with(['user', 'shop'])->first();
    //     $salesOrder->products = $salesOrder->products_with_details;
    //     $grouped = collect($salesOrder->products)->groupBy('hsn');
    //     $hsnSummary = [];

    //     foreach ($grouped as $hsn => $items) {
    //         $net = 0;
    //         $gstRate = null;
    //         $quantity = 0;

    //         foreach ($items as $item) {
    //             $rate = floatval($item['rate'] ?? 0);
    //             $qty = floatval($item['quantity'] ?? 0);
    //             $gstRate = floatval($item['gst'] ?? 0); // assuming same GST for all items under same HSN

    //             if (isset($item['unit']) && $item['unit'] == 'Carton') {
    //                 $qty = $qty * (Product::find($item['product_id'])->quantity_per_carton ?? 1);
    //             } elseif (isset($item['unit']) && $item['unit'] == 'Box') {
    //                 $qty = $qty * (Product::find($item['product_id'])->quantity_per_box ?? 1);
    //             } elseif (isset($item['unit']) && $item['unit'] == 'Bundle') {
    //                 $qty = $qty * (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
    //             } elseif (isset($item['unit']) && $item['unit'] == 'Ladi') {
    //                 $qty = $qty * (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
    //             }

    //             $net += $rate * $qty;
    //             if(isset($item['cd_per'])){
    //                 $net = $net - ($rate * $qty*$item['cd_per']/100);
    //             }
    //             if(isset($item['td_per'])){
    //                 $net = $net - ($rate * $qty*$item['td_per']/100);
    //             }
    //             $quantity += $qty;
    //         }

    //         $gstAmount = $net - $net * 100 / (100 + $gstRate);
    //         $cgst = $gstAmount / 2;
    //         $sgst = $gstAmount / 2;

    //         $hsnSummary[$hsn] = [
    //             'gross_amount' => round($net - $gstAmount, 2),
    //             'gst_rate' => $gstRate,
    //             'gst_amount' => round($gstAmount, 2),
    //             'cgst' => round($cgst, 2),
    //             'sgst' => round($sgst, 2),
    //             'net_amount' => round($net, 2),
    //             'quantity' => $quantity,
    //         ];
    //     }
    //     $roundOff = $this->customRound(collect($hsnSummary)->sum('net_amount'));

    //     $numberToWords = new NumberToWords();
    //     $amountInWords = ucfirst($numberToWords->getNumberTransformer('en')->toWords($roundOff)) . ' rupees only';

    //     return view('sales-order', compact('salesOrder', 'hsnSummary', 'amountInWords'));
    // }
    
    public function sales_order($order_number)
    {
        $salesOrder = SalesOrder::where('order_number', $order_number)->with(['user', 'shop'])->first();
        $salesOrder->products = $salesOrder->products_with_details;
        $grouped = collect($salesOrder->products)->groupBy('hsn');
        $hsnSummary = [];
        
        foreach ($grouped as $hsn => $items) {
            // Now group again by GST rate inside each HSN
            $gstGrouped = collect($items)->groupBy('gst');
        
            foreach ($gstGrouped as $gstRate => $gstItems) {
                $net = 0;
                $quantity = 0;
        
                foreach ($gstItems as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty = floatval($item['quantity'] ?? 0);
        
                    // handle unit conversions
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

        $roundOff = $this->customRound(collect($hsnSummary)->sum('net_amount'));

        $numberToWords = new NumberToWords();
        $amountInWords = ucfirst($numberToWords->getNumberTransformer('en')->toWords($roundOff)) . ' rupees only';

        return view('sales-order', compact('salesOrder', 'hsnSummary', 'amountInWords'));
    }
    
    

    public function sales_order_pdf($order_number)
    {
        $salesOrder = SalesOrder::where('order_number', $order_number)
            ->with(['user.warehouse', 'shop'])
            ->firstOrFail();
        $salesOrder->products = $salesOrder->products_with_details;
        $grouped = collect($salesOrder->products)->groupBy('hsn');
        $hsnSummary = [];
        
        foreach ($grouped as $hsn => $items) {
            // Now group again by GST rate inside each HSN
            $gstGrouped = collect($items)->groupBy('gst');
        
            foreach ($gstGrouped as $gstRate => $gstItems) {
                $net = 0;
                $quantity = 0;
        
                foreach ($gstItems as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty = floatval($item['quantity'] ?? 0);
        
                    // handle unit conversions
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
        $roundOff = $this->customRound(collect($hsnSummary)->sum('net_amount'));

        $numberToWords = new NumberToWords();
        $amountInWords = ucfirst($numberToWords->getNumberTransformer('en')->toWords($roundOff)) . ' rupees only';

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults(); 
        $fontDirs = $defaultConfig['fontDir']; 
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults(); 
        $fontData = $defaultFontConfig['fontdata'];
        
        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 5,
            'margin_right' => 5,
            'margin_bottom' => 5,
            'margin_left' => 5,
            'tempDir' => public_path('app/mpdf'),
            'fontDir' => array_merge($fontDirs, [ 
                public_path('assets/fonts'), 
            ]), 
            'fontdata' => $fontData + [ 
                'noto' => [ 
                    'R' => 'NotoSansDevanagari-Regular.ttf', 
                    'B' => 'NotoSansDevanagari-Bold.ttf', 
                ], 
            ], 
            'default_font' => 'noto'
        ]);
        $html = view('sales-order-pdf', compact('salesOrder', 'grouped', 'hsnSummary', 'amountInWords'))->render();
        $mpdf->setBasePath(public_path());
        $mpdf->WriteHTML($html);

        $pdfPath = public_path('Sales Orders/sales-order-' . $order_number . '.pdf');

        $mpdf->Output($pdfPath, 'F');

        if (file_exists($pdfPath)) {
            return response()->download($pdfPath)->deleteFileAfterSend(true);
        }

        abort(500, 'PDF generation failed.');
    }

    public function update_salesorder(Request $request)
    {
        \Log::info($request->all());
        $data = $request->validate([
            'id' => 'required|exists:sales_orders,id',
            'action' => 'required|string|in:approved,rejected,cancelled,packed,dispatched,delivery agent,delivered,returned_back,revert_pending,unpack',
            'shipment_weight' => 'required_if:action,dispatched|nullable|string|max:255',
            'delivery_partner' => 'required_if:action,dispatched|nullable|exists:users,id',
            'delivery_employee_id' => 'required_if:action,delivery agent|nullable|exists:users,id',
            'remark' => 'required_if:action,rejected',
        ]);
        
        $data = array_filter($data, function ($value) { return !is_null($value); });

        $user = Auth::user();

        // Restrict actions based on permission
        if ($request->action !== 'cancelled' && $request->action !== 'delivery agent' && $request->action !== 'packed' && $request->action !== 'dispatched' && $request->action !== 'delivered' && $request->action !== 'returned_back' && $request->action !== 'received_back' && $request->action !== 'unpack' && !$user->can('edit salesorder')) {
            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        if ($request->action === 'delivery agent' && !$user->can('assign delivery agent')) {
            return redirect()->back()->with('error', 'You do not have permission to assign delivery agents.');
        }

        if ($request->action === 'packed' && !$user->can('mark packed salesorder')) {
            return redirect()->back()->with('error', 'You do not have permission to mark sales order as packed.');
        }
        
        if ($request->action === 'unpack' && !$user->can('mark packed salesorder')) {
            \Log::info('error1');
            return redirect()->back()->with('error', 'You do not have permission to mark sales order as unpacked.');
        }

        if ($request->action === 'dispatched' && !$user->can('dispatch salesorder')) {
            return redirect()->back()->with('error', 'You do not have permission to dispatch sales orders.');
        }

        if ($request->action === 'delivered' && !$user->can('mark delivered salesorder')) {
            return redirect()->back()->with('error', 'You do not have permission to mark sales order as delivered.');
        }

        if($request->action === 'returned_back' && !$user->can('mark returned back salesorder')){
            return redirect()->back()->with('error', 'You do not have permission to mark order as returned back.');
        }

        if($request->action === 'received_back' && !$user->can('mark received back salesorder')){
            return redirect()->back()->with('error', 'You do not have permission to mark order as received back.');
        }
        
        if($request->action === 'cancelled' && !$user->can('cancel salesorder')){
            return redirect()->back()->with('error', 'You do not have permission to mark order as received back.');
        }
        
        if($request->action === 'revert_pending' && !$user->can('revert salesorder to pending')){
            return redirect()->back()->with('error', 'You do not have permission to revert order as pending.');
        }


        $salesOrder = SalesOrder::find($data['id']);
        $delivery_status = $salesOrder->delivery_status;

        if ($request->action == 'approved') {
            if ($salesOrder->approval_status == 'approved' || $salesOrder->delivery_status == 'delivered') {
                return redirect()->back()->with('error', 'Sales Order already approved or delivered!');
            }
            $data['approval_status'] = 'approved';
        } elseif ($request->action == 'rejected') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered') {
                return redirect()->back()->with('error', 'Sales Order already rejected or delivered!');
            }
            $data['approval_status'] = 'rejected';
            $data['rejection_remark'] = $request->remark;
        } elseif ($request->action == 'cancelled') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, delivered, rejected or not approved!');
            }
            $data['delivery_status'] = 'cancelled';
        } elseif ($request->action == 'packed') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, delivered, rejected or not approved!');
            }
            $data['packaging_status'] = 'packed';
        } elseif ($request->action == 'unpack') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved' || $salesOrder->dispatched) {
                \Log::info('error2');
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, dispatched, delivered, rejected or not approved!');
            }
            $data['packaging_status'] = 'pending';
        } elseif ($request->action == 'dispatched') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, delivered, rejected or not approved!');
            }
            $data['dispatched'] = 1;
            $weight = $request->shipment_weight;
            $data['delivery_charge'] = ceil($weight / 100) * 100;
        } elseif ($request->action == 'delivery agent') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, delivered, rejected or not approved!');
            }
        } elseif ($request->action == 'delivered') {
            if ($salesOrder->approval_status == 'rejected' || $salesOrder->delivery_status == 'delivered' || $salesOrder->delivery_status == 'cancelled' || $salesOrder->approval_status != 'approved') {
                return redirect()->back()->with('error', 'Sales Order is already either cancelled, delivered, rejected or not approved!');
            }
            if(!$salesOrder->delivery_employee_id){
                return redirect()->back()->with('error', 'Assign delivery agent first to deliver order.');
            }
            $data['delivery_status'] = 'delivered';
            if ($salesOrder->delivery_employee_id) {
                $data['delivery_employee_id'] = $salesOrder->delivery_employee_id;
            }
        } elseif($request->action == 'returned_back'){
            if ($salesOrder->returned_back || $salesOrder->delivery_status == 'delivered'){
                return redirect()->back()->with('error', 'Sales Order is already marked as returned back or delivered.');
            }
            $data['returned_back'] = 1;
        } elseif($request->action == 'received_back'){
            if ($salesOrder->received_back || $salesOrder->delivery_status == 'delivered'){
                return redirect()->back()->with('error', 'Sales Order is already marked as received back or delivered.');
            }
            $data['received_back'] = 1;
        } elseif ($request->action == 'revert_pending') {
            if ($salesOrder->delivery_status == 'pending') {
                return redirect()->back()->with('error', 'Order delivery is already pending.');
            }
            if (!$user->can('revert salesorder to pending')) {
                return redirect()->back()->with('error', 'You do not have permission to revert delivered orders.');
            }
            // Restore delivery status
            $data['delivery_status'] = 'pending';
            
            SalesOrderTracking::create([
                'sales_order_id' => $salesOrder->id,
                'checkpoint' => 'Marked delivery status pending',
                'checkpoint_time' => now(),
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email,
            ]);
        }

        $data['last_updated_by'] = Auth::user()->email;



        $salesOrder->update($data);
        $warehouseId = User::find($salesOrder->user_id)->warehouse_id;
        if ($request->action == 'rejected') {
            foreach ($salesOrder->products as $oldProduct) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $oldProduct['product_id'])
                    ->first();
                
                $product = Product::find($oldProduct['product_id']);

                if (isset($oldProduct['unit'])) {
                    switch ($oldProduct['unit']) {
                        case 'Carton':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_carton ?? 1);
                            break;
                        case 'Box':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_box ?? 1);
                            break;
                        case 'Bundle':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_bundle ?? 1);
                            break;
                        case 'Ladi':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_ladi ?? 1);
                            break;
                        default:
                            $convertedQty = $oldProduct['quantity'];
                            break;
                    }
                } else {
                    $convertedQty = $oldProduct['quantity'];
                }
                
                $inventory->available_quantity = $inventory->available_quantity + $convertedQty;
                $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $convertedQty);
                
                $productThreshold = $product?->threshold_limit;
                if ($inventory->available_quantity > $productThreshold) {
                    $inventory->status = 'sufficient_stock';
                }
                
                $inventory->save();
            }
        }
        if ($request->action == 'delivered') {
            foreach ($salesOrder->products as $oldProduct) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $oldProduct['product_id'])
                    ->first();
                    
                switch ($oldProduct['unit']) { 
                    case 'Piece': 
                        $qty = $oldProduct['quantity']; 
                        break; 
                    case 'Box': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_box ?? 1); 
                        break; 
                    case 'Bundle': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_bundle ?? 1); 
                        break; 
                    case 'Ladi': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_ladi ?? 1); 
                        break; 
                    default: // fallback 
                        $qty = $oldProduct['quantity']; 
                        break; 
                }

                $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $qty);

                $productThreshold = Product::find($oldProduct['product_id'])?->threshold_limit;

                if ($inventory->available_quantity > $productThreshold) {
                    $inventory->status = 'sufficient_stock';
                }

                $inventory->save();
            }
        }
        if ($request->action == 'revert_pending' && $delivery_status == 'delivered') {
            // Restore inventory (reverse the deduction done during delivery)
            foreach ($salesOrder->products as $oldProduct) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $oldProduct['product_id'])
                    ->first();
                    
                switch ($oldProduct['unit']) { 
                    case 'Piece': 
                        $qty = $oldProduct['quantity']; 
                        break; 
                    case 'Box': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_box ?? 1); 
                        break; 
                    case 'Bundle': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_bundle ?? 1); 
                        break; 
                    case 'Ladi': 
                        $qty = $oldProduct['quantity'] * ($product->quantity_per_ladi ?? 1); 
                        break; 
                    default: // fallback 
                        $qty = $oldProduct['quantity']; 
                        break; 
                }
        
                $inventory->reserved_quantity += $qty;
        
                // Recalculate stock status
                $productThreshold = Product::find($oldProduct['product_id'])?->threshold_limit;
                if ($inventory->available_quantity > $productThreshold) {
                    $inventory->status = 'sufficient_stock';
                }
        
                $inventory->save();
            }
        }

        $creator = User::withTrashed()->where('email', $salesOrder->created_by)->first();
        if (in_array($request->action, ['approved', 'rejected'])) {
            if (Auth::id() != $creator->id) {
                // Create notification
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Sales Order #{$salesOrder->order_number} {$data['approval_status']}",
                    'body' => "Your sales order has been {$data['approval_status']}.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                // Link to user
                NotificationUser::create([
                    'notification_id' => $notification->id,
                    'user_id' => $creator->id,
                ]);

                // Fire event
                event(new NotificationCreated($notification, $creator->id));

                // $creator->notify(new SalesOrderStatusNotification($salesOrder));
            }

            if ($request->action == 'approved') {
                $warehouse_users = User::where('warehouse_id', $warehouseId)->whereHas('roles', function ($query) {
                    $query->where('name', 'like', '%warehouse%');
                })->get();

                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Sales Order #{$salesOrder->order_number} {$data['approval_status']}",
                    'body' => "Sales order has been {$data['approval_status']}. You can start packaging process and update the status.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);
                foreach ($warehouse_users as $w_user) {

                    // Link to user
                    NotificationUser::create([
                        'notification_id' => $notification->id,
                        'user_id' => $w_user->id,
                    ]);

                    // Fire event
                    event(new NotificationCreated($notification, $w_user->id));

                    // $w_user->notify(new SalesOrderToWarehouse($salesOrder));
                }

                SalesOrderTracking::create([
                    'sales_order_id' => $salesOrder->id,
                    'checkpoint' => 'Order Confirmed',
                    'checkpoint_time' => now(),
                    'created_by' => Auth::user()->email,
                    'last_updated_by' => Auth::user()->email,
                ]);
            } else {
                SalesOrderTracking::create([
                    'sales_order_id' => $salesOrder->id,
                    'checkpoint' => 'Order Rejected',
                    'remarks' => $request->remark,
                    'checkpoint_time' => now(),
                    'created_by' => Auth::user()->email,
                    'last_updated_by' => Auth::user()->email,
                ]);
            }
        } elseif ($request->action == 'cancelled') {
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Delivery Cancelled for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order has been cancelled.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                event(new NotificationCreated($notification, $creator->id));
                // $creator->notify(new SalesOrderDeliveryCancelled($salesOrder));
            }

            SalesOrderTracking::create([
                'sales_order_id' => $salesOrder->id,
                'checkpoint' => 'Delivery Cancelled',
                'remarks' => $request->remarks ?? 'Due to non-availability of shop owner',
                'checkpoint_time' => now(),
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email,
            ]);
        } elseif ($request->action == 'packed') {
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Items Packed for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order item has been packed.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $tracking = SalesOrderTracking::create([
                    'sales_order_id' => $salesOrder->id,
                    'checkpoint' => 'Item Packed',
                    'checkpoint_time' => now(),
                    'created_by' => Auth::user()->email,
                    'last_updated_by' => Auth::user()->email,
                ]);

                event(new NotificationCreated($notification, $creator->id));
            }
        } elseif ($request->action == 'unpack') {
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Items Packed for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order item has been unpacked.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $tracking = SalesOrderTracking::create([
                    'sales_order_id' => $salesOrder->id,
                    'checkpoint' => 'Item UnPacked',
                    'checkpoint_time' => now(),
                    'created_by' => Auth::user()->email,
                    'last_updated_by' => Auth::user()->email,
                ]);

                event(new NotificationCreated($notification, $creator->id));
            }
        } elseif ($request->action == 'dispatched') {
            $tracking = SalesOrderTracking::create([
                'sales_order_id' => $salesOrder->id,
                'checkpoint' => 'Dispatched to Courier',
                'checkpoint_time' => now(),
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email,
            ]);
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Shipment dispatched for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order item has been dispatched to courier.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                event(new NotificationCreated($notification, $creator->id));
            }

            $logisticsManagers = User::whereHas('roles', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%logistics%')
                        ->orWhere('name', 'like', '%delivery%');
                })->where('name', '!=', 'delivery employee');
            })->get();
            $notification = Notification::create([
                'type' => 'sales_order_status',
                'title' => "Shipment received for Sales Order #{$salesOrder->order_number}",
                'body' => "New Shipment is received at courier.",
                'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                'sender_id' => Auth::id(),
            ]);

            foreach ($logisticsManagers as $manager) {

                $notification->users()->attach($manager->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                event(new NotificationCreated($notification, $manager->id));

                // $manager->notify(new SalesOrderToDelivery($salesOrder));
            }
        } elseif ($request->action == 'delivery agent') {
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Delivery Agent assigned for Sales Order #{$salesOrder->order_number}",
                    'body' => "Delivery Person is assigned for delivery.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $tracking = SalesOrderTracking::create([
                    'sales_order_id' => $salesOrder->id,
                    'checkpoint' => 'Delivery Agent Assigned',
                    'checkpoint_time' => now(),
                    'created_by' => Auth::user()->email,
                    'last_updated_by' => Auth::user()->email,
                ]);

                event(new NotificationCreated($notification, $creator->id));
                // $creator->notify(new SalesOrderToDelivery($salesOrder));
            }
        } elseif($request->action == 'returned_back'){
            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Shipment returned back for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order items has been returned back to warehouse.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                event(new NotificationCreated($notification, $creator->id));
            }

            $warehouseManagers = User::whereHas('roles', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%warehouse%');
                });
            })->get();
            
            $notification = Notification::create([
                'type' => 'sales_order_status',
                'title' => "Shipment returned back for Sales Order #{$salesOrder->order_number}",
                'body' => "Sales order items has been returned back to warehouse, please check and verify.",
                'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                'sender_id' => Auth::id(),
            ]);

            foreach ($warehouseManagers as $manager) {

                $notification->users()->attach($manager->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                event(new NotificationCreated($notification, $manager->id));

                // $manager->notify(new SalesOrderBackToWarehouse($salesOrder));
            }
            
            SalesOrderTracking::create([
                'sales_order_id' => $salesOrder->id,
                'checkpoint' => 'Order Returned Back',
                'checkpoint_time' => now(),
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email,
            ]);
        } elseif($request->action == 'received_back'){
            foreach ($salesOrder->products as $oldProduct) {
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('product_id', $oldProduct['product_id'])
                    ->first();
                
                $product = Product::find($oldProduct['product_id']);

                if (isset($oldProduct['unit'])) {
                    switch ($oldProduct['unit']) {
                        case 'Carton':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_carton ?? 1);
                            break;
                        case 'Box':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_box ?? 1);
                            break;
                        case 'Bundle':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_bundle ?? 1);
                            break;
                        case 'Ladi':
                            $convertedQty = $oldProduct['quantity'] * ($product->quantity_per_ladi ?? 1);
                            break;
                        default:
                            $convertedQty = $oldProduct['quantity'];
                            break;
                    }
                } else {
                    $convertedQty = $oldProduct['quantity'];
                }
                
                $inventory->available_quantity = $inventory->available_quantity + $convertedQty;
                $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $convertedQty);
                
                $productThreshold = $product?->threshold_limit;
                if ($inventory->available_quantity > $productThreshold) {
                    $inventory->status = 'sufficient_stock';
                }
                
                $inventory->save();


                // $inventory->available_quantity = $inventory->available_quantity + ((isset($oldProduct['unit']) && $oldProduct['unit'] == 'Carton') ? $oldProduct['quantity']*(Product::find($oldProduct['product_id'])->quantity_per_carton) : $oldProduct['quantity']);
                // $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - ((isset($oldProduct['unit']) && $oldProduct['unit'] == 'Carton') ? $oldProduct['quantity']*(Product::find($oldProduct['product_id'])->quantity_per_carton) : $oldProduct['quantity']));

                // $productThreshold = Product::find($oldProduct['product_id'])?->threshold_limit;

                // if ($inventory->available_quantity > $productThreshold) {
                //     $inventory->status = 'sufficient_stock';
                // }

                // $inventory->save();
            }

            if (Auth::id() != $creator->id) {
                $notification = Notification::create([
                    'type' => 'sales_order_status',
                    'title' => "Shipment marked received back for Sales Order #{$salesOrder->order_number}",
                    'body' => "Your sales order items has been received back at warehouse.",
                    'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
                    'sender_id' => Auth::id(),
                ]);

                $notification->users()->attach($creator->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                event(new NotificationCreated($notification, $creator->id));
            }
            
            SalesOrderTracking::create([
                'sales_order_id' => $salesOrder->id,
                'checkpoint' => 'Order Received Back',
                'checkpoint_time' => now(),
                'created_by' => Auth::user()->email,
                'last_updated_by' => Auth::user()->email,
            ]);
        }
        

        if(Auth::id() == 1 || $request->action == 'packed' || $request->action == 'dispatched'){
            return response()->json([
                'message' => 'Salesorder updated successfully!',
                'order_id' => $salesOrder->id,
                'warehouse_id' => $warehouseId,
                'order' => $salesOrder,
                'partner_name' => $salesOrder->partner?->name,
            ]);
        }
        return redirect()->back()->with('success', 'Sales Order updated successfully!');
    }
    
    public function update_delivery_partner(Request $request){
        
        $data = $request->validate([
            'id' => 'required|exists:sales_orders,id',
            'delivery_partner' => 'required|exists:users,id',
        ]);
        
        $salesOrder = SalesOrder::find($data['id']);
        
        if($salesOrder->delivery_status != 'pending' && !$salesOrder->dispatched){
            return response()->json([
                'message' => 'Sales Order delivered/cancelled or Delivery Partner is not yet assigned.'    
            ]);
        }
        
        $salesOrder->delivery_partner = $data['delivery_partner'];
        $partner_name = User::find($data['delivery_partner'])->name;
        $salesOrder->save();
        return response()->json([
            'message' => 'Sales Order delivery partner updated successfully!.',
            'partner_name' => $partner_name,
            'order_id' => $salesOrder->id,
        ]);
    }
    
    public function unapprove_salesorder($order_number){
        $salesorder = SalesOrder::where('order_number', $order_number)->first();
        if($salesorder){
            if($salesorder->approval_status == 'pending' || $salesorder->packaging_status != 'pending' || $salesorder->delivery_status != 'pending'){
                return redirect()->back()->with('error', "Sales order is already pending, packed or delivery processed. Can't unapprove now");
            }
            
            $warehouseId = User::find($salesorder->user_id)->warehouse_id;
            if ($salesorder->approval_status == 'rejected') { 
                foreach ($salesorder->products as $item) { 
                    $inventory = Inventory::where('warehouse_id', $warehouseId)->where('product_id', $item['product_id'])->first(); 
                    if ($inventory) { 
                        $product = Product::find($item['product_id']);

                        if (isset($item['unit'])) {
                            switch ($item['unit']) {
                                case 'Carton':
                                    $convertedQty = $item['quantity'] * ($product->quantity_per_carton ?? 1);
                                    break;
                                case 'Box':
                                    $convertedQty = $item['quantity'] * ($product->quantity_per_box ?? 1);
                                    break;
                                case 'Bundle':
                                    $convertedQty = $item['quantity'] * ($product->quantity_per_bundle ?? 1);
                                    break;
                                case 'Ladi':
                                    $convertedQty = $item['quantity'] * ($product->quantity_per_ladi ?? 1);
                                    break;
                                default:
                                    $convertedQty = $item['quantity'];
                                    break;
                            }
                        } else {
                            $convertedQty = $item['quantity'];
                        }
                        
                        $inventory->available_quantity = $inventory->available_quantity - $convertedQty;
                        $inventory->reserved_quantity = $inventory->reserved_quantity + $convertedQty;
                        
                        $productThreshold = $product?->threshold_limit;
                        if ($inventory->available_quantity > $productThreshold) {
                            $inventory->status = 'sufficient_stock';
                        }
                        
                        $inventory->save();
                    } 
                } 
            }
            
            $salesorder->approval_status = 'pending';
            $salesorder->save();
            return redirect()->back()->with('success', 'Sales order is now in pending state');    
        }
        return redirect()->back()->with('error', 'No sales order found');
        
    }

    public function delete_salesorder(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:sales_orders,id',
        ]);

        $salesOrder = SalesOrder::find($data['id']);
        
        if ($salesOrder->approval_status !== 'pending') { 
            return redirect()->back()->with('error', 'Only pending sales orders can be deleted.'); 
            
        }
        $warehouseId = $salesOrder->user?->warehouse_id;
        foreach ($salesOrder->products as $oldProduct) { 
            $product = Product::find($oldProduct['product_id']); 
            if (!$product) { 
                continue; 
                
            } 
            $qty = ($oldProduct['unit'] === 'Piece') ? $oldProduct['quantity'] : $oldProduct['quantity'] * $product->quantity_per_carton; 
            $inventory = Inventory::where('warehouse_id', $warehouseId) ->where('product_id', $oldProduct['product_id']) ->first(); 
            if ($inventory) { 
                $inventory->available_quantity += $qty; 
                $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $qty); 
                $productThreshold = $product->threshold_limit; 
                if ($inventory->available_quantity > $productThreshold) { 
                    $inventory->status = 'sufficient_stock'; 
                    
                } 
                $inventory->save(); 
            } 
            
        }
        
        $salesOrder->delete();

        return redirect()->back()->with('success', 'Sales Order deleted successfully!');
    }
    
    public function verify_salesorder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:sales_orders,id',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit' => 'required|in:Piece,Carton,Box,Bundle,Ladi',
            'products.*.received_quantity' => 'required|numeric|min:0',
            'products.*.received_unit' => 'required|in:Piece,Carton,Box,Bundle,Ladi',
        ]);    
        $salesOrder = SalesOrder::find($request->id);
        if ($salesOrder->received_back || $salesOrder->delivery_status == 'delivered'){
            return redirect()->back()->with('error', 'Sales Order is already marked as received back or delivered.');
        }
        $salesOrder->received_back = 1;
        
        $products = is_string($salesOrder->products) ? json_decode($salesOrder->products, true) : $salesOrder->products;
        
        $warehouseId = User::withTrashed()->find($salesOrder->user_id)->warehouse_id;
        foreach ($request->products as $newProduct) {
            $product = Product::find($newProduct['product_id']);
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_id', $newProduct['product_id'])
                ->first();
        
            // Convert ordered quantity to base units
            $orderedQty = $newProduct['quantity'];
            if (($newProduct['unit'] ?? '') === 'Carton') {
                $orderedQty *= ($product->quantity_per_carton ?? 1);
            } elseif (($newProduct['unit'] ?? '') === 'Box') {
                $orderedQty *= ($product->quantity_per_box ?? 1);
            } elseif (($newProduct['unit'] ?? '') === 'Bundle') {
                $orderedQty *= ($product->quantity_per_bundle ?? 1);
            } elseif (($newProduct['unit'] ?? '') === 'Ladi') {
                $orderedQty *= ($product->quantity_per_ladi ?? 1);
            }
        
            // Convert received quantity to base units
            $receivedQty = $newProduct['received_quantity'];
            if (($newProduct['received_unit'] ?? '') === 'Carton') {
                $receivedQty *= ($product->quantity_per_carton ?? 1);
            } elseif (($newProduct['received_unit'] ?? '') === 'Box') {
                $receivedQty *= ($product->quantity_per_box ?? 1);
            } elseif (($newProduct['received_unit'] ?? '') === 'Bundle') {
                $receivedQty *= ($product->quantity_per_bundle ?? 1);
            } elseif (($newProduct['received_unit'] ?? '') === 'Ladi') {
                $receivedQty *= ($product->quantity_per_ladi ?? 1);
            }
        
            // Calculate missing quantity (in original unit)
            $missingQty = $orderedQty - $receivedQty;
            if ($missingQty > 0) {
                foreach ($products as &$oldProduct) { 
                    if ($oldProduct['product_id'] == $newProduct['product_id']) { // Add missing_quantity key 
                        $oldProduct['missing_quantity'] = $missingQty . ' Piece';
                    } 
                }
            }
        
            // Update inventory
            $inventory->available_quantity += $receivedQty;
            $inventory->reserved_quantity = max(0, $inventory->reserved_quantity - $orderedQty);
        
            $productThreshold = $product?->threshold_limit;
            if ($inventory->available_quantity > $productThreshold) {
                $inventory->status = 'sufficient_stock';
            }
            // dd($inventory);
            $inventory->save();
        }
        
        $salesOrder->products = $products;
        $salesOrder->save();

        
        return redirect()->back()->with('success', 'Sales Order updated successfully!');
    }

    public function warehouseSalesOrders(Request $request)
    {
        $user = Auth::user();

        if ($request->filled('date')) { 
            $dates = explode(' - ', $request->date); 
            $filters['start_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[0]))->format('Y-m-d'); 
            $filters['end_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[1]))->format('Y-m-d'); 
        }
        $query = SalesOrder::whereHas('user', function ($q) use ($user) {
                    $q->where('warehouse_id', $user->warehouse_id);
                })->with('user');
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
        } 
        
        $filter = false;
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
            $filter = true;
        }
        
        // Apply other filters (examples) 
        if ($request->filled('approval_status')) { 
            $query->where('approval_status', $request->approval_status); 
            $filter = true;
        } 
        
        if ($request->filled('delivery_status')) { 
            $query->where('delivery_status', $request->delivery_status); 
        } 
        
        if ($request->filled('packaging_status')) { 
            $query->where('packaging_status', $request->packaging_status);
        } 
        
        
        if ($request->filled('sales_person_id')) { 
            $query->where('user_id', $request->sales_person_id);
        } 
            
        if ($request->filled('customer_area')) { 
            $query->whereHas('shop', fn($q) => $q->whereIn('area', $request->customer_area));
            $filter = true;
        }
        
        if ($request->filled('customer_id')) { 
            $query->whereHas('shop', fn($q) => $q->where('id', $request->customer_id));
            $filter = true;
        }
        
        if ($request->filled('customer_village')) { 
            $query->whereHas('shop', fn($q) => $q->where('village', 'like', "%{$request->customer_village}%"));
            $filter = true;
        }
        
        if(!$filter){
            $query->where('created_at', '>=', now()->subDays(3));
            // $query->take(10)->latest();
        }

        $salesOrders = (clone $query)->where('approval_status', 'approved')->where('delivery_status', 'pending')->where('packaging_status', 'pending')->get()
            ->groupBy(function ($order) {
                return $order->user->warehouse_id;
            });
            
        foreach ($salesOrders as $warehouseId => $orders) {
            foreach ($orders as $salesOrder) {
                $log = ChangeLog::where('table_name', 'sales_orders')
                    ->where('row_id', $salesOrder->id)
                    ->where('column_name', 'approval_status')
                    ->where('new_value', 'approved')
                    ->latest()
                    ->first();
        
                $salesOrder->approved_at = optional($log)->created_at;
            }
        }


        $otherOrders = (clone $query)->where('approval_status', 'approved')->where('packaging_status', 'packed')->get()
            ->groupBy(function ($order) {
                return $order->user->warehouse_id;
            });
            
        $partners = User::whereNotNull('warehouse_id')
            ->where('warehouse_id', $user->warehouse_id)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'delivery');
            })
            ->get();

        $warehouses = Warehouse::all()->keyBy('id');
        
        $shops = Shop::all();
        $areas = $shops->pluck('area')->unique();
        
        return view('warehouse-salesorders', compact('salesOrders', 'otherOrders', 'warehouses', 'partners', 'shops', 'areas', 'request'));
    }

    public function deliverySalesOrders(Request $request)
    {
        $user = Auth::user();

        if ($request->filled('date')) { 
            $dates = explode(' - ', $request->date); 
            $filters['start_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[0]))->format('Y-m-d'); 
            $filters['end_date'] = \Carbon\Carbon::createFromFormat('m/d/Y', trim($dates[1]))->format('Y-m-d'); 
        }
        $query = SalesOrder::with(['user', 'deliveryEmployee']);
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
        }
        
        $filter = false;
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('date', [$filters['start_date'], $filters['end_date']]);
            $filter = true;
        }
        
        // Apply other filters (examples) 
        if ($request->filled('approval_status')) { 
            $query->where('approval_status', $request->approval_status); 
            $filter = true;
        } 
        
        if ($request->filled('delivery_status')) { 
            $query->where('delivery_status', $request->delivery_status); 
        } 
        
        if ($request->filled('packaging_status')) { 
            $query->where('packaging_status', $request->packaging_status);
        } 
        
        
        if ($request->filled('sales_person_id')) { 
            $query->where('user_id', $request->sales_person_id);
        } 
            
        if ($request->filled('customer_area')) { 
            $query->whereHas('shop', fn($q) => $q->whereIn('area', $request->customer_area));
            $filter = true;
        }
        
        if ($request->filled('customer_id')) { 
            $query->whereHas('shop', fn($q) => $q->where('id', $request->customer_id));
            $filter = true;
        }
        
        if ($request->filled('customer_village')) { 
            $query->whereHas('shop', fn($q) => $q->where('village', 'like', "%{$request->customer_village}%"));
            $filter = true;
        }
        
        if(!$filter){
            $query->where('created_at', '>=', now()->subDays(5)); 
        }

        $salesOrders = (clone $query)->where('approval_status', 'approved')->where('delivery_status', 'pending')->where('packaging_status', 'packed')->where('dispatched', 1)->where('delivery_partner', $user->id)->get()
            ->groupBy(function ($order) {
                return $order->user->warehouse_id;
            });

        $otherOrders = (clone $query)->whereIn('delivery_status', ['delivered', 'cancelled'])->where('delivery_partner', $user->id)->get()
            ->groupBy(function ($order) {
                return $order->user->warehouse_id;
            });

        $warehouses = Warehouse::all()->keyBy('id');
        $deliveryAgents = User::role('delivery employee')->get();

        foreach ($salesOrders as $warehouseId => $orders) {
            foreach ($orders as $order) {
                $todayStatus = $order->trackings
                    ->filter(function ($tracking) {
                        return in_array($tracking->checkpoint, ['Delivered', 'Failed']) &&
                            Carbon::parse($tracking->checkpoint_time)->isToday();
                    })
                    ->sortByDesc('checkpoint_time')
                    ->first();
                
                $dStatus = $order->trackings
                    ->filter(function ($tracking) {
                        return in_array($tracking->checkpoint, ['Delivered', 'Failed']);
                    })
                    ->sortByDesc('checkpoint_time')
                    ->first();

                $order->status_today = $todayStatus->checkpoint ?? null;
                $order->dStatus = $dStatus->checkpoint ?? null;
            }
        }

        foreach ($otherOrders as $warehouseId => $orders) {
            foreach ($orders as $order) {
                $todayStatus = $order->trackings
                    ->filter(function ($tracking) {
                        return in_array($tracking->checkpoint, ['Delivered', 'Failed']) &&
                            Carbon::parse($tracking->checkpoint_time)->isToday();
                    })
                    ->sortByDesc('checkpoint_time')
                    ->first();

                $order->status_today = $todayStatus->checkpoint ?? null;
            }
        }
        $shops = Shop::all();
        $areas = $shops->pluck('area')->unique();

        return view('delivery-salesorders', compact('salesOrders', 'otherOrders', 'warehouses', 'deliveryAgents', 'shops', 'areas', 'request'));
    }

    public function generateDeliveryReport(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $user = Auth::user();
        $orders = SalesOrder::with(['user', 'shop', 'deliveryEmployee', 'latestTracking', 'report'])->whereHas('trackings', function ($query) use ($request) {
            $query->where('checkpoint', 'Dispatched to Courier')
                ->whereDate('checkpoint_time', Carbon::parse($request->date)->toDateString());
        })->where('delivery_partner', $user->id)->get();

        foreach ($orders as $order) {
            $report = DailyDeliveryReport::firstOrNew([
                'sales_order_id' => $order->id,
                'date' => Carbon::parse($request->date)->toDateString()
            ]);

            $report->user_id = Auth::id();
            $report->shop_id = $order->shop_id;
            $report->status = $order->delivery_status;
            $report->shipment_weight = $order->shipment_weight;
            $products = $order->products;
            $net = collect($products)->sum(function ($item) {
                $product = Product::find($item['product_id']);
            
                // decide conversion factor based on unit
                switch ($item['unit']) {
                    case 'Carton':
                        $factor = $product->quantity_per_carton;
                        break;
                    case 'Ladi':
                        $factor = $product->quantity_per_ladi;
                        break;
                    case 'Box':
                        $factor = $product->quantity_per_box;
                        break;
                    case 'Bundle':
                        $factor = $product->quantity_per_bundle;
                        break;
                    default:
                        $factor = 1; // fallback if unit not recognized
                }
            
                $convertedQuantity = $item['quantity'] * $factor;
            
                return $item['rate'] * $convertedQuantity;
            });

            if(($report->approval_status && $report->approval_status == 'pending') || !$report->approval_status){
                $report->delivery_charge = (($order->latestTracking->checkpoint == 'Delivered' || $order->latestTracking->checkpoint == 'Failed') && $order->latestTracking->proof_of_delivery)
                    ? (float) $order->delivery_charge
                    : 0 - (0.1 * (float) $net);
            }elseif($report->approval_status && $report->approval_status == 'approved'){
                $report->delivery_charge = (float) $order->delivery_charge;
            }elseif($report->approval_status && $report->approval_status == 'declined'){
                $report->delivery_charge = 0 - (0.1 * (float) $net);
            }elseif($report->approval_status && $report->approval_status == 'closed'){
                $report->delivery_charge = 0;
            }
            
            $failedProof = $order->trackings() ->where('checkpoint', 'Failed') ->whereNotNull('proof_of_delivery') ->latest('checkpoint_time') ->value('proof_of_delivery');

            $report->last_activity_at = $order->latestTracking->checkpoint_time;
            $report->proof_of_delivery = $order->latestTracking->proof_of_delivery ?? $failedProof;
            $report->remarks = $order->latestTracking->checkpoint . ' - ' . $order->latestTracking->remarks;

            $report->save();
            $order->load('report');
        }


        return view('eod-report-preview', compact('orders', 'request'));
    }

    public function viewDeliveryReport(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $query = DailyDeliveryReport::with([
                'user',
                'shop',
                'salesOrder.latestTracking'
            ])
            ->where('date', $request->date)
            ->where('sent', 1);
        if($request->user){
            $reports = $query->where('user_id', $request->user)->get();
        }else{
            $reports = $query->get();
        }
        
        foreach ($reports as $report) { 
            $order = $report->salesOrder; 
            if ($order && is_array($order->products)) { 
                $net = collect($order->products)->sum(function ($item) { 
                    $product = Product::find($item['product_id']); 
                    switch ($item['unit']) { 
                        case 'Carton': 
                            $factor = $product->quantity_per_carton; 
                            break; 
                        case 'Ladi': 
                            $factor = $product->quantity_per_ladi; 
                            break; 
                        case 'Box': 
                            $factor = $product->quantity_per_box; 
                            break; 
                        case 'Bundle': 
                            $factor = $product->quantity_per_bundle; 
                            break; 
                        default: $factor = 1; 
                        
                    } 
                    $convertedQuantity = $item['quantity'] * $factor; 
                    return $item['rate'] * $convertedQuantity; 
                    
                }); 
                $report->bill_amount = round($net); 
                
            } else { 
                $report->bill_amount = 0; 
                
            } 
            
        }

        return view('delivery-report', compact('reports', 'request'));
    }

    public function sendEODReport($date){
        $user = Auth::user();
        $orders = SalesOrder::with(['user', 'shop', 'deliveryEmployee', 'latestTracking'])->whereHas('trackings', function ($query) use ($date) {
            $query->where('checkpoint', 'Dispatched to Courier')
                ->whereDate('checkpoint_time', Carbon::parse($date)->toDateString());
        })->where('delivery_partner', $user->id)->get();

        $reports = DailyDeliveryReport::where('date', $date)->where('user_id', $user->id)->get();
        $pdf = PDF::loadView('report-pdf', [
            'orders' => $reports,
            'date' => $date
        ]);
        foreach($reports as $report){
            $report->sent = 1;
            $report->save();
        }
        $accountsDepartmentIds = Department::where('name', 'like', '%account%')->pluck('id');
        $accountsUsers = User::whereIn('department_id', $accountsDepartmentIds)->get();

        $filename = 'eod-report-' . $user->id . '_' . Carbon::parse($date)->format('Y-m-d') . '.pdf';
        $path = public_path("app/temp/{$filename}");
        $pdf->save($path);

        $notification = Notification::create([
            'type' => 'eod_report',
            'title' => "Delivery EOD Report.",
            'body' => "Delivery EOD Report submitted for date {$date}, please verify and approve.",
            // 'data' => ['order_id' => $salesOrder->id, 'link' => Route('sales-order', $salesOrder->order_number)],
            'sender_id' => Auth::id(),
        ]);
        foreach ($accountsUsers as $user) {

            $notification->users()->attach($user->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new NotificationCreated($notification, $user->id));
            if ($user->device_token) {
                $this->sendNotification($user->device_token, $notification['title'], $notification['body'], $notification->toArray());
            }
            
            // $user->notify(new EODReport($date, Auth::user()));
        }

        DeleteTempFile::dispatch($path)->delay(now()->addMinutes(10));


        return $pdf->download('report.pdf');
    }
    
    public function downloadEODReport(Request $request){
        $date = $request->date;

        $query = DailyDeliveryReport::where('date', $date);
        if($request->user_id){
            $query->where('user_id', $request->user_id);
        }
        $reports = $query->get();
        
        $rows = $reports->map(function ($order) { 
            if ($order->salesOrder && is_array($order->salesOrder->products)) { 
                $net = collect($order->salesOrder->products)->sum(function ($item) { 
                    $product = Product::find($item['product_id']); 
                    switch ($item['unit']) { 
                        case 'Carton': 
                            $factor = $product->quantity_per_carton; 
                            break; 
                        case 'Ladi': 
                            $factor = $product->quantity_per_ladi; 
                            break; 
                        case 'Box': 
                            $factor = $product->quantity_per_box; 
                            break; 
                        case 'Bundle': 
                            $factor = $product->quantity_per_bundle; 
                            break; 
                        default: $factor = 1; 
                        
                    } 
                    $convertedQuantity = $item['quantity'] * $factor; 
                    return $item['rate'] * $convertedQuantity; 
                    
                }); 
                $bill_amount = round($net); 
                
            } else { 
                $bill_amount = 0; 
                
            } 
            
            return [ 
                'Order ID' => $order->salesOrder->order_number,
                'Customer' => $order->shop->name,
                'Shipment Weight' => $order->shipment_weight . ' KG',
                'Delivery Charge' => $order->delivery_charge,
                'Status' => $order->status,
                'Checkpoint Time' => \Carbon\Carbon::parse($order->last_activity_at)->format('d M Y H:i:s'),
                'Proof' => $order->proof_of_delivery ? asset($order->proof_of_delivery) : 'N/A',
                'Remarks' => $order->remarks,
                'Approval Status' => $order->approval_status,
                'Billing Amount' => $this->customRound($bill_amount),
            ]; 
        });
        
        $rows->push([ 
            'Order ID' => '', 
            'Customer' => '', 
            'Shipment Weight' => '', 
            'Delivery Charge' => 'Rs. ' . $this->customRound($reports->sum('delivery_charge')), 
            'Status' => '', 
            'Checkpoint Time' => '', 
            'Proof' => '', 
            'Remarks' => '', 
            'Approval Status' => '',
            'Billing Amount' => ''
        ]);
        
        return Excel::download( new class($rows) implements FromCollection, WithHeadings { protected $data; public function __construct(Collection $data) { $this->data = $data; } public function collection() { return $this->data; } public function headings(): array { return [ 'Order ID', 'Customer', 'Shipment Weight', 'Delivery Charge', 'Status', 'Checkpoint Time', 'Proof', 'Remarks', 'Approval Status', 'Billing Amount']; } }, 'eod_report.xlsx' );
        
        // $pdf = PDF::loadView('report-pdf', [
        //     'orders' => $reports,
        //     'date' => $date
        // ]);
        
        // return $pdf->download('report.pdf');
    }

    public function eodReports(){
        if (Auth::user()->hasRole('delivery')) {
            $reports = DailyDeliveryReport::with(['user', 'shop', 'salesOrder.latestTracking'])->where('user_id', Auth::id())->where('sent', 1)->latest()->get()->groupBy('date');
        }else{
            $reports = DailyDeliveryReport::with(['user', 'shop', 'salesOrder.latestTracking'])->where('sent', 1)->latest()->get()->groupBy('date');
        }
        
        $reportsByUser = $reports->map(function ($dateGroup) {
            return $dateGroup->groupBy('user_id');
        });
        
        return view('eod-reports', compact('reports', 'reportsByUser'));
    }

    public function updateReportStatus(Request $request){
        $request->validate([
            'id' => 'required|exists:daily_delivery_reports,id',
            'status' => 'required|in:approve,decline,closed'
        ]);
        
        $report = DailyDeliveryReport::find($request->id);
        $order = SalesOrder::find($report->sales_order_id);
        $products = $order->products;
        $net = collect($products)->sum(function ($item) {
            $product = Product::find($item['product_id']);
                
            // decide conversion factor based on unit
            switch ($item['unit']) {
                case 'Carton':
                    $factor = $product->quantity_per_carton;
                    break;
                case 'Ladi':
                    $factor = $product->quantity_per_ladi;
                    break;
                case 'Box':
                    $factor = $product->quantity_per_box;
                    break;
                case 'Bundle':
                    $factor = $product->quantity_per_bundle;
                    break;
                default:
                    $factor = 1; // fallback if unit not recognized
            }
        
            $convertedQuantity = $item['quantity'] * $factor;
        
            return $item['rate'] * $convertedQuantity;
        });

        if($request->status == 'approve'){
            $report->approval_status = 'approved';
            if($report->delivery_charge != $order->delivery_charge){
                $report->delivery_charge = $order->delivery_charge;
            }
        }elseif($request->status == 'decline'){
            $report->approval_status = 'declined';
            if($report->delivery_charge == $order->delivery_charge){
                $report->delivery_charge = 0 - (0.1 * (float) $net);
            }
        } elseif($request->status == 'closed'){
            $report->approval_status = 'closed';
            $report->delivery_charge = 0;
        }
        $report->save();

        $deliveryDepartmentIds = Department::where('name', 'like', '%logistic%')->orWhere('name', 'like', '%delivery%')->pluck('id');
        $deliveryUsers = User::whereIn('department_id', $deliveryDepartmentIds)
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'delivery employee');
            })
            ->get();
        
        $date = Carbon::parse($report->date)->format('d M Y');

        $notification = Notification::create([
            'type' => 'eod_report',
            'title' => "Report status updated.",
            'body' => "Delivery Report for order {$order->order_number} on date {$date} is {$report->approval_status}.",
            'sender_id' => Auth::id(),
        ]);
        foreach ($deliveryUsers as $user) {

            $notification->users()->attach($user->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new NotificationCreated($notification, $user->id));
            if ($user->device_token) {
                $this->sendNotification($user->device_token, $notification['title'], $notification['body'], $notification->toArray());
            }
            
            if($request->status == 'decline'){
                // $user->notify(new ReportUpdate($report));
            }
        }

        // return redirect()->back()->with('success', 'Report updated successfully!');
        
        return response()->json([ 'success' => true, 'message' => 'Report updated successfully!', 'report' => [ 'id' => $report->id, 'approval_status' => $report->approval_status, 'delivery_charge' => $report->delivery_charge, 'order_number' => $order->order_number, 'date' => $date, ] ]);


    }
    
    public function warehouseReports(){
        $reports = WarehouseReport::with(['warehouse', 'user'])->latest()->get();
        return view('warehouse-reports', compact('reports'));
    }
    
    public function addWarehouseReport(Request $request){
        $data = $request->validate([
            'date' => 'required',
            'shift' => 'required',
            'stock_in' => 'required',
            'stock_out' => 'required',
            'closing_stock' => 'required',
            'vehicle_in' => 'required',
            'vehicle_out' => 'required',
            'first_vehicle_in' => 'required',
            'last_vehicle_out' => 'required',
            'overstays' => 'required',
            'total_purchase_amount' => 'required',
            'total_sales_amount' => 'required',
            'total_deliveries_completed' => 'required',
            'dispatch_orders' => 'required',
            'employees_present' => 'required',
            'loading_unloading' => 'required',
            'drivers' => 'required',
            'vehicle_driver_info' => 'required|array|min:1',
            'vehicle_driver_info.*.driver_name' => 'required',
            'vehicle_driver_info.*.vehicle_no' => 'required',
            'vehicle_driver_info.*.mobile_no' => 'required',
            'vehicle_driver_info.*.route' => 'required',
            'vehicle_driver_info.*.deliveries' => 'required',
            'vehicle_driver_info.*.status' => 'required',
            'notes' => 'nullable',
        ]);
        
        $data['vehicle_driver_info'] = json_encode($request->vehicle_driver_info);
        
        $data['prepared_by'] = Auth::id();
        $data['warehouse_id'] = Auth::user()->warehouse_id ?? 1;

        // Create the purchase order
        $warehouseReport = WarehouseReport::create($data);
        
        $received_back_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->where('received_back')->whereDate('updated_at', $request->date)->get();
        $received_back_count = $received_back_orders->count();
        $received_back_amount = 0;
        foreach ($received_back_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
        
                    $received_back_amount += round($net, 2); // accumulate net amount
                }
            }
        }
        
        $returned_back_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->where('returned_back')->whereNot('received_back')->whereDate('updated_at', $request->date)->get();
        $returned_back_count = $returned_back_orders->count();
        $returned_back_amount = 0;
        foreach ($returned_back_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
                    
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
        
                    $returned_back_amount += round($net, 2); // accumulate net amount
                }
            }
        }
        
        
        $pdf = Pdf::loadView('warehouse-report-pdf', ['report' => $warehouseReport, 'received_back_amount' => $received_back_amount, 'received_back_count' => $received_back_count, 'returned_back_amount' => $returned_back_amount, 'returned_back_count' => $returned_back_count]);

        // Define file path
        $fileName = 'WarehouseReport_' . $warehouseReport->id . '.pdf';
        $filePath = public_path('WarehouseReports/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(public_path('WarehouseReports'))) {
            mkdir(public_path('WarehouseReports'), 0777, true);
        }
        
        // Save PDF to public path
        $pdf->save($filePath);

        return redirect()->route('warehouse-reports')->with('success', 'Warehouse Report created successfully!');
    }
    
    public function editWarehouseReport(Request $request){
        $data = $request->validate([
            'id' => 'required|exists:warehouse_reports,id',
            'date' => 'required',
            'shift' => 'required',
            'stock_in' => 'required',
            'stock_out' => 'required',
            'closing_stock' => 'required',
            'vehicle_in' => 'required',
            'vehicle_out' => 'required',
            'first_vehicle_in' => 'required',
            'last_vehicle_out' => 'required',
            'overstays' => 'required',
            'total_purchase_amount' => 'required',
            'total_sales_amount' => 'required',
            'total_deliveries_completed' => 'required',
            'dispatch_orders' => 'required',
            'employees_present' => 'required',
            'loading_unloading' => 'required',
            'drivers' => 'required',
            'vehicle_driver_info' => 'required|array|min:1',
            'vehicle_driver_info.*.driver_name' => 'required',
            'vehicle_driver_info.*.vehicle_no' => 'required',
            'vehicle_driver_info.*.mobile_no' => 'required',
            'vehicle_driver_info.*.route' => 'required',
            'vehicle_driver_info.*.deliveries' => 'required',
            'vehicle_driver_info.*.status' => 'required',
            'notes' => 'nullable',
        ]);
        
        // dd($request->all());
        $data['vehicle_driver_info'] = json_encode($request->vehicle_driver_info);

        // Create the purchase order
        $warehouseReport = WarehouseReport::find($request->id);
        
        $warehouseReport->update($data);
        
        $received_back_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->where('received_back')->whereDate('updated_at', $request->date)->get();
        $received_back_count = $received_back_orders->count();
        $received_back_amount = 0;
        foreach ($received_back_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
        
                    $received_back_amount += round($net, 2); // accumulate net amount
                }
            }
        }
        
        $returned_back_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->where('returned_back')->whereNot('received_back')->whereDate('updated_at', $request->date)->get();
        $returned_back_count = $returned_back_orders->count();
        $returned_back_amount = 0;
        foreach ($returned_back_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
                    
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
        
                    $returned_back_amount += round($net, 2); // accumulate net amount
                }
            }
        }
        
        $pdf = Pdf::loadView('warehouse-report-pdf', ['report' => $warehouseReport, 'received_back_amount' => $received_back_amount, 'received_back_count' => $received_back_count, 'returned_back_amount' => $returned_back_amount, 'returned_back_count' => $returned_back_count]);

        // Define file path
        $fileName = 'WarehouseReport_' . $warehouseReport->id . '.pdf';
        $filePath = public_path('WarehouseReports/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(public_path('WarehouseReports'))) {
            mkdir(public_path('WarehouseReports'), 0777, true);
        }
        
        // Save PDF to public path
        $pdf->save($filePath);

        return redirect()->route('warehouse-reports')->with('success', 'Warehouse Report updated successfully!');
    }
    
    public function fetch(Request $request)
    {
        $date = $request->query('date');
        
        
        $data = [];
        
        $purchase_orders = PurchaseOrder::where('warehouse_id', Auth::user()->warehouse_id)->where('delivery_status', 'delivered')->whereDate('updated_at', $date)->get();
        
        $stock_in = 0;
        $purchase_amount = 0;

        foreach ($purchase_orders as $order) {
            // Decode products JSON into array
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $product) {
                    $stock_in += $product['received_quantity'] ?? 0;
                    $purchase_amount += ($product['received_quantity'] * $product['rate']) ?? 0;
                }
            }
        }
        
        $data['stock_in'] = $stock_in;
        $data['purchase_amount'] = $purchase_amount;
        
        $sales_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->whereHas('trackings', function ($query) use ($date) { 
                $query->where('checkpoint', 'Dispatched to Courier')->whereDate('checkpoint_time', $date);
            })
            ->get();
        
        $total_sales_amount = 0;
        $stock_out = 0;
        
        foreach ($sales_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
                    // Add to stock_out
                    $stock_out += $qty;
                    \Log::info('Product Name:' . Product::find($item['product_id'])->name);
                    \Log::info($qty);
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
        
                    $total_sales_amount += round($net, 2); // accumulate net amount
                }
            }
        }
        
        $data['stock_out'] = $stock_out;
        $data['sales_amount'] = $total_sales_amount;
        $data['deliveries'] = SalesOrder::whereHas('user', function ($query) {
                    $query->where('warehouse_id', Auth::user()->warehouse_id);
                })->whereDate('updated_at', $date)->where('delivery_status', 'delivered')
                ->count();
        $data['dispatch_orders'] = count($sales_orders);
        
        if(today()->format('Y-m-d') == $date){
            $closing_stock = Inventory::where('warehouse_id', Auth::user()->warehouse_id)->sum('available_quantity');
        }else{
            $closing_stock = null;
        }
        
        $data['closing_stock'] = $closing_stock;
    
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    
    }
    
    public function productwise_stock_out(Request $request){
        $request->validate([
            'date' => 'required',    
        ]);
        $date = $request->date;
        
        $sales_orders = SalesOrder::whereHas('user', function ($query) {
                $query->where('warehouse_id', Auth::user()->warehouse_id);
            })->whereHas('trackings', function ($query) use ($date) { 
                $query->where('checkpoint', 'Dispatched to Courier')->whereDate('checkpoint_time', $date);
            })
            ->get();
            
        $productReport = [];

        foreach ($sales_orders as $order) {
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    $product = Product::find($item['product_id']);
        
                    // Unit conversions
                    switch ($item['unit'] ?? '') {
                        case 'Carton':
                            $qty *= $product->quantity_per_carton ?? 1;
                            break;
                        case 'Box':
                            $qty *= $product->quantity_per_box ?? 1;
                            break;
                        case 'Bundle':
                            $qty *= $product->quantity_per_bundle ?? 1;
                            break;
                        case 'Ladi':
                            $qty *= $product->quantity_per_ladi ?? 1;
                            break;
                    }
        
                    // Net amount
                    $net = $rate * $qty;
                    $cdPer = floatval($item['cd_per'] ?? 0);
                    $tdPer = floatval($item['td_per'] ?? 0);
                    if ($cdPer > 0) $net -= ($net * $cdPer / 100);
                    if ($tdPer > 0) $net -= ($net * $tdPer / 100);
        
                    // Accumulate product‑wise
                    if (!isset($productReport[$product->id])) {
                        $productReport[$product->id] = [
                            'product_name' => $product->name,
                            'product_category' => $product->category->name,
                            'product_weight' => $product->weight,
                            'total_qty'    => 0,
                            'qty_in_carton' => '',
                            'total_amount' => 0,
                        ];
                    }
        
                    $productReport[$product->id]['total_qty']    += $qty;
                    $productReport[$product->id]['total_amount'] += round($net, 2);
                    
                    $perCarton = $product->quantity_per_carton ?? 1; 
                    $cartons = intdiv($productReport[$product->id]['total_qty'], $perCarton); 
                    $pieces = $productReport[$product->id]['total_qty'] % $perCarton; 
                    $productReport[$product->id]['qty_in_carton'] = "{$cartons} Carton(s) {$pieces} Piece(s)";
                }
            }
        }

        $collection = collect($productReport)->values(); 
        
        return Excel::download(new class($collection) implements FromCollection, WithHeadings { 
            protected $data; 
            public function __construct(Collection $data) { 
                $this->data = $data; 
                
            } 
            public function collection() { 
                return $this->data; 
                
            } 
            public function headings(): array { 
                return ['Product Name', 'Product Category', 'Product Weight', 'Total Quantity', 'Quantity in Carton', 'Total Amount']; 
                
            } 
            
        }, 'product_report.xlsx');
        
    }
    
    public function productwise_return_stock(Request $request){
        $request->validate([
            'date' => 'required',    
        ]);
        $date = $request->date;
        
        $ids = \DB::table('change_logs')->where('table_name', 'sales_orders')->whereDate('created_at', $date)->where('column_name', 'received_back')->where('new_value', '1')->pluck('row_id');
        // $sales_orders = SalesOrder::whereHas('user', function ($query) {
        //         $query->where('warehouse_id', Auth::user()->warehouse_id);
        //     })->whereHas('trackings', function ($query) use ($date) { 
        //         $query->where('checkpoint', 'Order Received Back')->whereDate('checkpoint_time', $date);
        //     })
        //     ->get();
        
        $sales_orders = SalesOrder::whereIn('id', $ids)->get();
            
        $productReport = [];

        foreach ($sales_orders as $order) {
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    $product = Product::find($item['product_id']);
        
                    // Unit conversions
                    switch ($item['unit'] ?? '') {
                        case 'Carton':
                            $qty *= $product->quantity_per_carton ?? 1;
                            break;
                        case 'Box':
                            $qty *= $product->quantity_per_box ?? 1;
                            break;
                        case 'Bundle':
                            $qty *= $product->quantity_per_bundle ?? 1;
                            break;
                        case 'Ladi':
                            $qty *= $product->quantity_per_ladi ?? 1;
                            break;
                    }
        
                    // Net amount
                    $net = $rate * $qty;
                    $cdPer = floatval($item['cd_per'] ?? 0);
                    $tdPer = floatval($item['td_per'] ?? 0);
                    if ($cdPer > 0) $net -= ($net * $cdPer / 100);
                    if ($tdPer > 0) $net -= ($net * $tdPer / 100);
        
                    // Accumulate product‑wise
                    if (!isset($productReport[$product->id])) {
                        $productReport[$product->id] = [
                            'product_name' => $product->name,
                            'product_weight' => $product->weight,
                            'total_qty'    => 0,
                            'qty_in_carton' => '',
                            'total_amount' => 0,
                        ];
                    }
        
                    $productReport[$product->id]['total_qty']    += $qty;
                    $productReport[$product->id]['total_amount'] += round($net, 2);
                    
                    $perCarton = $product->quantity_per_carton ?? 1; 
                    $cartons = intdiv($productReport[$product->id]['total_qty'], $perCarton); 
                    $pieces = $productReport[$product->id]['total_qty'] % $perCarton; 
                    $productReport[$product->id]['qty_in_carton'] = "{$cartons} Carton(s) {$pieces} Piece(s)";
                }
            }
        }

        $collection = collect($productReport)->values(); 
        
        return Excel::download(new class($collection) implements FromCollection, WithHeadings { 
            protected $data; 
            public function __construct(Collection $data) { 
                $this->data = $data; 
                
            } 
            public function collection() { 
                return $this->data; 
                
            } 
            public function headings(): array { 
                return ['Product Name', 'Product Weight', 'Total Quantity', 'Quantity in Carton', 'Total Amount']; 
                
            } 
            
        }, 'return_product_report.xlsx');
        
    }
    
    public function ledgers(){
        if (Auth::user()->hasRole('delivery')) { 
            $ledgers = Ledger::with('user')->where('prepared_by', Auth::id())->orderBy('date')->get();
        }else{
            $ledgers = Ledger::with('user')->orderBy('date')->get();
        }
        
        return view('ledgers', compact('ledgers'));
    }
    
    public function addLedger(Request $request){
        $data = $request->validate([
            'date' => 'required',
            'collected_amount' => 'required'
        ]);
        
        $data['prepared_by'] = Auth::id();
        
        $report = Ledger::where('date', $request->date)->where('prepared_by', Auth::id())->first();
        if($report){
            $report->update($data);
        }else{
            $report = Ledger::create($data);
        }
        
        $sales_orders = SalesOrder::with(['report'])->where('date', $request->date)->where('delivery_partner', Auth::id())->get();
        
        $total_orders = $sales_orders->count(); 
        $failed_orders_count = 0; 
        $cancelled_orders_count = 0; 
        $delivered_orders_count = 0; 
        $delivery_charge_orders_count = 0; 
        $penalty_orders_count = 0;
        $return_orders_count = 0;
        $closed_orders_count = 0;
        
        $total_sales_amount = 0;
        $failed_amount = 0;
        $cancelled_amount = 0;
        $delivered_amount = 0;
        $delivery_charge = 0;
        $penalty = 0;
        $returns = 0;
        
        foreach ($sales_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
                    
                    $total_sales_amount += $net; // accumulate net amount
                    if($order->delivery_status == 'pending'){
                        $failed_amount += $net;
                    }elseif ($order->delivery_status == 'cancelled'){
                        $cancelled_amount += $net;
                    }elseif ($order->delivery_status == 'delivered'){
                        $delivered_amount += $net;
                    }
                    
                    if($order->returned_back){
                        $returns += $net;
                    }
        
                }
            }
            
            $total_sales_amount = round($total_sales_amount);
            $failed_amount = round($failed_amount);
            $cancelled_amount = round($cancelled_amount);
            $delivered_amount = round($delivered_amount);
            $returns = round($returns);
            
            if($order->delivery_status == 'pending'){
                $failed_orders_count++;
            }elseif ($order->delivery_status == 'cancelled'){
                $cancelled_orders_count++;
            }elseif ($order->delivery_status == 'delivered'){
                $delivered_orders_count++;
            }
            
            if($order->returned_back){
                $return_orders_count++;
            }
            
            if($order->report){
                if($order->report->delivery_charge > 0){
                    $delivery_charge += $order->report->delivery_charge;
                    $delivery_charge_orders_count++;
                }elseif($order->report->delivery_charge == 0){
                    $closed_orders_count++;
                }else{
                    $penalty += $order->report->delivery_charge;
                    $penalty_orders_count++;
                }
            }
        }
        
        // Generate PDF 
        $pdf = Pdf::loadView('ledger_pdf', [ 
            'date' => $request->date, 
            'prepared_by' => Auth::user()->name, 
            'total_sales_amount' => $this->customRound($total_sales_amount), 
            'failed_amount' => $this->customRound($failed_amount), 
            'cancelled_amount' => $this->customRound($cancelled_amount), 
            'delivered_amount' => $this->customRound($delivered_amount), 
            'delivery_charge' => $this->customRound($delivery_charge), 
            'penalty' => $this->customRound($penalty), 
            'returns' => $this->customRound($returns),
            'collected_amount' => $request->collected_amount,
            'total_orders' => $total_orders, 
            'failed_orders_count' => $failed_orders_count, 
            'cancelled_orders_count' => $cancelled_orders_count, 
            'delivered_orders_count' => $delivered_orders_count, 
            'delivery_charge_orders_count' => $delivery_charge_orders_count, 
            'penalty_orders_count' => $penalty_orders_count,
            'return_orders_count' => $return_orders_count,
            'closed_orders_count' => $closed_orders_count,
        ]); 
        
        // Define file path
        $fileName = 'Ledger_' . $report->id . '.pdf';
        $filePath = public_path('Ledger/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(public_path('Ledger'))) {
            mkdir(public_path('Ledger'), 0777, true);
        }
        
        // Save PDF to public path
        $pdf->save($filePath);
        
        return redirect()->route('ledgers')->with('success', 'Ledger created successfully!');
        
        
    }
    
    function customRound($amount) {
        $decimal = $amount - floor($amount);
        if ($decimal >= 0.50) {
            return ceil($amount); // round up to next integer
        }
        return round($amount, 2); // keep two decimals
    }
    
    public function editLedger(Request $request){
        $data = $request->validate([
            'id' => 'required|exists:ledgers,id',
            'date' => 'required',
            'collected_amount' => 'required'
        ]);
        
        $report = Ledger::find($request->id);
        if($report){
            $report->update($data);
        }else{
            return redirect()->back()->with('error', 'Ledger not found');
        }
        
        $sales_orders = SalesOrder::with(['report'])->where('date', $request->date)->where('delivery_partner', $report->prepared_by)->get();
        
        
        
        $total_orders = $sales_orders->count(); 
        $failed_orders_count = 0; 
        $cancelled_orders_count = 0; 
        $delivered_orders_count = 0; 
        $delivery_charge_orders_count = 0; 
        $penalty_orders_count = 0;
        $return_orders_count = 0;
        $closed_orders_count = 0;
        
        $total_sales_amount = 0;
        $failed_amount = 0;
        $cancelled_amount = 0;
        $delivered_amount = 0;
        $delivery_charge = 0;
        $penalty = 0;
        $returns = 0;
        
        foreach ($sales_orders as $order) {
            // Use products_with_details if available
            $products = is_string($order->products) 
                ? json_decode($order->products, true) 
                : $order->products;
        
            if (is_array($products)) {
                foreach ($products as $item) {
                    $rate = floatval($item['rate'] ?? 0);
                    $qty  = floatval($item['quantity'] ?? 0);
        
                    // Unit conversions
                    if (($item['unit'] ?? '') === 'Carton') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_carton ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Box') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_box ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Bundle') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_bundle ?? 1);
                    } elseif (($item['unit'] ?? '') === 'Ladi') {
                        $qty *= (Product::find($item['product_id'])->quantity_per_ladi ?? 1);
                    }
        
        
                    // Net amount (rate × qty)
                    $net = $rate * $qty;
                    
                    $cdPer = floatval($item['cd_per'] ?? 0); 
                    $tdPer = floatval($item['td_per'] ?? 0); 
                    if ($cdPer > 0) { $net -= ($net * $cdPer / 100); } 
                    if ($tdPer > 0) { $net -= ($net * $tdPer / 100); }
                    
                    $total_sales_amount += $net; // accumulate net amount
                    if($order->delivery_status == 'pending'){
                        $failed_amount += $net;
                    }elseif ($order->delivery_status == 'cancelled'){
                        $cancelled_amount += $net;
                    }elseif ($order->delivery_status == 'delivered'){
                        $delivered_amount += $net;
                    }
                    
                    if($order->returned_back){
                        $returns += $net;
                    }
        
                }
            }
            
            $total_sales_amount = round($total_sales_amount);
            $failed_amount = round($failed_amount);
            $cancelled_amount = round($cancelled_amount);
            $delivered_amount = round($delivered_amount);
            $returns = round($returns);
            
            if($order->delivery_status == 'pending'){
                $failed_orders_count++;
            }elseif ($order->delivery_status == 'cancelled'){
                $cancelled_orders_count++;
            }elseif ($order->delivery_status == 'delivered'){
                $delivered_orders_count++;
            }
            
            if($order->returned_back){
                $return_orders_count++;
            }
            
            if($order->report){
                if($order->report->delivery_charge > 0){
                    $delivery_charge += $order->report->delivery_charge;
                    $delivery_charge_orders_count++;
                }elseif($order->report->delivery_charge == 0){
                    $closed_orders_count++;
                }else{
                    $penalty += $order->report->delivery_charge;
                    $penalty_orders_count++;
                }
            }
        }
        
        // Generate PDF 
        $pdf = Pdf::loadView('ledger_pdf', [ 
            'date' => $request->date, 
            'prepared_by' => $report->prepared_by, 
            'total_sales_amount' => $this->customRound($total_sales_amount), 
            'failed_amount' => $this->customRound($failed_amount), 
            'cancelled_amount' => $this->customRound($cancelled_amount), 
            'delivered_amount' => $this->customRound($delivered_amount), 
            'delivery_charge' => $this->customRound($delivery_charge), 
            'penalty' => $this->customRound($penalty), 
            'returns' => $this->customRound($returns),
            'collected_amount' => $request->collected_amount,
            'total_orders' => $total_orders, 
            'failed_orders_count' => $failed_orders_count, 
            'cancelled_orders_count' => $cancelled_orders_count, 
            'delivered_orders_count' => $delivered_orders_count, 
            'delivery_charge_orders_count' => $delivery_charge_orders_count, 
            'penalty_orders_count' => $penalty_orders_count,
            'return_orders_count' => $return_orders_count,
            'closed_orders_count' => $closed_orders_count,
        ]); 
        
        
        // Define file path
        $fileName = 'Ledger_' . $report->id . '.pdf';
        $filePath = public_path('Ledger/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(public_path('Ledger'))) {
            mkdir(public_path('Ledger'), 0777, true);
        }
        
        // Save PDF to public path
        $pdf->save($filePath);
        
        return redirect()->route('ledgers')->with('success', 'Ledger updated successfully!');
        
        
    }

    public function expenses()
    {
        $expenses = Expense::with(['category', 'user'])->latest()->get();
        $categories = ExpenseCategory::all();
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();
        return view('expenses', compact('expenses', 'categories', 'users'));
    }

    public function add_expense(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;
        if ($request->hasFile('receipt')) {
            $file = $request->receipt;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ExpenseReceipts/', $filename);
            $data['receipt'] = 'ExpenseReceipts/' . $filename;
        }

        Expense::create($data);

        return redirect()->back()->with('success', 'Expense added successfully!');
    }

    public function edit_expense(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:expenses,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $expense = Expense::findOrFail($data['id']);
        $data['last_updated_by'] = Auth::user()->email;
        if ($request->hasFile('receipt')) {

            $file = $request->receipt;
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ExpenseReceipts/', $filename);
            $data['receipt'] = 'ExpenseReceipts/' . $filename;
        }

        $expense->update($data);

        return redirect()->back()->with('success', 'Expense updated successfully!');
    }

    public function delete_expense(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:expenses,id',
        ]);

        $expense = Expense::findOrFail($data['id']);
        $expense->delete();

        return redirect()->back()->with('success', 'Expense deleted successfully!');
    }

    public function add_expensecategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:expense_categories,name',
            'terms' => 'nullable'
        ]);

        $data['created_by'] = Auth::user()->email;
        $data['last_updated_by'] = Auth::user()->email;

        ExpenseCategory::create($data);

        return redirect()->back()->with('success', 'Category added successfully!');
    }

    public function attendance_logs(Request $request)
    {
        if ($request->id) {
            $logs = AttendanceLog::where('user_id', $request->id)->with('user')->latest()->get();
        } else {
            $logs = AttendanceLog::with('user')->latest()->get();
        }
        $logs->transform(function ($log) {
            if ($log->punch_in && $log->punch_out) {
                $in  = Carbon::parse($log->punch_in);
                $out = Carbon::parse($log->punch_out);
                $log->duration = $in->diffForHumans($out, [
                    'short' => true,
                    'parts' => 2, // e.g., "3h 15m"
                    'syntax' => Carbon::DIFF_ABSOLUTE
                ]);
            } else {
                $log->duration = '—'; // or null, or "Pending"
            }

            return $log;
        });

        $groupedLogs = $logs->groupBy(fn($log) => $log->user->name)
            ->map(function ($userLogs) {
                return $userLogs->groupBy('date')->map(function ($dateLogs) {
                    // Sum total seconds for logs with punch_out
                    $totalSeconds = $dateLogs->reduce(function ($carry, $log) {
                        if ($log->punch_in && $log->punch_out) {
                            $in  = Carbon::parse($log->punch_in);
                            $out = Carbon::parse($log->punch_out);
                            return $carry + $out->diffInSeconds($in);
                        }
                        return $carry;
                    }, 0);

                    // Format total duration using diffForHumans
                    $start = Carbon::createFromTimestampUTC(0);
                    $end = Carbon::createFromTimestampUTC($totalSeconds);

                    $formattedTotal = $start->diffForHumans($end, [
                        'short' => true,
                        'parts' => 2,
                        'syntax' => Carbon::DIFF_ABSOLUTE
                    ]);

                    return [
                        'entries' => $dateLogs,
                        'total_duration' => $formattedTotal
                    ];
                });
            });

        // dd($groupedLogs);
        return view('attendance-logs', compact('logs', 'groupedLogs'));
    }
    
    public function areas(){
        $areas = Area::all();
        return view('areas', compact('areas'));
    }
    
    public function add_area(Request $request){
        $request->validate([
            'area' => 'required',
            'pincode' => 'required'
        ]);
        $area = Area::where('area', $request->area)->where('pincode', $request->pincode)->first();
        
        if($area){
            return response()->json([
                'status' => true,
                'message' => $request->area . ' already exist!',
                'data' => $area
            ]);
        }else{
            $area = new Area();
            $area->area = $request->area;
            $area->pincode = $request->pincode;
            $area->save();
            return response()->json([
                'status' => true,
                'message' => $request->area . ' added successfully!',
                'data' => $area    
            ]);
        }
    }
    
    public function edit_area(Request $request){
        $request->validate([
            'id' => 'required|exists:areas',
            'area' => 'required',
            'pincode' => 'required'
        ]);
        $area = Area::find($request->id);
        
        if($area){
            $area->area = $request->area;
            $area->pincode = $request->pincode;
            $area->save();
            return response()->json([
                'status' => true,
                'message' => $request->area . ' updated successfully!',
                'data' => $area    
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => $request->area . ' not found! Please refresh and try again.',
            ]);
        }
    }
    
    public function delete_area($id){
        $area = Area::find($id);
        $villages = Village::where('pincode', $area->pincode)->where('area', $area->area)->delete();
        $area->delete();
        
        return redirect()->back()->with('success', 'Area deleted with all linked villages!');
    }
    
    public function villages($id){
        $area = Area::findOrFail($id);
        $villages = Village::where('area', $area->area)->where('pincode', $area->pincode)->get();
        return view('villages', compact('area', 'villages'));
    }
    
    public function add_village(Request $request){
        $request->validate([
            'pincode' => 'required',
            'area' => 'required',
            'village' => 'required'
        ]);
        
        $village = Village::where('area', $request->area)->where('pincode', $request->pincode)->where('village', $request->village)->first();
        
        if($village){
            return response()->json([
                'status' => true,
                'message' => $request->village . ' already exist!',
                'data' => $village
            ]);
        }else{
            $village = new Village();
            $village->area = $request->area;
            $village->pincode = $request->pincode;
            $village->village = $request->village;
            $village->save();
            return response()->json([
                'status' => true,
                'message' => $request->village . ' added successfully!',
                'data' => $village    
            ]);
        }
    }


    public function edit_village(Request $request){
        $request->validate([
            'id' => 'required|exists:villages',
            'area' => 'required',
            'pincode' => 'required',
            'village' => 'required',
        ]);
        $village = Village::find($request->id);
        
        if($village){
            $village->area = $request->area;
            $village->pincode = $request->pincode;
            $village->village = $request->village;
            $village->save();
            return response()->json([
                'status' => true,
                'message' => $request->village . ' updated successfully!',
                'data' => $village    
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => $request->village . ' not found! Please refresh and try again.',
            ]);
        }
    }
    
    public function delete_village($id){
        $village = Village::find($id);
        $village->delete();
        
        return redirect()->back()->with('success', 'Village deleted successfully!');
    }
    
    function export_villages(){
        return Excel::download(new VillageExport, 'villages.xlsx');
    }
    
    












































    public function markAsRead(Request $request)
    {
        $notificationUser = NotificationUser::where('notification_id', $request->id)
            ->where('user_id', Auth::user()->id)
            ->first();

        if ($notificationUser) {
            $notificationUser->is_read = true;
            $notificationUser->read_at = now(); // optional timestamp
            $notificationUser->save();

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'error'], 404);
    }

    public function markAllRead()
    {
        $notificationUsers = NotificationUser::where('user_id', Auth::user()->id)
            ->where('is_read', 0)
            ->get();

        foreach ($notificationUsers as $notificationUser) {
            $notificationUser->is_read = 1;
            $notificationUser->read_at = now(); // optional timestamp
            $notificationUser->save();
        }

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
    

    function exportShopsData(){
        return Excel::download(new ShopsExport, 'shops.xlsx');
    }
    
    function exportSalesRoutePlans(){
        return Excel::download(new SalesRoutePlan, 'sales_route_plans.xlsx');
    }
    
    function exportSalesOrders(Request $request){

        $startDate = $request->input('start_date'); 
        $endDate = $request->input('end_date');
        
        if($request->warehouse_id){
            return Excel::download(new ExportSalesOrder($request->warehouse_id, null, $startDate, $endDate), 'sales_orders.xlsx');
        } elseif($request->delivery_partner){
            return Excel::download(new ExportSalesOrder(null, $request->delivery_partner, $startDate, $endDate), 'sales_orders.xlsx');
        }else{
            return Excel::download(new ExportSalesOrder(null, null, $startDate, $endDate), 'sales_orders.xlsx');
        }
    }
    
    function exportInventory(Request $request){
        $request->validate([
            'id' => 'required|exists:warehouses,id',    
        ]);
        return Excel::download(new ExportInventory($request->id), 'inventory.xlsx');
    }

}
