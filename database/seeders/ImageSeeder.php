<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Örnek görselleri (database/seeders/images) public diske (storage/app/public) kopyalar.
 * Admin panelinden yüklenen görsellerle aynı yerde durdukları için panelde önizlenebilir ve değiştirilebilirler.
 */
class ImageSeeder extends Seeder
{
    public function run(): void
    {
        $source = database_path('seeders/images');
        $disk = Storage::disk('public');

        foreach (File::allFiles($source) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));

            if (! $disk->exists($relative)) {
                $disk->put($relative, File::get($file->getPathname()));
            }
        }
    }
}
