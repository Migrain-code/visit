<?php

namespace App\Filament\Resources\Provinces\Schemas;

use App\Filament\Support\FormHelpers;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProvinceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('İl bilgileri')->schema([
                    Grid::make(2)->schema(FormHelpers::titleAndSlug('name', 'İl adı', 'provinces')),
                    Textarea::make('description')
                        ->label('Kısa açıklama')
                        ->rows(2)
                        ->maxLength(300)
                        ->helperText('Bölge listelerinde ve sayfa girişinde kullanılır.')
                        ->columnSpanFull(),
                    RichEditor::make('content')
                        ->label('Bölgeye özel içerik')
                        ->helperText('Sadece il adını değiştirilmiş kopya içerik kullanmayın; bölgeye özgü bilgiler yazın.')
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
