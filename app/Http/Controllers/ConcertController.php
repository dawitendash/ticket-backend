<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConcertRequest;
use App\Http\Requests\UpdateConcertRequest;
use App\Http\Resources\ConcertResource;
use App\Http\Resources\ConcertCollection;
use App\Services\ConcertService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Concert;

class ConcertController extends Controller
{
    use AuthorizesRequests;
    protected ConcertService $concertService;

    public function __construct(ConcertService $concertService)
    {
        $this->concertService = $concertService;
    }

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================

    /**
     * Display a listing of active concerts (Public).
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search'
        ]);

        // Only show active concerts to public (upcoming and ongoing)
        if (!auth()->check()) {
            $filters['status'] = ['upcoming', 'ongoing'];
        }

        $perPage = $request->get('per_page', 15);
        
        $concerts = $this->concertService->listConcerts($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Display a listing of upcoming concerts (Public).
     */
    public function upcoming(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        
        $concerts = $this->concertService->listConcerts(['status' => 'upcoming'], $perPage);

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Display the specified concert (Public).
     */
    public function show(string $id)
    {
        $concert = $this->concertService->getConcertWithTicketTypes($id);

        if (!$concert) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not found',
            ], 404);
        }

        // Check if concert is active for public view
        if (!auth()->check() && !in_array($concert->status, ['upcoming', 'ongoing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not available',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ConcertResource($concert),
        ]);
    }

    /**
     * Get concert with ticket types (Public).
     */
    public function withTicketTypes(string $id)
    {
        try {
            $concert = $this->concertService->getConcertWithTicketTypes($id);

            if (!$concert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert not found',
                ], 404);
            }

            // Only show active concerts to public
            if (!auth()->check() && !in_array($concert->status, ['upcoming', 'ongoing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert not available',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get next upcoming concert (Public).
     */
    public function next()
    {
        $concert = $this->concertService->getNextUpcoming();

        if (!$concert) {
            return response()->json([
                'success' => false,
                'message' => 'No upcoming concerts found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ConcertResource($concert),
        ]);
    }

    // =============================================
    // AUTHENTICATED ROUTES - Requires authentication
    // =============================================

    /**
     * Display a listing of all concerts (Authenticated Users).
     */
    public function all(Request $request)
    {
        $this->authorize('viewAny', Concert::class);

        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $concerts = $this->concertService->listConcerts($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    // =============================================
    // SCANNER ROUTES - Scanner and Admin only
    // =============================================

    /**
     * Get concert for scanning (Scanner/Admin only).
     */
    public function getForScanning(string $id)
    {
        $this->authorize('scan', Concert::class);

        try {
            $concert = $this->concertService->findOrFail($id);
            
            // Only allow scanning for ongoing concerts
            if ($concert->status !== 'ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Concert is not open for scanning',
                ], 422);
            }
            
            $concertData = [
                'concert' => $concert,
                'total_tickets_sold' => $concert->total_tickets_sold,
                'checked_in_count' => $concert->checked_in_count,
                'remaining' => $concert->total_tickets_sold - $concert->checked_in_count,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'concert' => new ConcertResource($concertData['concert']),
                    'total_tickets_sold' => $concertData['total_tickets_sold'],
                    'checked_in_count' => $concertData['checked_in_count'],
                    'remaining' => $concertData['remaining'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert for scanning: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get concert statistics for scanner (Scanner/Admin only).
     */
    public function scannerStatistics(string $id)
    {
        $this->authorize('viewStatistics', Concert::class);

        try {
            $stats = $this->concertService->getConcertStatistics($id);
            $stats['attendance_logs'] = $this->concertService->getAttendanceLogs($id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES - Admin only
    // =============================================

    /**
     * Display a listing of all concerts with trashed (Admin only).
     */
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', Concert::class);

        $filters = $request->only([
            'status', 'artist', 'venue', 'date_from', 'date_to', 'search', 'with_trashed'
        ]);

        $perPage = $request->get('per_page', 15);
        
        $concerts = $this->concertService->listConcerts($filters, $perPage, true);

        return response()->json([
            'success' => true,
            'data' => ConcertResource::collection($concerts),
        ]);
    }

    /**
     * Store a newly created concert (Admin only).
     */
    public function store(StoreConcertRequest $request)
    {
        $this->authorize('create', Concert::class);

        try {
            $data = $request->validated();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('concerts', 'public');
                $data['image_url'] = asset('storage/' . $path);
            }

            $concert = $this->concertService->createConcert($data);

            return response()->json([
                'success' => true,
                'message' => 'Concert created successfully',
                'data' => new ConcertResource($concert),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified concert (Admin only).
     */
    public function update(UpdateConcertRequest $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('update', $concert);

        try {
            $data = $request->validated();

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('concerts', 'public');
                $data['image_url'] = asset('storage/' . $path);
            }

            $concert = $this->concertService->updateConcert($concert, $data);

            return response()->json([
                'success' => true,
                'message' => 'Concert updated successfully',
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update concert status (Admin only).
     */
    public function updateStatus(Request $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('update', $concert);

        $request->validate([
            'status' => 'required|in:upcoming,ongoing,completed,cancelled',
        ]);

        try {
            $concert = $this->concertService->updateConcertStatus($concert, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Concert status updated successfully',
                'data' => new ConcertResource($concert),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update concert status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified concert (Admin only).
     */
    public function destroy(Request $request, string $id)
    {
        $concert = $this->concertService->findOrFail($id);
        $this->authorize('delete', $concert);

        try {
            $force = $request->get('force', false);
            $this->concertService->deleteConcert($concert, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'Concert permanently deleted' : 'Concert moved to trash',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a deleted concert (Admin only).
     */
    public function restore(string $id)
    {
        try {
            // First, try to find the concert in trash (soft-deleted)
            $concert = $this->concertService->findTrashedOrFail($id);
            $this->authorize('restore', $concert);
            
            $restoredConcert = $this->concertService->restoreConcert($id);

            return response()->json([
                'success' => true,
                'message' => 'Concert restored successfully',
                'data' => new ConcertResource($restoredConcert),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Concert not found in trash or does not exist',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore concert: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get concert statistics (Admin only).
     */
    public function statistics(string $id)
    {
        $this->authorize('viewStatistics', Concert::class);

        try {
            $stats = $this->concertService->getConcertStatistics($id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get concert statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}