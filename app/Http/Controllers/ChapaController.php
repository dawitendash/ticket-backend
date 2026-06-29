<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\UserInformation;  
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;  
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str; 
use App\Services\UserInformationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ChapaController extends Controller
{
    protected UserInformationService $service;

    public function __construct(UserInformationService $service)
    {
        $this->service = $service;
    }
 
    /**
     * Initialize Chapa payment
     * POST /api/pay/chapa/initialize
     */
    public function initialize(Request $request)
    {   
        // Start transaction for rollback
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'ticket_type_id' => 'required|string|exists:ticket_types,ticket_type_id',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20',
                'address' => 'required|string',
                'device_id' => 'required|string', 
                'national_id_number' => 'nullable|string',
                'national_id_front_image' => 'nullable|string',
                'national_id_back_image' => 'nullable|string',
                'email' => 'nullable|email|max:255',
            ]);

            // Register or get existing user
            $user = $this->registerUser($request);
            
            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to register user information'
                ], 400);
            }

            // Get ticket type details
            $ticketType = TicketType::where('ticket_type_id', $request->ticket_type_id)->first();
            
            if (!$ticketType) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid ticket type'
                ], 400);
            }

            // Check if tickets are available
            if ($ticketType->available_tickets <= 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No tickets available for this ticket type'
                ], 400);
            }

            // Generate unique transaction reference
            $tx_ref = 'TK-' . time() . strtoupper(Str::random(6));
            
            // Create ticket with proper data
            $ticket = Ticket::create([
                'ticket_type_id' => $request->ticket_type_id, 
                'device_id' => $request->device_id,
                'order_reference' => $tx_ref,
                'payment_status' => 'pending',   
                'concert_id' => $ticketType->concert_id,
                'price_paid' => $ticketType->price,
                'purchase_date' => now(),
                'status' => 'active',
                'user_information_id' => $user->id, 
            ]);

            // Prepare email - ensure it's valid
            $email = $request->email;
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone_number);
                $email = $cleanPhone . '@example.com';
            }

            // Prepare data for Chapa
            $chapaData = [
                'amount' => (string) $ticketType->price, 
                'currency' => 'ETB',
                'email' => $email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'tx_ref' => $tx_ref,
                'callback_url' => url('/api/pay/chapa/callback/' . $tx_ref),
                'return_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/purchase?tx_ref=' . $tx_ref,
                'customization' => [
                    'title' => 'Ticket Payment',
                    'description' => 'Payment for ' . $ticketType->display_name
                ]
            ];

            // Skip Chapa for local testing if enabled
            if (env('SKIP_CHAPA', false) && app()->environment('local')) {
                $ticket->update([
                    'payment_status' => 'success',
                    'status' => 'active',
                    'payment_date' => now(),
                ]);
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'checkout_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/purchase?tx_ref=' . $tx_ref,
                        'tx_ref' => $tx_ref
                    ],
                    'message' => 'Test mode - Payment bypassed',
                    'test_mode' => true
                ]);
            }

            // Call Chapa API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.chapa.secret_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post('https://api.chapa.co/v1/transaction/initialize', $chapaData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['data']['checkout_url'])) {
                    // Commit transaction - keep ticket and user
                    DB::commit();
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment initialized successfully',
                        'data' => [
                            'checkout_url' => $responseData['data']['checkout_url'],
                            'tx_ref' => $tx_ref
                        ]
                    ]);
                } else {
                    // Chapa returned success but missing checkout_url - rollback
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment initialization failed: Invalid response from payment gateway',
                        'error' => $responseData
                    ], 400);
                }
            }

            // Chapa API failed - rollback
            DB::rollBack();

            $errorMessage = 'Payment initialization failed';
            $responseData = $response->json();
            
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData['error'])) {
                $errorMessage = $responseData['error'];
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => $responseData ?? null,
            ], 400);

        } catch (\Exception $e) {
            // Rollback everything on exception
            DB::rollBack();
            
            Log::error('Chapa payment exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment service error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique ticket code
     */
    private function generateTicketCode(): string
    {
        $prefix = 'TKT';
        $code = $prefix . strtoupper(Str::random(10));
        
        while (Ticket::where('ticket_code', $code)->exists()) {
            $code = $prefix . strtoupper(Str::random(10));
        }
        
        return $code;
    }

    /**
     * Store base64 image to public directory
     */
    private function storeBase64Image($base64Image, $prefix = 'national_id')
    {
        if (empty($base64Image)) {
            return null;
        }
        
        try {
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
                return null;
            }
            
            $imageType = $matches[1];
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $imageData = base64_decode($imageData);
            
            if ($imageData === false) {
                return null;
            }
            
            $filename = $prefix . '_' . time() . '_' . Str::random(10) . '.' . $imageType;
            $directory = public_path('uploads/national_ids');
            
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $path = $directory . '/' . $filename;
            file_put_contents($path, $imageData);
            
            return '/uploads/national_ids/' . $filename;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Register or find existing user
     */
    private function registerUser(Request $request)
    {
        try {
            $user = UserInformation::where('phone_number', $request->phone_number)->first();
            
            if ($user) {
                return $user;
            }
            
            $fullName = [
                'en' => trim($request->first_name . ' ' . $request->last_name),
                'am' => trim($request->first_name . ' ' . $request->last_name)
            ];
            
            $address = [
                'en' => $request->address,
                'am' => $request->address
            ];
            
            $frontImagePath = $this->storeBase64Image($request->national_id_front_image, 'front');
            $backImagePath = $this->storeBase64Image($request->national_id_back_image, 'back');
            
            $user = UserInformation::create([
                'full_name' => json_encode($fullName),
                'phone_number' => $request->phone_number,
                'device_id' => $request->device_id,
                'address' => json_encode($address),
                'national_id_number' => $request->national_id_number ?? null,
                'national_id_front_image' => $frontImagePath,
                'national_id_back_image' => $backImagePath,
                'email' => $request->email ?? null,
            ]);
            
            return $user;

        } catch (\Exception $e) {
            Log::error('User registration failed:', [
                'message' => $e->getMessage(),
                'phone' => $request->phone_number
            ]);
            return null;
        }
    }

    /**
     * Verify Chapa payment - NO ROLLBACK HERE (just checking status)
     */
    public function verify(string $tx_ref)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.chapa.secret_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.chapa.co/v1/transaction/verify/' . $tx_ref);

            $data = $response->json();

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => $data['message'] ?? 'Payment verification failed',
                    'error' => $data['data'] ?? null,
                ], 400);
            }

            $payment = $data['data'] ?? [];
            $ticket = Ticket::where('order_reference', $tx_ref)->first();

            if (isset($payment['status']) && $payment['status'] === 'success' && $ticket && $ticket->payment_status !== 'success') {
                $reference = $payment['reference'] ?? null;
                $ticket->update([
                    'payment_status' => 'success',
                    'receipt_url' => $reference ? 'https://chapa.link/payment-receipt/' . $reference : $ticket->receipt_url,
                    'status' => 'active',
                    'payment_date' => now(),
                    'chapa_reference' => $reference,
                ]);

                $ticketType = TicketType::where('ticket_type_id', $ticket->ticket_type_id)->first();
                if ($ticketType && $ticketType->available_tickets > 0) {
                    $ticketType->decrement('available_tickets');
                }

                $this->clearDashboardCache($ticket->concert_id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => $payment
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $payment['status'] === 'success' ? 'Payment already processed' : 'Payment not successful',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear dashboard cache
     */
    private function clearDashboardCache(?string $concertId = null): void
    {
        try {
            Cache::forget('dashboard:admin');
            Cache::forget('dashboard:charts:week');
            Cache::forget('dashboard:charts:month');
            Cache::forget('dashboard:charts:year');
            Cache::forget('dashboard:scanner:current');

            if ($concertId) {
                Cache::forget('dashboard:concert:' . $concertId);
                Cache::forget('dashboard:scanner:' . $concertId);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear cache:', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Handle Chapa callback - WITH ROLLBACK ON FAILURE
     */
    public function callback(Request $request, $tx_ref)
    {
        // Start transaction for rollback
        DB::beginTransaction();
        
        try {
            // Verify Chapa signature (webhook security)
            $chapaSignature = $request->header('x-chapa-signature');
            $localHash = env('CHAPA_WEBHOOK_HASH');

            if (app()->environment('production') && $chapaSignature) {
                if ($chapaSignature !== $localHash) {
                    DB::rollBack();
                    return response()->json(['message' => 'Unauthorized'], 401);
                }
            }

            // Verify payment with Chapa
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.chapa.secret_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get('https://api.chapa.co/v1/transaction/verify/' . $tx_ref);
            
            $data = $response->json();

            // Check if payment was successful
            if ($response->successful() && isset($data['data']) && $data['data']['status'] === 'success') {
                $chapaInternalRef = $data['data']['reference'] ?? 'NOT_FOUND';
                $officialReceiptUrl = "https://chapa.link/payment-receipt/" . $chapaInternalRef;

                $ticket = Ticket::where('order_reference', $tx_ref)->first();

                if ($ticket) {
                    // Update ticket to confirmed status
                    $ticket->update([
                        'payment_status' => 'success',
                        'receipt_url' => $officialReceiptUrl,
                        'status' => 'active',
                        'payment_date' => now(),
                        'chapa_reference' => $chapaInternalRef,
                    ]);
                    
                    $this->clearDashboardCache($ticket->concert_id);
                    
                    // Decrease ticket quantity in TicketType
                    $ticketType = TicketType::where('ticket_type_id', $ticket->ticket_type_id)->first();
                    if ($ticketType && $ticketType->available_tickets > 0) {
                        $ticketType->decrement('available_tickets');
                    }

                    // Commit transaction - keep everything
                    DB::commit();

                    return view('payment_result', [
                        'status' => 'success',
                        'ticket' => $ticket,
                        'ticket_id' => $ticket->ticket_code,
                        'receipt_url' => $officialReceiptUrl,
                        'message' => 'Payment completed successfully!'
                    ]);

                } else {
                    // Ticket not found - rollback
                    DB::rollBack();
                    
                    return view('payment_result', [
                        'status' => 'success',
                        'message' => 'Payment successful but ticket record not found. Please contact support.',
                        'receipt_url' => $officialReceiptUrl,
                        'ticket_id' => null
                    ]);
                }
            }

            // PAYMENT FAILED - ROLLBACK EVERYTHING
            DB::rollBack();
            
            $ticket = Ticket::where('order_reference', $tx_ref)->first();
            
            if ($ticket) {
                // Get the user associated with this ticket
                $user = UserInformation::find($ticket->user_information_id);
                
                // Delete the ticket
                $ticket->delete();
                
                // If user has no other tickets and was created recently, delete them too
                if ($user) {
                    $hasOtherTickets = Ticket::where('user_information_id', $user->id)
                        ->where('order_reference', '!=', $tx_ref)
                        ->exists();
                    
                    if (!$hasOtherTickets) {
                        // Check if user was created in the last 30 minutes (fresh user)
                        $isNewUser = $user->created_at->diffInMinutes(now()) <= 30;
                        
                        if ($isNewUser) {
                            // Delete user and their uploaded images
                            $this->deleteUserImages($user);
                            $user->delete();
                            Log::info('User deleted due to payment failure:', ['user_id' => $user->id]);
                        } else {
                            // Existing user - just mark as inactive
                            $user->update(['is_active' => false]);
                            Log::info('User marked inactive due to payment failure:', ['user_id' => $user->id]);
                        }
                    }
                }
                
                Log::info('Ticket deleted due to payment failure:', [
                    'ticket_id' => $ticket->id,
                    'tx_ref' => $tx_ref
                ]);
            }

            return view('payment_result', [
                'status' => 'failed',
                'message' => 'Payment verification failed. Please try again.',
                'ticket_id' => $ticket->ticket_code ?? null
            ]);

        } catch (\Exception $e) {
            // Rollback on any exception
            DB::rollBack();
            
            Log::error('Callback error:', [
                'tx_ref' => $tx_ref,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return view('payment_result', [
                'status' => 'failed',
                'message' => 'An error occurred while processing your payment. Please contact support.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ]);
        }
    }

    /**
     * Delete user images from storage
     */
    private function deleteUserImages(UserInformation $user): void
    {
        try {
            if ($user->national_id_front_image) {
                $path = public_path($user->national_id_front_image);
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            
            if ($user->national_id_back_image) {
                $path = public_path($user->national_id_back_image);
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete user images:', ['message' => $e->getMessage()]);
        }
    }
}