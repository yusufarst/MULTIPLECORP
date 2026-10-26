<?php

namespace App\Modules\DOC\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DOC\Data\Download;
use App\Modules\DOC\Http\FileResponses;
use App\Modules\DOC\Queries\FileDownloads;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `DOC DownloadFile` (ROUTE-226; INT-014, INT-226) and `DOC ShowThumbnail` (ROUTE-227; INT-227): a private file
 * streamed after its owning record authorizes the viewer at request time (DP-05; FL-07) — never a path from the
 * request, never a signed link alone —, and an image's thumbnail, a background request by its route that reads the
 * session and writes nothing (RB-05; PF-32). A denied and an unknown file answer alike (AZ-11). ADMIN_FLOW IP-16's
 * re-encoded preview of an evidence photo is the same route's `varian=pratinjau`.
 */
final class FileController extends Controller
{
    public function __construct(private readonly FileDownloads $files) {}

    public function download(Request $request, string $id): Response
    {
        $download = $this->files->download($request->user() ?? throw new NotFoundHttpException, $id);

        return $this->attachment($download);
    }

    public function thumbnail(Request $request, string $id): Response
    {
        $variant = $request->query('varian') === 'pratinjau' ? 'preview' : 'thumbnail';
        $download = $this->files->derivative($request->user() ?? throw new NotFoundHttpException, $id, $variant);

        return $this->attachment($download);
    }

    private function attachment(Download $download): Response
    {
        return FileResponses::attachment($download->stream, $download->mimeType, $download->size, $download->name);
    }
}
