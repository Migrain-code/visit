<?php

namespace App\Filament\Resources\Features;

use App\Filament\Resources\Features\Pages\ManageFeatures;
use App\Filament\Support\FormHelpers;
use App\Models\Feature;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Ana Sayfa Maddesi';

    protected static ?string $pluralModelLabel = 'Ana Sayfa Maddeleri';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('type')->label('Bölüm')->options(Feature::TYPES)->required()->native(false),
                FormHelpers::iconInput(),
                TextInput::make('title')->label('Başlık')->required()->maxLength(100),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                Textarea::make('description')->label('Açıklama')->rows(2)->maxLength(300)->columnSpanFull(),
                Toggle::make('is_active')->label('Yayında')->default(true),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Bölüm')->badge()->formatStateUsing(fn (string $state) => Feature::TYPES[$state] ?? $state)->sortable(),
                TextColumn::make('title')->label('Başlık')->searchable()->weight('semibold'),
                TextColumn::make('description')->label('Açıklama')->limit(60)->wrap(),
                TextColumn::make('icon')->label('İkon')->color('gray')->toggleable(),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Bölüm')->options(Feature::TYPES),
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
            ->defaultSort('type');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFeatures::route('/'),
        ];
    }
}
