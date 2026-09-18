<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageCustomFieldRequest;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\Organization;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    public function index(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventCustomField::class, $event]);

        $fields = $event->customFields()->with('options')->orderBy('sort_order')->orderBy('id')->get();

        return view('organizer.events.fields.index', ['org' => $organization, 'event' => $event, 'fields' => $fields]);
    }

    public function create(Organization $organization, Event $event): View
    {
        Gate::authorize('manage', [EventCustomField::class, $event]);

        return view('organizer.events.fields.create', [
            'org' => $organization,
            'event' => $event,
            'types' => EventCustomField::TYPES,
            'optionTypes' => ManageCustomFieldRequest::OPTION_TYPES,
        ]);
    }

    public function store(ManageCustomFieldRequest $request, Organization $organization, Event $event): RedirectResponse
    {
        $valid = $request->validated();

        $field = DB::transaction(function () use ($event, $valid, $request): EventCustomField {
            $field = $event->customFields()->create(
                array_intersect_key($valid, array_flip((new EventCustomField)->getFillable()))
            );
            $this->saveOptions($field, $valid['options'] ?? []);
            $this->audit->record($request->user(), 'field.created', EventCustomField::class, $field->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);

            return $field;
        });

        return redirect()->route('organizer.events.fields.show', [$organization->slug, $event->slug, $field->id])
            ->with('status', 'Field berhasil dibuat.');
    }

    public function show(Organization $organization, Event $event, EventCustomField $field): View
    {
        Gate::authorize('manage', $field);

        $field->load('options');

        return view('organizer.events.fields.show', ['org' => $organization, 'event' => $event, 'field' => $field]);
    }

    public function edit(Organization $organization, Event $event, EventCustomField $field): View
    {
        Gate::authorize('manage', $field);

        $field->load('options');
        $optionsText = $field->options->sortBy('sort_order')
            ->map(fn ($option) => $option->label.'|'.$option->value)
            ->implode("\n");

        return view('organizer.events.fields.edit', [
            'org' => $organization,
            'event' => $event,
            'field' => $field,
            'types' => EventCustomField::TYPES,
            'optionTypes' => ManageCustomFieldRequest::OPTION_TYPES,
            'optionsText' => $optionsText,
        ]);
    }

    public function update(ManageCustomFieldRequest $request, Organization $organization, Event $event, EventCustomField $field): RedirectResponse
    {
        $valid = $request->validated();

        DB::transaction(function () use ($field, $event, $valid, $request): void {
            $payload = array_intersect_key($valid, array_flip($field->getFillable()));
            $old = $field->only(array_keys($payload));
            $field->fill($payload)->save();
            if (array_key_exists('options', $valid)) {
                $this->saveOptions($field, $valid['options'] ?? []);
            }
            $this->audit->record($request->user(), 'field.updated', EventCustomField::class, $field->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'old' => $old,
                'new' => $payload,
            ]);
        });

        return redirect()->route('organizer.events.fields.show', [$organization->slug, $event->slug, $field->id])
            ->with('status', 'Field berhasil diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventCustomField $field): RedirectResponse
    {
        Gate::authorize('manage', $field);

        DB::transaction(function () use ($field, $event): void {
            $field->delete();
            $this->audit->record(request()->user(), 'field.deleted', EventCustomField::class, $field->id, [
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
            ]);
        });

        return redirect()->route('organizer.events.fields.index', [$organization->slug, $event->slug])
            ->with('status', 'Field berhasil dihapus.');
    }

    /** @param array<int, array{label: string, value: string, sort_order?: int}> $options */
    private function saveOptions(EventCustomField $field, array $options): void
    {
        $field->options()->delete();

        foreach (array_values($options) as $index => $option) {
            $field->options()->create([
                'label' => $option['label'],
                'value' => $option['value'],
                'sort_order' => $option['sort_order'] ?? $index,
            ]);
        }
    }
}
