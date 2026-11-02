<?php

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
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaxInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::view('/releases', 'releases.index')->name('releases');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

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

    // Excel import
    Route::get('import', [ImportController::class, 'create'])->name('import.create');
    Route::post('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import/commit', [ImportController::class, 'commit'])->name('import.commit');
});
