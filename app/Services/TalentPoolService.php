<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TalentPoolService
{
    public function __construct(private AuditLogService $audit) {}

    /**
     * @param  array{q?: ?string, skills?: ?string, city?: ?string, min_attendance?: ?int, page?: ?int}  $filters
     */
    public function search(Organization $organization, User $actor, array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('registrations', function ($q) use ($organization) {
                $q->whereHas('event', function ($eq) use ($organization) {
                    $eq->where('organization_id', $organization->id);
                })->whereIn('status', ['accepted', 'completed']);
            })
            ->whereHas('volunteerProfile', function ($pq) {
                $pq->where('visibility', '!=', 'private');
            })
            ->with(['volunteerProfile']);

        if (! empty($filters['q'])) {
            $keyword = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'ILIKE', $keyword)
                    ->orWhere('email', 'ILIKE', $keyword);
            });
        }

        if (! empty($filters['city'])) {
            $city = '%'.trim((string) $filters['city']).'%';
            $query->whereHas('volunteerProfile', function ($pq) use ($city) {
                $pq->where('city', 'ILIKE', $city);
            });
        }

        if (! empty($filters['skills'])) {
            $skill = '%'.trim((string) $filters['skills']).'%';
            $query->whereHas('volunteerProfile', function ($pq) use ($skill) {
                $pq->whereRaw('skills::text ILIKE ?', [$skill]);
            });
        }

        $results = $query->paginate(15);

        $this->audit->record($actor, 'talent.searched', Organization::class, $organization->id, [
            'filters' => $filters,
        ]);

        return $results;
    }
}
