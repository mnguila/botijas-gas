<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\AllocationRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomsProcessController;
use App\Http\Controllers\CustomsProcessTaskController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OperationalExpenseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesPointAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('profile.password.update');
    Route::view('/no-access', 'access.none')->name('access.none');
    Route::redirect('/', '/modules')->name('home');

    Route::middleware('permission:products.manage')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
        Route::post('/products', [CatalogController::class, 'storeProduct'])->name('products.store');
        Route::get('/products/{product}/edit', [CatalogController::class, 'editProduct'])->name('products.edit');
        Route::patch('/products/{product}', [CatalogController::class, 'updateProduct'])->name('products.update');
        Route::post('/products/{product}/stock', [CatalogController::class, 'addProductStock'])->name('products.stock.add');
        Route::post('/products/{product}/damage', [CatalogController::class, 'reportCentralDamage'])->name('products.damage.store');
        Route::delete('/products/{product}', [CatalogController::class, 'destroyProduct'])->name('products.destroy');
        Route::patch('/products/{product}/restore', [CatalogController::class, 'restoreProduct'])->name('products.restore');
    });
    Route::middleware('permission:locations.manage')->group(function () {
        Route::get('/locations', [CatalogController::class, 'locations'])->name('locations.index');
        Route::post('/locations', [CatalogController::class, 'storeLocation'])->name('locations.store');
        Route::patch('/locations/{location}', [CatalogController::class, 'updateLocation'])->name('locations.update');
        Route::patch('/locations/{location}/status', [CatalogController::class, 'updateLocationStatus'])->name('locations.status');
    });
    Route::middleware('permission:allocations.manage')->group(function () {
        Route::get('/allocations', [AllocationController::class, 'index'])->name('allocations.index');
        Route::post('/allocations', [AllocationController::class, 'store'])->name('allocations.store');
    });
    Route::get('/allocation-requests', [AllocationRequestController::class, 'index'])->name('allocation-requests.index');
    Route::post('/allocation-requests', [AllocationRequestController::class, 'store'])->middleware('permission:allocations.request')->name('allocation-requests.store');
    Route::post('/allocation-requests/{allocationRequest}/approve', [AllocationRequestController::class, 'approve'])->middleware('permission:allocations.approve')->name('allocation-requests.approve');
    Route::post('/allocation-requests/{allocationRequest}/reject', [AllocationRequestController::class, 'reject'])->middleware('permission:allocations.approve')->name('allocation-requests.reject');
    Route::post('/allocation-requests/{allocationRequest}/dispatch', [AllocationRequestController::class, 'dispatch'])->middleware('permission:allocations.dispatch')->name('allocation-requests.dispatch');
    Route::get('/allocation-requests/{allocationRequest}/guide', [AllocationRequestController::class, 'guide'])->name('allocation-requests.guide');
    Route::post('/allocation-requests/{allocationRequest}/receive', [AllocationRequestController::class, 'receive'])->middleware('permission:allocations.receive')->name('allocation-requests.receive');
    Route::get('/allocation-requests/{allocationRequest}/driver-document', [AllocationRequestController::class, 'driverDocument'])->name('allocation-requests.driver-document');
    Route::get('/expenses', [OperationalExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [OperationalExpenseController::class, 'store'])->middleware('permission:expenses.create')->name('expenses.store');
    Route::post('/expenses/{expense}/approve', [OperationalExpenseController::class, 'approve'])->middleware('permission:expenses.approve')->name('expenses.approve');
    Route::post('/expenses/{expense}/reject', [OperationalExpenseController::class, 'reject'])->middleware('permission:expenses.approve')->name('expenses.reject');
    Route::post('/expenses/{expense}/cancel', [OperationalExpenseController::class, 'cancel'])->middleware('permission:expenses.approve')->name('expenses.cancel');
    Route::get('/expenses/{expense}/receipt', [OperationalExpenseController::class, 'receipt'])->name('expenses.receipt');
    Route::middleware('permission:sales.create')->group(function () {
        Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
        Route::post('/sales/wholesale', [SalesController::class, 'wholesale'])->name('sales.wholesale');
        Route::post('/sales/unit', [SalesController::class, 'unit'])->name('sales.unit');
        Route::post('/sales/damages', [SalesController::class, 'reportStandDamage'])->name('sales.damages.store');
        Route::post('/sales/{sale}/return', [SalesController::class, 'returnDamaged'])->name('sales.return');
        Route::post('/sales/{sale}/cancel', [SalesController::class, 'cancel'])->name('sales.cancel');
        Route::get('/sales/{sale}/delivery-guide', [SalesController::class, 'deliveryGuide'])->name('sales.delivery-guide');
    });
    Route::get('/sales/history', [SalesController::class, 'history'])->middleware('permission:sales.history.view')->name('sales.history');
    Route::get('/sales/history/pdf', [SalesController::class, 'historyPdf'])->middleware('permission:sales.history.view')->name('sales.history.pdf');
    Route::middleware('permission:sales.points.assign')->group(function () {
        Route::get('/sales/points', [SalesPointAssignmentController::class, 'index'])->name('sales.points.index');
        Route::patch('/sales/points/{user}', [SalesPointAssignmentController::class, 'update'])->name('sales.points.update');
    });
    Route::middleware('permission:access.manage')->group(function () {
        Route::get('/access/users', [AccessController::class, 'users'])->name('access.users');
        Route::post('/access/users', [AccessController::class, 'storeUser'])->name('access.users.store');
        Route::get('/access/users/{user}/edit', [AccessController::class, 'editUser'])->name('access.users.edit');
        Route::patch('/access/users/{user}', [AccessController::class, 'updateUser'])->name('access.users.update');
        Route::get('/access/groups', [AccessController::class, 'groups'])->name('access.groups');
        Route::post('/access/groups', [AccessController::class, 'storeGroup'])->name('access.groups.store');
        Route::get('/access/groups/{group}/edit', [AccessController::class, 'editGroup'])->name('access.groups.edit');
        Route::patch('/access/groups/{group}', [AccessController::class, 'updateGroup'])->name('access.groups.update');
    });
    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::patch('/customers/{customer}/status', [CustomerController::class, 'toggleActive'])->name('customers.status');
    });
    Route::middleware('permission:customs.manage')->group(function () {
        Route::get('/customs-processes', [CustomsProcessController::class, 'index'])->name('customs.index');
        Route::get('/customs-processes/create', [CustomsProcessController::class, 'create'])->name('customs.create');
        Route::post('/customs-processes', [CustomsProcessController::class, 'store'])->name('customs.store');
        Route::get('/customs-processes/{process}', [CustomsProcessController::class, 'show'])->name('customs.show');
        Route::get('/customs-processes/{process}/timeline', [CustomsProcessController::class, 'timeline'])->name('customs.timeline');
        Route::get('/customs-processes/{process}/edit', [CustomsProcessController::class, 'edit'])->name('customs.edit');
        Route::patch('/customs-processes/{process}', [CustomsProcessController::class, 'update'])->name('customs.update');
        Route::get('/customs-processes/{process}/attachments/{kind}', [CustomsProcessController::class, 'downloadAttachment'])->name('customs.attachment');
        Route::post('/customs-processes/{process}/documents', [CustomsProcessController::class, 'addDocuments'])->name('customs.documents.store');
        Route::get('/customs-processes/{process}/documents/{document}', [CustomsProcessController::class, 'downloadDocument'])->name('customs.documents.download');
        Route::post('/customs-processes/{process}/tasks', [CustomsProcessTaskController::class, 'store'])->name('customs.tasks.store');
        Route::get('/customs-processes/{process}/tasks/create', [CustomsProcessTaskController::class, 'create'])->name('customs.tasks.create');
        Route::patch('/customs-processes/{process}/tasks/{task}', [CustomsProcessTaskController::class, 'update'])->name('customs.tasks.update');
        Route::get('/customs-processes/{process}/tasks/{task}/documents/{document}', [CustomsProcessTaskController::class, 'downloadDocument'])->name('customs.tasks.documents.download');
    });
});
