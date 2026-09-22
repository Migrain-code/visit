<?php

namespace App\Filament\Resources\ReservationRequests\Schemas;

use App\Models\ReservationRequest;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Müşteri')->schema([
                    TextEntry::make('name')->label('Ad Soyad')->weight('semibold'),
                    TextEntry::make('phone')->label('Telefon')->copyable()->url(fn (ReservationRequest $record) => phone_href($record->phone)),
                    TextEntry::make('email')->label('E-posta')->placeholder('-')->copyable(),
                    TextEntry::make('location_label')->label('Konakladığı bölge')->placeholder('-'),
                    TextEntry::make('people_count')->label('Kişi sayısı')->badge()->color('info'),
                    TextEntry::make('preferred_date')->label('Tercih edilen tarih')->date('d.m.Y')->placeholder('-'),
                ])->columns(3)->columnSpanFull(),

                Section::make('Talep')->schema([
                    TextEntry::make('tour.title')->label('Tur')->placeholder('-'),
                    TextEntry::make('departure.label')->label('Sefer')->placeholder('Sefer seçilmedi'),
                    TextEntry::make('group.code')->label('Açılan grup kaydı')->placeholder('Henüz kayda dönüşmedi')->badge()
                        ->color(fn (?string $state) => $state ? 'success' : 'gray'),
                    TextEntry::make('message')->label('Mesaj')->placeholder('Mesaj yazılmamış.')->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Takip')->schema([
                    TextEntry::make('status')
                        ->label('Durum')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => ReservationRequest::STATUSES[$state] ?? $state)
                        ->color(fn (string $state) => ReservationRequest::STATUS_COLORS[$state] ?? 'gray'),
                    TextEntry::make('assignee.name')->label('İlgilenen personel')->placeholder('Atanmadı')
                        ->badge()->color(fn (?string $state) => $state ? 'success' : 'gray'),
                    TextEntry::make('assigned_at')->label('Atama zamanı')->dateTime('d.m.Y H:i')->placeholder('-'),
                    IconEntry::make('kvkk_accepted')->label('KVKK onayı')->boolean(),
                    TextEntry::make('created_at')->label('Gönderim')->dateTime('d.m.Y H:i'),
                    TextEntry::make('assignment_note')->label('Atama notu')->placeholder('-'),
                    TextEntry::make('admin_notes')->label('Notlar')->placeholder('-')->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Teknik')->schema([
                    TextEntry::make('source')->label('Kaynak'),
                    TextEntry::make('page_url')->label('Gönderilen sayfa')->placeholder('-')->url(fn (?string $state) => $state)->openUrlInNewTab(),
                    TextEntry::make('ip')->label('IP')->placeholder('-'),
                    TextEntry::make('user_agent')->label('Tarayıcı')->placeholder('-')->limit(80)->columnSpanFull(),
                ])->columns(3)->collapsible()->collapsed()->columnSpanFull(),
            ]);
    }
}
