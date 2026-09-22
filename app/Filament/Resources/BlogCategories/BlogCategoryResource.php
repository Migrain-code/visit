<?php

namespace App\Filament\Resources\BlogCategories;

use App\Filament\Resources\BlogCategories\Pages\ManageBlogCategories;
use App\Filament\Support\FormHelpers;
use App\Models\BlogCategory;
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

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Blog Kategorisi';

    protected static ?string $pluralModelLabel = 'Blog Kategorileri';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema(FormHelpers::titleAndSlug('name', 'Kategori adı')),
            Textarea::make('description')->label('Açıklama')->rows(2)->maxLength(300)->columnSpanFull()
                ->helperText('AI konu üretiminde kategori bağlamı olarak kullanılır.'),
            FormHelpers::seoSection(),
            Grid::make(3)->schema([
                Toggle::make('is_active')->label('Yayında')->default(true),
                Toggle::make('auto_generate')->label('AI bu kategoriden konu üretsin')->default(true)
                    ->helperText('Kapalıysa günlük üretim bu kategoriyi atlar.'),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Kategori')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('posts_count')->label('Yazı')->counts('posts')->badge(),
                TextColumn::make('published_posts_count')->label('Yayında')->counts('publishedPosts')->badge()->color('success'),
                ToggleColumn::make('auto_generate')->label('AI üretimi'),
                ToggleColumn::make('is_active')->label('Yayında'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlogCategories::route('/')];
    }
}
