<?php

namespace App\Filament\Resources\TourDepartures\RelationManagers;

use App\Models\TourLedgerEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Turun ekstra gelir/gider hareketleri. Yeni hareket üstteki "Kasa hareketi" ile eklenir.
 */
class LedgerRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Kasa hareketleri';

    protected static ?string $modelLabel = 'hareket';

    protected static ?string $pluralModelLabel = 'hareketler';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->viewsReports() ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Tür')->options(TourLedgerEntry::TYPES)->required()->native(false),
            TextInput::make('title')->label('Açıklama')->required()->maxLength(150),
            TextInput::make('amount')->label('Tutar')->numeric()->minValue(0.01)->step('0.01')->suffix('₺')->required(),
            DatePicker::make('entry_date')->label('Tarih')->native(false)->displayFormat('d.m.Y'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('entry_date')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('type')->label('Tür')->badge()
                    ->formatStateUsing(fn (string $state) => TourLedgerEntry::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === TourLedgerEntry::TYPE_INCOME ? 'success' : 'danger'),
                TextColumn::make('title')->label('Açıklama')->weight('semibold'),
                TextColumn::make('amount')->label('Tutar')->formatStateUsing(fn ($state) => money_label($state)),
                TextColumn::make('creator.name')->label('Ekleyen')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('entry_date')
            ->emptyStateHeading('Ekstra gelir ya da gider girilmedi')
            ->emptyStateDescription('Yolcu geliri, araç ücreti ve komisyon kendiliğinden hesaplanır; yalnız bunların dışındaki kalemleri buraya yazın.');
    }
}
