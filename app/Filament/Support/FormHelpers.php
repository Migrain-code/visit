<?php

namespace App\Filament\Support;

use App\Rules\UniquePublicSlug;
use App\Services\Media\WebpConverter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FormHelpers
{
    /**
     * Başlık + otomatik üretilen slug alanları.
     *
     * @param  string|null  $publicTable  Kök dizinde yayınlanan tablolar için (tours, provinces, pages) çakışma kontrolü.
     */
    public static function titleAndSlug(string $titleField = 'title', string $titleLabel = 'Başlık', ?string $publicTable = null): array
    {
        return [
            TextInput::make($titleField)
                ->label($titleLabel)
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old) {
                    $current = $get('slug');

                    if (blank($current) || $current === Str::slug((string) $old)) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),

            TextInput::make('slug')
                ->label('URL (slug)')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->rules($publicTable
                    ? [fn (?Model $record) => new UniquePublicSlug($publicTable, $record?->getKey())]
                    : [])
                ->helperText('Adres çubuğunda görünecek kısa isim. Örn: ayder-yaylasi-turu'),
        ];
    }

    public static function seoSection(): Section
    {
        return Section::make('SEO')
            ->description('Boş bırakılırsa başlık ve açıklamadan otomatik üretilir.')
            ->schema([
                TextInput::make('meta_title')
                    ->label('Meta başlık')
                    ->maxLength(70)
                    ->helperText('Google sonuçlarında görünen başlık (en fazla 60-70 karakter).'),
                Textarea::make('meta_description')
                    ->label('Meta açıklama')
                    ->rows(3)
                    ->maxLength(320)
                    ->helperText('Google sonuçlarında görünen açıklama (150-160 karakter idealdir).'),
            ])
            ->collapsible()
            ->collapsed()
            ->columnSpanFull();
    }

    public static function faqRepeater(string $field = 'faqs', string $label = 'Sık Sorulan Sorular'): Repeater
    {
        return Repeater::make($field)
            ->label($label)
            ->schema([
                TextInput::make('question')->label('Soru')->required()->maxLength(255),
                Textarea::make('answer')->label('Cevap')->required()->rows(3),
            ])
            ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
            ->addActionLabel('Soru ekle')
            ->collapsible()
            ->collapsed()
            ->reorderableWithButtons()
            ->defaultItems(0)
            ->columnSpanFull();
    }

    public static function simpleList(string $field, string $label, string $addLabel = 'Madde ekle'): Repeater
    {
        return Repeater::make($field)
            ->label($label)
            ->simple(
                TextInput::make('item')->label('Madde')->required()->maxLength(255)
            )
            ->addActionLabel($addLabel)
            ->reorderableWithButtons()
            ->defaultItems(0)
            ->columnSpanFull();
    }

    public static function stepsRepeater(string $field = 'itinerary', string $label = 'Tur Programı'): Repeater
    {
        return Repeater::make($field)
            ->label($label)
            ->schema([
                TextInput::make('title')->label('Başlık')->required()->maxLength(255)->placeholder('Sabah: Hareket ve Fırtına Vadisi'),
                Textarea::make('description')->label('Açıklama')->rows(3),
            ])
            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
            ->addActionLabel('Adım ekle')
            ->collapsible()
            ->reorderableWithButtons()
            ->defaultItems(0)
            ->columnSpanFull();
    }

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

    /** Birden çok görsel (tur galerisi). Her biri WebP'ye çevrilir; sıra sürükleyerek değişir. */
    public static function galleryUpload(string $field, string $directory, string $label = 'Galeri'): FileUpload
    {
        return static::imageUpload($field, $directory, $label)
            ->multiple()
            ->reorderable()
            ->appendFiles()
            ->maxFiles(20)
            ->panelLayout('grid')
            ->columnSpanFull();
    }

    public static function iconInput(string $field = 'icon'): TextInput
    {
        return TextInput::make($field)
            ->label('İkon (Font Awesome sınıfı)')
            ->placeholder('fa-solid fa-mountain-sun')
            ->maxLength(100)
            ->helperText('Örn: fa-solid fa-mountain-sun, fa-solid fa-landmark, fa-solid fa-umbrella-beach. İkon listesi: fontawesome.com/icons');
    }
}
