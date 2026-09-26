<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubmitRegistrationRequest;
use App\Http\Resources\Api\V1\RegistrationResource;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RegistrationController extends Controller
{
    public function __construct(private RegistrationService $registrationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $registrations = Registration::query()
            ->with(['event', 'role', 'answers'])
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->paginate(15);

        return RegistrationResource::collection($registrations);
    }

    public function register(SubmitRegistrationRequest $request, string $slug): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $event = Event::where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['message' => 'Event tidak ditemukan.'], 404);
        }

        $roleId = $request->input('event_role_id');
        $role = EventRole::where('id', $roleId)->where('event_id', $event->id)->first();
        if (! $role) {
            return response()->json(['message' => 'Peran (role) tidak valid untuk event ini.'], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key') ?? Str::uuid()->toString();
        $answers = $request->input('answers', []);

        try {
            $registration = $this->registrationService->submit(
                $event,
                $role,
                $user,
                $answers,
                $idempotencyKey
            );

            return response()->json([
                'data' => new RegistrationResource($registration->load(['event', 'role', 'answers'])),
                'message' => 'Pendaftaran berhasil diajukan.',
            ], 201);
        } catch (HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $registration = Registration::find($id);

        if (! $registration || (int) $registration->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Pendaftaran tidak ditemukan.'], 404);
        }

        try {
            $this->registrationService->withdraw($registration, $user);

            return response()->json([
                'message' => 'Pendaftaran berhasil dibatalkan.',
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
