<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\RenderFailed;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Models\DocumentVersion;

/** The binding until TASK-029 writes the layouts' templates: every attempt fails visibly, never silently. */
final class UnwrittenTemplates implements RenditionTemplate
{
    public function html(DocumentVersion $version, string $assetDirectory): string
    {
        throw new RenderFailed("No template is written for the layout of {$version->document_type_code} yet.");
    }
}
