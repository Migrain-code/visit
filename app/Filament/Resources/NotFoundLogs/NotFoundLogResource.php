<?php

namespace App\Filament\Resources\NotFoundLogs;

use App\Filament\Resources\NotFoundLogs\Pages\ManageNotFoundLogs;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Seo\RedirectSuggester;
use App\Support\PathNormalizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class NotFoundLogResource extends Resource
{
    protected static ?string $model = NotFoundLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Gözlem';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = '404 Kaydı';

    protected static ?string $pluralModelLabel = '404 Kayıtları';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = NotFoundLog::query()->unresolved()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('path')->label('Bulunamayan adres')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('hits')->label('İstek')->numeric()->sortable()->badge()
                    ->color(fn (int $state) => $state >= 20 ? 'danger' : ($state >= 5 ? 'warning' : 'gray')),
                TextColumn::make('suggested_path')->label('Önerilen hedef')->placeholder('öneri yok')->wrap()
                    ->description(fn (NotFoundLog $r) => $r->suggestion_score ? 'güven: %'.round($r->suggestion_score * 100) : null),
                TextColumn::make('last_referrer')->label('Gelen bağlantı')->placeholder('-')->limit(40)->toggleable(),
                TextColumn::make('last_seen_at')->label('Son görülme')->since()->sortable(),
            ])
            ->filters([TernaryFilter::make('resolved')->label('Çözüldü mü')])
            ->headerActions([
                Action::make('refresh')->label('Önerileri yenile')->icon('heroicon-o-sparkles')
                    ->action(function (RedirectSuggester $suggester) {
                        $r = $suggester->fillSuggestions();

                        Notification::make()->title('Öneriler güncellendi')
                            ->body("{$r['scanned']} kayıt tarandı, {$r['suggested']} öneri bulundu.")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('createRedirect')
                    ->label('Yönlendirme oluştur')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('success')
                    ->schema(fn (NotFoundLog $record) => [
                        TextInput::make('to_path')->label('Hedef adres')->required()
                            ->default($record->suggested_path ?: '/')
                            ->helperText('Öneri deterministik olarak üretilir; hedef uydurulmaz.'),
                    ])
                    ->action(function (NotFoundLog $record, array $data) {
                        Redirect::updateOrCreate(
                            ['from_hash' => PathNormalizer::hash($record->path)],
                            [
                                'from_path' => $record->path,
                                'to_path' => $data['to_path'],
                                'status_code' => 301,
                                'is_active' => true,
                                'source' => Redirect::SOURCE_MANUAL,
                            ],
                        );

                        $record->forceFill(['resolved' => true])->saveQuietly();

                        Notification::make()->title('Yönlendirme oluşturuldu')->success()->send();
                    }),
                Action::make('ignore')->label('Yoksay')->icon('heroicon-o-eye-slash')->color('gray')
                    ->action(fn (NotFoundLog $record) => $record->forceFill(['resolved' => true])->saveQuietly()),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('hits', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotFoundLogs::route('/')];
    }
}
