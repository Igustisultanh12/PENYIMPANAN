<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Search\SearchFilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(protected SearchFilesAction $searchAction) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:image,video,audio,document,archive,other'],
            'extension' => ['nullable', 'string', 'max:20'],
        ]);

        $results = $this->searchAction->execute($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    public function whatsappLookup(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $userProfile = $this->searchAction->lookupByWhatsApp($request->input('phone'));

        if (!$userProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna dengan nomor tersebut tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $userProfile,
        ]);
    }
}
