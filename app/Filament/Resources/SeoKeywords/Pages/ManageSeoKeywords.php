<?php

namespace App\Filament\Resources\SeoKeywords\Pages;

use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use App\Models\SeoKeyword;
use App\Services\Seo\KeywordPool;
use App\Support\TurkishText;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoKeywords extends ManageRecords
{
    protected static string $resource = SeoKeywordResource::class;

    public function getSubheading(): ?string
    {
        $stats = app(KeywordPool::class)->stats();

        return sprintf(
            'Toplam %d kelime · %d sahiplenilmiş · %d mevcut içerikte geçiyor · AI havuzunda %d boş kelime',
            $stats['total'], $stats['assigned'], $stats['covered'], $stats['free'],
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkAdd')
                ->label('Toplu kelime ekle')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    Textarea::make('keywords')
                        ->label('Her satıra bir kelime')
                        ->rows(10)
                        ->required()
                        ->placeholder("ardeşen çıkışlı ayder turu\ngünübirlik uzungöl turu\nbatum turu fiyatları"),
                ])
                ->action(function (array $data) {
                    $lines = collect(preg_split('/\r\n|\r|\n/', $data['keywords']))
                        ->map(fn ($l) => trim($l))->filter()->unique();

                    $added = 0;

                    foreach ($lines as $line) {
                        $hash = md5(TurkishText::lower($line));

                        if (SeoKeyword::where('keyword_hash', $hash)->exists()) {
                            continue;
                        }

                        SeoKeyword::create(['keyword' => $line, 'keyword_type' => 'BLOG_PRIMARY', 'search_intent' => 'informational']);
                        $added++;
                    }

                    Notification::make()
                        ->title($added.' kelime eklendi')
                        ->body(($lines->count() - $added).' tanesi zaten kayıtlıydı.')
                        ->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
