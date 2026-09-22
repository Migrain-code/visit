<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Sık Sorulan Soru';

    protected static ?string $pluralModelLabel = 'Sık Sorulan Sorular';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')->label('Soru')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('answer')->label('Cevap')->required()->rows(4)->columnSpanFull(),
            Grid::make(3)->schema([
                Toggle::make('is_active')->label('Yayında')->default(true),
                Toggle::make('show_on_home')->label('Ana sayfada göster')->default(true),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->label('Soru')->searchable()->wrap()->weight('semibold'),
                ToggleColumn::make('show_on_home')->label('Ana sayfa'),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqs::route('/'),
        ];
    }
}
