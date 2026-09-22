<?php

namespace App\Filament\Resources\Tours\Schemas;

use App\Filament\Support\FormHelpers;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class TourForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tur')
                    ->tabs([
                        Tab::make('Genel')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(2)->schema(FormHelpers::titleAndSlug('title', 'Tur adı', 'tours')),
                                Grid::make(3)->schema([
                                    Select::make('tour_category_id')
                                        ->label('Kategori')
                                        ->relationship('category', 'name')
                                        ->preload()
                                        ->searchable()
                                        ->native(false),
                                    TextInput::make('duration_days')->label('Gün')->numeric()->minValue(1)->maxValue(60)->default(1)->required(),
                                    TextInput::make('duration_nights')->label('Gece')->numeric()->minValue(0)->maxValue(60)->default(0)->required()
                                        ->helperText('Günübirlik tur: 1 gün, 0 gece.'),
                                ]),
                                Textarea::make('short_description')
                                    ->label('Kısa açıklama')
                                    ->rows(2)
                                    ->maxLength(220)
                                    ->helperText('Tur kartlarında görünür.')
                                    ->columnSpanFull(),
                                Grid::make(2)->schema([
                                    TextInput::make('destinations')->label('Gezilecek yerler')->maxLength(255)->placeholder('Fırtına Vadisi, Şenyuva Köprüsü, Ayder Yaylası, Gelintülü Şelalesi'),
                                    TextInput::make('departure_point')->label('Kalkış yeri')->maxLength(255)->placeholder('Rize ve Trabzon\'daki otelinizden ya da biniş noktanızdan'),
                                    TextInput::make('transport')->label('Ulaşım')->maxLength(255)->placeholder('Klimalı minibüs; yayla yolunda yayla aracı'),
                                    TextInput::make('accommodation')->label('Konaklama')->maxLength(255)->placeholder('4* otel, yarım pansiyon'),
                                ]),
                                RichEditor::make('description')->label('Tur açıklaması')->columnSpanFull(),
                            ]),

                        Tab::make('Fiyat')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('price')->label('Kişi başı fiyat')->numeric()->minValue(0)->step('0.01')
                                        ->helperText('Seferde ayrı fiyat girilmezse bu fiyat geçerlidir.'),
                                    TextInput::make('old_price')->label('Eski fiyat (üstü çizili)')->numeric()->minValue(0)->step('0.01')
                                        ->gt('price')
                                        ->helperText('İndirim göstermek için; boş bırakılabilir.'),
                                    Select::make('currency')->label('Para birimi')
                                        ->options(['TRY' => '₺ Türk Lirası', 'EUR' => '€ Euro', 'USD' => '$ Dolar', 'GBP' => '£ Sterlin'])
                                        ->default('TRY')->required()->native(false),
                                ]),
                                TextInput::make('price_note')->label('Fiyat notu')->maxLength(255)
                                    ->placeholder('Kişi başı, çift kişilik odada. 0-6 yaş ücretsiz.')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Görseller')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                FormHelpers::imageUpload('image', 'tours', 'Kapak görseli')->columnSpanFull(),
                                TextInput::make('image_alt')->label('Kapak görseli ALT metni')->maxLength(150)
                                    ->helperText('SEO ve erişilebilirlik için görseli tanımlayın.'),
                                FormHelpers::galleryUpload('gallery', 'tours/gallery', 'Tur galerisi'),
                            ]),

                        Tab::make('Program & Detay')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                FormHelpers::stepsRepeater('itinerary', 'Gün gün tur programı')
                                    ->addActionLabel('Gün ekle'),
                                FormHelpers::simpleList('highlights', 'Öne çıkanlar'),
                                FormHelpers::simpleList('included', 'Fiyata dahil olanlar'),
                                FormHelpers::simpleList('excluded', 'Fiyata dahil olmayanlar'),
                                FormHelpers::faqRepeater('faqs', 'Sık sorulan sorular'),
                            ]),

                        Tab::make('SEO & Yayın')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                FormHelpers::seoSection(),
                                Section::make('Yayın')->schema([
                                    Toggle::make('is_active')->label('Yayında')->default(true),
                                    Toggle::make('is_featured')->label('Ana sayfada göster')->default(true),
                                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                                ])->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
