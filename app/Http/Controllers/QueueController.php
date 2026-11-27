<?php

namespace App\Http\Controllers;

use App\Services\QueueWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(
        private readonly QueueWorkflowService $workflowService,
    ) {}

    public function addQueue(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function callNext(): JsonResponse
    {
        return response()->json($this->workflowService->callNext());
    }

    public function finishQueue(): RedirectResponse
    {
        $this->workflowService->finishCurrentWithoutBmsCheck();

        return redirect()->route('dashboard');
    }

    public function updateDescription(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (! is_numeric($id)) {
            return response()->json(['success' => false, 'error' => 'Queue Item not found']);
        }

        $comment = (string) $request->input('comment', '');
        $rawStatusId = $request->input('status_id');
        $statusId = is_numeric($rawStatusId) ? (int) $rawStatusId : null;

        return response()->json(
            $this->workflowService->updateDescription((int) $id, $comment, $statusId)
        );
    }

    public function toggleUrgent(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (! is_numeric($id)) {
            return response()->json(['success' => false, 'error' => 'Queue Item not found']);
        }

        return response()->json(
            $this->workflowService->toggleUrgent((int) $id, $request->input('is_urgent', false))
        );
    }

    public function insertAdhoc(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (! is_numeric($id)) {
            return response()->json(['success' => false, 'error' => 'Queue Item not found']);
        }

        return response()->json($this->workflowService->insertAdhoc((int) $id));
    }

    public function finishAdhoc(): JsonResponse
    {
        return response()->json($this->workflowService->finishAdhoc());
    }

    public function closeQueue(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (! is_numeric($id)) {
            return response()->json(['success' => false, 'error' => 'Queue Item not found']);
        }

        return response()->json($this->workflowService->closeItem((int) $id));
    }
}
