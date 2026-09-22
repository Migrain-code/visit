<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use App\Jobs\GenerateBlogArticle;
use App\Models\BlogCategory;
use App\Services\Ai\AiClient;
use App\Services\Ai\TopicGenerator;
use App\Services\Seo\KeywordPool;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Throwable;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('AI ile konu üret')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                // AI anahtarı yoksa aksiyon GİZLENİR, hata vermez (spec §10.5).
                ->visible(fn () => app(AiClient::class)->isConfigured())
                ->schema([
                    Select::make('category_id')
                        ->label('Kategori')
                        ->options(BlogCategory::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->required()
                        ->native(false),
                    TextInput::make('count')->label('Kaç yazı?')->numeric()->default(1)->minValue(1)->maxValue(5)->required(),
                ])
                ->modalHeading('AI ile blog konusu üret')
                ->modalDescription(function (KeywordPool $pool) {
                    $stats = $pool->stats();

                    return "Havuzda {$stats['free']} sahipsiz anahtar kelime var. ".
                        'Üretilen konular çakışma filtrelerinden geçer; takılanlar reddedilir ve gerekçesi kaydedilir.';
                })
                ->modalSubmitActionLabel('Üret ve kuyruğa al')
                ->action(function (array $data, TopicGenerator $topics) {
                    $category = BlogCategory::findOrFail($data['category_id']);

                    try {
                        $result = $topics->generate($category, (int) $data['count']);
                    } catch (Throwable $e) {
                        Notification::make()->title('Konu üretilemedi')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    if ($result['accepted'] === []) {
                        // Sessizce başarısız OLMA: sebebi söyle (spec §3.4).
                        $reasons = collect($result['rejected'])->map(fn ($r) => '• '.($r['title'] ?? '?').' — '.($r['reason'] ?? ''))->implode("\n");

                        Notification::make()
                            ->title('Uygun konu bulunamadı')
                            ->body(trim(
                                "Model {$result['raw_count']} aday üretti, hiçbiri filtreleri geçemedi.\n".
                                "Boştaki kelime: {$result['pool_size']}\n\n".$reasons
                            ))
                            ->warning()->persistent()->send();

                        return;
                    }

                    foreach ($result['accepted'] as $i => $topic) {
                        GenerateBlogArticle::dispatch(
                            $category->getKey(),
                            $topic,
                            Carbon::now()->addMinutes(5 + $i)->toDateTimeString(),
                        );
                    }

                    Notification::make()
                        ->title(count($result['accepted']).' konu kuyruğa alındı')
                        ->body('Reddedilen aday: '.count($result['rejected'])."\nYazılar kuyruk işçisi tarafından üretilecek (php artisan queue:work).")
                        ->success()->persistent()->send();
                }),
            CreateAction::make(),
        ];
    }
}
