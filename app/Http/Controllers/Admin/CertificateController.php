<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $orgId = $request->query('org');
        $q = $request->query('q');

        $query = Certificate::with(['event.organization', 'user'])
            ->orderByDesc('id');

        if ($status === 'valid') {
            $query->whereNull('revoked_at');
        } elseif ($status === 'revoked') {
            $query->whereNotNull('revoked_at');
        }

        if (is_numeric($orgId)) {
            $query->whereHas('event', fn ($eq) => $eq->where('organization_id', (int) $orgId));
        }

        if (! empty($q)) {
            $term = '%'.trim((string) $q).'%';
            $query->where(function ($sub) use ($term) {
                $sub->where('certificate_no', 'ILIKE', $term)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'ILIKE', $term)->orWhere('email', 'ILIKE', $term));
            });
        }

        $items = $query->paginate(20)->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $items,
            'selectedStatus' => (string) $status,
            'selectedOrg' => is_numeric($orgId) ? (int) $orgId : '',
            'searchQuery' => (string) $q,
            'orgOptions' => Organization::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
