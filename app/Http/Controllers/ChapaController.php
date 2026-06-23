<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\UserInformation;  
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;  
use Illuminate\Support\Str; 
use App\services\UserInformationService;

class ChapaController extends Controller
{
    protected UserInformationService $service;

    public function __construct(UserInformationService $service)
    {
        $this->service = $service;
    }
 
    public function initialize(Request $request)
    {  
        $user = $this->registerUser($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register user information'
            ], 400);
        }

        // 2. Then proceed with payment initialization
        $ticketAmount = TicketType::where('ticket_type_id', $request->ticket_type_id)->first();
        
        if (!$ticketAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket type'
            ], 400);
        }

        $ticketIds = explode(',', $request->ticket_type_id); 
        $tx_ref = 'TIK' . $ticketIds[0] . '_' . time(); 
        
        Ticket::whereIn('id', $ticketIds)->update([
            'order_reference' => $tx_ref,
            'user_id' => $user->id // Associate tickets with the user
        ]);

        $data = [
            'amount' => (float) $ticketAmount->price, 
            'currency' => 'ETB',
            'email' => $user->email ?? $request->email ?? 'customer@example.com',
            'first_name' => $user->first_name ?? $request->first_name ?? 'Customer',
            'last_name' => $user->last_name ?? $request->last_name ?? 'User',
            'tx_ref' => $tx_ref,
            'callback_url' => route('chapa.callback', [$tx_ref]), 
            'return_url' => env('APP_URL') . "/payment-success/" . $tx_ref, 
            'customization' => [
                'title' => 'Ticket Payment',
                'description' => 'Payment for Ticket' 
            ]
        ];

        try {
            $response = Http::withToken(env('CHAPA_SECRET_KEY'))
                ->post('https://api.chapa.co/v1/transaction/initialize', $data);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'checkout_url' => $response->json()['data']['checkout_url'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Chapa API Error: ' . ($response->json()['message'] ?? 'Unknown'),
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register or find existing user
     */
    private function registerUser(Request $request)
    {
        try {
            // Check if user exists by email
            $user = UserInformation::where('email', $request->email)->first();
            // Create new user
            $user = UserInformation::create([ 
                'full_name' => $request->first_name . ' ' . $request->last_name , 
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'national_id_number' => $request->national_id_number ?? null,
                'national_id_front_image' => $request->national_id_front_image ?? null,
                'national_id_back_image' => $request->national_id_back_image ?? null,
            ]);

            return $user;

        } catch (\Exception $e) {
            Log::error('User registration failed: ' . $e->getMessage());
            return null;
        }
    }

    public function callback(Request $request, $tx_ref)
    { 
        $chapaSignature = $request->header('x-chapa-signature');
        $localHash = env('CHAPA_WEBHOOK_HASH');

        if (app()->environment('production') || $chapaSignature) {
            if ($chapaSignature !== $localHash) {
                Log::warning("Unauthorized Webhook Attempt! Ref: " . $tx_ref);
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        $response = Http::withToken(env('CHAPA_SECRET_KEY'))
            ->get('https://api.chapa.co/v1/transaction/verify/' . $tx_ref);
        $data = $response->json();

        if ($response->successful() && isset($data['data']) && $data['data']['status'] === 'success') {
            $chapaInternalRef = $data['data']['reference'] ?? 'NOT_FOUND';
            $officialReceiptUrl = "https://chapa.link/payment-receipt/" . $chapaInternalRef;

            $updated = Ticket::where('order_reference', $tx_ref)->update([
                'payment_status' => 'success', 
                'receipt_url' => $officialReceiptUrl 
            ]);

            return view('payment_result', [
                'status' => 'success', 
                'ticket_id' => $tx_ref, 
                'receipt_url' => $officialReceiptUrl
            ]);
        }

        // Handle Failure
        Log::error("Payment failed or verification failed for: " . $tx_ref);
        return view('payment_result', ['status' => 'failed']);
    }
}