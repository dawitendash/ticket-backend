<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Http\Requests\BulkStoreTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Services\TicketTypeService;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;
    
    protected TicketTypeService $ticketTypeService;

    public function __construct(TicketTypeService $ticketTypeService)
    {
        $this->ticketTypeService = $ticketTypeService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    /**
     * Display a listing of ticket types.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'concert_id', 'is_active', 'min_price', 'max_price', 'available', 'search'
        ]);

        $perPage = $request->get('per_page', 15);
        $ticketTypes = $this->ticketTypeService->listTicketTypes($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
        ]);
    }

    /**
     * Get ticket types for a specific concert.
     */
    public function getByConcert(string $concertId)
    {
        $ticketTypes = $this->ticketTypeService->getTicketTypesByConcert($concertId);

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
        ]);
    }

    /**
     * Get available ticket types for a concert.
     */
    public function getAvailableByConcert(string $concertId)
    {
        $ticketTypes = $this->ticketTypeService->getAvailableTicketTypes($concertId);

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
        ]);
    }

    /**
     * Display the specified ticket type.
     */
    public function show(string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new TicketTypeResource($ticketType),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not found',
            ], 404);
        }
    }

    /**
     * Check ticket type availability.
     */
    public function checkAvailability(Request $request, string $id)
    {
        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = $request->get('quantity', 1);
        $available = $this->ticketTypeService->checkAvailability($id, $quantity);

        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => $available,
                    'available_count' => $ticketType->capacity - $ticketType->sold_count,
                    'requested_quantity' => $quantity,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket type not found',
            ], 404);
        }
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of all ticket types (Admin).
     */
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', TicketType::class);

        $filters = $request->only([
            'concert_id', 'is_active', 'min_price', 'max_price', 'available', 'search', 'with_trashed'
        ]);

        $perPage = $request->get('per_page', 15);
        $ticketTypes = $this->ticketTypeService->listTicketTypes($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => TicketTypeResource::collection($ticketTypes),
        ]);
    }

    /**
     * Store a newly created ticket type.
     */
    public function store(StoreTicketTypeRequest $request)
    {
        $this->authorize('create', TicketType::class);

        try {
            $data = $request->validated();
            $ticketType = $this->ticketTypeService->createTicketType($data);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type created successfully',
                'data' => new TicketTypeResource($ticketType),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create ticket types for a concert.
     */
    public function bulkStore(BulkStoreTicketTypeRequest $request)
    {
        $this->authorize('create', TicketType::class);

        try {
            $data = $request->validated();
            $ticketTypes = $this->ticketTypeService->bulkCreateTicketTypes(
                $data['concert_id'],
                $data['ticket_types']
            );

            return response()->json([
                'success' => true,
                'message' => count($ticketTypes) . ' ticket types created successfully',
                'data' => TicketTypeResource::collection($ticketTypes),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket types: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified ticket type.
     */
    public function update(UpdateTicketTypeRequest $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('update', $ticketType);

            $data = $request->validated();
            $ticketType = $this->ticketTypeService->updateTicketType($ticketType, $data);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type updated successfully',
                'data' => new TicketTypeResource($ticketType),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket type.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $force = $request->get('force', false);
            $result = $this->ticketTypeService->deleteTicketType($ticketType, $force);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'ticket_count' => $result['ticket_count'] ?? 0,
                    'suggestion' => 'Use force=true to delete with all associated tickets, or soft_delete=true to only delete if no tickets exist',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'ticket_count' => $result['ticket_count'] ?? 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ticket type and all its tickets.
     */
    public function destroyWithTickets(Request $request, string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $force = $request->get('force', false);
            $result = $this->ticketTypeService->deleteTicketTypeWithTickets($ticketType, $force);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'ticket_count' => $result['ticket_count'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete a ticket type (only if no tickets exist).
     */
    public function softDelete(string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->findTicketTypeOrFail($id);
            $this->authorize('delete', $ticketType);

            $result = $this->ticketTypeService->softDeleteTicketType($ticketType);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'ticket_count' => $result['ticket_count'] ?? 0,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted ticket type.
     */
    public function restore(string $id)
    {
        try {
            $ticketType = $this->ticketTypeService->restoreTicketType($id);
            $this->authorize('restore', $ticketType);

            return response()->json([
                'success' => true,
                'message' => 'Ticket type restored successfully',
                'data' => new TicketTypeResource($ticketType),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore ticket type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket type statistics.
     */
    public function statistics(string $id)
    {
        try {
            
            $this->authorize('viewStatistics', TicketType::class);
            $stats = $this->ticketTypeService->getTicketTypeStatistics($id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get ticket type statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sales analytics for a concert.
     */
    public function salesAnalytics(string $concertId)
    {
        try {
            $this->authorize('viewStatistics', TicketType::class);
            $analytics = $this->ticketTypeService->getSalesAnalytics($concertId);

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sales analytics: ' . $e->getMessage(),
            ], 500);
        }
    }
}