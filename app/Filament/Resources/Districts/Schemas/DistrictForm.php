<?php

namespace App\Filament\Resources\Districts\Schemas;

use App\Filament\Support\FormHelpers;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class DistrictForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('İlçe bilgileri')->schema([
                    Grid::make(3)->schema([
                        Select::make('province_id')
                            ->label('İl')
                            ->relationship('province', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        TextInput::make('name')
                            ->label('İlçe adı')
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
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('province_id', $get('province_id')),
                            )
                            ->helperText('Adres: /il-slug/ilce-slug'),
                    ]),
                    Textarea::make('description')
                        ->label('Kısa açıklama')
                        ->rows(2)
                        ->maxLength(300)
                        ->columnSpanFull(),
                    RichEditor::make('content')
                        ->label('Bölgeye özel içerik')
                        ->helperText('İlçeye özgü bilgiler yazın (yolcuları nereden alıyorsunuz, kalkış saatleri, bu ilçeden en çok hangi turlara katılım oluyor vb.).')
                        ->columnSpanFull(),
                    TagsInput::make('pickup_points')
                        ->label('Biniş noktaları')
                        ->placeholder('Biniş noktası yazıp Enter\'a basın (örn. Otogar önü)')
                        ->columnSpanFull(),
                ])->columnSpanFull(),
                FormHelpers::faqRepeater('faqs', 'Bölgeye özel sık sorulan sorular'),
                FormHelpers::seoSection(),
                Section::make('Yayın')->schema([
                    Toggle::make('is_active')->label('Yayında')->default(true),
                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}
