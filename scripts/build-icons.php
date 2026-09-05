<?php

declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "The GD extension is required to create PWA icons.\n");
    exit(1);
}

foreach ([192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);
    imageantialias($image, true);
    $blue = imagecolorallocate($image, 12, 109, 178);
    $white = imagecolorallocate($image, 255, 255, 255);
    $water = imagecolorallocate($image, 126, 205, 241);
    imagefill($image, 0, 0, $blue);
    $scale = $size / 512;
    $points = [256, 82, 214, 142, 143, 228, 143, 299, 143, 367, 193, 421, 256, 421,
        319, 421, 369, 367, 369, 299, 369, 228, 298, 142];
    $scaled = array_map(static fn (int $value): int => (int) round($value * $scale), $points);
    imagefilledpolygon($image, $scaled, 12, $white);
    imagefilledellipse($image, (int) (256 * $scale), (int) (326 * $scale), (int) (145 * $scale), (int) (91 * $scale), $water);
    imagepng($image, dirname(__DIR__) . '/assets/icons/icon-' . $size . '.png', 9);
    imagedestroy($image);
}
