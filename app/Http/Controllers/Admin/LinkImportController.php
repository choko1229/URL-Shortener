<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportLinksRequest;
use App\Models\User;
use App\Services\ShortUrl\CsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** CSV でまとめて短縮URLを発行する（requirements.md 4-2: 管理者のみ） */
final class LinkImportController extends Controller
{
    public const RESULT_SESSION_KEY = 'link_import_result';

    private const TEMPLATE_FILENAME = 'url-shortener-import-template.csv';

    public function template(): Response
    {
        return response(CsvImporter::template(), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, self::TEMPLATE_FILENAME),
        ]);
    }

    public function store(ImportLinksRequest $request, CsvImporter $importer): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $result = $importer->import($request->csvPath(), $admin, (string) $request->ip());

        return back()
            ->with($result->imported > 0 ? 'notice' : 'error', $result->message())
            ->with(self::RESULT_SESSION_KEY, $result);
    }
}
