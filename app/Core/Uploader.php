<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Image uploads, treated as hostile input.
 *
 * The original file is never trusted or kept: the type is detected from its
 * bytes (not the client-supplied name or MIME), the image is decoded and
 * re-encoded through GD — which strips EXIF, embedded payloads and polyglot
 * headers — and it is written under a random name with a fixed extension.
 * The uploads directory additionally has PHP execution disabled via .htaccess.
 */
final class Uploader
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    private const MAX_DIM   = 1600;            // longest edge, in pixels

    /** @var array<int, string> allowed image types, keyed by the IMAGETYPE_* constant */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * @param array<string, mixed> $file a single entry from $_FILES
     * @param string $folder subdirectory under public/uploads (e.g. "avatars")
     * @return string the stored relative path, e.g. "avatars/9f2a....jpg"
     */
    public static function image(array $file, string $folder, string $field = 'image'): string
    {
        self::assertImageSupport($field);
        self::assertUploadOk($file, $field);

        $tmp = (string) $file['tmp_name'];

        // Must be a real uploaded file — blocks a path passed in by other means.
        if (!is_uploaded_file($tmp)) {
            throw new ValidationException([$field => 'That file could not be processed.']);
        }

        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new ValidationException([$field => 'Images must be 5 MB or smaller.']);
        }

        $info = @getimagesize($tmp);
        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new ValidationException([$field => 'Upload a JPG, PNG or WebP image.']);
        }

        [$width, $height, $type] = $info;

        // Guard against decompression bombs before allocating memory for GD.
        if ($width * $height > 50_000_000) {
            throw new ValidationException([$field => 'That image is too large to process.']);
        }

        $image = self::decode($tmp, $type);
        if ($image === null) {
            throw new ValidationException([$field => 'That image file appears to be damaged.']);
        }

        $image = self::constrain($image, $width, $height);

        $extension = self::ALLOWED[$type];
        $name      = bin2hex(random_bytes(16)) . '.' . $extension;
        $directory = self::directory($folder);
        $path      = $directory . DIRECTORY_SEPARATOR . $name;

        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 85),
            IMAGETYPE_PNG  => imagepng($image, $path, 6),
            IMAGETYPE_WEBP => imagewebp($image, $path, 85),
            default        => false,
        };

        imagedestroy($image);

        if ($saved === false) {
            throw new ValidationException([$field => 'We could not save that image. Please try again.']);
        }

        @chmod($path, 0644);

        return $folder . '/' . $name;
    }

    /**
     * Every upload is re-encoded through GD, so without the extension there is
     * no safe path to store one. Say so as a form error instead of letting the
     * undefined function surface as a 500 the artist cannot act on.
     */
    private static function assertImageSupport(string $field): void
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return;
        }

        Logger::error('Image upload attempted but the GD extension is not loaded.');

        throw new ValidationException([
            $field => 'Image uploads are unavailable on this server right now. Please contact support.',
        ]);
    }

    private static function assertUploadOk(array $file, string $field): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new ValidationException([$field => match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server allows.',
            UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE                        => 'Please choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the file. Contact support.',
            default                                   => 'That file could not be uploaded.',
        }]);
    }

    private static function decode(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /** Downscale so the longest edge is at most MAX_DIM, preserving transparency. */
    private static function constrain(\GdImage $image, int $width, int $height): \GdImage
    {
        $longest = max($width, $height);
        if ($longest <= self::MAX_DIM) {
            return $image;
        }

        $scale     = self::MAX_DIM / $longest;
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private static function directory(string $folder): string
    {
        // Only simple folder names are accepted — no traversal, no nesting.
        if (preg_match('/^[a-z0-9_-]+$/', $folder) !== 1) {
            throw new \InvalidArgumentException('Invalid upload folder.');
        }

        $directory = PP_PUBLIC . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Upload directory is not writable: ' . $directory);
        }

        return $directory;
    }

    /** Remove a previously stored upload, refusing anything outside uploads/. */
    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $base = realpath(PP_PUBLIC . DIRECTORY_SEPARATOR . 'uploads');
        if ($base === false) {
            return;
        }

        $target = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if ($target !== false && str_starts_with($target, $base) && is_file($target)) {
            @unlink($target);
        }
    }
}
