<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\File;

class ImageUtils
{
    /**
     * Convert local path to Base64 encoded data source
     *
     * @return string
     */
    public static function toBase64Src($path)
    {
        if (! $path || ! file_exists($path)) {
            // Return a 1x1 transparent PNG as placeholder
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }

        try {
            return sprintf('data:%s;base64,%s', File::mimeType($path), base64_encode(File::get($path)));
        } catch (\Exception $e) {
            // Return placeholder on error
            return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }
    }
}
