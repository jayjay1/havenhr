<?php

namespace App\Http\Controllers;

use App\Models\Resume;
use Illuminate\Http\JsonResponse;

class PublicResumeController extends Controller
{
    /**
     * Show a public resume by its sharing token.
     *
     * GET /api/v1/public/resumes/{token}
     * No authentication required.
     */
    public function show(string $token): JsonResponse
    {
        $resume = Resume::where('public_link_token', $token)
            ->where('public_link_active', true)
            ->first();

        if (! $resume) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resume not found.',
                ],
            ], 404);
        }

        $content = $resume->content;

        // Exclude email and phone from personal_info unless show_contact_on_public is true
        if (! $resume->show_contact_on_public && isset($content['personal_info'])) {
            unset($content['personal_info']['email'], $content['personal_info']['phone']);
        }

        return response()->json([
            'data' => [
                'id' => $resume->id,
                'title' => $resume->title,
                'template_slug' => $resume->template_slug,
                'content' => $content,
                'is_complete' => $resume->is_complete,
                'show_contact_on_public' => $resume->show_contact_on_public,
            ],
        ]);
    }
}
