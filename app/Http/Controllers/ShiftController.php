<?php

namespace App\Http\Controllers;

use App\Contracts\HostnameResolver;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly HostnameResolver $hostnameResolver,
    ) {}

    public function toggle(Request $request): JsonResponse
    {
        $shouldClose = (bool) $request->input('closed', false);
        $clientIp = $this->hostnameResolver->resolveClientIp($request);
        $hostname = $this->hostnameResolver->resolveHostname($clientIp);
        $actor = $hostname !== '' ? $hostname : $clientIp;

        return response()->json(
            $this->shiftService->toggleShiftStatus($shouldClose, $actor)
        );
    }
}
