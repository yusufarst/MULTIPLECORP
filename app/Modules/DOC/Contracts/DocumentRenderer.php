<?php

namespace App\Modules\DOC\Contracts;

/**
 * The renderer of SECURITY FL-08 behind its small interface (ARCHITECTURE §2; TASK-022's MODULARITY): an
 * issued document's HTML in, its PDF out, reading only the bundled assets and the per-render directory of
 * allowlisted identity assets. Replaceable without touching a template beyond the shared print style.
 */
interface DocumentRenderer
{
    /**
     * @param  string|null  $assetDirectory  a directory holding this render's identity assets, also allowlisted
     *
     * @throws RenderFailed
     */
    public function render(string $html, ?string $assetDirectory = null): string;

    /** The renderer and its version, recorded on the rendition (`document_renditions.renderer_version`). */
    public function version(): string;
}
