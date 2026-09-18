<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\EventRole;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VolunteerRegistrationController extends Controller
{
    public function __construct(private RegistrationService $registrations) {}

    public function index(): View
    {
        $list = request()->user()->registrations()->with(['event', 'role'])
            ->orderByDesc('id')->paginate(12);

        return view('registrations.index', ['list' => $list]);
    }

    public function show(Registration $registrationVol): View
    {
        $registrationVol->load(['event', 'role', 'answers.field']);

        return view('registrations.show', ['registration' => $registrationVol]);
    }

    public function create(Event $eventPublic): View
    {
        $eventPublic->load([
            'roles' => fn ($query) => $query->where('status', 'active')->orderBy('name'),
            'customFields' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'customFields.options',
        ]);

        return view('registrations.create', [
            'event' => $eventPublic,
            'roles' => $eventPublic->roles,
            'fields' => $eventPublic->customFields,
        ]);
    }

    public function store(StoreRegistrationRequest $request, Event $eventPublic): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->volunteerProfile()->exists(), 422, 'Lengkapi profil volunteer dulu.');

        $valid = $request->validated();
        $role = EventRole::whereKey($valid['role_id'])
            ->where('event_id', $eventPublic->id)->firstOrFail();

        $registration = $this->registrations->submit(
            $eventPublic,
            $role,
            $user,
            $this->mapAnswers($eventPublic, $valid['answers'] ?? []),
            $valid['idempotency_key']
        );

        return redirect()->route('registrations.show', $registration->id)
            ->with('status', 'Pendaftaran berhasil dikirim.');
    }

    public function withdraw(Registration $registrationVol): RedirectResponse
    {
        $this->registrations->withdraw($registrationVol, request()->user());

        return redirect()->route('registrations.show', $registrationVol->id)
            ->with('status', 'Pendaftaran berhasil ditarik.');
    }

    /** @param array<string, mixed> $answers */
    private function mapAnswers(Event $event, array $answers): array
    {
        $fields = $event->customFields()->with('options')->where('is_active', true)->get();
        $mapped = [];

        foreach ($fields as $field) {
            $key = (string) $field->id;
            if (! array_key_exists($key, $answers) && ! array_key_exists($field->id, $answers)) {
                continue;
            }
            $value = $answers[$key] ?? $answers[$field->id];
            if ($value === null || $value === '') {
                continue;
            }
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $path = Storage::putFileAs(
                    'registration-answers',
                    $value,
                    (string) Str::uuid().'.'.strtolower($value->getClientOriginalExtension())
                );
                $mapped[] = [
                    'event_custom_field_id' => $field->id,
                    'file_path' => $path === false ? null : $path,
                ];

                continue;
            }
            if (is_array($value)) {
                $mapped[] = [
                    'event_custom_field_id' => $field->id,
                    'value_jsonb' => array_values($value),
                ];

                continue;
            }
            $mapped[] = [
                'event_custom_field_id' => $field->id,
                'value_text' => (string) $value,
            ];
        }

        return $mapped;
    }
}
