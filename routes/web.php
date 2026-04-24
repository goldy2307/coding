<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SalesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});
Route::get('sales-privacy-policy', function(){
    return view('privacy-policy-sales');
});
Route::get('delivery-privacy-policy', function (){
    return view('privacy-policy-delivery');
});


Auth::routes(['register' => false]);

Route::get('track/{order_number}', [SalesController::class, 'salesOrderTracking'])->name('sales-order-tracking');

Route::get('/home', [HomeController::class, 'index'])->name('index');

Route::middleware(['auth', 'activity'])->group(function () {
    Route::get('/admin/dashboard', [HomeController::class, 'adminDashboard'])->name('admin.dashboard')->middleware(['role:admin']);

    Route::get('/roles', [HomeController::class, 'roles'])->name('roles')->middleware(['role:admin', 'permission:view role']);
    Route::post('/add-role', [HomeController::class, 'add_role'])->name('add-role')->middleware(['role:admin', 'permission:create role']);
    Route::get('/permissions', [HomeController::class, 'permissions'])->name('permissions')->middleware(['role:admin']);
    Route::post('/update-permission', [HomeController::class, 'update_permission'])->name('update-permission')->middleware(['role:admin']);

    Route::get('/departments', [HomeController::class, 'departments'])->name('departments')->middleware(['permission:view department']);
    Route::post('/add-department', [HomeController::class, 'add_department'])->name('add-department')->middleware(['permission:create department']);
    Route::post('/edit-department', [HomeController::class, 'edit_department'])->name('edit-department')->middleware(['permission:edit department']);
    Route::post('/delete-department', [HomeController::class, 'delete_department'])->name('delete-department')->middleware(['permission:delete department']);

    Route::get('/vendors', [HomeController::class, 'vendors'])->name('vendors')->middleware(['permission:view vendor']);
    Route::post('/add-vendor', [HomeController::class, 'add_vendor'])->name('add-vendor')->middleware(['permission:create vendor']);
    Route::post('/edit-vendor', [HomeController::class, 'edit_vendor'])->name('edit-vendor')->middleware(['permission:edit vendor']);
    Route::post('/delete-vendor', [HomeController::class, 'delete_vendor'])->name('delete-vendor')->middleware(['permission:delete vendor']);

    Route::get('/warehouses', [HomeController::class, 'warehouses'])->name('warehouses')->middleware(['permission:view warehouse']);
    Route::post('/add-warehouse', [HomeController::class, 'add_warehouse'])->name('add-warehouse')->middleware(['permission:create warehouse']);
    Route::post('/edit-warehouse', [HomeController::class, 'edit_warehouse'])->name('edit-warehouse')->middleware(['permission:edit warehouse']);
    Route::post('/delete-warehouse', [HomeController::class, 'delete_warehouse'])->name('delete-warehouse')->middleware(['permission:delete warehouse']);

    Route::get('/employees', [HomeController::class, 'employees'])->name('employees')->middleware(['permission:view user']);
    Route::post('/add-employee', [HomeController::class, 'add_employee'])->name('add-employee')->middleware(['permission:create user']);
    Route::post('/edit-employee', [HomeController::class, 'edit_employee'])->name('edit-employee')->middleware(['permission:edit user']);
    Route::post('/delete-employee', [HomeController::class, 'delete_employee'])->name('delete-employee')->middleware(['permission:delete user']);
    Route::post('/restore-employee', [HomeController::class, 'restore_employee'])->name('restore-employee')->middleware(['permission:create user']);

    Route::get('/hr/dashboard', [HomeController::class, 'hrDashboard'])->name('hr.dashboard')->middleware(['role:hr']);

    Route::post('/create-offerletter', [HomeController::class, 'create_offerletter'])->name('create-offerletter')->middleware(['permission:generate offerletter']);
    Route::get('/send-offerletter/{user_id}', [HomeController::class, 'send_offerletter'])->name('send-offerletter')->middleware(['permission:send offerletter']);

    Route::get('/salarycomponents', [HomeController::class, 'salarycomponents'])->name('salarycomponents')->middleware(['permission:view salarycomponent']);
    Route::post('/add-salarycomponent', [HomeController::class, 'add_salarycomponent'])->name('add-salarycomponent')->middleware(['permission:create salarycomponent']);
    Route::post('/edit-salarycomponent', [HomeController::class, 'edit_salarycomponent'])->name('edit-salarycomponent')->middleware(['permission:edit salarycomponent']);
    Route::post('/delete-salarycomponent', [HomeController::class, 'delete_salarycomponent'])->name('delete-salarycomponent')->middleware(['permission:delete salarycomponent']);

    Route::get('/salarystructure', [HomeController::class, 'salarystructures'])->name('salarystructures')->middleware(['permission:view salarystructure']);
    Route::post('/add-salarystructure', [HomeController::class, 'add_salarystructure'])->name('add-salarystructure')->middleware(['permission:create salarystructure']);
    Route::post('/edit-salarystructure', [HomeController::class, 'edit_salarystructure'])->name('edit-salarystructure')->middleware(['permission:edit salarystructure']);
    Route::post('/delete-salarystructure', [HomeController::class, 'delete_salarystructure'])->name('delete-salarystructure')->middleware(['permission:delete salarystructure']);

    Route::get('/salaryslip', [HomeController::class, 'salaryslips'])->name('salaryslips')->middleware(['permission:view salaryslip']);
    Route::post('/add-salaryslip', [HomeController::class, 'add_salaryslip'])->name('add-salaryslip')->middleware(['permission:create salaryslip']);
    Route::post('/edit-salaryslip', [HomeController::class, 'edit_salaryslip'])->name('edit-salaryslip')->middleware(['permission:edit salaryslip']);
    Route::post('/delete-salaryslip', [HomeController::class, 'delete_salaryslip'])->name('delete-salaryslip')->middleware(['permission:delete salaryslip']);
    Route::post('/salary-slip/fetch', [HomeController::class, 'fetch_salaryslip'])->name('salary-slip/fetch');
    Route::get('salary-slip/{user_id}-{month}', [HomeController::class, 'salary_slip'])->name('salary-slip')->middleware(['permission:view salaryslip']);
    Route::get('send-salaryslip/{user_id}-{month}', [HomeController::class, 'send_salaryslip'])->name('send-salaryslip')->middleware(['permission:view salaryslip']);

    Route::post('generate-notice', [HomeController::class, 'generate_notice'])->name('generate-notice')->middleware(['permission:generate notice']);
    Route::get('notices', [HomeController::class, 'notices'])->name('notices')->middleware(['permission:generate notice']);
    Route::get('notice/{id}', [HomeController::class, 'notice'])->name('notice')->middleware(['permission:generate notice']);
    Route::get('send-notice/{id}', [HomeController::class, 'send_notice'])->name('send-notice')->middleware(['permission:send notice']);
    Route::post('delete-notice', [HomeController::class, 'delete_notice'])->name('delete-notice')->middleware(['permission:generate notice']);

    Route::get('/sales/dashboard', [HomeController::class, 'salesDashboard'])->name('sales.dashboard')->middleware(['role:sales']);

    Route::get('/salesrouteplannings', [HomeController::class, 'salesrouteplannings'])->name('salesrouteplannings')->middleware(['permission:view salesrouteplanning']);
    Route::get('/get-areas-by-pincode/{pincode}', [HomeController::class, 'getAreasByPincode'])->name('get-areas-by-pincode');
    Route::get('/get-villages-by-area', [HomeController::class, 'getVillagesByArea'])->name('get-villages-by-area');
    Route::post('/add-salesrouteplanning', [HomeController::class, 'add_salesrouteplanning'])->name('add-salesrouteplanning')->middleware(['permission:create salesrouteplanning']);
    Route::post('/edit-salesrouteplanning', [HomeController::class, 'edit_salesrouteplanning'])->name('edit-salesrouteplanning')->middleware(['permission:edit salesrouteplanning']);
    Route::post('/delete-salesrouteplanning', [HomeController::class, 'delete_salesrouteplanning'])->name('delete-salesrouteplanning')->middleware(['permission:delete salesrouteplanning']);
    
    Route::get('/shopsperroute/{route_id}', [HomeController::class, 'shopsperroutes'])->name('shopsperroutes')->middleware(['permission:view shopsperroute']);
    Route::post('/add-shopsperroute', [HomeController::class, 'add_shopsperroute'])->name('add-shopsperroute')->middleware(['permission:create shopsperroute']);
    Route::post('/edit-shopsperroute', [HomeController::class, 'edit_shopsperroute'])->name('edit-shopsperroute')->middleware(['permission:edit shopsperroute']);
    Route::post('/delete-shopsperroute', [HomeController::class, 'delete_shopsperroute'])->name('delete-shopsperroute')->middleware(['permission:delete shopsperroute']);

    Route::get('/shops', [HomeController::class, 'shops'])->name('shops')->middleware(['permission:view shop']);
    Route::post('/add-shop', [HomeController::class, 'add_shop'])->name('add-shop')->middleware(['permission:create shop']);
    Route::post('/edit-shop', [HomeController::class, 'edit_shop'])->name('edit-shop')->middleware(['permission:edit shop']);
    Route::post('/delete-shop', [HomeController::class, 'delete_shop'])->name('delete-shop')->middleware(['permission:delete shop']);

    Route::get('/categories', [HomeController::class, 'categories'])->name('categories')->middleware(['permission:view category']);
    Route::post('/add-category', [HomeController::class, 'add_category'])->name('add-category')->middleware(['permission:create category']);
    Route::post('/edit-category', [HomeController::class, 'edit_category'])->name('edit-category')->middleware(['permission:edit category']);
    Route::post('/delete-category', [HomeController::class, 'delete_category'])->name('delete-category')->middleware(['permission:delete category']);

    Route::get('/products/{category_id}', [HomeController::class, 'products'])->name('products')->middleware(['permission:view product']);
    Route::post('/add-product', [HomeController::class, 'add_product'])->name('add-product')->middleware(['permission:create product']);
    Route::post('/upload-products', [HomeController::class, 'uploadProducts'])->name('upload-products')->middleware(['permission:create product']);
    Route::get('/export-products', [HomeController::class, 'exportProducts'])->name('export-products')->middleware(['permission:view product']);
    Route::post('/edit-product', [HomeController::class, 'edit_product'])->name('edit-product')->middleware(['permission:edit product']);
    Route::post('/delete-product', [HomeController::class, 'delete_product'])->name('delete-product')->middleware(['permission:delete product']);
    Route::get('/all-products/', [HomeController::class, 'all_products'])->name('all-products')->middleware(['permission:view product']);
    Route::get('/products-by-user/{user_id}', [HomeController::class, 'productsByUser'])->middleware(['permission:view product']);

    Route::get('/inventories', [HomeController::class, 'inventories'])->name('inventories')->middleware(['permission:view inventory']);
    Route::get('/warehouse-inventories/{id}', [HomeController::class, 'warehouseInventories'])->name('warehouse.inventories')->middleware(['permission:view inventory']);
    Route::post('/add-inventory', [HomeController::class, 'add_inventory'])->name('add-inventory')->middleware(['permission:create inventory']);
    Route::post('/edit-inventory', [HomeController::class, 'edit_inventory'])->name('edit-inventory')->middleware(['permission:edit inventory']);
    Route::post('/delete-inventory', [HomeController::class, 'delete_inventory'])->name('delete-inventory')->middleware(['permission:delete inventory']);

    Route::get('/purchaseorders', [HomeController::class, 'purchaseorders'])->name('purchaseorders')->middleware(['permission:view purchaseorder']);
    Route::post('/add-purchaseorder', [HomeController::class, 'add_purchaseorder'])->name('add-purchaseorder')->middleware(['permission:create purchaseorder']);
    Route::post('/edit-purchaseorder', [HomeController::class, 'edit_purchaseorder'])->name('edit-purchaseorder')->middleware(['permission:edit purchaseorder']);
    Route::get('/add-purchase-order', [HomeController::class, 'add_purchase_order'])->name('add-purchase-order')->middleware(['permission:create purchaseorder']);
    Route::get('/edit-purchase-order/{id}', [HomeController::class, 'edit_purchase_order'])->name('edit-purchase-order')->middleware(['permission:edit purchaseorder']);
    Route::post('/delete-purchaseorder', [HomeController::class, 'delete_purchaseorder'])->name('delete-purchaseorder')->middleware(['permission:delete purchaseorder']);
    Route::post('/update-purchaseorder', [HomeController::class, 'update_purchaseorder'])->name('update-purchaseorder')->middleware(['permission:edit purchaseorder']);
    Route::post('/confirm-delivery', [HomeController::class, 'confirm_delivery'])->name('confirm-delivery')->middleware(['permission:mark delivered purchaseorder']);
    Route::get('/purchase-order/{order_number}', [HomeController::class, 'purchase_order'])->name('purchase-order')->middleware(['permission:view purchaseorder']);
    Route::get('/purchase-order-pdf/{order_number}', [HomeController::class, 'purchase_order_pdf'])->name('purchase-order-pdf')->middleware(['permission:view purchaseorder']);
    Route::get('products-api/{id}', [HomeController::class, 'getProductById'])->name('products-api');

    Route::get('/salesorders', [HomeController::class, 'salesorders'])->name('salesorders')->middleware(['permission:view salesorder']);
    Route::get('/add-sales-order', [HomeController::class, 'add_sales_order'])->name('add-sales-order')->middleware(['permission:create salesorder']);
    Route::get('/edit-sales-order/{id}', [HomeController::class, 'edit_sales_order'])->name('edit-sales-order')->middleware(['permission:edit salesorder']);
    Route::post('/add-salesorder', [HomeController::class, 'add_salesorder'])->name('add-salesorder')->middleware(['permission:create salesorder']);
    Route::post('/edit-salesorder', [HomeController::class, 'edit_salesorder'])->name('edit-salesorder')->middleware(['permission:edit salesorder']);
    Route::post('/delete-salesorder', [HomeController::class, 'delete_salesorder'])->name('delete-salesorder')->middleware(['permission:delete salesorder']);
    Route::post('/update-salesorder', [HomeController::class, 'update_salesorder'])->name('update-salesorder')->middleware(['permission:edit salesorder|mark packed salesorder|assign delivery agent|mark returned back salesorder|mark received back salesorder|cancel salesorder|revert salesorder to pending']);
    Route::post('/update-delivery-partner', [HomeController::class, 'update_delivery_partner'])->name('update-delivery-partner')->middleware(['permission:dispatch salesorder']);
    Route::get('/unapprove-sales-order/{order_number}', [HomeController::class, 'unapprove_salesorder'])->name('unapprove-sales-order')->middleware(['permission:unapprove salesorder']);
    Route::post('/mark-as-delivered', [HomeController::class, 'mark_as_delivered'])->name('mark-as-delivered')->middleware(['permission:mark delivered salesorder']);
    Route::get('/sales-order/{order_number}', [HomeController::class, 'sales_order'])->name('sales-order')->middleware(['permission:view salesorder']);
    Route::get('/sales-order-pdf/{order_number}', [HomeController::class, 'sales_order_pdf'])->name('sales-order-pdf')->middleware(['permission:view salesorder']);
    Route::post('/verify-salesorder', [HomeController::class, 'verify_salesorder'])->name('verify-salesorder')->middleware(['permission:mark received back salesorder']);

    Route::get('warehouse-salesorders', [HomeController::class, 'warehouseSalesOrders'])->name('warehouse-salesorders')->middleware(['permission:view salesorder']);
    Route::get('delivery-salesorders', [HomeController::class, 'deliverySalesOrders'])->name('delivery-salesorders')->middleware(['permission:view salesorder']);
    Route::post('generate-delivery-report', [HomeController::class, 'generateDeliveryReport'])->name('generate-delivery-report')->middleware(['permission:generate delivery report']);
    Route::get('view-delivery-report', [HomeController::class, 'viewDeliveryReport'])->name('view-delivery-report')->middleware(['permission:see delivery report']);
    Route::get('sendEODReport/{date}', [HomeController::class, 'sendEODReport'])->name('sendEODReport');
    Route::get('downloadEODReport', [HomeController::class, 'downloadEODReport'])->name('downloadEODReport');
    Route::get('eod-reports', [HomeController::class, 'eodReports'])->name('eod-reports')->middleware(['permission:see delivery report']);
    Route::post('update-report-status', [HomeController::class, 'updateReportStatus'])->name('update-report-status')->middleware(['permission:approve delivery report']);
    Route::get('warehouse-reports', [HomeController::class, 'warehouseReports'])->name('warehouse-reports')->middleware(['permission:see warehouse report']);
    Route::post('add-warehouse-report', [HomeController::class, 'addWarehouseReport'])->name('add-warehouse-report')->middleware(['permission:generate warehouse report']);
    Route::post('edit-warehouse-report', [HomeController::class, 'editWarehouseReport'])->name('edit-warehouse-report')->middleware(['permission:generate warehouse report']);
    Route::get('/warehouse-report/fetch', [HomeController::class, 'fetch'])->name('warehouse-report.fetch');
    Route::post('/productwise-stock-out', [HomeController::class, 'productwise_stock_out'])->name('productwise-stock-out');
    Route::post('/productwise-return-stock', [HomeController::class, 'productwise_return_stock'])->name('productwise-return-stock');
    Route::get('ledgers', [HomeController::class, 'ledgers'])->name('ledgers')->middleware(['permission:see ledger']);
    Route::post('add-ledger', [HomeController::class, 'addLedger'])->name('add-ledger')->middleware(['permission:generate ledger']);
    Route::post('edit-ledger', [HomeController::class, 'editLedger'])->name('edit-ledger')->middleware(['permission:generate ledger']);

    Route::get('/expenses', [HomeController::class, 'expenses'])->name('expenses')->middleware(['permission:view expense']);
    Route::post('/add-expense', [HomeController::class, 'add_expense'])->name('add-expense')->middleware(['permission:create expense']);
    Route::post('/edit-expense', [HomeController::class, 'edit_expense'])->name('edit-expense')->middleware(['permission:edit expense']);
    Route::post('/delete-expense', [HomeController::class, 'delete_expense'])->name('delete-expense')->middleware(['permission:delete expense']);

    Route::get('/expense-categories', [HomeController::class, 'expense_categories'])->name('expense-categories')->middleware(['permission:view expensecategory']);
    Route::post('/add-expensecategory', [HomeController::class, 'add_expensecategory'])->name('add-expensecategory')->middleware(['permission:create expensecategory']);
    Route::post('/edit-expensecategory', [HomeController::class, 'edit_expensecategory'])->name('edit-expensecategory')->middleware(['permission:edit expensecategory']);
    Route::post('/delete-expensecategory', [HomeController::class, 'delete_expensecategory'])->name('delete-expensecategory')->middleware(['permission:delete expensecategory']);

    Route::get('/attendance-logs', [HomeController::class, 'attendance_logs'])->name('attendance-logs')->middleware(['permission:view attendancelog']);
    // Route::post('/add-attendancelog', [HomeController::class, 'add_attendancelog'])->name('add-attendancelog')->middleware(['permission:create attendancelog']);
    // Route::post('/edit-attendancelog', [HomeController::class, 'edit_attendancelog'])->name('edit-attendancelog')->middleware(['permission:edit attendancelog']);
    Route::post('/delete-attendancelog', [HomeController::class, 'delete_attendancelog'])->name('delete-attendancelog')->middleware(['permission:delete attendancelog']);
    
    Route::get('/areas', [HomeController::class, 'areas'])->name('areas')->middleware(['role:admin']);
    Route::post('add-area', [HomeController::class, 'add_area'])->name('add-area')->middleware(['role:admin']);
    Route::post('edit-area', [HomeController::class, 'edit_area'])->name('edit-area')->middleware(['role:admin']);
    Route::get('delete-area/{id}', [HomeController::class, 'delete_area'])->name('delete-area')->middleware(['role:admin']);
    Route::get('/villages/{id}', [HomeController::class, 'villages'])->name('villages')->middleware(['role:admin']);
    Route::post('add-village', [HomeController::class, 'add_village'])->name('add-village')->middleware(['role:admin']);
    Route::post('edit-village', [HomeController::class, 'edit_village'])->name('edit-village')->middleware(['role:admin']);
    Route::get('delete-village/{id}', [HomeController::class, 'delete_village'])->name('delete-village')->middleware(['role:admin']);
    
    Route::get('export-villages', [HomeController::class, 'export_villages'])->name('export-villages')->middleware(['role:admin']);

    Route::post('profile/upload-pic', [HomeController::class, 'uploadProfilePic'])->name('profile.upload');
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [HomeController::class, 'profile'])->name('profile');
Route::post('/profile/update', [HomeController::class, 'updateProfile'])->name('profile.update');





















    Route::post('/notifications/mark-read', [HomeController::class, 'markAsRead'])->name('mark-read');
    Route::get('/notifications/mark-all-read', [HomeController::class, 'markAllRead'])->name('mark-all-read');
    Route::get('export-shops', [HomeController::class, 'exportShopsData'])->name('export-shops');
    Route::get('export-sales-route-plans', [HomeController::class, 'exportSalesRoutePlans'])->name('export-sales-route-plans');
    Route::get('export-sales-orders', [HomeController::class, 'exportSalesOrders'])->name('export-sales-orders');
    Route::post('export-inventory', [HomeController::class, 'exportInventory'])->name('export-inventory');
    

});





