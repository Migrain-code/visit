<?php

namespace App\Filament\Resources\InternalLinkRules;

use App\Filament\Resources\InternalLinkRules\Pages\ManageInternalLinkRules;
use App\Models\InternalLinkRule;
use App\Models\SeoTarget;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InternalLinkRuleResource extends Resource
{
    protected static ?string $model = InternalLinkRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'İç Link Kuralı';

    protected static ?string $pluralModelLabel = 'İç Link Kuralları';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('anchor_text')->label('Bağlantı metni (anchor)')->required()->maxLength(190)
                    ->helperText('Yazı içinde bu metin geçtiğinde link basılır. Kelime sınırına uyulur.'),
                Select::make('target_url')->label('Hedef adres')->required()->searchable()
                    ->options(fn () => SeoTarget::query()->active()->orderBy('name')->pluck('url', 'url'))
                    ->helperText('Yalnız gerçek hedef sayfalar listelenir; adres uydurulmaz.'),
                Select::make('scope_type')->label('Kapsam')->options(InternalLinkRule::SCOPES)->default('blog')->required()->native(false),
                TextInput::make('max_per_article')->label('Makale başına tavan')->numeric()->default(1)->minValue(1)->maxValue(10)->required(),
                TextInput::make('priority')->label('Öncelik')->numeric()->default(50)->minValue(0)->maxValue(100)
                    ->helperText('Yüksek olan önce denenir. Eşitlikte uzun anchor kazanır.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('anchor_text')->label('Anchor')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('target_url')->label('Hedef')->searchable()->wrap(),
                TextColumn::make('scope_type')->label('Kapsam')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => InternalLinkRule::SCOPES[$state] ?? $state),
                TextColumn::make('max_per_article')->label('Tavan'),
                TextColumn::make('priority')->label('Öncelik')->sortable(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([SelectFilter::make('scope_type')->label('Kapsam')->options(InternalLinkRule::SCOPES)])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('priority', 'desc')
            ->emptyStateHeading('Henüz kural yok')
            ->emptyStateDescription('İç Link Önerileri ekranından onaylayarak kural üretebilirsiniz.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageInternalLinkRules::route('/')];
    }
}
