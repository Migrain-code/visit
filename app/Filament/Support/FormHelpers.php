<?php

namespace App\Filament\Support;

use App\Services\Media\WebpConverter;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FormHelpers
{
    /**
     * Görsel yükleme alanı.
     *
     * Yüklenen her görsel OTOMATİK WebP'ye çevrilir: aynı kalitede belirgin
     * küçük dosya, daha hızlı sayfa. Telefon fotoğraflarındaki EXIF dönüklüğü
     * düzeltilir ve çok büyük görseller küçültülür.
     */
    public static function imageUpload(string $field, string $directory, string $label = 'Görsel'): FileUpload
    {
        return FileUpload::make($field)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->imageEditor()
            ->maxSize(8192)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, FileUpload $component) use ($directory) {
                return app(WebpConverter::class)->store(
                    $file,
                    $component->getDiskName(),
                    $directory,
                    $component->getVisibility(),
                );
            })
            ->helperText('JPG, PNG, GIF veya WebP; en fazla 8 MB. Yüklenen görsel otomatik olarak WebP\'ye çevrilir.');
    }
}
