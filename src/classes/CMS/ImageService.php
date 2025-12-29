<?php
namespace PhpBook\CMS;

use RuntimeException;

class ImageService
{
    private string $uploadsDir;

    public function __construct(string $uploadsDir)
    {
        $this->uploadsDir = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Save uploaded image using GD:
     * - validates upload (size + MIME via finfo)
     * - determines orientation (with EXIF auto-rotate for JPEG when possible)
     * - resizes to TFOL targets (landscape 1200x700, portrait 420x560)
     * - saves as JPG with standardized TFOL filename
     *
     * Returns: ['filename' => string, 'landscape' => int]
     */
    public function saveUploadedStoryImage(array $file, int $imageId, string $storyTitle): array
    {
        // ---- Validate upload payload ----
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload payload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (PHP error code: ' . $file['error'] . ').');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload temp file missing.');
        }
        if (!isset($file['size']) || $file['size'] <= 0) {
            throw new RuntimeException('Empty upload.');
        }
        if ($file['size'] > (int) MAX_SIZE) {
            throw new RuntimeException(
                'File too large. Max is ' . round(MAX_SIZE / 1024 / 1024) . 'MB.',
            );
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, MEDIA_TYPES, true)) {
            throw new RuntimeException('Unsupported image type: ' . $mime);
        }

        // ---- Load image with GD (based on real MIME) ----
        $src = $this->gdCreateFromMime($file['tmp_name'], $mime);
        if (!$src) {
            throw new RuntimeException('Failed to load image with GD.');
        }

        // Auto-rotate JPEG from EXIF if possible (smartphone photos!)
        if ($mime === 'image/jpeg') {
            $src = $this->autoOrientJpegIfPossible($src, $file['tmp_name']);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($src);
            throw new RuntimeException('Invalid image dimensions.');
        }

        // ---- Determine target dims and landscape flag ----
        if ($origW > $origH) {
            $targetW = 1200;
            $targetH = 700;
            $landscape = 1;
        } elseif ($origW < $origH) {
            $targetW = 420;
            $targetH = 560;
            $landscape = 0;
        } else {
            // square: keep it square but constrain to 700 (or adjust if you prefer)
            $targetW = min($origW, 700);
            $targetH = $targetW;
            $landscape = 0;
        }

        // ---- Resize to cover target (so we can center-crop) ----
        // Scale so BOTH dimensions meet/exceed target, then crop center.
        $scale = max($targetW / $origW, $targetH / $origH);
        $resizeW = (int) ceil($origW * $scale);
        $resizeH = (int) ceil($origH * $scale);

        $resized = imagecreatetruecolor($resizeW, $resizeH);
        if (!$resized) {
            imagedestroy($src);
            throw new RuntimeException('Failed to allocate resized image.');
        }

        // Better quality for downscaling
        imagealphablending($resized, true);
        imagesavealpha($resized, false);

        if (!imagecopyresampled($resized, $src, 0, 0, 0, 0, $resizeW, $resizeH, $origW, $origH)) {
            imagedestroy($src);
            imagedestroy($resized);
            throw new RuntimeException('Resample failed.');
        }

        // ---- Center crop to exact target size ----
        $cropX = (int) max(0, floor(($resizeW - $targetW) / 2));
        $cropY = (int) max(0, floor(($resizeH - $targetH) / 2));

        $final = imagecreatetruecolor($targetW, $targetH);
        if (!$final) {
            imagedestroy($src);
            imagedestroy($resized);
            throw new RuntimeException('Failed to allocate final image.');
        }

        if (!imagecopy($final, $resized, 0, 0, $cropX, $cropY, $targetW, $targetH)) {
            imagedestroy($src);
            imagedestroy($resized);
            imagedestroy($final);
            throw new RuntimeException('Crop failed.');
        }

        // ---- Build TFOL filename and save as JPG ----
        $filename = $this->buildFilename($imageId, $storyTitle, 'jpg');
        $destPath = $this->uploadsDir . $filename;

        $this->normalizeUploadsDir($this->uploadsDir);

        if (
            !imagejpeg(
                $final,
                $destPath,
                defined('IMAGE_JPEG_QUALITY') ? (int) IMAGE_JPEG_QUALITY : 82,
            )
        ) {
            imagedestroy($src);
            imagedestroy($resized);
            imagedestroy($final);
            throw new RuntimeException('Failed to write image to uploads.');
        }

        // ✅ normalize perms AFTER final write succeeds
        $this->normalizeUploadsDir($this->uploadsDir); // optional here; better once earlier (see note below)
        $this->normalizeUploadFile($destPath);

        // Cleanup
        imagedestroy($src);
        imagedestroy($resized);
        imagedestroy($final);

        return ['filename' => $filename, 'landscape' => $landscape];
    }

    private function detectMime(string $tmpPath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($tmpPath) ?: '';
    }

    private function gdCreateFromMime(string $tmpPath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/gif' => @imagecreatefromgif($tmpPath),
            'image/webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($tmpPath)
                : false,
            default => false,
        };
    }
    public function saveUploadedMemberImage(
        array $file,
        int $memberId,
        string $displayName = '',
    ): array {
        // Validate upload payload (same checks as story)
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Invalid upload payload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed (PHP error code: ' . $file['error'] . ').');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Upload temp file missing.');
        }
        if (!isset($file['size']) || $file['size'] <= 0) {
            throw new \RuntimeException('Empty upload.');
        }
        if ($file['size'] > (int) MAX_SIZE) {
            throw new \RuntimeException(
                'File too large. Max is ' . round(MAX_SIZE / 1024 / 1024) . 'MB.',
            );
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, MEDIA_TYPES, true)) {
            throw new \RuntimeException('Unsupported image type: ' . $mime);
        }

        $src = $this->gdCreateFromMime($file['tmp_name'], $mime);
        if (!$src) {
            throw new \RuntimeException('Failed to load image with GD.');
        }

        // Auto-rotate JPEGs using EXIF (smartphones)
        if ($mime === 'image/jpeg') {
            $src = $this->autoOrientJpegIfPossible($src, $file['tmp_name']);
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($src);
            throw new \RuntimeException('Invalid image dimensions.');
        }

        // Target: square avatar
        $target = 512; // adjust to 400/512 as you prefer
        $targetW = $target;
        $targetH = $target;

        // Resize-to-cover then center-crop
        $scale = max($targetW / $origW, $targetH / $origH);
        $resizeW = (int) ceil($origW * $scale);
        $resizeH = (int) ceil($origH * $scale);

        $resized = imagecreatetruecolor($resizeW, $resizeH);
        if (!$resized) {
            imagedestroy($src);
            throw new \RuntimeException('Failed to allocate resized image.');
        }

        imagealphablending($resized, true);
        imagesavealpha($resized, false);

        if (!imagecopyresampled($resized, $src, 0, 0, 0, 0, $resizeW, $resizeH, $origW, $origH)) {
            imagedestroy($src);
            imagedestroy($resized);
            throw new \RuntimeException('Resample failed.');
        }

        $cropX = (int) max(0, floor(($resizeW - $targetW) / 2));
        $cropY = (int) max(0, floor(($resizeH - $targetH) / 2));

        $final = imagecreatetruecolor($targetW, $targetH);
        if (!$final) {
            imagedestroy($src);
            imagedestroy($resized);
            throw new \RuntimeException('Failed to allocate final image.');
        }

        if (!imagecopy($final, $resized, 0, 0, $cropX, $cropY, $targetW, $targetH)) {
            imagedestroy($src);
            imagedestroy($resized);
            imagedestroy($final);
            throw new \RuntimeException('Crop failed.');
        }

        // Stable filename (no renames needed if display name changes)
        $id = str_pad((string) $memberId, 6, '0', STR_PAD_LEFT);
        $filename = "{$id}_profile.jpg";

        // Optional: keep member pics organized
        $subdir = 'members' . DIRECTORY_SEPARATOR;
        $destDir = $this->uploadsDir . $subdir;

        $this->normalizeUploadsDir($this->uploadsDir);
        $this->normalizeUploadsDir($destDir);

        $destPath = $destDir . $filename;

        if (
            !imagejpeg(
                $final,
                $destPath,
                defined('IMAGE_JPEG_QUALITY') ? (int) IMAGE_JPEG_QUALITY : 82,
            )
        ) {
            imagedestroy($src);
            imagedestroy($resized);
            imagedestroy($final);
            throw new \RuntimeException('Failed to write member image to uploads.');
        }

        // ✅ normalize AFTER final write
        $this->normalizeUploadFile($destPath);

        // Store path relative to uploads so templates can build URL easily
        return ['filename' => 'members/' . $filename];
    }

    private function normalizeUploadsDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // directory should be rwx for owner/group; and setgid so new files inherit group
        @chmod($dir, 02775);

        // best-effort: keep group consistent (won't always work, but harmless to try)
        @chgrp($dir, 'geoff');
    }

    private function normalizeUploadFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        // Most important: make it group-writable so you can edit without sudo
        @chmod($path, 0664);

        // best-effort group fix
        @chgrp($path, 'geoff');
    }

    private function autoOrientJpegIfPossible($img, string $tmpPath)
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($tmpPath);
        if (!$exif || empty($exif['Orientation'])) {
            return $img;
        }

        $orientation = (int) $exif['Orientation'];

        // Only handle the common rotations; others are rare
        return match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    private function buildFilename(int $imageId, string $title, string $ext): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        $id = str_pad((string) $imageId, 6, '0', STR_PAD_LEFT);
        return "{$id}_{$slug}.{$ext}";
    }
} //end class
