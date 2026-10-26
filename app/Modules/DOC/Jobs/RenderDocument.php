<?php

namespace App\Modules\DOC\Jobs;

use App\Modules\DOC\Contracts\DocumentRenderer;
use App\Modules\DOC\Contracts\RenderFailed;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Services\Renditions;
use App\Support\Jobs\PlatformJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * JB-01 (CONCURRENCY_IDEMPOTENCY §15; SF-RENDER): renders an issued version's PDF in system context (AZ-09) —
 * dispatched after AX-15's commit for its PENDING attempt, or by JB-02 for an attempt owed. Unique by the
 * attempt it is dispatched for, its lock outliving the 120-second timeout and the queue wait it tolerates
 * (HO-22): it claims its attempt, renders outside every transaction from the issued payload alone, then
 * finishes or fails the attempt. A retry adds an attempt and never reissues (BR-DOC-03; C-12).
 */
final class RenderDocument extends PlatformJob implements ShouldBeUnique
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout;

    public int $uniqueFor;

    public function __construct(public readonly int $renditionId)
    {
        parent::__construct();
        $this->timeout = config()->integer('files.renditions.timeout_seconds', 120);
        $this->uniqueFor = config()->integer('files.renditions.unique_for_seconds', 600);
    }

    public function uniqueId(): string
    {
        return (string) $this->renditionId;
    }

    public function handle(Renditions $renditions, RenditionTemplate $template, DocumentRenderer $renderer): void
    {
        $attempt = $renditions->claim($this->renditionId);

        if ($attempt === null) {
            return;
        }

        $version = DocumentVersion::query()->findOrFail($attempt->document_version_id);
        $assets = sys_get_temp_dir().'/render-'.$attempt->id.'-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($assets, 0700);

        try {
            $pdf = $renderer->render($template->html($version, $assets), $assets);
        } catch (RenderFailed $failed) {
            $renditions->fail($attempt, $failed->getMessage());

            return;
        } catch (Throwable $unexpected) {
            $renditions->fail($attempt, 'The render stopped: '.class_basename($unexpected));

            throw $unexpected;
        } finally {
            File::deleteDirectory($assets);
        }

        $renditions->finish($attempt, $pdf, $renderer->version());
    }
}
