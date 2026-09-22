<?php

namespace App\Filament\Resources\InternalLinkRules\Pages;

use App\Filament\Resources\InternalLinkRules\InternalLinkRuleResource;
use App\Services\InternalLink\LinkApplier;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInternalLinkRules extends ManageRecords
{
    protected static string $resource = InternalLinkRuleResource::class;

    public function getSubheading(): ?string
    {
        $applier = app(LinkApplier::class);

        if (! $applier->enabled()) {
            return '⚠️ İç link motoru KAPALI. Kurallar kayıtlı ama hiçbir link basılmıyor. '
                .'SEO & AI Ayarları → İç link motoru bölümünden açabilirsiniz.';
        }

        return sprintf(
            'Motor açık · makale başına en fazla %d link · toplam tavan %d · anasayfa tavanı %d',
            $applier->maxPerArticle(), $applier->maxTotal(), $applier->maxHome(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
