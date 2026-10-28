<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Contracts\RenderFailed;
use App\Modules\DOC\Contracts\RenditionTemplate;
use App\Modules\DOC\Models\DocumentVersion;
use App\Modules\DOC\Models\FileObject;
use Mustache\Engine;
use Mustache\Loader\FilesystemLoader;

/**
 * The one template per layout (DOCUMENT_PREVIEW_PRODUCER §3; DESIGN_SYSTEM §14.3): `resources/documents/DOC-nn.mustache`
 * with its shared partials and `paper.css`, which the browser's composer imports from the same files. An issued
 * version renders from its immutable payload alone (BR-DOC-03; DP-17): every value escaped — no template writes a raw
 * value and no value is ever called (SM-10) —, its identity assets copied by their immutable file and checksum into the
 * render's own asset directory, the only files besides the bundled fonts the renderer may read (FL-08).
 */
final class MustacheTemplates implements RenditionTemplate
{
    /** @var list<string> */
    public const ASSETS = ['logo', 'stamp', 'signature'];

    public function __construct(private readonly FileStore $store) {}

    public function html(DocumentVersion $version, string $assetDirectory): string
    {
        $payload = $version->payload;
        $paper = is_array($payload) && is_array($payload['paper'] ?? null) ? $payload['paper'] : throw new RenderFailed('The version has no issued payload.');
        $assets = is_array($payload['assets'] ?? null) ? $payload['assets'] : [];

        foreach (self::ASSETS as $name) {
            $asset = $assets[$name] ?? null;

            if (! is_array($asset) || ! is_numeric($asset['file'] ?? null)) {
                continue;
            }

            $source = $this->asset((int) $asset['file'], is_string($asset['sha256'] ?? null) ? $asset['sha256'] : '', $assetDirectory, $name);

            if ($name === 'logo') {
                $paper['issuer'] = [...(is_array($paper['issuer'] ?? null) ? $paper['issuer'] : []), 'logo_src' => $source];
            } else {
                $paper["{$name}_src"] = $source;
            }
        }

        return self::document(self::render($version->document_type_code, $paper));
    }

    /**
     * The layout's paper, from its template and a view.
     *
     * @param  array<mixed>  $paper
     */
    public static function render(string $layout, array $paper): string
    {
        if (preg_match('/^DOC-(0[1-9]|1[0-4])$/', $layout) !== 1) {
            throw new RenderFailed("No template is written for the layout {$layout}.");
        }

        $engine = new Engine([
            'loader' => new FilesystemLoader(self::directory()),
            'partials_loader' => new FilesystemLoader(self::directory().'/partials'),
            'escape' => fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'),
            'strict_callables' => true,
        ]);

        return $engine->render($layout, $paper);
    }

    /** The HTML document the renderer reads: the bundled fonts by their relative address, the shared print style inline. */
    public static function document(string $body): string
    {
        $style = (string) file_get_contents(self::directory().'/paper.css');

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Dokumen</title><link rel="stylesheet" href="fonts.css"><style>'.$style.'</style></head><body>'.$body.'</body></html>';
    }

    public static function directory(): string
    {
        return resource_path('documents');
    }

    /** An identity asset copied by its immutable file, its checksum verified, into the render's directory (DATABASE §14). */
    private function asset(int $fileId, string $sha256, string $assetDirectory, string $name): string
    {
        $file = FileObject::query()->find($fileId);

        if ($file === null) {
            throw new RenderFailed("The {$name} the version names is missing.");
        }

        $stream = $this->store->stream($file->storage_key, $file->storage_disk);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);

        if (! hash_equals($sha256, hash('sha256', $bytes))) {
            throw new RenderFailed("The {$name} the version names no longer matches its checksum.");
        }

        $extension = match ($file->mime_type) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => throw new RenderFailed("The {$name} is not an image the paper prints."),
        };
        $path = rtrim($assetDirectory, '/\\')."/{$name}.{$extension}";
        file_put_contents($path, $bytes);

        return 'file://'.str_replace('\\', '/', (string) realpath($path));
    }
}
