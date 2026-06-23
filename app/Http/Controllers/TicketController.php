<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\ScanTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketController extends Controller
{
    use AuthorizesRequests;
    
    protected TicketService $service;

    public function __construct(TicketService $service)
    {
        $this->service = $service;
    }

    // =============================================
    // PUBLIC ROUTES
    // =============================================

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'user_id', 'concert_id', 'ticket_type_id', 'status', 'device_id', 'search', 'date_from', 'date_to'
        ]);

        $perPage = $request->integer('per_page', 15);
        $tickets = $this->service->listTickets($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Get tickets for the authenticated user.
     */
    public function myTickets()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tickets = $this->service->getUserTickets($user->user_id);

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Get active tickets for the authenticated user.
     */
    public function myActiveTickets()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tickets = $this->service->getUserActiveTickets($user->user_id);

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Display the specified ticket.
     */
    public function show(string $id)
    {
        try {
            $ticket = $this->service->findTicketOrFail($id);
            $this->authorize('view', $ticket);

            return response()->json([
                'success' => true,
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }
    }

    /**
     * Get ticket by QR code.
     */
    public function showByQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $ticket = $this->service->findByQrCode($request->qr_code);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $this->authorize('view', $ticket);

        return response()->json([
            'success' => true,
            'data' => new TicketResource($ticket),
        ]);
    }

    // =============================================
    // ADMIN ROUTES
    // =============================================

    /**
     * Display a listing of all tickets (Admin only).
     */
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only([
            'user_id', 'concert_id', 'ticket_type_id', 'status', 'device_id', 'search', 'date_from', 'date_to', 'with_trashed'
        ]);

        $perPage = $request->integer('per_page', 15);
        $tickets = $this->service->listTickets($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Create a new ticket (Admin only).
     */
    public function store(StoreTicketRequest $request)
    {
        //$this->authorize('create', Ticket::class);

        try {
            $data = $request->validated();
            $ticket = $this->service->createTicket($data);

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => new TicketResource($ticket),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create tickets (Admin only).
     */
    public function bulkStore(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $request->validate([
            'tickets' => ['required', 'array', 'min:1'],
            'tickets.*.user_id' => ['required', 'string', 'exists:users,user_id'],
            'tickets.*.ticket_type_id' => ['required', 'string', 'exists:ticket_types,ticket_type_id'],
            'tickets.*.concert_id' => ['required', 'string', 'exists:concerts,concert_id'],
            'tickets.*.price_paid' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $tickets = $this->service->bulkCreateTickets($request->tickets);

            return response()->json([
                'success' => true,
                'message' => count($tickets) . ' tickets created successfully',
                'data' => TicketResource::collection($tickets),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tickets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a ticket (Admin only).
     */
    public function update(UpdateTicketRequest $request, string $id)
    {
        try {
            $ticket = $this->service->findTicketOrFail($id);
            $this->authorize('update', $ticket);

            $data = $request->validated();
            $ticket = $this->service->updateTicket($ticket, $data);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a ticket (Admin only).
     */
    public function cancel(string $id)
    {
        try {
            $ticket = $this->service->findTicketOrFail($id);
            $this->authorize('update', $ticket);

            $ticket->markAsCancelled();

            return response()->json([
                'success' => true,
                'message' => 'Ticket cancelled successfully',
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a ticket (Admin only).
     */
    public function refund(string $id)
    {
        try {
            $ticket = $this->service->findTicketOrFail($id);
            $this->authorize('update', $ticket);

            $ticket->markAsRefunded();

            return response()->json([
                'success' => true,
                'message' => 'Ticket refunded successfully',
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refund ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ticket (Admin only).
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $ticket = $this->service->findTicketOrFail($id);
            $this->authorize('delete', $ticket);

            $force = $request->get('force', false);
            $this->service->deleteTicket($ticket, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Ticket permanently deleted' : 'Ticket deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a ticket (Admin only).
     */
    public function restore(string $id)
    {
        try {
            $this->authorize('restore', Ticket::class);
            
            $ticket = $this->service->restoreTicket($id);

            return response()->json([
                'success' => true,
                'message' => 'Ticket restored successfully',
                'data' => new TicketResource($ticket),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket statistics (Admin only).
     */
    public function statistics(Request $request)
    {
        $this->authorize('viewStatistics', Ticket::class);

        $concertId = $request->get('concert_id');
        $stats = $this->service->getStatistics($concertId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    // =============================================
    // SCANNER ROUTES - Scanner and Admin only
    // =============================================

    /**
     * Scan a ticket (Scanner/Admin only).
     */
    public function scan(ScanTicketRequest $request)
    {
        $this->authorize('scan', Ticket::class);

        try {
            $data = $request->validated();
            
            // Get scanner info
            $scannerId = auth()->id();
            $deviceId = $data['device_id'] ?? null;
            $gateNumber = $data['gate_number'] ?? null;

            // Scan the ticket
            $result = $this->service->scanTicket(
                $data['qr_code'],
                $scannerId,
                $deviceId,
                $gateNumber
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'status' => $result['status'],
                    'data' => isset($result['ticket']) ? new TicketResource($result['ticket']) : null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'ticket' => new TicketResource($result['ticket']),
                    'attendance_log' => $result['attendance_log'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scan ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate a ticket without marking as used (Scanner/Admin only).
     */
    public function validateTicket(Request $request)
    {
        $this->authorize('validateTicket', Ticket::class);

        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        try {
            $result = $this->service->validateTicket($request->qr_code);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'status' => $result['status'],
                    'data' => isset($result['ticket']) ? new TicketResource($result['ticket']) : null,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new TicketResource($result['ticket']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate ticket: ' . $e->getMessage(),
            ], 500);
        }
    }
}