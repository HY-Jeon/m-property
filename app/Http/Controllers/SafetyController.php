<?php

namespace App\Http\Controllers;

use App\Services\SafeReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SafetyController extends Controller
{
    public function __construct(private readonly SafeReportService $safeReport)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $report = $q !== '' ? $this->safeReport->buildReport($q) : null;

        return view('safety.report', [
            'q'        => $q,
            'report'   => $report,
            'demoMode' => $this->safeReport->isDemoMode(),
            'demos'    => $this->safeReport->demoAddresses(),
        ]);
    }
}
