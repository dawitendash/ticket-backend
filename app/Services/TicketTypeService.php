<?php

namespace App\Services;

use App\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketTypeService
{
    /**
     * List ticket types with filters.
     */
    public function listTicketTypes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return TicketType::query()
            ->when($filters['concert_id'] ?? null, fn($q, $id) => $q->where('concert_id', $id))
            ->when($filters['is_active'] ?? null, fn($q, $active) => $q->where('is_active', $active))
            ->when($filters['min_price'] ?? null, fn($q, $price) => $q->where('price', '>=', $price))
            ->when($filters['max_price'] ?? null, fn($q, $price) => $q->where('price', '<=', $price))
            ->when($filters['available'] ?? null, function($q) {
                $q->where('is_active', true)->whereRaw('capacity > sold_count');
            })
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->whereRaw("JSON_EXTRACT(ticket_type_name, '$.en') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("JSON_EXTRACT(ticket_type_name, '$.am') LIKE ?", ["%{$search}%"]);
                });
            })
            ->with(['concert'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get ticket types for a specific concert.
     */
    public function getTicketTypesByConcert(string $concertId): \Illuminate\Database\Eloquent\Collection
    {
        return TicketType::where('concert_id', $concertId)
            ->where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get available ticket types for a concert.
     */
    public function getAvailableTicketTypes(string $concertId): \Illuminate\Database\Eloquent\Collection
    {
        return TicketType::where('concert_id', $concertId)
            ->where('is_active', true)
            ->whereRaw('capacity > sold_count')
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Find a ticket type by ID.
     */
    public function findTicketType(string $id): ?TicketType
    {
        return TicketType::with(['concert', 'tickets'])->find($id);
    }

    /**
     * Find a ticket type by ID or fail.
     */
    public function findTicketTypeOrFail(string $id): TicketType
    {
        return TicketType::with(['concert', 'tickets'])->findOrFail($id);
    }

    /**
     * Create a new ticket type.
     */
    public function createTicketType(array $data): TicketType
    {
        return DB::transaction(function () use ($data) {
            $data['ticket_type_id'] = Str::uuid();
            $data['sold_count'] = $data['sold_count'] ?? 0;
            
            // Ensure JSON fields are properly formatted
            if (isset($data['ticket_type_name']) && !is_array($data['ticket_type_name'])) {
                $data['ticket_type_name'] = json_decode($data['ticket_type_name'], true) ?? ['en' => $data['ticket_type_name']];
            }
            
            if (isset($data['ticket_type_description']) && !is_array($data['ticket_type_description'])) {
                $data['ticket_type_description'] = json_decode($data['ticket_type_description'], true) ?? ['en' => $data['ticket_type_description']];
            }
            
            return TicketType::create($data);
        });
    }

    /**
     * Bulk create ticket types for a concert.
     */
    public function bulkCreateTicketTypes(string $concertId, array $ticketTypes): array
    {
        return DB::transaction(function () use ($concertId, $ticketTypes) {
            $created = [];
            foreach ($ticketTypes as $data) {
                $data['concert_id'] = $concertId;
                $data['ticket_type_id'] = Str::uuid();
                $data['sold_count'] = 0;
                
                // Ensure JSON fields are properly formatted
                if (isset($data['ticket_type_name']) && !is_array($data['ticket_type_name'])) {
                    $data['ticket_type_name'] = ['en' => $data['ticket_type_name']];
                }
                
                $created[] = TicketType::create($data);
            }
            return $created;
        });
    }

    /**
     * Update an existing ticket type.
     */
    public function updateTicketType(TicketType $ticketType, array $data): TicketType
    {
        return DB::transaction(function () use ($ticketType, $data) {
            // Ensure JSON fields are properly formatted
            if (isset($data['ticket_type_name']) && !is_array($data['ticket_type_name'])) {
                $data['ticket_type_name'] = json_decode($data['ticket_type_name'], true) ?? ['en' => $data['ticket_type_name']];
            }
            
            if (isset($data['ticket_type_description']) && !is_array($data['ticket_type_description'])) {
                $data['ticket_type_description'] = json_decode($data['ticket_type_description'], true) ?? ['en' => $data['ticket_type_description']];
            }
            
            $ticketType->update($data);
            return $ticketType->fresh();
        });
    }

   public function deleteTicketType(TicketType $ticketType, bool $force = false): array
    {
        return DB::transaction(function () use ($ticketType, $force) {
            // Check if there are tickets associated with this ticket type
            $ticketCount = $ticketType->tickets()->count();
            
            if ($ticketCount > 0) {
                return [
                    'success' => false,
                    'message' => "Cannot delete ticket type. It has {$ticketCount} associated tickets.",
                    'ticket_count' => $ticketCount,
                ];
            }

            if ($force) {
                $ticketType->forceDelete();
            } else {
                $ticketType->delete();
            }

            return [
                'success' => true,
                'message' => $force ? 'Ticket type permanently deleted' : 'Ticket type moved to trash',
            ];
        });
    }

    /**
     * Delete a ticket type and all its associated tickets.
     */
    public function deleteTicketTypeWithTickets(TicketType $ticketType, bool $force = false): array
    {
        return DB::transaction(function () use ($ticketType, $force) {
            // Get all tickets associated with this ticket type
            $tickets = $ticketType->tickets;
            $ticketCount = $tickets->count();

            // Delete all associated tickets first
            if ($ticketCount > 0) {
                foreach ($tickets as $ticket) {
                    if ($force) {
                        $ticket->forceDelete();
                    } else {
                        $ticket->delete();
                    }
                }
            }

            // Now delete the ticket type
            if ($force) {
                $ticketType->forceDelete();
            } else {
                $ticketType->delete();
            }

            return [
                'success' => true,
                'message' => "Ticket type and {$ticketCount} associated tickets deleted successfully",
                'ticket_count' => $ticketCount,
            ];
        });
    }

    /**
     * Soft delete a ticket type (only if no tickets exist).
     */
    public function softDeleteTicketType(TicketType $ticketType): array
    {
        return DB::transaction(function () use ($ticketType) {
            // Check if there are tickets associated with this ticket type
            $ticketCount = $ticketType->tickets()->count();
            
            if ($ticketCount > 0) {
                return [
                    'success' => false,
                    'message' => "Cannot delete ticket type. It has {$ticketCount} associated tickets.",
                    'ticket_count' => $ticketCount,
                ];
            }

            $ticketType->delete();

            return [
                'success' => true,
                'message' => 'Ticket type moved to trash',
                'ticket_count' => 0,
            ];
        });
    }

    /**
     * Restore a soft-deleted ticket type.
     */
    public function restoreTicketType(string $id): TicketType
    {
        $ticketType = TicketType::onlyTrashed()->findOrFail($id);
        $ticketType->restore();
        return $ticketType;
    }

    /**
     * Increment sold count for a ticket type.
     */
    public function incrementSoldCount(TicketType $ticketType, int $amount = 1): bool
    {
        if ($ticketType->available_tickets < $amount) {
            return false;
        }
        
        $ticketType->increment('sold_count', $amount);
        return true;
    }

    /**
     * Decrement sold count for a ticket type.
     */
    public function decrementSoldCount(TicketType $ticketType, int $amount = 1): bool
    {
        if ($ticketType->sold_count < $amount) {
            return false;
        }
        
        $ticketType->decrement('sold_count', $amount);
        return true;
    }

    /**
     * Check if a ticket type has available tickets.
     */
    public function checkAvailability(string $id, int $quantity = 1): bool
    {
        $ticketType = TicketType::findOrFail($id);
        return $ticketType->is_active && ($ticketType->capacity - $ticketType->sold_count) >= $quantity;
    }

    /**
     * Get ticket type statistics.
     */
    public function getTicketTypeStatistics(string $id): array
    {
        $ticketType = TicketType::with(['tickets'])->findOrFail($id);
        
        return [
            'id' => $ticketType->ticket_type_id,
            'name' => $ticketType->ticket_type_name,
            'price' => $ticketType->price,
            'capacity' => $ticketType->capacity,
            'sold' => $ticketType->sold_count,
            'available' => $ticketType->capacity - $ticketType->sold_count,
            'sold_percentage' => $ticketType->capacity > 0 ? round(($ticketType->sold_count / $ticketType->capacity) * 100, 2) : 0,
            'is_available' => ($ticketType->capacity - $ticketType->sold_count) > 0,
            'revenue' => (float) $ticketType->tickets->sum('price_paid'),
        ];
    }

    /**
     * Get ticket type sales analytics.
     */
    public function getSalesAnalytics(string $concertId): array
    {
        $ticketTypes = TicketType::where('concert_id', $concertId)->get();
        
        return [
            'total_capacity' => $ticketTypes->sum('capacity'),
            'total_sold' => $ticketTypes->sum('sold_count'),
            'total_available' => $ticketTypes->sum('capacity') - $ticketTypes->sum('sold_count'),
            'total_revenue' => $ticketTypes->sum(function ($type) {
                return $type->sold_count * $type->price;
            }),
            'ticket_types' => $ticketTypes->map(function ($type) {
                return [
                    'id' => $type->ticket_type_id,
                    'name' => $type->ticket_type_name,
                    'sold' => $type->sold_count,
                    'available' => $type->capacity - $type->sold_count,
                    'revenue' => $type->sold_count * $type->price,
                ];
            }),
        ];
    }
}