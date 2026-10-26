<?php

namespace App\Modules\DOC\Services;

use App\Modules\DOC\Enums\DetectedType;
use GdImage;

/**
 * The image library of SECURITY FL-03 and H9-12 (DIR-099 C2): PHP's GD, with only the JPEG, PNG and WebP
 * decoders, each called by name — no other coder, delegate, remote URL or indirect read exists on this path.
 * The dimensions are read from the header before any decode, so a decompression bomb is refused unread
 * (≤ 40 megapixels, ≤ 10,000 px per side); re-encoding writes fresh bytes, which carry no EXIF/GPS or trailing
 * payload. Time and memory are bounded by the request's or the job's own limits.
 */
final class ImageCodec
{
    public const MAXIMUM_SIDE = 10_000;

    public const MAXIMUM_PIXELS = 40_000_000;

    /** The derivatives of JB-04: a list thumbnail (PT-22's 40 px at twice its density) and a preview. */
    public const VARIANTS = ['thumbnail' => 80, 'preview' => 1600];

    /** Refuses an image whose header does not name its detected type, or whose dimensions exceed FL-03's. */
    public function checkHeader(string $path, DetectedType $type): void
    {
        $info = @getimagesize($path);

        if ($info === false || $info[2] !== self::imageType($type)) {
            throw new UploadRefused(UploadRefused::IMAGE);
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1 || $width > self::MAXIMUM_SIDE || $height > self::MAXIMUM_SIDE || $width * $height > self::MAXIMUM_PIXELS) {
            throw new UploadRefused(UploadRefused::IMAGE_DIMENSIONS);
        }
    }

    /** H9-12's memory bound of a decode: FL-03's 40 megapixels at four bytes each, with room for one derivative. */
    public const MEMORY_LIMIT = '512M';

    /** Decodes with the one decoder of the detected type, after the header check. */
    public function decode(string $path, DetectedType $type): GdImage
    {
        $this->checkHeader($path, $type);
        self::boundMemory();

        $image = match ($type) {
            DetectedType::Jpeg => @imagecreatefromjpeg($path),
            DetectedType::Png => @imagecreatefrompng($path),
            DetectedType::Webp => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new UploadRefused(UploadRefused::IMAGE);
        }

        return $image;
    }

    /** Fresh bytes of the same type, without the original's metadata or trailing payload. */
    public function reencode(string $path, DetectedType $type): string
    {
        $image = $this->decode($path, $type);

        if ($type !== DetectedType::Jpeg) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return $this->encode($image, $type);
    }

    /** A metadata-free WebP no larger than the variant's box, never enlarged (JB-04; PF-31). */
    public function derive(string $path, DetectedType $type, string $variant): string
    {
        $box = self::VARIANTS[$variant];
        $image = $this->decode($path, $type);
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $box / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        return $this->encode($target, DetectedType::Webp);
    }

    private function encode(GdImage $image, DetectedType $type): string
    {
        ob_start();
        $written = match ($type) {
            DetectedType::Jpeg => imagejpeg($image, null, 90),
            DetectedType::Png => imagepng($image, null, 9),
            default => imagewebp($image, null, 85),
        };
        $bytes = (string) ob_get_clean();

        if (! $written || $bytes === '') {
            throw new UploadRefused(UploadRefused::IMAGE);
        }

        return $bytes;
    }

    /** Raises a lower memory limit to the decode's bound, never lowers a higher or unlimited one. */
    private static function boundMemory(): void
    {
        $current = (string) ini_get('memory_limit');

        if ($current !== '-1' && self::bytes($current) < self::bytes(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }

    private static function bytes(string $limit): int
    {
        $value = (int) $limit;

        return match (strtoupper(substr(trim($limit), -1))) {
            'G' => $value * 1024 ** 3,
            'M' => $value * 1024 ** 2,
            'K' => $value * 1024,
            default => $value,
        };
    }

    private static function imageType(DetectedType $type): int
    {
        return match ($type) {
            DetectedType::Jpeg => IMAGETYPE_JPEG,
            DetectedType::Png => IMAGETYPE_PNG,
            DetectedType::Webp => IMAGETYPE_WEBP,
            default => -1,
        };
    }
}
