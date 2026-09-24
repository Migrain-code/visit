<?php

namespace App\Filament\Resources\TourGroups\Tables;

use App\Enums\GroupStatus;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TourGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['departure', 'vehicle', 'creator', 'passengers']))
            ->columns([
                TextColumn::make('name')->label('Grup')->searchable(['name', 'contact_name', 'contact_phone'])->weight('semibold')
                    ->description(fn (TourGroup $record) => $record->passengers->map(fn ($p) => $p->full_name)->take(4)->implode(', ')
                        .($record->passengers->count() > 4 ? ' …' : '')),
                TextColumn::make('departure.starts_at')->label('Tur')->date('d.m.Y')->sortable()
                    ->description(fn (TourGroup $record) => $record->departure?->title),
                TextColumn::make('passenger_count')->label('Kişi')->badge()->color('info')->sortable(),
                TextColumn::make('contact_phone')->label('Telefon')->copyable()->toggleable(),
                TextColumn::make('vehicle.name')->label('Araç')->placeholder('Yerleşmedi')->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'warning'),
                TextColumn::make('balance')->label('Kalan ödeme')
                    ->getStateUsing(fn (TourGroup $record) => $record->balance)
                    ->formatStateUsing(fn ($state) => (float) $state <= 0 ? 'Ödendi' : money_label($state))
                    ->badge()
                    ->color(fn ($state) => (float) $state <= 0 ? 'success' : 'danger')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (GroupStatus $state) => $state->label())
                    ->color(fn (GroupStatus $state) => $state->color()),
                TextColumn::make('creator.name')->label('Kaydeden')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Kayıt tarihi')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tour_departure_id')
                    ->label('Tur')
                    ->options(fn () => TourDeparture::query()->visibleTo(auth()->user())
                        ->orderByDesc('starts_at')->limit(100)->get()
                        ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->starts_at->format('d.m.Y').' · '.$d->title]))
                    ->searchable(),
                SelectFilter::make('status')->label('Durum')->options(GroupStatus::options()),
                Filter::make('upcoming')
                    ->label('Yalnız yaklaşan turlar')
                    ->query(fn (Builder $query) => $query->whereHas('departure', fn (Builder $q) => $q->upcoming()))
                    ->default(),
                Filter::make('waiting')
                    ->label('Araca yerleşmemiş')
                    ->query(fn (Builder $query) => $query->seatHolding()->whereNull('departure_vehicle_id')),
                Filter::make('mine')
                    ->label('Benim kaydettiklerim')
                    ->query(fn (Builder $query) => $query->where('created_by', auth()->id())),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->iconButton()
                    ->visible(fn (TourGroup $record) => phone_digits($record->contact_phone) !== '')
                    ->url(fn (TourGroup $record) => 'https://wa.me/'.ltrim(phone_digits($record->contact_phone), '+'))
                    ->openUrlInNewTab(),
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->defaultSort('id', 'desc');
    }
}
