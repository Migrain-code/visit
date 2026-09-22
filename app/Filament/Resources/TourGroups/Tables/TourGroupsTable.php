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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['departure.tour', 'vehicle', 'creator']))
            ->columns([
                TextColumn::make('code')->label('Kod')->badge()->color('gray')->searchable()->sortable(),
                TextColumn::make('contact_name')->label('Grup / ilgili kişi')->searchable(['contact_name', 'name', 'contact_phone'])->weight('semibold')
                    ->formatStateUsing(fn (TourGroup $record) => $record->name ?: $record->contact_name)
                    ->description(fn (TourGroup $record) => $record->contact_phone),
                TextColumn::make('departure.starts_at')->label('Sefer')->dateTime('d.m.Y')->sortable()
                    ->description(fn (TourGroup $record) => $record->departure?->tour?->title),
                TextColumn::make('passenger_count')->label('Kişi')->badge()->color('info')->sortable(),
                TextColumn::make('vehicle.name')->label('Araç')->placeholder('Yerleşmedi')->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'warning'),
                TextColumn::make('balance')->label('Kalan ödeme')
                    ->getStateUsing(fn (TourGroup $record) => $record->balance)
                    ->formatStateUsing(fn ($state) => (float) $state <= 0 ? 'Ödendi' : money_label($state))
                    ->badge()
                    ->color(fn ($state) => (float) $state <= 0 ? 'success' : 'danger')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (GroupStatus $state) => $state->label())
                    ->color(fn (GroupStatus $state) => $state->color()),
                TextColumn::make('creator.name')->label('Kaydeden')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Kayıt tarihi')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tour_departure_id')
                    ->label('Sefer')
                    ->options(fn () => TourDeparture::query()->visibleTo(auth()->user())->with('tour:id,title')
                        ->orderByDesc('starts_at')->limit(100)->get()
                        ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->starts_at->format('d.m.Y').' · '.$d->tour?->title]))
                    ->searchable(),
                SelectFilter::make('status')->label('Durum')->options(GroupStatus::options()),
                Filter::make('upcoming')
                    ->label('Yalnız yaklaşan seferler')
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
                    ->url(fn (TourGroup $record) => 'https://wa.me/'.ltrim(phone_digits($record->contact_phone), '+'))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
