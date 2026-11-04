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
    Route::get('activity/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->name('activity.export');

    // Users (Admin)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

    // Masters
    Route::resource('agencies', AgencyController::class)->except(['show', 'destroy']);
    Route::get('agencies/{agency}/logo', [AgencyController::class, 'logo'])->name('agencies.logo');
    Route::resource('clients', ClientController::class)->except(['show', 'destroy']);
    Route::resource('contracts', GemContractController::class)->except(['destroy']);
    Route::post('contracts/{contract}/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::post('posts/quick', [PostController::class, 'quickStore'])->name('posts.quick');
    Route::post('projects/{project}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::resource('employees', EmployeeController::class)->except(['destroy']);
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
    Route::get('attendance/{sheet}/salary-payouts', [AttendanceController::class, 'payouts'])->name('attendance.payouts');
    Route::get('attendance/{sheet}/certificate', [AttendanceController::class, 'certificate'])->name('attendance.certificate');
    Route::get('attendance/{sheet}/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('attendance/{sheet}/submitted-pdf', [AttendanceController::class, 'submittedPdf'])->name('attendance.submitted-pdf');

    // Billing
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/covering-letter', [InvoiceController::class, 'coveringLetter'])->name('invoices.covering-letter');

    // Tax invoices (combine a client's sub-bills for a month)
    Route::get('tax-invoices', [TaxInvoiceController::class, 'index'])->name('tax-invoices.index');
    Route::get('tax-invoices/create', [TaxInvoiceController::class, 'create'])->name('tax-invoices.create');
    Route::post('tax-invoices', [TaxInvoiceController::class, 'store'])->name('tax-invoices.store');
    Route::get('tax-invoices/{taxInvoice}', [TaxInvoiceController::class, 'show'])->name('tax-invoices.show');
    Route::post('tax-invoices/{taxInvoice}/status', [TaxInvoiceController::class, 'status'])->name('tax-invoices.status');
    Route::delete('tax-invoices/{taxInvoice}', [TaxInvoiceController::class, 'destroy'])->name('tax-invoices.destroy');
    Route::get('tax-invoices/{taxInvoice}/pdf', [TaxInvoiceController::class, 'pdf'])->name('tax-invoices.pdf');

    // Master data from Excel templates
    Route::get('data-upload', [\App\Http\Controllers\MasterImportController::class, 'index'])->name('master-import.index');
    Route::get('data-upload/{type}/template', [\App\Http\Controllers\MasterImportController::class, 'template'])->name('master-import.template');
    Route::post('data-upload/{type}/check', [\App\Http\Controllers\MasterImportController::class, 'preview'])->name('master-import.preview');
    Route::post('data-upload/{type}/save', [\App\Http\Controllers\MasterImportController::class, 'commit'])->name('master-import.commit');

    // Excel import
    Route::get('import', [ImportController::class, 'create'])->name('import.create');
    Route::post('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import/commit', [ImportController::class, 'commit'])->name('import.commit');
});
