<?php

namespace Tests\Support\Fixtures;

use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Enums\FilePurpose;
use App\Modules\DOC\Jobs\RenderDocument;
use App\Modules\DOC\Models\Document;
use App\Modules\DOC\Models\DocumentRendition;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\EvidenceDocument;
use App\Modules\DOC\Models\EvidenceDocumentVersion;
use App\Modules\DOC\Services\UploadRefused;
use App\Modules\DOC\Services\Uploads;
use App\Modules\ORG\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sample page of TASK-022's browser suite (TL-09–TL-12; ROUTE-226, ROUTE-227, ROUTE-285): a signed-in account
 * uploads a file through the real pipeline (FL-01–FL-06) — the fixture registering it as a synthetic company's client
 * order, the record that authorizes its download —, downloads it through ROUTE-226, and reads an issued synthetic
 * document's rendition state through ROUTE-285. Local only (routes/fixtures.php): the upload route and the evidence
 * registration are TASK-030's; no real company, document or file.
 */
final class FilesFixture
{
    public static function page(Request $request): Response
    {
        $files = DB::table('file_objects')->where('uploaded_by', $request->user()?->getAuthIdentifier())
            ->where('purpose', 'UPLOAD')->orderByDesc('id')->limit(10)->get(['public_id', 'original_filename', 'mime_type']);

        return Inertia::render('Fixtures/Files', [
            'files' => $files->map(fn (object $file): array => ['id' => $file->public_id, 'name' => $file->original_filename, 'image' => str_starts_with((string) $file->mime_type, 'image/')])->all(),
            'rendition' => $request->query('versi'),
        ]);
    }

    public static function upload(Request $request): JsonResponse
    {
        $file = $request->file('berkas');
        $user = $request->user();

        if (! $file instanceof UploadedFile || ! $file->isValid() || $user === null) {
            return self::refused();
        }

        $company = Company::factory()->create(['code' => 'B'.Str::upper(Str::random(6))]);

        try {
            $stored = app(Uploads::class)->accept((string) $file->getRealPath(), $file->getClientOriginalName(), FilePurpose::Upload, $company->id, (int) $user->getAuthIdentifier());
        } catch (UploadRefused) {
            return self::refused();
        }

        $reference = 'SP/UJI/'.Str::upper(Str::random(6));
        $evidence = EvidenceDocument::factory()->create([
            'company_id' => $company->id, 'evidence_type' => 'CLIENT_ORDER', 'issuer_reference' => $reference,
            'identity_key' => 'sma uji|CLIENT_ORDER|'.strtolower($reference).'|2026-10-01',
        ]);
        EvidenceDocumentVersion::factory()->create(['evidence_document_id' => $evidence->id, 'file_object_id' => $stored->id, 'uploaded_by' => $user->getAuthIdentifier()]);

        return response()->json(['id' => $stored->public_id, 'name' => $stored->original_filename], 201);
    }

    /** An issued synthetic invoice whose first attempt is owed, rendered at once in the request when `render` is set. */
    public static function rendition(Request $request): JsonResponse
    {
        $company = Company::factory()->create(['code' => 'R'.Str::upper(Str::random(6))]);
        // Issued by the signed-in account: the factories' own synthetic users repeat across a long-lived local database.
        $version = DocumentVersion::factory()->issued()->for(Document::factory()->state(['company_id' => $company->id]), 'document')
            ->create(['issued_by' => $request->user()?->getAuthIdentifier()]);
        $attempt = DocumentRendition::factory()->create(['document_version_id' => $version->id]);

        if ($request->boolean('render')) {
            app()->instance(RenditionTemplate::class, new class implements RenditionTemplate
            {
                public function html(DocumentVersion $version, string $assetDirectory): string
                {
                    return '<!doctype html><html lang="id"><head><meta charset="utf-8"><link rel="stylesheet" href="fonts.css"></head><body style="font-family:\'Inter Variable\'"><h1>Faktur uji</h1><p>'.e((string) $version->formatted_number).'</p></body></html>';
                }
            });
            dispatch_sync(new RenderDocument($attempt->id));
        }

        return response()->json(['version' => $version->public_id, 'number' => $version->formatted_number]);
    }

    private static function refused(): JsonResponse
    {
        return response()->json(['message' => __('messages.msg_31', ['jenis' => 'PDF, JPG, PNG, WebP, DOCX, XLSX', 'ukuran' => '20 MB'])], 422);
    }
}
