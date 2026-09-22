<?php

namespace App\Filament\Resources\GalleryCategories;

use App\Filament\Resources\GalleryCategories\Pages\ManageGalleryCategories;
use App\Models\GalleryCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class GalleryCategoryResource extends Resource
{
    protected static ?string $model = GalleryCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Galeri Kategorisi';

    protected static ?string $pluralModelLabel = 'Galeri Kategorileri';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Kategori adı')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $old) {
                    if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')->label('URL (slug)')->required()->maxLength(100)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Kategori')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('slug')->label('Slug')->color('gray'),
                TextColumn::make('items_count')->label('Görsel sayısı')->counts('items')->badge(),
                TextColumn::make('sort_order')->label('Sıra')->sortable(),
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
            'index' => ManageGalleryCategories::route('/'),
        ];
    }
}
