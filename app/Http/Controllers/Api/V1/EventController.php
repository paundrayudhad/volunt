<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventDetailResource;
use App\Http\Resources\Api\V1\EventListResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Event::query()
            ->with(['organization'])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });

        if ($request->filled('q')) {
            $search = (string) $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }

        if ($request->filled('city')) {
            $query->where('address', 'like', '%'.$request->input('city').'%');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $events = $query->latest('published_at')->paginate($perPage);

        return EventListResource::collection($events);
    }

    public function show(string $slug): JsonResponse
    {
        $event = Event::query()
            ->with(['organization', 'roles', 'shifts', 'customFields'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (! $event) {
            return response()->json([
                'message' => 'Event tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        return response()->json([
            'data' => new EventDetailResource($event),
        ]);
    }
}
