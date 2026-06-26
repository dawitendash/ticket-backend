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

class ChapaController extends Controller
{
    protected UserInformationService $service;

    public function __construct(UserInformationService $service)
    {
        $this->service = $service;
    }
 
    public function initialize(Request $request)
    {  
        // Validate request
        $request->validate([
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
            Log::error('User registration failed for phone: ' . $request->phone_number);
            return response()->json([
                'success' => false,
                'message' => 'Failed to register user information'
            ], 400);
        }

        // Get ticket type details
        $ticketType = TicketType::where('ticket_type_id', $request->ticket_type_id)->first();
        
        if (!$ticketType) {
            Log::error('Invalid ticket type: ' . $request->ticket_type_id);
            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket type'
            ], 400);
        }

        // Check if tickets are available
        if ($ticketType->available_tickets <= 0) {
            Log::warning('No tickets available for type: ' . $request->ticket_type_id);
            return response()->json([
                'success' => false,
                'message' => 'No tickets available for this ticket type'
            ], 400);
        }

        // Generate unique transaction reference
        $tx_ref = 'T' . time() . strtoupper(Str::random(6));
        
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
            // Generate a valid email from phone number if email is invalid or missing
            $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone_number);
            $email = $cleanPhone . '@example.com';
        }

        // Prepare data for Chapa
        $data = [
            'amount' => (float) $ticketType->price,
            'currency' => 'ETB',
            'email' => $email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'tx_ref' => $tx_ref,
            'callback_url' => route('chapa.callback', [$tx_ref]),
            'return_url' => rtrim(env('FRONTEND_URL', env('APP_URL')), '/') . "/purchase?tx_ref=" . $tx_ref,
            'customization' => [
                'title' => 'Ticket Payment',
                'description' => 'Payment for ' . $ticketType->display_name
            ],
            'phone_number' => $request->phone_number,
        ];

        // Log the data being sent for debugging
        Log::info('Chapa payment initialization data:', [
            'tx_ref' => $tx_ref,
            'amount' => $data['amount'],
            'email' => $data['email'],
            'ticket_type' => $ticketType->display_name
        ]);

        // Skip Chapa for local testing if enabled
        if (env('SKIP_CHAPA', false) && app()->environment('local')) {
            Log::info('Skipping Chapa - Test mode enabled');
            $ticket->update([
                'payment_status' => 'success',
                'status' => 'active',
                'payment_date' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'checkout_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/purchase?tx_ref=' . $tx_ref,
                'tx_ref' => $tx_ref,
                'message' => 'Test mode - Payment bypassed',
                'test_mode' => true
            ]);
        }

        try {
            $response = Http::withToken(env('CHAPA_SECRET_KEY'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post('https://api.chapa.co/v1/transaction/initialize', $data);

            // Log the response for debugging
            Log::info('Chapa API response:', [
                'status' => $response->status(),
                'tx_ref' => $tx_ref,
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if the response contains the expected data
                if (isset($responseData['data']['checkout_url'])) {
                    return response()->json([
                        'success' => true,
                        'checkout_url' => $responseData['data']['checkout_url'],
                        'tx_ref' => $tx_ref,
                        'message' => 'Payment initialized successfully'
                    ]);
                } else {
                    // Chapa returned success but missing checkout_url
                    Log::error('Chapa response missing checkout_url:', $responseData);
                    $ticket->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment initialization failed: Invalid response from payment gateway',
                        'details' => $responseData
                    ], 400);
                }
            }

            // If Chapa fails, delete the pending ticket
            $ticket->delete();

            // Get detailed error message
            $errorMessage = 'Payment initialization failed';
            $responseData = $response->json();
            
            if (isset($responseData['message'])) {
                $errorMessage = $responseData['message'];
            } elseif (isset($responseData['error'])) {
                $errorMessage = $responseData['error'];
            }

            Log::error('Chapa API error:', [
                'status' => $response->status(),
                'message' => $errorMessage,
                'response' => $responseData,
                'tx_ref' => $tx_ref
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'details' => $responseData ?? null,
            ], 400);

        } catch (\Exception $e) {
            // Delete the pending ticket on error
            if (isset($ticket)) {
                $ticket->delete();
            }
            
            Log::error('Chapa payment exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tx_ref' => $tx_ref
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
        
        // Make sure it's unique
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
            // Check if it's a valid base64 image
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
                Log::warning('Invalid base64 image format');
                return null;
            }
            
            $imageType = $matches[1]; // jpeg, png, etc.
            
            // Remove the data:image/{type};base64, part
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $imageData = base64_decode($imageData);
            
            if ($imageData === false) {
                Log::warning('Failed to decode base64 image');
                return null;
            }
            
            // Generate unique filename
            $filename = $prefix . '_' . time() . '_' . Str::random(10) . '.' . $imageType;
            
            // Ensure the directory exists
            $directory = public_path('uploads/national_ids');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Save the image
            $path = $directory . '/' . $filename;
            file_put_contents($path, $imageData);
            
            // Return the public URL
            return '/uploads/national_ids/' . $filename;
            
        } catch (\Exception $e) {
            Log::error('Failed to store image:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            // Check if user exists by phone number
            $user = UserInformation::where('phone_number', $request->phone_number)->first();
            
            // If user exists, return the existing user
            if ($user) {
                Log::info('Existing user found:', [
                    'phone' => $user->phone_number,
                    'user_id' => $user->id
                ]);
                return $user;
            }
            
            // Prepare user data
            $fullName = [
                'en' => trim($request->first_name . ' ' . $request->last_name),
                'am' => trim($request->first_name . ' ' . $request->last_name)
            ];
            
            $address = [
                'en' => $request->address,
                'am' => $request->address
            ];
            
            // Process and store images
            $frontImagePath = $this->storeBase64Image($request->national_id_front_image, 'front');
            $backImagePath = $this->storeBase64Image($request->national_id_back_image, 'back');
            
            // Create new user
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

            Log::info('New user created:', [
                'phone' => $user->phone_number,
                'user_id' => $user->id,
                'front_image' => $frontImagePath,
                'back_image' => $backImagePath
            ]);
            
            return $user;

        } catch (\Exception $e) {
            Log::error('User registration failed:', [
                'message' => $e->getMessage(),
                'phone' => $request->phone_number,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Process base64 image - validate and prepare for storage (deprecated, use storeBase64Image instead)
     */
    private function processBase64Image($base64Image)
    {
        return $this->storeBase64Image($base64Image, 'national_id');
    }

    public function verify(string $tx_ref)
    {
        Log::info('Payment verification initiated for tx_ref: ' . $tx_ref);

        try {
            $response = Http::withToken(env('CHAPA_SECRET_KEY'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get('https://api.chapa.co/v1/transaction/verify/' . $tx_ref);

            $data = $response->json();

            Log::info('Chapa verification response:', [
                'tx_ref' => $tx_ref,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $data['message'] ?? 'Payment verification failed',
                    'data' => $data['data'] ?? null,
                ], 400);
            }

            $payment = $data['data'] ?? [];
            $ticket = Ticket::where('order_reference', $tx_ref)->first();

            if (($payment['status'] ?? null) === 'success' && $ticket && $ticket->payment_status !== 'success') {
                $reference = $payment['reference'] ?? null;
                $ticket->update([
                    'payment_status' => 'success',
                    'receipt_url' => $reference ? 'https://chapa.link/payment-receipt/' . $reference : $ticket->receipt_url,
                    'status' => 'active',
                    'payment_date' => now(),
                    'chapa_reference' => $reference,
                ]);

                // Decrease available tickets
                $ticketType = TicketType::where('ticket_type_id', $ticket->ticket_type_id)->first();
                if ($ticketType && $ticketType->available_tickets > 0) {
                    $ticketType->decrement('available_tickets');
                    Log::info('Ticket availability decreased:', [
                        'ticket_type' => $ticketType->display_name,
                        'remaining' => $ticketType->available_tickets
                    ]);
                }

                $this->clearDashboardCache($ticket->concert_id);
                
                Log::info('Payment verified successfully for tx_ref: ' . $tx_ref);
            }

            return response()->json([
                'status' => $payment['status'] ?? 'pending',
                'message' => $data['message'] ?? 'Payment verification completed',
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment verification error:', [
                'tx_ref' => $tx_ref,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

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
            
            Log::info('Dashboard cache cleared for concert: ' . ($concertId ?? 'all'));
        } catch (\Exception $e) {
            Log::error('Failed to clear cache:', ['message' => $e->getMessage()]);
        }
    }

    public function callback(Request $request, $tx_ref)
    { 
        Log::info('Payment callback received:', [
            'tx_ref' => $tx_ref,
            'ip' => $request->ip(),
            'headers' => $request->headers->all()
        ]);

        // Verify Chapa signature
        $chapaSignature = $request->header('x-chapa-signature');
        $localHash = env('CHAPA_WEBHOOK_HASH');

        if (app()->environment('production') && $chapaSignature) {
            if ($chapaSignature !== $localHash) {
                Log::warning("Unauthorized Webhook Attempt!", [
                    'ref' => $tx_ref,
                    'signature' => $chapaSignature
                ]);
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        try {
            // Verify payment with Chapa
            $response = Http::withToken(env('CHAPA_SECRET_KEY'))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get('https://api.chapa.co/v1/transaction/verify/' . $tx_ref);
            
            $data = $response->json();

            Log::info('Chapa callback verification response:', [
                'tx_ref' => $tx_ref,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful() && isset($data['data']) && $data['data']['status'] === 'success') {
                Log::info('Payment callback verified successfully for: ' . $tx_ref);
                
                $chapaInternalRef = $data['data']['reference'] ?? 'NOT_FOUND';
                $officialReceiptUrl = "https://chapa.link/payment-receipt/" . $chapaInternalRef;

                // Find the ticket by order_reference
                $ticket = Ticket::where('order_reference', $tx_ref)->first();

                if ($ticket) {
                    // Update existing ticket to confirmed status
                    $ticket->update([
                        'payment_status' => 'success',
                        'receipt_url' => $officialReceiptUrl,
                        'status' => 'active',
                        'payment_date' => now(),
                        'chapa_reference' => $chapaInternalRef,
                    ]);

                    Log::info('Ticket updated successfully:', [
                        'ticket_code' => $ticket->ticket_code,
                        'tx_ref' => $tx_ref
                    ]);
                    
                    $this->clearDashboardCache($ticket->concert_id);
                    
                    // Decrease ticket quantity in TicketType
                    $ticketType = TicketType::where('ticket_type_id', $ticket->ticket_type_id)->first();
                    if ($ticketType && $ticketType->available_tickets > 0) {
                        $ticketType->decrement('available_tickets');
                        Log::info('Ticket availability decreased via callback:', [
                            'ticket_type' => $ticketType->display_name,
                            'remaining' => $ticketType->available_tickets
                        ]);
                    }

                    return view('payment_result', [
                        'status' => 'success',
                        'ticket' => $ticket,
                        'ticket_id' => $ticket->ticket_code,
                        'receipt_url' => $officialReceiptUrl,
                        'message' => 'Payment completed successfully!'
                    ]);

                } else {
                    // If ticket not found
                    Log::warning('Ticket not found for tx_ref: ' . $tx_ref);
                    
                    return view('payment_result', [
                        'status' => 'success',
                        'message' => 'Payment successful but ticket record not found. Please contact support.',
                        'receipt_url' => $officialReceiptUrl,
                        'ticket_id' => null
                    ]);
                }
            }

            // Handle failed payment
            Log::error("Payment callback verification failed for: " . $tx_ref);
            
            // Update ticket status to failed if exists
            $ticket = Ticket::where('order_reference', $tx_ref)->first();
            if ($ticket) {
                $ticket->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ]);
                Log::info('Ticket marked as failed:', ['ticket_code' => $ticket->ticket_code]);
            }

            return view('payment_result', [
                'status' => 'failed',
                'message' => 'Payment verification failed. Please try again.',
                'ticket_id' => $ticket->ticket_code ?? null
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