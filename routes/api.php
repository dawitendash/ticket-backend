<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ConcertController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserDeviceController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\UserInformationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChapaController;
use App\Http\Controllers\AttendanceLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =============================================
// PUBLIC ROUTES - No authentication required
// =============================================

// Public attendance stats
Route::get('attendance/stats/{concertId}', [AttendanceLogController::class, 'stats']);

Route::prefix('concerts')->group(function () {
    Route::get('/', [ConcertController::class, 'index']);
    Route::get('/upcoming', [ConcertController::class, 'upcoming']);
    Route::get('/next', [ConcertController::class, 'next']);
    Route::get('/{id}/ticket-types', [ConcertController::class, 'withTicketTypes']);
    Route::get('/{id}', [ConcertController::class, 'show']);
});

// Payment
Route::prefix('pay')->group(function () {
    Route::post('/chapa', [ChapaController::class, 'initialize']);
    Route::get('/chapa/verify/{tx_ref}', [ChapaController::class, 'verify']);
     Route::get('/chapa/callback/{tx_ref}', [ChapaController::class, 'callback'])
        ->name('chapa.callback');
});

Route::prefix('ticket-types')->group(function () {
    Route::get('/', [TicketTypeController::class, 'index']);
    Route::get('/{id}', [TicketTypeController::class, 'show']);
});

Route::post('/devices', [UserDeviceController::class, 'store']);
Route::get('payment-accounts/', [PaymentAccountController::class, 'index']);
Route::get('payment-accounts/{id}', [PaymentAccountController::class, 'show']);

// User Information
Route::prefix('user-informations')->group(function () {
    Route::post('/', [UserInformationController::class, 'store']);
    Route::get('/user/{userId}', [UserInformationController::class, 'getByUserId']);
    Route::get('/device/{deviceId}', [UserInformationController::class, 'getByDeviceId']);
    Route::get('/phone', [UserInformationController::class, 'getByPhoneNumber']);
});

// Tickets
Route::prefix('tickets')->group(function () {
    Route::post('/', [TicketController::class, 'store']);
    Route::get('/all', [TicketController::class, 'index']);
});

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgetPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});


// =============================================
// AUTHENTICATED ROUTES
// =============================================
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/', [UserController::class, 'index']);
        Route::get('/active', [UserController::class, 'active']);
        Route::get('/by-role', [UserController::class, 'byRole']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::patch('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });
});

// =============================================
// ADMIN ROUTES
// =============================================
Route::prefix('admin')->middleware(['auth:api', 'throttle:60,1', 'role:admin'])->group(function () {
    // Concerts
    Route::get('concerts/', [ConcertController::class, 'adminIndex']);
    Route::post('concerts/', [ConcertController::class, 'store']);
    Route::put('concerts/{id}', [ConcertController::class, 'update']);
    Route::patch('concerts/{id}/status', [ConcertController::class, 'updateStatus']);
    Route::delete('concerts/{id}', [ConcertController::class, 'destroy']);
    Route::get('concerts/{id}/statistics', [ConcertController::class, 'statistics']);

    // Ticket Types
    Route::get('ticket-types/', [TicketTypeController::class, 'adminIndex']);
    Route::post('ticket-types/', [TicketTypeController::class, 'store']);
    Route::put('ticket-types/{id}', [TicketTypeController::class, 'update']);
    Route::delete('ticket-types/{id}', [TicketTypeController::class, 'destroy']);
    Route::delete('ticket-types/{id}/with-tickets', [TicketTypeController::class, 'destroyWithTickets']);
    Route::post('ticket-types/{id}/soft-delete', [TicketTypeController::class, 'softDelete']);
    Route::get('ticket-types/{id}/statistics', [TicketTypeController::class, 'statistics']);
    Route::patch('ticket-types/{id}/restore', [TicketTypeController::class, 'restore']);
    Route::get('concert/{concertId}/analytics', [TicketTypeController::class, 'salesAnalytics']);

    // Roles
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('device', UserDeviceController::class);
    Route::get('device/statistics', [UserDeviceController::class, 'adminStatistics']);
    Route::patch('device/{id}/restore', [UserDeviceController::class, 'adminRestore']);

    // Payment Accounts
    Route::post('payment-accounts', [PaymentAccountController::class, 'store']);
    Route::put('payment-accounts/{id}', [PaymentAccountController::class, 'update']);
    Route::delete('payment-accounts/{id}', [PaymentAccountController::class, 'destroy']);
    Route::post('payment-accounts/{id}/default', [PaymentAccountController::class, 'makeDefault']);
    Route::post('payment-accounts/{id}/toggle-status', [PaymentAccountController::class, 'toggleStatus']);

    // User Information
    Route::get('user-informations/statistics', [UserInformationController::class, 'statistics']);
    Route::get('user-informations', [UserInformationController::class, 'index']);
    Route::get('user-informations/{id}', [UserInformationController::class, 'show']);

    // Ticket Management
    Route::get('ticket', [TicketController::class, 'adminIndex']);
    Route::post('ticket', [TicketController::class, 'store']);
    Route::put('ticket/{id}', [TicketController::class, 'update']);
    Route::delete('ticket/{id}', [TicketController::class, 'destroy']);
    Route::patch('ticket/{id}/restore', [TicketController::class, 'restore']);
    Route::post('ticket/{id}/cancel', [TicketController::class, 'cancel']);
    Route::post('ticket/{id}/refund', [TicketController::class, 'refund']);
    Route::get('ticket/statistics', [TicketController::class, 'statistics']);

    // =============================================
    // ATTENDANCE LOGS - ADMIN ROUTES
    // =============================================
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceLogController::class, 'index']);
        Route::get('/{id}', [AttendanceLogController::class, 'show']);
        Route::post('/', [AttendanceLogController::class, 'store']);
        Route::put('/{id}', [AttendanceLogController::class, 'update']);
        Route::patch('/{id}', [AttendanceLogController::class, 'update']);
        Route::delete('/{id}', [AttendanceLogController::class, 'destroy']);
        Route::get('/concert/{concertId}', [AttendanceLogController::class, 'concertLogs']);
        Route::get('/user/{userId}', [AttendanceLogController::class, 'userLogs']);
        Route::get('/statistics', [AttendanceLogController::class, 'statistics']);
        Route::get('/dashboard/summary', [AttendanceLogController::class, 'dashboardSummary']);
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'adminDashboard']);
    Route::get('dashboard/concert/{concertId}', [DashboardController::class, 'concertDashboard']);
    Route::get('dashboard/charts', [DashboardController::class, 'chartData']);
});

// =============================================
// SCANNER ROUTES
// =============================================
Route::prefix('scanner')->middleware(['auth:api', 'throttle:60,1', 'role:scanner,admin'])->group(function () {
    Route::post('/scan', [TicketController::class, 'scan']);
    Route::post('/validate', [TicketController::class, 'validateTicket']);
    Route::get('/dashboard', [DashboardController::class, 'scannerDashboard']);
    Route::get('/history', [AttendanceLogController::class, 'scannerHistory']);
    Route::get('/today', [AttendanceLogController::class, 'todayScans']);
    Route::get('/stats', [AttendanceLogController::class, 'scannerStats']);
});