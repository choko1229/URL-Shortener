<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Rules\NotOwnDomain;
use App\Services\CardPreview\DestinationCardFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** 発行フォームの「X に貼ったときの見え方」: 転送先ページのカード情報を返す */
final class CardPreviewController extends Controller
{
    public function __invoke(Request $request, DestinationCardFetcher $fetcher): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new NotOwnDomain],
        ]);

        return response()->json([
            'card' => $fetcher->fetch($validated['url'])?->toArray(),
        ]);
    }
}
