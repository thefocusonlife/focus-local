<?php
declare(strict_types=1);

final class ImagickProcessor implements ImageProcessorInterface
{
    public function resizeToMaxDim(
        string $srcPath,
        string $destPath,
        int $maxDim,
        string $format,
        int $quality,
    ): void {
        $img = new Imagick($srcPath);

        // auto-orient (important for phone photos)
        if (method_exists($img, 'autoOrient')) {
            $img->autoOrient();
        } else {
            // fallback for older Imagick builds
            $orientation = $img->getImageOrientation();
            if ($orientation !== Imagick::ORIENTATION_UNDEFINED) {
                $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            }
        }

        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        // Only shrink (never enlarge)
        $scale = min($maxDim / max($w, 1), $maxDim / max($h, 1), 1.0);

        if ($scale < 1.0) {
            $newW = (int) max(1, round($w * $scale));
            $newH = (int) max(1, round($h * $scale));
            // Lanczos = good quality
            $img->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1);
        }

        $format = strtolower($format);
        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        // Flatten transparency for JPG
        if ($format === 'jpg') {
            $img->setImageFormat('jpg');
            $img->setImageCompression(Imagick::COMPRESSION_JPEG);
            $img->setImageCompressionQuality($quality);

            // If source has alpha, flatten onto white
            if ($img->getImageAlphaChannel()) {
                $bg = new Imagick();
                $bg->newImage($img->getImageWidth(), $img->getImageHeight(), 'white');
                $bg->compositeImage($img, Imagick::COMPOSITE_OVER, 0, 0);
                $img->destroy();
                $img = $bg;
                $img->setImageFormat('jpg');
                $img->setImageCompression(Imagick::COMPRESSION_JPEG);
                $img->setImageCompressionQuality($quality);
            }
        } elseif ($format === 'webp') {
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality($quality);
        } elseif ($format === 'png') {
            $img->setImageFormat('png');
            // PNG compression is different; keep default
        } else {
            throw new RuntimeException("Unsupported output format: {$format}");
        }

        $ok = $img->writeImage($destPath);
        $img->destroy();

        if (!$ok) {
            throw new RuntimeException('Imagick failed to write output image.');
        }
    }
}
