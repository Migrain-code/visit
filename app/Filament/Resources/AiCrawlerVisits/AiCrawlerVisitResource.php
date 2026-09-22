<?php

namespace App\Filament\Resources\AiCrawlerVisits;

use App\Filament\Resources\AiCrawlerVisits\Pages\ManageAiCrawlerVisits;
use App\Models\AiCrawlerVisit;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AiCrawlerVisitResource extends Resource
{
    protected static ?string $model = AiCrawlerVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Gözlem';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'AI Bot Ziyareti';

    protected static ?string $pluralModelLabel = 'AI Bot Ziyaretleri';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bot')->label('Bot')->badge()->searchable()->sortable(),
                TextColumn::make('path')->label('Adres')->searchable()->wrap(),
                TextColumn::make('hits')->label('Ziyaret')->numeric()->sortable()->badge(),
                TextColumn::make('last_status')->label('Son durum')->badge()
                    ->color(fn (?int $state) => $state === 200 ? 'success' : ($state === 404 ? 'danger' : 'gray')),
                TextColumn::make('last_seen_at')->label('Son görülme')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('bot')->label('Bot')
                    ->options(fn () => AiCrawlerVisit::query()->distinct()->pluck('bot', 'bot')->all()),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAiCrawlerVisits::route('/')];
    }
}
