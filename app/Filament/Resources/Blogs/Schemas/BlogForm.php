<?php

namespace App\Filament\Resources\Blogs\Schemas;

use App\Filament\Support\FormHelpers;
use App\Models\Blog;
use App\Services\Seo\DuplicateGuard;
use App\Support\TurkishText;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Yazı')->tabs([
                Tab::make('İçerik')->icon('heroicon-o-document-text')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('title')
                            ->label('Başlık')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old) {
                                if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                                    $set('slug', TurkishText::slug((string) $state));
                                }
                            })
                            // Çakışma filtresi PANELDE de uyarır: elle girilen başlık da tekrar olabilir.
                            ->helperText(function (Get $get, ?Model $record) {
                                $title = (string) $get('title');

                                if (trim($title) === '') {
                                    return null;
                                }

                                $guard = app(DuplicateGuard::class);
                                $match = $guard->bestMatch($title, $record?->getKey());

                                if ($match && $match['score'] >= $guard->threshold()) {
                                    return '⚠️ Bu başlık mevcut bir içerikle %'.round($match['score'] * 100).' benzer: '.$match['label'];
                                }

                                return null;
                            }),
                        TextInput::make('slug')->label('URL (slug)')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true)
                            ->prefix(url('/blog').'/'),
                    ]),
                    Grid::make(2)->schema([
                        Select::make('blog_category_id')->label('Kategori')->relationship('category', 'name')->preload()->searchable(),
                        TextInput::make('primary_keyword')->label('Ana anahtar kelime')
                            ->maxLength(255)
                            ->helperText('Bu yazının hedeflediği tek kelime. Aynı kelimeyi ikinci bir yazıya vermeyin.'),
                    ]),
                    Textarea::make('excerpt')->label('Özet')->rows(2)->maxLength(300)->columnSpanFull(),
                    FormHelpers::imageUpload('image', 'blog', 'Kapak görseli'),
                    TextInput::make('image_alt')->label('Görsel ALT metni')->maxLength(200),
                    RichEditor::make('body_html')->label('Yazı')->columnSpanFull()
                        ->helperText('Gövdeye elle link eklemeyin; iç link motoru render anında basar.'),
                ]),

                Tab::make('SSS & SEO')->icon('heroicon-o-globe-alt')->schema([
                    FormHelpers::faqRepeater('faqs', 'Sık sorulan sorular'),
                    FormHelpers::seoSection(),
                ]),

                Tab::make('Yayın')->icon('heroicon-o-calendar')->schema([
                    Section::make()->schema([
                        Grid::make(3)->schema([
                            Select::make('status')->label('Durum')->options(Blog::STATUSES)->default(Blog::STATUS_DRAFT)->required()->native(false),
                            DateTimePicker::make('publish_at')->label('Yayın zamanı')->native(false)->seconds(false)->displayFormat('d.m.Y H:i')
                                ->helperText('Gelecek bir tarih verirseniz yazı o an otomatik yayınlanır.'),
                            Select::make('source')->label('Kaynak')->options(Blog::SOURCES)->default(Blog::SOURCE_MANUAL)->native(false)->disabled(),
                        ]),
                    ]),
                    Section::make('Çakışma birleştirme')
                        ->schema([
                            Select::make('merged_into_id')->label('Devredildiği yazı')->relationship('mergedInto', 'title')->searchable()->preload()
                                ->helperText('Doluysa bu yazı yayından kaldırılmış ve adresi 301 ile hedefe yönlendirilmiştir. İçerik SİLİNMEZ.'),
                        ])
                        ->visible(fn (?Model $record) => $record?->merged_into_id !== null)
                        ->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
        ]);
    }
}
