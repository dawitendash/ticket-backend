<?php

namespace App\Services;

use App\Models\Concert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\TicketType;

class ConcertService
{
    public function listConcerts(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Concert::query()
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['artist'] ?? null, function($q, $artist) {
                $q->whereRaw("JSON_EXTRACT(artist, '$.en') LIKE ?", ["%{$artist}%"])
                    ->orWhereRaw("JSON_EXTRACT(artist, '$.am') LIKE ?", ["%{$artist}%"]);
            })
            ->when($filters['venue'] ?? null, function($q, $venue) {
                $q->whereRaw("JSON_EXTRACT(venue, '$.en') LIKE ?", ["%{$venue}%"]);
            })
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->where('concert_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->where('concert_date', '<=', $date))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->whereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("JSON_EXTRACT(name, '$.am') LIKE ?", ["%{$search}%"]);
                });
            })
            ->with(['ticketTypes'])
            ->latest('concert_date')
            ->paginate($perPage);
    }

    public function createConcert(array $data): Concert
    {
        return DB::transaction(function () use ($data) {
            $data['concert_id'] = Str::uuid();
            return Concert::create($data);
        });
    }

    public function updateConcert(Concert $concert, array $data): Concert
    {
        return DB::transaction(function () use ($concert, $data) {
            $concert->update($data);
            return $concert->fresh();
        });
    }

    public function deleteConcert(Concert $concert, bool $force = false): void
    {
        DB::transaction(function () use ($concert, $force) {
            if ($force) {
                $concert->forceDelete();
            } else {
                $concert->delete();
            }
        });
    }

    public function restoreConcert(string $id): Concert
    {
        $concert = Concert::onlyTrashed()->findOrFail($id);
        $concert->restore();
        return $concert;
    }

    public function getConcertStatistics(string $id): array
    {
        $concert = Concert::findOrFail($id);
        
        $totalTickets = $concert->tickets()->count();
        $usedTickets = $concert->tickets()->where('status', 'used')->count();
        $activeTickets = $concert->tickets()->where('status', 'active')->count();

        return [
            'concert_name' => $concert->name,
            'total_tickets' => $totalTickets,
            'used_tickets' => $usedTickets,
            'active_tickets' => $activeTickets,
            'attendance_rate' => $totalTickets > 0 ? round(($usedTickets / $totalTickets) * 100, 2) : 0,
            'total_revenue' => $concert->tickets()->sum('price_paid'),
        ];
    }

    public function updateConcertStatus(Concert $concert, string $status): Concert
    {
        $concert->update(['status' => $status]);
        return $concert->fresh();
    }
   
    public function getNextUpcoming(): Concert
    {
        return Concert::where('concert_date', '>', now())->orderBy('concert_date')->firstOrFail();
    }

    public function getConcertWithTicketTypes(string $id): Concert
    {
        return Concert::with('ticketTypes')->findOrFail($id);
    }
 
}