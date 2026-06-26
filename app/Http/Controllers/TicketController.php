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
use Illuminate\Support\Facades\Cache;

class TicketController extends Controller
{
    use AuthorizesRequests;
    
    protected TicketService $service;

    // Cache TTL in seconds
    protected int $publicCacheTTL = 300; // 5 minutes
    protected int $userCacheTTL = 120; // 2 minutes
    protected int $adminCacheTTL = 600; // 10 minutes
    protected int $scannerCacheTTL = 60; // 1 minute

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
        
        $cacheKey = $this->getCacheKey('tickets_list', $filters, $perPage);
        
        $tickets = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($filters, $perPage) {
            return $this->service->listTickets($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
            'cached' => Cache::has($cacheKey),
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

        $cacheKey = $this->getCacheKey('user_tickets', $user->user_id);
        
        $tickets = Cache::remember($cacheKey, $this->userCacheTTL, function () use ($user) {
            return $this->service->getUserTickets($user->user_id);
        });

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
            'cached' => Cache::has($cacheKey),
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

        $cacheKey = $this->getCacheKey('user_active_tickets', $user->user_id);
        
        $tickets = Cache::remember($cacheKey, $this->userCacheTTL, function () use ($user) {
            return $this->service->getUserActiveTickets($user->user_id);
        });

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
            'cached' => Cache::has($cacheKey),
        ]);
    }

    /**
     * Display the specified ticket.
     */
    public function show(string $id)
    {
        try {
            $cacheKey = $this->getCacheKey('ticket_detail', $id);
            
            $ticket = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($id) {
                return $this->service->findTicketOrFail($id);
            });
            
            $this->authorize('view', $ticket);

            return response()->json([
                'success' => true,
                'data' => new TicketResource($ticket),
                'cached' => Cache::has($cacheKey),
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

        $cacheKey = $this->getCacheKey('ticket_by_qr', md5($request->qr_code));
        
        $ticket = Cache::remember($cacheKey, $this->publicCacheTTL, function () use ($request) {
            return $this->service->findByQrCode($request->qr_code);
        });

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
            'cached' => Cache::has($cacheKey),
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
        
        $cacheKey = $this->getCacheKey('tickets_admin_list', $filters, $perPage);
        
        $tickets = Cache::remember($cacheKey, $this->adminCacheTTL, function () use ($filters, $perPage) {
            return $this->service->listTickets($filters, $perPage);
        });

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
            'cached' => Cache::has($cacheKey),
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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
            
            // Clear all ticket caches
            $this->clearAllTicketCaches();

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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
            
            // Clear relevant caches
            $this->clearTicketCaches($ticket);

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
        
        $cacheKey = $this->getCacheKey('ticket_statistics', $concertId ?? 'all');
        
        $stats = Cache::remember($cacheKey, $this->adminCacheTTL, function () use ($concertId) {
            return $this->service->getStatistics($concertId);
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
            'cached' => Cache::has($cacheKey),
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

            // Clear caches on successful scan
            if ($result['success'] && isset($result['ticket'])) {
                $this->clearTicketCaches($result['ticket']);
                $this->clearScannerCaches($scannerId);
            }

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
            $cacheKey = $this->getCacheKey('ticket_validation', md5($request->qr_code));
            
            $result = Cache::remember($cacheKey, $this->scannerCacheTTL, function () use ($request) {
                return $this->service->validateTicket($request->qr_code);
            });

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
                'cached' => Cache::has($cacheKey),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // CACHE HELPER METHODS
    // =============================================

    /**
     * Generate cache key
     */
    private function getCacheKey(string $prefix, ...$params): string
    {
        $key = $prefix;
        foreach ($params as $param) {
            if (is_array($param)) {
                ksort($param);
                $key .= '_' . md5(json_encode($param));
            } elseif (is_object($param)) {
                $key .= '_' . md5(serialize($param));
            } else {
                $key .= '_' . (string) $param;
            }
        }
        return $key;
    }

    /**
     * Clear ticket-related caches
     */
    private function clearTicketCaches($ticket): void
    {
        try {
            // Clear specific ticket caches
            Cache::forget($this->getCacheKey('ticket_detail', $ticket->ticket_id ?? $ticket->id));
            Cache::forget($this->getCacheKey('ticket_by_qr', md5($ticket->qr_code ?? '')));
            
            // Clear user ticket caches
            if (isset($ticket->user_id)) {
                Cache::forget($this->getCacheKey('user_tickets', $ticket->user_id));
                Cache::forget($this->getCacheKey('user_active_tickets', $ticket->user_id));
            }
            
            // Clear list caches
            Cache::forget('tickets_list');
            Cache::forget('tickets_admin_list');
            Cache::forget('ticket_statistics');
            
            // Clear validation cache
            if (isset($ticket->qr_code)) {
                Cache::forget($this->getCacheKey('ticket_validation', md5($ticket->qr_code)));
            }
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('tickets_list_');
            $this->clearCacheByPattern('tickets_admin_list_');
            
        } catch (\Exception $e) {
            \Log::warning('Failed to clear ticket caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear all ticket caches
     */
    private function clearAllTicketCaches(): void
    {
        try {
            // Clear specific keys
            Cache::forget('tickets_list');
            Cache::forget('tickets_admin_list');
            Cache::forget('ticket_statistics');
            
            // Clear pattern-based caches
            $this->clearCacheByPattern('tickets_list_');
            $this->clearCacheByPattern('tickets_admin_list_');
            $this->clearCacheByPattern('ticket_detail_');
            $this->clearCacheByPattern('ticket_by_qr_');
            $this->clearCacheByPattern('user_tickets_');
            $this->clearCacheByPattern('user_active_tickets_');
            $this->clearCacheByPattern('ticket_validation_');
            
            \Log::info('All ticket caches cleared');
        } catch (\Exception $e) {
            \Log::error('Failed to clear all ticket caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear scanner caches
     */
    private function clearScannerCaches(string $scannerId): void
    {
        try {
            $this->clearCacheByPattern('scanner_history_' . $scannerId);
            $this->clearCacheByPattern('scanner_today_' . $scannerId);
            $this->clearCacheByPattern('scanner_stats_' . $scannerId);
        } catch (\Exception $e) {
            \Log::warning('Failed to clear scanner caches: ' . $e->getMessage());
        }
    }

    /**
     * Clear cache keys by pattern (Redis only)
     */
    private function clearCacheByPattern(string $pattern): void
    {
        if (Cache::getDefaultDriver() === 'redis') {
            try {
                $redis = Cache::store('redis')->getClient();
                $keys = $redis->keys($pattern . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Exception $e) {
                \Log::warning('Could not clear cache by pattern: ' . $e->getMessage());
            }
        }
    }
}