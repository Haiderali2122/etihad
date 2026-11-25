<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceMachineController;
use App\Http\Controllers\AttendanceSyncController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CashFloatController;
use App\Http\Controllers\ContractorController;
use App\Http\Controllers\ContractorJobController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeletionController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseHeadController;
use App\Http\Controllers\ForemanCashController;
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
use App\Http\Controllers\MaterialBillController;
use App\Http\Controllers\PaperIssueController;
use App\Http\Controllers\PaperReceiptController;
use App\Http\Controllers\PaperStockController;
use App\Http\Controllers\PaperTypeController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PlateJobController;
use App\Http\Controllers\PlatePurchaseController;
use App\Http\Controllers\ProcessorController;
use App\Http\Controllers\ProcessorPaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RecordDeletionController;
use App\Http\Controllers\RecordDetailController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RimLoadingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryAdvanceController;
use App\Http\Controllers\SaleController;
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
    Route::resource('paper-receipts', PaperReceiptController::class)->only(['create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:paper.receive')
        ->middlewareFor('show', 'can:paper.view');
    Route::resource('paper-issues', PaperIssueController::class)->only(['create', 'store'])->middleware('can:paper.issue');
    Route::patch('paper-issues/{paperIssue}/settle', [PaperIssueController::class, 'settle'])->middleware('can:paper.issue')->name('paper-issues.settle');
    Route::patch('paper-loans/{paperLoan}/settle', [PaperIssueController::class, 'settleLoan'])->middleware('can:paper.issue')->name('paper-loans.settle');
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
        Route::post('work-orders/{workOrder}/younus-paper', [WorkOrderController::class, 'carryPaper'])->name('work-orders.younus-paper');
        Route::post('work-orders/{workOrder}/outside-receipts', [WorkOrderController::class, 'receiveOutside'])->name('work-orders.outside-receipts.store');
        Route::post('work-orders/{workOrder}/rim-loadings', [RimLoadingController::class, 'store'])->name('rim-loadings.store');
        Route::post('rim-loadings/pay-all', [RimLoadingController::class, 'payAll'])->name('rim-loadings.pay-all');
        Route::post('rim-loadings', [RimLoadingController::class, 'storeLine'])->name('rim-loadings.store-line');
        Route::post('rim-loadings/advance', [RimLoadingController::class, 'advance'])->name('rim-loadings.advance');
        Route::post('rim-loadings/settle', [RimLoadingController::class, 'settle'])->name('rim-loadings.settle');
        Route::patch('rim-loadings/{rimLoading}/pay', [RimLoadingController::class, 'pay'])->name('rim-loadings.pay');
        Route::get('rim-loadings/{rimLoading}/edit', [RimLoadingController::class, 'edit'])->name('rim-loadings.edit');
        Route::put('rim-loadings/{rimLoading}', [RimLoadingController::class, 'update'])->name('rim-loadings.update');
        Route::delete('rim-loadings/{rimLoading}', [RimLoadingController::class, 'destroy'])->name('rim-loadings.destroy');
    });
    Route::get('rim-loadings', [RimLoadingController::class, 'index'])->middleware('can:orders.view')->name('rim-loadings.index');
    Route::get('rim-loadings/receipt', [RimLoadingController::class, 'receipt'])->middleware('can:orders.view')->name('rim-loadings.receipt');

    Route::resource('contractors', ContractorController::class)
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:contractors.manage');
    Route::middleware('can:contractors.manage')->group(function () {
        Route::post('contractors/{contractor}/payments', [ContractorController::class, 'pay'])->name('contractors.payments.store');
        Route::get('contractor-jobs/create', [ContractorJobController::class, 'create'])->name('contractor-jobs.create');
        Route::post('contractor-jobs', [ContractorJobController::class, 'store'])->name('contractor-jobs.store');
        Route::patch('contractor-jobs/{contractorJob}/done', [ContractorJobController::class, 'done'])->name('contractor-jobs.done');
        Route::get('contractor-jobs/{contractorJob}/edit', [ContractorJobController::class, 'edit'])->name('contractor-jobs.edit');
        Route::put('contractor-jobs/{contractorJob}', [ContractorJobController::class, 'update'])->name('contractor-jobs.update');
        Route::delete('contractor-jobs/{contractorJob}', [ContractorJobController::class, 'destroy'])->name('contractor-jobs.destroy');
        Route::get('contractor-payments/{payment}/edit', [ContractorController::class, 'editPayment'])->name('contractor-payments.edit');
        Route::put('contractor-payments/{payment}', [ContractorController::class, 'updatePayment'])->name('contractor-payments.update');
        Route::delete('contractor-payments/{payment}', [ContractorController::class, 'destroyPayment'])->name('contractor-payments.destroy');
    });

    Route::get('plate-jobs', [PlateJobController::class, 'index'])->middleware('can:orders.view')->name('plate-jobs.index');
    Route::middleware('can:plates.manage')->group(function () {
        Route::get('plate-jobs/create', [PlateJobController::class, 'create'])->name('plate-jobs.create');
        Route::post('plate-jobs', [PlateJobController::class, 'store'])->name('plate-jobs.store');
        Route::patch('plate-jobs/{plateJob}/pay', [BillPaymentController::class, 'payPlate'])->name('plate-jobs.pay');
        Route::post('plate-jobs/pay-all', [BillPaymentController::class, 'payAllPlates'])->name('plate-jobs.pay-all');
        // Money to a processor: clears the oldest bills; anything more is kept as an advance.
        Route::post('processors/{processor}/pay', [ProcessorPaymentController::class, 'store'])->name('processors.pay');
        Route::post('plate-jobs/payments', [ProcessorPaymentController::class, 'store'])->name('plate-jobs.payments.store');
        Route::post('plate-jobs/returns', [PlateJobController::class, 'returnPicked'])->name('plate-jobs.returns.store');
        Route::patch('plate-jobs/{plateJob}/return', [PlateJobController::class, 'returnPlates'])->name('plate-jobs.return');
        Route::get('plate-jobs/{plateJob}/edit', [PlateJobController::class, 'edit'])->name('plate-jobs.edit');
        Route::put('plate-jobs/{plateJob}', [PlateJobController::class, 'update'])->name('plate-jobs.update');
        Route::delete('plate-jobs/{plateJob}', [PlateJobController::class, 'destroy'])->name('plate-jobs.destroy');
    });

    // Plate processors, kept apart from the other parties.
    Route::resource('processors', ProcessorController::class)->parameters(['processors' => 'processor'])
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:plates.manage');

    Route::resource('dispatches', DispatchController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor(['index', 'show'], 'can:orders.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:dispatch.manage');

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
    Route::patch('inventory-issues/{inventoryIssue}/settle', [InventoryIssueController::class, 'settle'])->middleware('can:inventory.issue')->name('inventory-issues.settle');
    Route::redirect('purchases', 'inventory?tab=purchases')->name('purchases.index');
    Route::resource('purchases', PurchaseController::class)->only(['create', 'store'])->middleware('can:purchases.create');
    Route::get('plate-stock', [PlatePurchaseController::class, 'index'])->middleware('can:purchases.view')->name('plate-stock.index');
    Route::middleware('can:purchases.create')->group(function () {
        Route::get('plate-stock/create', [PlatePurchaseController::class, 'create'])->name('plate-stock.create');
        Route::post('plate-stock', [PlatePurchaseController::class, 'store'])->name('plate-stock.store');
        Route::get('plate-stock/{platePurchase}/edit', [PlatePurchaseController::class, 'edit'])->name('plate-stock.edit');
        Route::put('plate-stock/{platePurchase}', [PlatePurchaseController::class, 'update'])->name('plate-stock.update');
        Route::patch('plate-stock/{platePurchase}/pay', [BillPaymentController::class, 'payPlatePurchase'])->name('plate-stock.pay');
        Route::post('plate-stock/pay-all', [BillPaymentController::class, 'payAllPlatePurchases'])->name('plate-stock.pay-all');
    });
    Route::patch('purchases/{purchase}/pay', [BillPaymentController::class, 'payPurchase'])->middleware('can:purchases.create')->name('purchases.pay');
    Route::post('purchases/pay-all', [BillPaymentController::class, 'payAllPurchases'])->middleware('can:purchases.create')->name('purchases.pay-all');
    Route::get('inventory/materials/{inventory_category}/purchase', [PurchaseController::class, 'createMaterial'])->middleware('can:purchases.create')->name('materials.purchase');
    Route::post('inventory/materials/{inventory_category}/purchase', [PurchaseController::class, 'storeMaterial'])->middleware('can:purchases.create')->name('materials.purchase.store');
    Route::get('inventory/purchases/{purchase}/receipt', [PurchaseController::class, 'materialReceipt'])->middleware('can:purchases.view')->name('materials.receipt');
    Route::get('inventory/materials/{inventory_category}/sales/create', [SaleController::class, 'create'])->middleware('can:expenses.create')->name('sales.create');
    Route::post('inventory/materials/{inventory_category}/sales', [SaleController::class, 'store'])->middleware('can:expenses.create')->name('sales.store');
    Route::middleware('can:expenses.create')->group(function () {
        Route::get('inventory/materials/{inventory_category}/bills/create', [MaterialBillController::class, 'create'])->name('material-bills.create');
        Route::post('material-bills', [MaterialBillController::class, 'store'])->name('material-bills.store');
        Route::get('material-bills/{materialBill}/edit', [MaterialBillController::class, 'edit'])->name('material-bills.edit');
        Route::put('material-bills/{materialBill}', [MaterialBillController::class, 'update'])->name('material-bills.update');
        Route::patch('material-bills/{materialBill}/pay', [BillPaymentController::class, 'payBill'])->name('material-bills.pay');
        Route::post('inventory/materials/{inventory_category}/pay-all', [BillPaymentController::class, 'payAllBills'])->name('material-bills.pay-all');
    });
    Route::delete('bill-payments/{billPayment}', [BillPaymentController::class, 'destroy'])->name('bill-payments.destroy');
    Route::patch('sales/{sale}/receive', [SaleController::class, 'receive'])->middleware('can:expenses.create')->name('sales.receive');
    Route::post('inventory/materials/{inventory_category}/receive-all', [SaleController::class, 'receiveAll'])->middleware('can:expenses.create')->name('sales.receive-all');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->middleware('can:purchases.view')->name('sales.show');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'can:expenses.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:expenses.create');
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
    Route::patch('employees/{employee}/leave', [EmployeeController::class, 'leave'])->middleware('can:staff.manage')->name('employees.leave');
    Route::patch('employees/{employee}/rejoin', [EmployeeController::class, 'rejoin'])->middleware('can:staff.manage')->name('employees.rejoin');
    Route::resource('designations', DesignationController::class)->except('show')->middleware('can:staff.manage');

    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('can:staff.view')->name('attendance.index');
    Route::middleware('can:attendance.manage')->group(function () {
        Route::get('attendance/day', [AttendanceController::class, 'day'])->name('attendance.day');
        Route::put('attendance/day', [AttendanceController::class, 'saveDay'])->name('attendance.save-day');
        Route::post('attendance/shift', [AttendanceController::class, 'moveShift'])->name('attendance.move-shift');
        Route::get('attendance/import', [AttendanceController::class, 'importForm'])->name('attendance.import');
        Route::get('attendance/machine', [AttendanceMachineController::class, 'index'])->name('attendance.machine');
        Route::patch('attendance/machine/{device}', [AttendanceMachineController::class, 'approve'])->name('attendance.machine.approve');
        Route::post('attendance/machine/add-all', [AttendanceMachineController::class, 'addAll'])->middleware('can:staff.manage')->name('attendance.machine.add-all');
        Route::get('attendance/machine/{device}/reader-program', [AttendanceSyncController::class, 'program'])->name('attendance.machine.reader-program');
        Route::get('attendance/machine/{device}/reader-settings', [AttendanceSyncController::class, 'settingsFile'])->name('attendance.machine.reader-settings');
        Route::post('attendance/machine/{device}/fetch', [AttendanceMachineController::class, 'fetch'])->name('attendance.machine.fetch');
        Route::patch('attendance/machine/people/{deviceUser}', [AttendanceMachineController::class, 'link'])->name('attendance.machine.link');
        Route::post('attendance/import', [AttendanceController::class, 'import'])->name('attendance.import.store');
    });

    Route::get('salary-advances/mid-month', [SalaryAdvanceController::class, 'midMonth'])->middleware('can:advances.create')->name('salary-advances.mid-month');
    Route::post('salary-advances/mid-month', [SalaryAdvanceController::class, 'storeMidMonth'])->middleware('can:advances.create')->name('salary-advances.mid-month.store');
    Route::get('salary-advances/mid-month/slip/{employee}', [SalaryAdvanceController::class, 'midMonthSlip'])->middleware('can:advances.create')->name('salary-advances.mid-month.slip');
    Route::resource('salary-advances', SalaryAdvanceController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor(['index', 'show'], 'can:staff.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:advances.create');
    Route::resource('foreman-sheets', ForemanSheetController::class)->only(['create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor('show', 'can:staff.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:advances.create');
    Route::resource('loans', LoanController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->middlewareFor(['index', 'show'], 'can:staff.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'can:advances.create');
    Route::post('loans/{loan}/repayments', [LoanController::class, 'repay'])->middleware('can:advances.create')->name('loans.repay');

    Route::get('foreman-cash', [ForemanCashController::class, 'index'])->middleware('can:staff.view')->name('foreman-cash.index');
    Route::middleware('can:advances.create')->group(function () {
        Route::post('foreman-cash', [ForemanCashController::class, 'store'])->name('foreman-cash.store');
        Route::get('foreman-cash/{foremanCash}/edit', [ForemanCashController::class, 'edit'])->name('foreman-cash.edit');
        Route::put('foreman-cash/{foremanCash}', [ForemanCashController::class, 'update'])->name('foreman-cash.update');
    });
    Route::get('payroll', [PayrollRunController::class, 'index'])->middleware('can:staff.view')->name('payroll.index');
    Route::get('payroll/tenth', [PayrollRunController::class, 'tenth'])->middleware('can:staff.view')->name('payroll.tenth');
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

    // Whoever entered something the owner sent back can open it again, corrected, in the usual form.
    Route::get('sent-back/{type}/{id}/resend', [ApprovalController::class, 'resend'])->name('approvals.resend')
        ->whereIn('type', array_keys(ApprovalController::Types))->whereNumber('id');

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

    // Owner only, and not in the sidebar: opened by typing /deletions.
    Route::get('deletions', [DeletionController::class, 'index'])->name('deletions.index');
    Route::delete('records/{type}/{id}', [RecordDeletionController::class, 'destroy'])->whereNumber('id')->name('records.destroy');
    // Any record's details, read only; opened by clicking its row.
    Route::get('records/{type}/{id}', [RecordDetailController::class, 'show'])->whereNumber('id')->name('records.show');
});
