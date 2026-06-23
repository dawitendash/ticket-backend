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
use Illuminate\Support\Facades\Route;

  Route::prefix('concerts')->group(function () { //tested
        Route::get('/', [ConcertController::class, 'index']); 
        Route::get('/upcoming', [ConcertController::class, 'upcoming']);  
        Route::get('/next', [ConcertController::class, 'next']); 
        Route::get('/{id}/ticket-types', [ConcertController::class, 'withTicketTypes']);
        Route::get('/{id}', [ConcertController::class, 'show']);
});
//payment
    Route::post('/pay/chapa', [ChapaController::class, 'initialize']);
    // Route for Chapa to verify the payment (Webhook)
    Route::get('/pay/chapa/callback/{tx_ref}', [ChapaController::class, 'callback'])->name('chapa.callback');

   Route::prefix('ticket-types')->group(function () {
        Route::get('/', [TicketTypeController::class, 'index']);
        //Route::get('/concert/{concertId}', [TicketTypeController::class, 'getByConcert']);
        //Route::get('/concert/{concertId}/available', [TicketTypeController::class, 'getAvailableByConcert']);
        //Route::get('/{id}/availability', [TicketTypeController::class, 'checkAvailability']);
        //Route::get('/{id}', [TicketTypeController::class, 'show']);
    });
    Route::post('/devices', [UserDeviceController::class, 'store']);
    Route::get('payment-accounts/', [PaymentAccountController::class, 'index']);
    Route::get('payment-accounts/{id}', [PaymentAccountController::class, 'show']);
    
    // user information tetsed
    Route::prefix('user-informations')->group(function () { // tested
        Route::post('/', [UserInformationController::class, 'store']);
        Route::get('/user/{userId}', [UserInformationController::class, 'getByUserId']);
        Route::get('/device/{deviceId}', [UserInformationController::class, 'getByDeviceId']);
        Route::get('/phone', [UserInformationController::class, 'getByPhoneNumber']);
       
    });

    // ticket 

    Route::prefix('tickets')->group(function () {
        Route::post('/', [TicketController::class, 'store']);
        Route::get('/', [TicketController::class, 'index']);  
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('/forgot-password', [AuthController::class, 'forgetPassword'])->middleware('throttle:3,1');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

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

      Route::prefix('admin')->middleware(['auth:api','throttle:60,1', 'role:admin'])->group(function () {//tested
                Route::get('concerts/', [ConcertController::class, 'adminIndex']);
                Route::post('concerts/', [ConcertController::class, 'store']); 
                Route::put('concerts/{id}', [ConcertController::class, 'update']); 
                Route::patch('concerts/{id}/status', [ConcertController::class, 'updateStatus']); 
                Route::delete('concerts/{id}', [ConcertController::class, 'destroy']); 
               // Route::patch('concerts/{id}/restore', [ConcertController::class, 'restore']); 
                Route::get('concerts/{id}/statistics', [ConcertController::class, 'statistics']);
               
                //ticket-type
                Route::get('ticket-types/', [TicketTypeController::class, 'adminIndex']);
                Route::post('ticket-types/', [TicketTypeController::class, 'store']);
               // Route::post('ticket-types/bulk', [TicketTypeController::class, 'bulkStore']);
                Route::put('ticket-types/{id}', [TicketTypeController::class, 'update']);
                Route::delete('ticket-types/{id}', [TicketTypeController::class, 'destroy']);
                Route::delete('ticket-types/{id}/with-tickets', [TicketTypeController::class, 'destroyWithTickets']);
                Route::post('ticket-types/{id}/soft-delete', [TicketTypeController::class, 'softDelete']);
               // Route::patch('ticket-types/{id}/restore', [TicketTypeController::class, 'restore']);
                Route::get('ticket-types/{id}/statistics', [TicketTypeController::class, 'statistics']);
                Route::patch('ticket-types/{id}/restore', [TicketTypeController::class, 'restore']); 
                Route::get('concert/{concertId}/analytics', [TicketTypeController::class, 'salesAnalytics']);

                //roles managemet
                 Route::apiResource('roles', RoleController::class);
                 Route::apiResource('device', UserDeviceController::class);
                 //device managment
                // Route::get('device/statistics', [UserDeviceController::class, 'statistics']);
                  Route::get('device/statistics', [UserDeviceController::class, 'adminStatistics']);
                  Route::patch('device/{id}/restore', [UserDeviceController::class, 'adminRestore']);

                  //payment-account  tested
            
                    
                    Route::post('payment-accounts/', [PaymentAccountController::class, 'store']);
                    Route::put('payment-accounts/{id}', [PaymentAccountController::class, 'update']);
                    Route::delete('payment-accounts/{id}', [PaymentAccountController::class, 'destroy']);
                   // Route::patch('payment-accounts/{id}/restore', [PaymentAccountController::class, 'restore']);
                    
                     
                    Route::post('payment-accounts/{id}/default', [PaymentAccountController::class, 'makeDefault']);
                    Route::post('payment-accounts/{id}/toggle-status', [PaymentAccountController::class, 'toggleStatus']);

                    // user information management  tested
                    Route::get('user-informations/statistics', [UserInformationController::class, 'statistics']);
                    Route::get('/user-informations', [UserInformationController::class, 'index']);
                    Route::get('/{id}', [UserInformationController::class, 'show']);

                    // ticket management
                    Route::get('ticket/', [TicketController::class, 'adminIndex']);
                    Route::post('ticket/', [TicketController::class, 'store']);
                    //Route::post('ticket/bulk', [TicketController::class, 'bulkStore']);
                    Route::put('ticket/{id}', [TicketController::class, 'update']);
                    Route::delete('ticket/{id}', [TicketController::class, 'destroy']);
                    Route::patch('ticket/{id}/restore', [TicketController::class, 'restore']);
                    Route::post('ticket/{id}/cancel', [TicketController::class, 'cancel']);
                    Route::post('ticket/{id}/refund', [TicketController::class, 'refund']);
                    Route::get('ticket/statistics', [TicketController::class, 'statistics']);

                    // dashboard
                    Route::get('/dashboard', [DashboardController::class, 'adminDashboard']);
                        Route::get('dashboard/concert/{concertId}', [DashboardController::class, 'concertDashboard']);
                        Route::get('dashboard/charts', [DashboardController::class, 'chartData']);
           });


        

                Route::prefix('scanner')->middleware(['auth:api','throttle:60,1', 'role:scanner,admin'])->group(function () {
                
                    Route::post('/scan', [TicketController::class, 'scan']);
                    Route::post('/validate', [TicketController::class, 'validateTicket']);
                     Route::get('/dashboard', [DashboardController::class, 'scannerDashboard']);
                });