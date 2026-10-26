<?php

namespace App\Modules\DOC\Contracts;

use App\Modules\DOC\Models\DocumentVersion;

/**
 * The issued layout's template (DESIGN_SYSTEM §14.3; DOCUMENT_PREVIEW_PRODUCER §3): the HTML of an issued
 * version from its immutable payload alone, every value escaped, the same template the browser's composer
 * reads. It may place the version's identity assets in the render's asset directory, read in system context
 * (AZ-09; PJ-19). TASK-029 writes the templates; until then no layout renders.
 */
interface RenditionTemplate
{
    /** @throws RenderFailed when no template is written for the version's layout */
    public function html(DocumentVersion $version, string $assetDirectory): string;
}
