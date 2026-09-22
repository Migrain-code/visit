<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Support\FormHelpers;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
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

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'İçerik';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Sayfa';

    protected static ?string $pluralModelLabel = 'Sayfalar';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sayfa')->schema([
                Grid::make(2)->schema(FormHelpers::titleAndSlug('title', 'Sayfa başlığı', 'pages')),
                RichEditor::make('content')->label('İçerik')->columnSpanFull(),
            ])->columnSpanFull(),
            FormHelpers::seoSection(),
            Section::make('Yayın')->schema([
                Toggle::make('is_active')->label('Yayında')->default(true),
                Toggle::make('show_in_footer')->label('Footer\'da bağlantı göster')->default(true),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Başlık')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Page $record) => '/'.$record->slug),
                ToggleColumn::make('show_in_footer')->label('Footer'),
                ToggleColumn::make('is_active')->label('Yayında'),
                TextColumn::make('updated_at')->label('Güncelleme')->since(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Sitede gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $record) => $record->url)
                    ->openUrlInNewTab(),
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
