<?php

namespace App\Filament\Resources\AiGenerations;

use App\Filament\Resources\AiGenerations\Pages\ManageAiGenerations;
use App\Models\AiGeneration;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AiGenerationResource extends Resource
{
    protected static ?string $model = AiGeneration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'AI İşlemi';

    protected static ?string $pluralModelLabel = 'AI İşlemleri';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('İşlem')->schema([
                TextEntry::make('operation_label')->label('İşlem'),
                TextEntry::make('model')->label('Model'),
                TextEntry::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (string $state) => AiGeneration::STATUSES[$state] ?? $state),
                TextEntry::make('duration_ms')->label('Süre')->formatStateUsing(fn (?int $state) => $state ? round($state / 1000, 1).' sn' : '-'),
                TextEntry::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i:s'),
            ])->columns(3)->columnSpanFull(),
            Section::make('Gönderilen istem')->schema([
                TextEntry::make('input.system')->label('Sistem')->prose()->columnSpanFull(),
                TextEntry::make('input.user')->label('Kullanıcı')->prose()->columnSpanFull(),
            ])->collapsible()->collapsed()->columnSpanFull(),
            Section::make('Yanıt')->schema([
                TextEntry::make('output')->label('Çıktı')->prose()->placeholder('-')->columnSpanFull(),
                TextEntry::make('error')->label('Hata')->color('danger')->placeholder('-')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Zaman')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('operation')->label('İşlem')->badge()
                    ->formatStateUsing(fn (string $state) => AiGeneration::OPERATIONS[$state] ?? $state),
                TextColumn::make('model')->label('Model')->color('gray')->toggleable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (string $state) => AiGeneration::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        AiGeneration::STATUS_SUCCESS => 'success',
                        AiGeneration::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('duration_ms')->label('Süre')
                    ->formatStateUsing(fn (?int $state) => $state ? round($state / 1000, 1).' sn' : '-'),
                TextColumn::make('error')->label('Hata')->limit(50)->color('danger')->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('operation')->label('İşlem')->options(AiGeneration::OPERATIONS),
                SelectFilter::make('status')->label('Durum')->options(AiGeneration::STATUSES),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAiGenerations::route('/')];
    }
}
