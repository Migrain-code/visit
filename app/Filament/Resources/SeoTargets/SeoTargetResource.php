<?php

namespace App\Filament\Resources\SeoTargets;

use App\Filament\Resources\SeoTargets\Pages\ManageSeoTargets;
use App\Models\SeoTarget;
use App\Services\Seo\TargetSynchroniser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SeoTargetResource extends Resource
{
    protected static ?string $model = SeoTarget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Hedef Sayfa';

    protected static ?string $pluralModelLabel = 'Hedef Sayfalar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('name')->label('Ad')->required()->maxLength(255),
                TextInput::make('url')->label('Adres')->required()->maxLength(500)->prefix(url('/')),
                Select::make('target_type')->label('Tip')->options(SeoTarget::TYPES)->default('custom')->required()->native(false),
                Toggle::make('status')->label('Aktif')->default(true),
            ]),
            Textarea::make('note')->label('Not')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Hedef')->searchable()->sortable()->weight('semibold')
                    ->description(fn (SeoTarget $r) => $r->url),
                TextColumn::make('target_type')->label('Tip')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => SeoTarget::TYPES[$state] ?? $state),
                TextColumn::make('keywords_count')->label('Kelime')->counts('keywords')->badge(),
                ToggleColumn::make('status')->label('Aktif'),
            ])
            ->filters([SelectFilter::make('target_type')->label('Tip')->options(SeoTarget::TYPES)])
            ->headerActions([
                Action::make('sync')->label('İçerikten güncelle')->icon('heroicon-o-arrow-path')
                    ->action(function (TargetSynchroniser $sync) {
                        $r = $sync->sync();

                        Notification::make()->title('Hedefler güncellendi')
                            ->body("{$r['created']} yeni · {$r['updated']} güncel · {$r['deactivated']} pasife alındı")
                            ->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('open')->label('Aç')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SeoTarget $r) => url($r->url))->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('target_type');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSeoTargets::route('/')];
    }
}
