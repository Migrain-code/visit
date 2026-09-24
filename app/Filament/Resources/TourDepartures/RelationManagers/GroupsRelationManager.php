<?php

namespace App\Filament\Resources\TourDepartures\RelationManagers;

use App\Enums\GroupStatus;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\TourGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Turun grupları. Kayıt ve düzenleme, yolcu satırlarıyla birlikte tam sayfa
 * "Yolcu Ekle" ekranında yapılır; burası özet listedir.
 */
class GroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'groups';

    protected static ?string $title = 'Gruplar';

    protected static ?string $modelLabel = 'grup';

    protected static ?string $pluralModelLabel = 'gruplar';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['vehicle', 'passengers']))
            ->columns([
                TextColumn::make('name')->label('Grup')->searchable(['name', 'contact_name', 'contact_phone'])->weight('semibold')
                    ->description(fn (TourGroup $record) => $record->passengers->map(fn ($p) => $p->full_name)->implode(', ')),
                TextColumn::make('passenger_count')->label('Kişi')->badge()->color('info')->sortable(),
                TextColumn::make('contact_phone')->label('Telefon')->copyable(),
                TextColumn::make('vehicle.name')->label('Araç')->placeholder('Yerleşmedi')->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'warning'),
                IconColumn::make('is_pinned')->label('Sabit')->boolean()
                    ->trueIcon('heroicon-m-lock-closed')->falseIcon('heroicon-m-minus')->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pickup_point')->label('Biniş')->placeholder('-')->toggleable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (GroupStatus $state) => $state->label())
                    ->color(fn (GroupStatus $state) => $state->color()),
                TextColumn::make('creator.name')->label('Kaydeden')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(GroupStatus::options()),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Yolcu ekle')
                    ->icon('heroicon-o-user-plus')
                    ->url(fn () => TourGroupResource::getUrl('create', ['departure' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn () => (auth()->user()?->can('create', TourGroup::class) ?? false) && $this->getOwnerRecord()->status->acceptsGroups()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(fn (TourGroup $record) => (auth()->user()?->can('update', $record) ?? false) ? 'Düzenle' : 'Görüntüle')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (TourGroup $record) => (auth()->user()?->can('update', $record) ?? false)
                        ? TourGroupResource::getUrl('edit', ['record' => $record])
                        : TourGroupResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->recordUrl(fn (TourGroup $record) => TourGroupResource::getUrl('view', ['record' => $record]))
            ->defaultSort('id')
            ->emptyStateHeading('Bu tura henüz yolcu eklenmedi');
    }
}
