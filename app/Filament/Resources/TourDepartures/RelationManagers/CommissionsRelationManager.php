<?php

namespace App\Filament\Resources\TourDepartures\RelationManagers;

use App\Models\TourCommission;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Bu turda personele yazılan komisyonlar. Toplu giriş üstteki "Komisyon ekle" ile;
 * burada tek tek düzeltilir ya da silinir.
 */
class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Komisyonlar';

    protected static ?string $modelLabel = 'komisyon';

    protected static ?string $pluralModelLabel = 'komisyonlar';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return (bool) ($user?->managesCommissions() || $user?->viewsReports());
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->managesCommissions() ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')->label('Tutar')->numeric()->minValue(0.01)->step('0.01')->suffix('₺')->required(),
            TextInput::make('note')->label('Not')->maxLength(150),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Personel')->weight('semibold'),
                TextColumn::make('amount')->label('Tutar')->formatStateUsing(fn ($state) => money_label($state))
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Toplam')->formatStateUsing(fn ($state) => money_label($state))),
                TextColumn::make('note')->label('Not')->placeholder('-'),
                TextColumn::make('created_at')->label('Yazıldı')->dateTime('d.m.Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()?->managesCommissions() ?? false),
                DeleteAction::make()->visible(fn () => auth()->user()?->managesCommissions() ?? false),
            ])
            ->defaultSort('id')
            ->emptyStateHeading('Bu tura komisyon yazılmadı')
            ->emptyStateDescription('Üstteki "Komisyon ekle" düğmesiyle personele tutar yazın.');
    }
}
