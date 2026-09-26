<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private AdminAnalyticsService $analytics) {}

    public function index(): View
    {
        $summary = $this->analytics->getPlatformSummary();

        return view('admin.analytics.index', [
            'summary' => $summary,
        ]);
    }
}
