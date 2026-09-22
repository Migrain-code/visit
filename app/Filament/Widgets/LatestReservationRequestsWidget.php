<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReservationRequests\ReservationRequestResource;
use App\Models\ReservationRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestReservationRequestsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Son Rezervasyon Talepleri';

    public static function canView(): bool
    {
        return auth()->user()?->registersGroups() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReservationRequest::query()->with(['province', 'district', 'tour', 'assignee'])->latest())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i'),
                TextColumn::make('name')->label('Ad Soyad')->weight('semibold'),
                TextColumn::make('phone')->label('Telefon')->copyable(),
                TextColumn::make('tour.title')->label('Tur')->placeholder('-')->limit(30),
                TextColumn::make('people_count')->label('Kişi')->badge()->color('info'),
                TextColumn::make('assignee.name')->label('İlgilenen')->placeholder('atanmadı')
                    ->badge()->color(fn (?string $state) => $state ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ReservationRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ReservationRequest::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('İncele')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ReservationRequest $record) => ReservationRequestResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
