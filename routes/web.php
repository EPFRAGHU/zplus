<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\GemContractController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PiPortalController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaxInvoiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Every signed-in user
Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/releases', 'releases.index')->name('releases');
    Route::get('/choose-company', [\App\Http\Controllers\AgencyChoiceController::class, 'show'])->name('agency.choose');
    Route::post('/choose-company', [\App\Http\Controllers\AgencyChoiceController::class, 'store'])->name('agency.choose.store');
    Route::get('/account/password', [AccountController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'update'])->middleware('throttle:6,1')->name('account.password.update');
});

// PI portal: a PI sees only the projects they are in charge of
Route::middleware(['auth', 'active', 'role:pi'])->prefix('pi')->name('pi.')->group(function () {
    Route::get('/', [PiPortalController::class, 'home'])->name('home');
    Route::post('/projects/{project}/months', [PiPortalController::class, 'start'])->name('start');
    Route::get('/attendance/{sheet}', [PiPortalController::class, 'sheet'])->name('sheet');
    Route::put('/attendance/{sheet}', [PiPortalController::class, 'save'])->name('save');
    Route::post('/attendance/{sheet}/submit', [PiPortalController::class, 'submit'])->name('submit');
    Route::get('/attendance/{sheet}/pdf', [PiPortalController::class, 'pdf'])->name('pdf');
    Route::post('/attendance/{sheet}/signed', [PiPortalController::class, 'uploadSigned'])->name('signed');
    Route::get('/attendance/{sheet}/signed', [PiPortalController::class, 'signed'])->name('signed.show');
    Route::get('/bills', [PiPortalController::class, 'bills'])->name('bills');
    Route::get('/bills/{invoice}/pdf', [PiPortalController::class, 'billPdf'])->name('bills.pdf');
});

// Agency staff
Route::middleware(['auth', 'active', 'role:admin,accounts,supervisor', \App\Http\Middleware\GuardAgencyInput::class])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Activity log (Super Admin)
    Route::get('activity', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity.index');
    Route::get('backups', [\App\Http\Controllers\BackupStatusController::class, 'index'])->name('backups.index');   // developer only (owner, 26 Nov 2026)
    Route::get('activity/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->middleware('throttle:20,1')->name('activity.export');

    // Users (Admin)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

    // Masters
    Route::resource('agencies', AgencyController::class)->except(['show', 'destroy']);
    Route::get('agencies/{agency}/logo', [AgencyController::class, 'logo'])->name('agencies.logo');
    Route::get('reports/post-wise', [\App\Http\Controllers\ReportController::class, 'postWise'])->name('reports.post-wise');
    Route::get('reports/post-wise/pdf', [\App\Http\Controllers\ReportController::class, 'postWisePdf'])->name('reports.post-wise.pdf');
    Route::post('contracts/{contract}/setup', [GemContractController::class, 'setup'])->name('contracts.setup');
    Route::get('clients/merge', [\App\Http\Controllers\ClientMergeController::class, 'show'])->name('clients.merge');
    Route::post('clients/merge', [\App\Http\Controllers\ClientMergeController::class, 'store'])->name('clients.merge.store');
    Route::resource('clients', ClientController::class)->except(['show', 'destroy']);
    Route::get('contracts/export', [GemContractController::class, 'export'])->middleware('throttle:20,1')->name('contracts.export');
    Route::resource('contracts', GemContractController::class)->except(['destroy']);
    Route::resource('incharges', \App\Http\Controllers\ProjectInchargeController::class)->except(['show', 'destroy']);
    Route::get('staff', [\App\Http\Controllers\StaffController::class, 'index'])->name('staff.index');
    Route::post('contracts/{contract}/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('delete-mode', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Gate::authorize('delete-masters');
        $request->session()->put('delete_mode', ! $request->session()->get('delete_mode'));

        return back()->with('status', $request->session()->get('delete_mode') ? 'Delete options shown. Switch them off when you are done.' : 'Delete options hidden.');
    })->name('delete-mode');
    Route::delete('agencies/{agency}', [AgencyController::class, 'destroy'])->name('agencies.destroy');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::delete('contracts/{contract}', [GemContractController::class, 'destroy'])->name('contracts.destroy');
    Route::delete('incharges/{incharge}', [\App\Http\Controllers\ProjectInchargeController::class, 'destroy'])->name('incharges.destroy');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/move', [ProjectController::class, 'move'])->name('projects.move');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::post('posts/quick', [PostController::class, 'quickStore'])->name('posts.quick');
    Route::post('projects/{project}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::post('employees/{employee}/send-for-approval', [EmployeeController::class, 'sendForApproval'])->name('employees.send-for-approval');
    Route::patch('employees/{employee}/statutory', [EmployeeController::class, 'statutory'])->name('employees.statutory');
    Route::get('employees/{employee}/appointment-letter', [EmployeeController::class, 'appointmentLetter'])->name('employees.appointment-letter');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
    Route::get('documents/{document}', [EmployeeDocumentController::class, 'show'])->name('documents.show');
    Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');

    // Attendance
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance', [AttendanceController::class, 'open'])->name('attendance.open');
    Route::get('attendance/{sheet}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::put('attendance/{sheet}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::post('attendance/{sheet}/lock', [AttendanceController::class, 'lock'])->name('attendance.lock');
    Route::post('attendance/{sheet}/unlock', [AttendanceController::class, 'unlock'])->name('attendance.unlock');
    Route::post('attendance/{sheet}/reset', [AttendanceController::class, 'reset'])->name('attendance.reset');
    Route::get('attendance/{sheet}/salary-payouts', [AttendanceController::class, 'payouts'])->name('attendance.payouts');
    Route::get('attendance/{sheet}/bank-letter', [AttendanceController::class, 'bankLetter'])->name('attendance.bank-letter');
    Route::get('bank-letters', [\App\Http\Controllers\BankLetterController::class, 'index'])->name('bank-letters.index');
    Route::get('bank-letters/pdf', [\App\Http\Controllers\BankLetterController::class, 'pdf'])->name('bank-letters.pdf');
    Route::get('bank-letters/excel', [\App\Http\Controllers\BankLetterController::class, 'excel'])->middleware('throttle:20,1')->name('bank-letters.excel');
    Route::get('bank-letters/neft-excel', [\App\Http\Controllers\BankLetterController::class, 'neftExcel'])->middleware('throttle:20,1')->name('bank-letters.neft-excel');
    Route::get('bank-letters/neft-pdf', [\App\Http\Controllers\BankLetterController::class, 'neftPdf'])->middleware('throttle:20,1')->name('bank-letters.neft-pdf');
    Route::get('month-documents.zip', [\App\Http\Controllers\SignedDocumentController::class, 'zip'])->middleware('throttle:20,1')->name('month-documents.zip');
    Route::post('invoices/{invoice}/signed', [\App\Http\Controllers\SignedDocumentController::class, 'storeForInvoice'])->name('invoices.signed');
    Route::post('tax-invoices/{taxInvoice}/signed', [\App\Http\Controllers\SignedDocumentController::class, 'storeForTaxInvoice'])->name('tax-invoices.signed');
    Route::post('bank-letters/signed', [\App\Http\Controllers\SignedDocumentController::class, 'storeForBankLetter'])->name('bank-letters.signed');
    Route::post('invoices/{invoice}/email', [\App\Http\Controllers\AgencyEmailController::class, 'invoice'])->middleware('throttle:10,1')->name('invoices.email');
    Route::post('tax-invoices/{taxInvoice}/email', [\App\Http\Controllers\AgencyEmailController::class, 'taxInvoice'])->middleware('throttle:10,1')->name('tax-invoices.email');
    Route::post('contracts/{contract}/import', [\App\Http\Controllers\ContractImportController::class, 'upload'])->middleware('throttle:20,1')->name('contracts.import');
    Route::get('contracts/{contract}/import', [\App\Http\Controllers\ContractImportController::class, 'show'])->name('contracts.import.show');
    Route::post('contracts/{contract}/import/save', [\App\Http\Controllers\ContractImportController::class, 'store'])->name('contracts.import.store');
    Route::post('contracts/{contract}/papers', [\App\Http\Controllers\MonthlyPaperController::class, 'store'])->name('papers.store');
    Route::get('papers/{paper}', [\App\Http\Controllers\MonthlyPaperController::class, 'show'])->name('papers.show');
    Route::delete('papers/{paper}', [\App\Http\Controllers\MonthlyPaperController::class, 'destroy'])->name('papers.destroy');
    Route::get('contracts/{contract}/bill-pack', [\App\Http\Controllers\MonthlyPaperController::class, 'pack'])->middleware('throttle:20,1')->name('contracts.bill-pack');
    Route::get('approvals', [\App\Http\Controllers\ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{approval}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{approval}/return', [\App\Http\Controllers\ApprovalController::class, 'returnBack'])->name('approvals.return');
    Route::post('attendance/{sheet}/send-for-approval', [\App\Http\Controllers\ApprovalController::class, 'sendAttendance'])->name('approvals.send.attendance');
    Route::post('invoices/{invoice}/send-for-approval', [\App\Http\Controllers\ApprovalController::class, 'sendInvoice'])->name('approvals.send.invoice');
    Route::post('tax-invoices/{taxInvoice}/send-for-approval', [\App\Http\Controllers\ApprovalController::class, 'sendTaxInvoice'])->name('approvals.send.tax-invoice');
    Route::post('bank-letters/send-for-approval', [\App\Http\Controllers\ApprovalController::class, 'sendPayout'])->name('approvals.send.payout');
    Route::get('signed-documents/{signedDocument}', [\App\Http\Controllers\SignedDocumentController::class, 'show'])->name('signed-documents.show');
    Route::get('attendance/{sheet}/certificate', [AttendanceController::class, 'certificate'])->name('attendance.certificate');
    Route::get('attendance/{sheet}/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('attendance/{sheet}/submitted-pdf', [AttendanceController::class, 'submittedPdf'])->name('attendance.submitted-pdf');

    // Billing
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::post('invoices/{invoice}/recalculate', [InvoiceController::class, 'recalculate'])->name('invoices.recalculate');
    Route::post('invoices-recalculate-outdated', [InvoiceController::class, 'recalculateOutdated'])->name('invoices.recalculate-outdated');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/covering-letter', [InvoiceController::class, 'coveringLetter'])->name('invoices.covering-letter');

    // Month bill: an agency's month in one sheet, one row per work order (internal summary, owner 22 Nov 2026)
    Route::get('month-bill', [\App\Http\Controllers\MonthBillController::class, 'show'])->name('month-bill.show');
    Route::get('month-bill/pdf', [\App\Http\Controllers\MonthBillController::class, 'pdf'])->name('month-bill.pdf');
    Route::get('month-bill/sub-bills', [\App\Http\Controllers\MonthBillController::class, 'subBills'])->name('month-bill.sub-bills');
    Route::get('month-bill/consolidated-tax-invoice', [\App\Http\Controllers\MonthBillController::class, 'consolidated'])->name('month-bill.consolidated');

    // Payments received from the client: receipts split over sub-bills with TDS / GST TDS (owner, 22 Nov 2026)
    Route::get('payments', [\App\Http\Controllers\PaymentReceiptController::class, 'index'])->name('payments.index');
    Route::get('payments/create', [\App\Http\Controllers\PaymentReceiptController::class, 'create'])->name('payments.create');
    Route::post('payments', [\App\Http\Controllers\PaymentReceiptController::class, 'store'])->name('payments.store');
    Route::get('payments/export/{tab}.{format}', [\App\Http\Controllers\PaymentReceiptController::class, 'export'])->name('payments.export');
    Route::get('payments/{receipt}', [\App\Http\Controllers\PaymentReceiptController::class, 'show'])->name('payments.show');
    Route::get('payments/{receipt}/edit', [\App\Http\Controllers\PaymentReceiptController::class, 'edit'])->name('payments.edit');
    Route::put('payments/{receipt}', [\App\Http\Controllers\PaymentReceiptController::class, 'update'])->name('payments.update');
    Route::post('payments/{receipt}/approve', [\App\Http\Controllers\PaymentReceiptController::class, 'approve'])->name('payments.approve');
    Route::post('payments/{receipt}/return', [\App\Http\Controllers\PaymentReceiptController::class, 'sendBack'])->name('payments.return');
    Route::post('payments/{receipt}/checks', [\App\Http\Controllers\PaymentReceiptController::class, 'checks'])->name('payments.checks');
    Route::delete('payments/{receipt}', [\App\Http\Controllers\PaymentReceiptController::class, 'destroy'])->name('payments.destroy');
    Route::get('payments/{receipt}/advice', [\App\Http\Controllers\PaymentReceiptController::class, 'advice'])->name('payments.advice');

    // Post-wise summary of sub-bills for GeM returns: work order → sub-bill → post (owner, 22 Nov 2026)
    Route::get('post-summary', [\App\Http\Controllers\PostSummaryController::class, 'show'])->name('post-summary.show');
    Route::get('post-summary/pdf', [\App\Http\Controllers\PostSummaryController::class, 'pdf'])->name('post-summary.pdf');
    Route::get('post-summary/excel', [\App\Http\Controllers\PostSummaryController::class, 'excel'])->name('post-summary.excel');

    // Tax invoices (combine a client's sub-bills for a month)
    Route::get('tax-invoices', [TaxInvoiceController::class, 'index'])->name('tax-invoices.index');
    Route::get('tax-invoices/create', [TaxInvoiceController::class, 'create'])->name('tax-invoices.create');
    Route::post('tax-invoices', [TaxInvoiceController::class, 'store'])->name('tax-invoices.store');
    Route::get('tax-invoices/{taxInvoice}', [TaxInvoiceController::class, 'show'])->name('tax-invoices.show');
    Route::post('tax-invoices/{taxInvoice}/status', [TaxInvoiceController::class, 'status'])->name('tax-invoices.status');
    Route::post('tax-invoices/{taxInvoice}/recalculate', [TaxInvoiceController::class, 'recalculate'])->name('tax-invoices.recalculate');
    Route::delete('tax-invoices/{taxInvoice}', [TaxInvoiceController::class, 'destroy'])->name('tax-invoices.destroy');
    Route::get('tax-invoices/{taxInvoice}/pdf', [TaxInvoiceController::class, 'pdf'])->name('tax-invoices.pdf');

    // Master data from Excel templates
    Route::get('data-upload', [\App\Http\Controllers\MasterImportController::class, 'index'])->name('master-import.index');
    Route::get('data-upload/{type}/template', [\App\Http\Controllers\MasterImportController::class, 'template'])->name('master-import.template');
    Route::post('data-upload/{type}/check', [\App\Http\Controllers\MasterImportController::class, 'preview'])->middleware('throttle:20,1')->name('master-import.preview');
    Route::post('data-upload/{type}/save', [\App\Http\Controllers\MasterImportController::class, 'commit'])->name('master-import.commit');

    // Excel import
    Route::get('import', [ImportController::class, 'create'])->name('import.create');
    Route::post('import/preview', [ImportController::class, 'preview'])->middleware('throttle:20,1')->name('import.preview');
    Route::post('import/commit', [ImportController::class, 'commit'])->name('import.commit');
});
