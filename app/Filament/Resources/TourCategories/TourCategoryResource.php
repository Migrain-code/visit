<?php

namespace App\Filament\Resources\TourCategories;

use App\Filament\Resources\TourCategories\Pages\CreateTourCategory;
use App\Filament\Resources\TourCategories\Pages\EditTourCategory;
use App\Filament\Resources\TourCategories\Pages\ListTourCategories;
use App\Filament\Support\FormHelpers;
use App\Models\TourCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class TourCategoryResource extends Resource
{
    protected static ?string $model = TourCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Turlar';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Tur Kategorisi';

    protected static ?string $pluralModelLabel = 'Tur Kategorileri';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kategori')->schema([
                Grid::make(2)->schema(FormHelpers::titleAndSlug('name', 'Kategori adı')),
                Grid::make(2)->schema([
                    FormHelpers::iconInput(),
                    TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                ]),
                Textarea::make('description')->label('Kısa açıklama')->rows(2)->maxLength(300)
                    ->helperText('Kategori kartında ve sayfa başında görünür.')->columnSpanFull(),
                FormHelpers::imageUpload('image', 'categories', 'Kategori görseli')->columnSpanFull(),
                RichEditor::make('content')->label('Kategori sayfası içeriği')->columnSpanFull(),
                FormHelpers::faqRepeater(),
            ])->columnSpanFull(),
            FormHelpers::seoSection(),
            Section::make('Yayın')->schema([
                Toggle::make('is_active')->label('Yayında')->default(true),
                Toggle::make('is_featured')->label('Ana sayfada göster')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $canManage = fn (): bool => auth()->user()?->managesCatalog() ?? false;

        return $table
            ->columns([
                TextColumn::make('name')->label('Kategori')->searchable()->weight('semibold')
                    ->description(fn (TourCategory $record) => $record->path()),
                TextColumn::make('tours_count')->label('Tur')->counts('tours')->sortable(),
                ToggleColumn::make('is_featured')->label('Ana sayfa')->disabled(fn () => ! $canManage()),
                ToggleColumn::make('is_active')->label('Yayında')->disabled(fn () => ! $canManage()),
            ])
            ->recordActions([
                Action::make('preview')->label('Sitede gör')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TourCategory $record) => $record->url)->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTourCategories::route('/'),
            'create' => CreateTourCategory::route('/create'),
            'edit' => EditTourCategory::route('/{record}/edit'),
        ];
    }
}
