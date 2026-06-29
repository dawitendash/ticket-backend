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
                'national_id_front_image' => 'nullable|file|image|max:5120', // 5MB max
                'national_id_back_image' => 'nullable|file|image|max:5120', // 5MB max
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
                // Create ticket only after successful payment
                $ticket = Ticket::create([
                    'ticket_type_id' => $request->ticket_type_id, 
                    'device_id' => $request->device_id,
                    'order_reference' => $tx_ref,
                    'payment_status' => 'success',
                    'concert_id' => $ticketType->concert_id,
                    'price_paid' => $ticketType->price,
                    'purchase_date' => now(),
                    'status' => 'active',
                    'user_information_id' => $user->id,
                    'ticket_code' => $this->generateTicketCode(),
                    'payment_date' => now(),
                ]);
                
                // Decrease available tickets
                $ticketType->decrement('available_tickets');
                
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
                    // Store pending payment in database instead of cache
                    $pendingPayment = [
                        'ticket_type_id' => $request->ticket_type_id,
                        'device_id' => $request->device_id,
                        'concert_id' => $ticketType->concert_id,
                        'price_paid' => $ticketType->price,
                        'user_information_id' => $user->id,
                        'tx_ref' => $tx_ref,
                        'created_at' => now()
                    ];
                    
                    // Store in a pending_payments table or use a database approach
                    // For now, we'll store in a JSON file or use a database table
                    // Option 1: Create a pending_payments table
                    // Option 2: Store in session
                    // Option 3: Store in database with a cleanup job
                    
                    // Using database approach - you'll need to create a PendingPayment model
                    // \App\Models\PendingPayment::create($pendingPayment);
                    
                    // For now, we'll store in session as a temporary solution
                    session(['pending_payment_' . $tx_ref => $pendingPayment]);
                    
                    // Commit only user registration
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
                    // Chapa returned success but missing checkout_url - rollback user
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment initialization failed: Invalid response from payment gateway',
                        'error' => $responseData
                    ], 400);
                }
            }

            // Chapa API failed - rollback user
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
     * Store uploaded file to public directory
     */
    private function storeUploadedFile($file, $prefix = 'national_id')
    {
        if (!$file) {
            return null;
        }
        
        try {
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = $prefix . '_' . time() . '_' . Str::random(10) . '.' . $extension;
            
            // Define the path relative to public directory
            $relativePath = 'uploads/national_ids/' . $filename;
            $fullPath = public_path($relativePath);
            
            // Create directory if it doesn't exist
            $directory = dirname($fullPath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Move the uploaded file
            $file->move($directory, $filename);
            
            // Return the public path
            return '/' . $relativePath;
            
        } catch (\Exception $e) {
            Log::error('File upload failed:', [
                'message' => $e->getMessage(),
                'prefix' => $prefix
            ]);
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
                // Update existing user's images if new ones are uploaded
                if ($request->hasFile('national_id_front_image')) {
                    $this->deleteUserImage($user->national_id_front_image);
                    $frontImagePath = $this->storeUploadedFile($request->file('national_id_front_image'), 'front');
                    if ($frontImagePath) {
                        $user->national_id_front_image = $frontImagePath;
                    }
                }
                
                if ($request->hasFile('national_id_back_image')) {
                    $this->deleteUserImage($user->national_id_back_image);
                    $backImagePath = $this->storeUploadedFile($request->file('national_id_back_image'), 'back');
                    if ($backImagePath) {
                        $user->national_id_back_image = $backImagePath;
                    }
                }
                
                // Update other fields if needed
                $user->save();
                
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
            
            // Store uploaded files
            $frontImagePath = null;
            $backImagePath = null;
            
            if ($request->hasFile('national_id_front_image')) {
                $frontImagePath = $this->storeUploadedFile($request->file('national_id_front_image'), 'front');
            }
            
            if ($request->hasFile('national_id_back_image')) {
                $backImagePath = $this->storeUploadedFile($request->file('national_id_back_image'), 'back');
            }
            
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
     * Delete a single user image
     */
    private function deleteUserImage($imagePath): void
    {
        if (!$imagePath) {
            return;
        }
        
        try {
            $fullPath = public_path($imagePath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
                Log::info('Deleted user image:', ['path' => $imagePath]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete user image:', [
                'path' => $imagePath,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete user images from storage
     */
    private function deleteUserImages(UserInformation $user): void
    {
        $this->deleteUserImage($user->national_id_front_image);
        $this->deleteUserImage($user->national_id_back_image);
    }

    /**
     * Verify Chapa payment - Creates ticket only on successful payment
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
            
            // Check if payment is successful
            if (isset($payment['status']) && $payment['status'] === 'success') {
                // Check if ticket already exists
                $ticket = Ticket::where('order_reference', $tx_ref)->first();
                
                if (!$ticket) {
                    // Get pending payment data from session
                    $pendingData = session('pending_payment_' . $tx_ref);
                    
                    if (!$pendingData) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment data expired or not found. Please contact support.',
                        ], 404);
                    }
                    
                    // Create ticket only now
                    DB::beginTransaction();
                    try {
                        $ticket = Ticket::create([
                            'ticket_type_id' => $pendingData['ticket_type_id'],
                            'device_id' => $pendingData['device_id'],
                            'order_reference' => $tx_ref,
                            'payment_status' => 'success',
                            'concert_id' => $pendingData['concert_id'],
                            'price_paid' => $pendingData['price_paid'],
                            'purchase_date' => now(),
                            'status' => 'active',
                            'user_information_id' => $pendingData['user_information_id'],
                            'ticket_code' => $this->generateTicketCode(),
                            'payment_date' => now(),
                            'chapa_reference' => $payment['reference'] ?? null,
                            'receipt_url' => isset($payment['reference']) ? 'https://chapa.link/payment-receipt/' . $payment['reference'] : null,
                        ]);
                        
                        // Decrease available tickets
                        $ticketType = TicketType::where('ticket_type_id', $pendingData['ticket_type_id'])->first();
                        if ($ticketType && $ticketType->available_tickets > 0) {
                            $ticketType->decrement('available_tickets');
                        }
                        
                        // Remove pending data from session
                        session()->forget('pending_payment_' . $tx_ref);
                        
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => [
                        'ticket' => $ticket,
                        'payment' => $payment
                    ]
                ]);
            }

            // Payment not successful - clean up any pending data
            session()->forget('pending_payment_' . $tx_ref);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment not successful',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            Log::error('Payment verification exception:', [
                'tx_ref' => $tx_ref,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Chapa callback - Creates ticket only on successful payment
     */
    public function callback(Request $request, $tx_ref)
    {
        try {
            // Verify Chapa signature (webhook security)
            $chapaSignature = $request->header('x-chapa-signature');
            $localHash = env('CHAPA_WEBHOOK_HASH');

            if (app()->environment('production') && $chapaSignature) {
                if ($chapaSignature !== $localHash) {
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

                // Check if ticket already exists
                $ticket = Ticket::where('order_reference', $tx_ref)->first();

                if (!$ticket) {
                    // Get pending payment data from session
                    $pendingData = session('pending_payment_' . $tx_ref);
                    
                    if (!$pendingData) {
                        return view('payment_result', [
                            'status' => 'failed',
                            'message' => 'Payment data expired or not found. Please contact support.',
                            'ticket_id' => null,
                            'receipt_url' => $officialReceiptUrl
                        ]);
                    }
                    
                    // Create ticket in a transaction
                    DB::beginTransaction();
                    try {
                        $ticket = Ticket::create([
                            'ticket_type_id' => $pendingData['ticket_type_id'],
                            'device_id' => $pendingData['device_id'],
                            'order_reference' => $tx_ref,
                            'payment_status' => 'success',
                            'concert_id' => $pendingData['concert_id'],
                            'price_paid' => $pendingData['price_paid'],
                            'purchase_date' => now(),
                            'status' => 'active',
                            'user_information_id' => $pendingData['user_information_id'],
                            'ticket_code' => $this->generateTicketCode(),
                            'payment_date' => now(),
                            'chapa_reference' => $chapaInternalRef,
                            'receipt_url' => $officialReceiptUrl,
                        ]);
                        
                        // Decrease available tickets
                        $ticketType = TicketType::where('ticket_type_id', $pendingData['ticket_type_id'])->first();
                        if ($ticketType && $ticketType->available_tickets > 0) {
                            $ticketType->decrement('available_tickets');
                        }
                        
                        // Remove pending data from session
                        session()->forget('pending_payment_' . $tx_ref);
                        
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }

                return view('payment_result', [
                    'status' => 'success',
                    'ticket' => $ticket,
                    'ticket_id' => $ticket->ticket_code,
                    'receipt_url' => $officialReceiptUrl,
                    'message' => 'Payment completed successfully!'
                ]);
            }

            // PAYMENT FAILED - Clean up any pending data
            session()->forget('pending_payment_' . $tx_ref);
            
            return view('payment_result', [
                'status' => 'failed',
                'message' => 'Payment verification failed. Please try again.',
                'ticket_id' => null
            ]);

        } catch (\Exception $e) {
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
}