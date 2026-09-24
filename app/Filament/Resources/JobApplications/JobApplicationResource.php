<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ManageJobApplications;
use App\Models\JobApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Sitedeki "İş Başvurusu" formundan gelen başvurular.
 */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'İş Başvurusu';

    protected static ?string $pluralModelLabel = 'İş Başvuruları';

    protected static ?string $slug = 'is-basvurulari';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->managesRequests() ?? false)) {
            return null;
        }

        $count = JobApplication::query()->where('status', JobApplication::STATUS_NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Takip')->schema([
                Select::make('status')->label('Durum')->options(JobApplication::STATUSES)->required()->native(false),
                Textarea::make('admin_notes')->label('Notlar (başvuran görmez)')->rows(4)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Başvuran')->schema([
                TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
                TextInput::make('phone')->label('Telefon')->required()->tel()->maxLength(30),
                TextInput::make('email')->label('E-posta')->email()->maxLength(150),
                TextInput::make('position')->label('Başvurulan görev')->maxLength(100),
                Textarea::make('message')->label('Mesaj')->rows(4)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Başvuran')->schema([
                TextEntry::make('name')->label('Ad Soyad')->weight('semibold'),
                TextEntry::make('phone')->label('Telefon')->copyable()->url(fn (JobApplication $record) => phone_href($record->phone)),
                TextEntry::make('email')->label('E-posta')->placeholder('-')->copyable(),
                TextEntry::make('birth_date')->label('Doğum tarihi')->date('d.m.Y')->placeholder('-'),
                TextEntry::make('position')->label('Başvurulan görev')->placeholder('-')->badge()->color('info'),
                TextEntry::make('cv_path')->label('Özgeçmiş')
                    ->formatStateUsing(fn () => 'Dosyayı indir')
                    ->url(fn (JobApplication $record) => $record->cv_url)
                    ->openUrlInNewTab()
                    ->placeholder('Dosya yüklenmedi'),
                TextEntry::make('message')->label('Mesaj')->placeholder('-')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Takip')->schema([
                TextEntry::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (string $state) => JobApplication::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => JobApplication::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('created_at')->label('Gönderim')->dateTime('d.m.Y H:i'),
                TextEntry::make('admin_notes')->label('Notlar')->placeholder('-')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->since()->dateTimeTooltip('d.m.Y H:i')->sortable(),
                TextColumn::make('name')->label('Ad Soyad')->searchable()->weight('semibold')
                    ->description(fn (JobApplication $record) => $record->email),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable(),
                TextColumn::make('position')->label('Görev')->placeholder('-')->badge()->color('info'),
                TextColumn::make('cv_path')->label('CV')
                    ->formatStateUsing(fn () => 'İndir')
                    ->url(fn (JobApplication $record) => $record->cv_url)
                    ->openUrlInNewTab()
                    ->placeholder('-'),
                SelectColumn::make('status')->label('Durum')->options(JobApplication::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(JobApplication::STATUSES),
                SelectFilter::make('position')->label('Görev')->options(array_combine(JobApplication::POSITIONS, JobApplication::POSITIONS)),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (JobApplication $record) => 'https://wa.me/'.ltrim(phone_digits($record->phone), '+'))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobApplications::route('/'),
        ];
    }
}
