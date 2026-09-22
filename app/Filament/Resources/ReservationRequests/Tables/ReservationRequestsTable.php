<?php

namespace App\Filament\Resources\ReservationRequests\Tables;

use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\ReservationRequest;
use App\Models\TourGroup;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReservationRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tour', 'departure', 'district', 'province', 'assignee', 'group']))
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
                TextColumn::make('name')->label('Ad Soyad')->searchable()->weight('semibold'),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable()->copyMessage('Kopyalandı'),
                TextColumn::make('tour.title')->label('Tur')->placeholder('-')->limit(28)
                    ->description(fn (ReservationRequest $record) => $record->departure?->starts_at?->format('d.m.Y')),
                TextColumn::make('people_count')->label('Kişi')->badge()->color('info'),
                TextColumn::make('location_label')->label('Bölge')->placeholder('-')->toggleable(),
                TextColumn::make('assignee.name')->label('İlgilenen')
                    ->placeholder('atanmadı')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'gray'),
                SelectColumn::make('status')->label('Durum')->options(ReservationRequest::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(ReservationRequest::STATUSES),
                SelectFilter::make('tour_id')->label('Tur')->relationship('tour', 'title')->preload(),
                SelectFilter::make('assigned_to')
                    ->label('İlgilenen personel')
                    ->options(fn () => User::query()->registrars()->active()->ordered()->pluck('name', 'id')),
                Filter::make('unassigned')
                    ->label('Atanmamış talepler')
                    ->query(fn ($query) => $query->whereNull('assigned_to')),
            ])
            ->recordActions([
                Action::make('convert')
                    ->label('Gruba dönüştür')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (ReservationRequest $record) => $record->group === null
                        && $record->status !== ReservationRequest::STATUS_CANCELLED
                        && (auth()->user()?->can('create', TourGroup::class) ?? false))
                    ->url(fn (ReservationRequest $record) => TourGroupResource::getUrl('create', ['request' => $record->getKey()])),
                Action::make('assign')
                    ->label(fn (ReservationRequest $record) => $record->assigned_to ? 'Personeli değiştir' : 'Personele ata')
                    ->icon('heroicon-o-user-circle')
                    ->color('gray')
                    ->visible(fn (ReservationRequest $record) => auth()->user()?->can('assign', $record) ?? false)
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Personel')
                            ->options(fn () => User::query()->registrars()->active()->ordered()->get()
                                ->mapWithKeys(fn (User $u) => [
                                    $u->id => $u->name.' — '.$u->assignedRequests()
                                        ->whereIn('status', [ReservationRequest::STATUS_NEW, ReservationRequest::STATUS_CONTACTED])
                                        ->count().' açık talep',
                                ]))
                            ->required()
                            ->native(false),
                        Textarea::make('assignment_note')->label('Not')->rows(3),
                    ])
                    ->modalHeading('Talebi personele ata')
                    ->modalSubmitActionLabel('Ata')
                    ->fillForm(fn (ReservationRequest $record) => [
                        'assigned_to' => $record->assigned_to,
                        'assignment_note' => $record->assignment_note,
                    ])
                    ->action(function (ReservationRequest $record, array $data) {
                        $staff = User::findOrFail($data['assigned_to']);
                        $record->assignTo($staff, auth()->user(), $data['assignment_note'] ?? null);

                        Notification::make()->title('Talep atandı')->body($record->name.' → '.$staff->name)->success()->send();
                    }),
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (ReservationRequest $record) => 'https://wa.me/'.ltrim(phone_digits($record->phone), '+'))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_contacted')
                        ->label('İletişime geçildi olarak işaretle')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->update(['status' => ReservationRequest::STATUS_CONTACTED]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }
}
