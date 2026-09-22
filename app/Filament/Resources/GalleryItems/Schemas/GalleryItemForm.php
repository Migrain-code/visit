<?php

namespace App\Filament\Resources\GalleryItems\Schemas;

use App\Filament\Support\FormHelpers;
use App\Models\GalleryCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GalleryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Görsel')->schema([
                    FormHelpers::imageUpload('image', 'gallery', 'Fotoğraf')->required()->columnSpanFull(),
                    Grid::make(2)->schema([
                        TextInput::make('title')->label('Başlık')->required()->maxLength(150)
                            ->helperText('Örn: Pokut Yaylası\'nda bulut denizi'),
                        TextInput::make('alt_text')->label('ALT metni')->maxLength(150)
                            ->helperText('Boş bırakılırsa başlık kullanılır. Görseli tanımlayan kısa bir cümle yazın.'),
                        Select::make('gallery_category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')->label('Kategori adı')->required()->maxLength(100),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return GalleryCategory::query()->create([
                                    'name' => $data['name'],
                                    'slug' => Str::slug($data['name']),
                                    'sort_order' => GalleryCategory::query()->max('sort_order') + 1,
                                ])->getKey();
                            }),
                        Select::make('district_id')
                            ->label('İlçe (isteğe bağlı)')
                            ->relationship('district', 'name')
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name.' / '.$record->province->name),
                    ]),
                    Textarea::make('description')->label('Açıklama (isteğe bağlı)')->rows(2)->maxLength(300)->columnSpanFull(),
                ])->columnSpanFull(),
                Section::make('Yayın')->schema([
                    Toggle::make('is_active')->label('Yayında')->default(true),
                    Toggle::make('show_on_home')->label('Ana sayfada göster')->default(true),
                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                ])->columns(3)->columnSpanFull(),
            ]);
    }
}
