<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        protected NotificationPreferenceService $preferenceService,
    ) {}

    /**
     * Get the authenticated candidate's notification preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $candidate = $request->user();
        $preferences = $this->preferenceService->getPreferences($candidate);

        return response()->json(['data' => $preferences]);
    }

    /**
     * Update the authenticated candidate's notification preferences.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $candidate = $request->user();
        $updated = $this->preferenceService->updatePreferences(
            $candidate,
            $request->validated(),
        );

        return response()->json(['data' => $updated]);
    }
}
