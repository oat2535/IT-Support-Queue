<?php

namespace App\Http\Controllers;

use App\Contracts\HostnameResolver;
use App\Services\QueueDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly QueueDashboardService $dashboardService,
        private readonly HostnameResolver $hostnameResolver,
    ) {}

    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'waiting');
        $searchQuery = trim((string) $request->query('q', ''));
        $rawPage = $request->query('page', 1);
        $page = is_numeric($rawPage) ? (int) $rawPage : 1;

        $data = $this->dashboardService->buildDashboardData($statusFilter, $searchQuery, $page);

        $clientIp = $this->hostnameResolver->resolveClientIp($request);
        $clientHostname = $this->hostnameResolver->resolveHostname($clientIp);

        $firstName = (string) $request->session()->get('first_name', '');
        $lastName = (string) $request->session()->get('last_name', '');
        $loggedInMember = trim("{$firstName} {$lastName}");

        return view('queue.dashboard', array_merge($data, [
            'is_staff_member' => (int) $request->session()->get('is_staff', 0) === 1,
            'logged_in_member' => $loggedInMember !== '' ? $loggedInMember : null,
            'client_ip' => $clientIp,
            'client_hostname' => $clientHostname,
        ]));
    }
}
