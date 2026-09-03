<?php

declare(strict_types=1);

/**
 * Rebuild the responsive variants for one hero photograph.
 *
 *   php bin/showcase.php path/to/photo.jpg nails
 *
 * Takes any large JPEG, PNG or WebP and writes the four files the hero tiles
 * expect — 480w and 960w, in both WebP and JPEG — into
 * public/assets/img/showcase/.
 *
 * Kept as a script rather than doing it at request time because resizing a
 * 4000px photo on every page view would be absurd, and because the person
 * swapping the picture should see what came out before it ships.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

const SHOWCASE_WIDTHS = [480, 960];
const SHOWCASE_NAMES  = ['nails', 'hair', 'makeup', 'skin', 'menu', 'studio', 'clients', 'products'];

cli_heading('PolishedPro — hero image builder');

$source = $argv[1] ?? '';
$name   = $argv[2] ?? '';

if ($source === '' || $name === '') {
    echo "Usage: php bin/showcase.php <source-image> <name>\n\n";
    echo "  <name> is one of: " . implode(', ', SHOWCASE_NAMES) . "\n";
    echo "  or any new name, if you have also referenced it from a view.\n";
    exit(1);
}

if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
    cli_error('The name may only contain lowercase letters, digits and hyphens.');
    exit(1);
}

if (!is_file($source)) {
    cli_error("No such file: {$source}");
    exit(1);
}

if (!extension_loaded('gd')) {
    cli_error('The GD extension is required to resize images.');
    exit(1);
}

$info = @getimagesize($source);
if ($info === false) {
    cli_error('That file is not an image GD can read.');
    exit(1);
}

[$width, $height, $type] = $info;

$image = match ($type) {
    IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
    IMAGETYPE_PNG  => @imagecreatefrompng($source),
    IMAGETYPE_WEBP => @imagecreatefromwebp($source),
    default        => false,
};

if (!$image instanceof \GdImage) {
    cli_error('Only JPEG, PNG and WebP sources are supported.');
    exit(1);
}

// The tiles are 3:4 portraits. Anything else gets centre-cropped to fit rather
// than squashed, so a landscape source still produces a usable tile.
$targetRatio = 3 / 4;
$sourceRatio = $width / $height;

$cropW = $width;
$cropH = $height;
$cropX = 0;
$cropY = 0;

if (abs($sourceRatio - $targetRatio) > 0.01) {
    if ($sourceRatio > $targetRatio) {
        $cropW = (int) round($height * $targetRatio);
        $cropX = (int) round(($width - $cropW) / 2);
    } else {
        $cropH = (int) round($width / $targetRatio);
        $cropY = (int) round(($height - $cropH) / 2);
    }

    cli_warn(sprintf('Cropped from %dx%d to %dx%d to fit the 3:4 tile.', $width, $height, $cropW, $cropH));
}

if ($cropW < 960) {
    cli_warn('Source is narrower than 960px — the 960w file will be upscaled and may look soft.');
}

$directory = PP_PUBLIC . '/assets/img/showcase';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    cli_error('Could not create ' . $directory);
    exit(1);
}

$written = 0;

foreach (SHOWCASE_WIDTHS as $targetWidth) {
    $targetHeight = (int) round($targetWidth / $targetRatio);

    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled(
        $canvas, $image,
        0, 0, $cropX, $cropY,
        $targetWidth, $targetHeight, $cropW, $cropH
    );

    foreach (['jpg' => 82, 'webp' => 78] as $format => $quality) {
        $path = "{$directory}/{$name}-{$targetWidth}.{$format}";

        $ok = $format === 'jpg'
            ? imagejpeg($canvas, $path, $quality)
            : imagewebp($canvas, $path, $quality);

        if ($ok === false) {
            cli_error("Could not write {$path}");
            exit(1);
        }

        @chmod($path, 0644);
        $written++;
        printf("  %-28s %6.1f KB\n", basename($path), filesize($path) / 1024);
    }

    imagedestroy($canvas);
}

imagedestroy($image);

cli_ok("Wrote {$written} files for \"{$name}\".");
echo "\nSample a tint from the new photo and update the \$swatches / \$tiles array\n";
echo "in the view, so the tile shows the right colour while the image loads.\n";
