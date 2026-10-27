<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CashFloatController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ContractorJobController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseHeadController;
use App\Http\Controllers\ForemanSheetController;
use App\Http\Controllers\GodownController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryIssueController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MachineRepairController;
use App\Http\Controllers\PaperIssueController;
use App\Http\Controllers\PaperReceiptController;
use App\Http\Controllers\PaperStockController;
use App\Http\Controllers\PaperTypeController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PlateJobController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WasteEntryController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('locale/{locale}', LocaleController::class)->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

/*
| Every screen is behind a permission from config/permissions.php.
*/
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('paper', [PaperStockController::class, 'index'])->middleware('can:paper.view')->name('paper.index');
    Route::resource('paper-receipts', PaperReceiptController::class)->only(['create', 'store', 'show'])
        ->middlewareFor(['create', 'store'], 'can:paper.receive')
        ->middlewareFor('show', 'can:paper.view');
    Route::resource('paper-issues', PaperIssueController::class)->only(['create', 'store'])->middleware('can:paper.issue');
    Route::resource('godowns', GodownController::class)->except('show')->middleware('can:godowns.manage');
    Route::resource('paper-types', PaperTypeController::class)->except(['show', 'destroy'])->middleware('can:paper.types');

    Route::resource('books', BookController::class)
        ->middlewareFor(['index', 'show'], 'can:books.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:books.manage');

    // Production chain (Phase 3)
    Route::resource('work-orders', WorkOrderController::class)->only(['index', 'create', 'store', 'show', 'destroy'])
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store', 'destroy'], 'can:orders.manage');
    Route::middleware('can:orders.manage')->group(function () {
        Route::patch('work-orders/{workOrder}/stage', [WorkOrderController::class, 'advance'])->name('work-orders.advance');
        Route::post('work-orders/{workOrder}/outside-receipts', [WorkOrderController::class, 'receiveOutside'])->name('work-orders.outside-receipts.store');
    });

    Route::resource('contractors', ContractorController::class)
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:contractors.manage');
    Route::middleware('can:contractors.manage')->group(function () {
        Route::post('contractors/{contractor}/payments', [ContractorController::class, 'pay'])->name('contractors.payments.store');
        Route::get('contractor-jobs/create', [ContractorJobController::class, 'create'])->name('contractor-jobs.create');
        Route::post('contractor-jobs', [ContractorJobController::class, 'store'])->name('contractor-jobs.store');
        Route::patch('contractor-jobs/{contractorJob}/done', [ContractorJobController::class, 'done'])->name('contractor-jobs.done');
    });

    Route::get('plate-jobs', [PlateJobController::class, 'index'])->middleware('can:orders.view')->name('plate-jobs.index');
    Route::middleware('can:plates.manage')->group(function () {
        Route::get('plate-jobs/create', [PlateJobController::class, 'create'])->name('plate-jobs.create');
        Route::post('plate-jobs', [PlateJobController::class, 'store'])->name('plate-jobs.store');
        Route::patch('plate-jobs/{plateJob}/pay', [PlateJobController::class, 'pay'])->name('plate-jobs.pay');
    });

    Route::resource('dispatches', DispatchController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store'], 'can:dispatch.manage');

    Route::get('buyers', [BuyerController::class, 'index'])->middleware('can:orders.view')->name('buyers.index');
    Route::get('buyers/{buyer}', [BuyerController::class, 'show'])->middleware('can:orders.view')->name('buyers.show');
    Route::post('buyers/{buyer}/entries', [BuyerController::class, 'storeEntry'])->middleware('can:buyers.manage')->name('buyers.entries.store');

    Route::get('waste', [WasteEntryController::class, 'index'])->middleware('can:orders.view')->name('waste.index');
    Route::get('waste/create', [WasteEntryController::class, 'create'])->middleware('can:waste.manage')->name('waste.create');
    Route::post('waste', [WasteEntryController::class, 'store'])->middleware('can:waste.manage')->name('waste.store');

    Route::resource('parties', PartyController::class)->except(['show', 'destroy'])->middleware('can:parties.manage');
    Route::get('inventory', [InventoryController::class, 'index'])->middleware('can:purchases.view')->name('inventory.index');
    Route::resource('inventory-categories', InventoryCategoryController::class)->except('show')->middleware('can:inventory.manage');
    Route::resource('inventory-items', InventoryItemController::class)->except('index')
        ->middlewareFor('show', 'can:purchases.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:inventory.manage');
    Route::resource('inventory-issues', InventoryIssueController::class)->only(['create', 'store'])->middleware('can:inventory.issue');
    Route::redirect('purchases', 'inventory?tab=purchases')->name('purchases.index');
    Route::resource('purchases', PurchaseController::class)->only(['create', 'store'])->middleware('can:purchases.create');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store'])
        ->middlewareFor('index', 'can:expenses.view')
        ->middlewareFor(['create', 'store'], 'can:expenses.create');
    Route::resource('expense-heads', ExpenseHeadController::class)->except('show')->middleware('can:expense-heads.manage');
    Route::post('cash-floats', [CashFloatController::class, 'store'])->middleware('can:give-cash')->name('cash-floats.store');

    Route::resource('machines', MachineController::class)->except(['destroy'])
        ->middlewareFor(['index', 'show'], 'can:machines.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:machines.manage');
    Route::resource('machine-repairs', MachineRepairController::class)->only(['create', 'store'])->middleware('can:machines.repair');

    // Staff & payroll (Phase 2)
    Route::resource('employees', EmployeeController::class)
        ->middlewareFor(['index', 'show'], 'can:staff.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:staff.manage');
    Route::resource('designations', DesignationController::class)->except('show')->middleware('can:staff.manage');

    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('can:staff.view')->name('attendance.index');
    Route::middleware('can:attendance.manage')->group(function () {
        Route::get('attendance/day', [AttendanceController::class, 'day'])->name('attendance.day');
        Route::put('attendance/day', [AttendanceController::class, 'saveDay'])->name('attendance.save-day');
        Route::get('attendance/import', [AttendanceController::class, 'importForm'])->name('attendance.import');
        Route::post('attendance/import', [AttendanceController::class, 'import'])->name('attendance.import.store');
    });

    Route::get('salary-advances/mid-month', [SalaryAdvanceController::class, 'midMonth'])->middleware('can:advances.create')->name('salary-advances.mid-month');
    Route::post('salary-advances/mid-month', [SalaryAdvanceController::class, 'storeMidMonth'])->middleware('can:advances.create')->name('salary-advances.mid-month.store');
    Route::resource('salary-advances', SalaryAdvanceController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['index', 'show'], 'can:staff.view')
        ->middlewareFor(['create', 'store'], 'can:advances.create');
    Route::resource('foreman-sheets', ForemanSheetController::class)->only(['create', 'store', 'show'])
        ->middlewareFor('show', 'can:staff.view')
        ->middlewareFor(['create', 'store'], 'can:advances.create');
    Route::resource('loans', LoanController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor(['index', 'show'], 'can:staff.view')
        ->middlewareFor(['create', 'store'], 'can:advances.create');
    Route::post('loans/{loan}/repayments', [LoanController::class, 'repay'])->middleware('can:advances.create')->name('loans.repay');

    Route::get('payroll', [PayrollRunController::class, 'index'])->middleware('can:staff.view')->name('payroll.index');
    Route::get('payroll/{payrollRun}', [PayrollRunController::class, 'show'])->middleware('can:staff.view')->name('payroll.show');
    Route::get('payroll/{payrollRun}/slips', [PayrollRunController::class, 'slips'])->middleware('can:staff.view')->name('payroll.slips');
    Route::middleware('can:payroll.prepare')->group(function () {
        Route::post('payroll', [PayrollRunController::class, 'store'])->name('payroll.store');
        Route::put('payroll/{payrollRun}/lines', [PayrollRunController::class, 'updateLines'])->name('payroll.lines.update');
        Route::post('payroll/{payrollRun}/recalculate', [PayrollRunController::class, 'recalculate'])->name('payroll.recalculate');
    });
    Route::middleware('can:payroll.finalize')->group(function () {
        Route::post('payroll/{payrollRun}/finalize', [PayrollRunController::class, 'finalize'])->name('payroll.finalize');
        Route::post('payroll/{payrollRun}/reopen', [PayrollRunController::class, 'reopen'])->name('payroll.reopen');
    });

    Route::get('reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports.index');

    Route::middleware('can:approve')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('approvals/{type}/{id}', [ApprovalController::class, 'update'])->name('approvals.update')
            ->whereIn('type', array_keys(ApprovalController::Types))->whereNumber('id');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::resource('roles', RoleController::class)->except('show');
    });
});
