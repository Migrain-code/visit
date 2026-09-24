<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageJobApplications extends ManageRecords
{
    protected static string $resource = JobApplicationResource::class;

    public function getSubheading(): ?string
    {
        return 'Sitedeki "İş Başvurusu" formundan gelenler. Durumu tablodan değiştirebilirsiniz.';
    }
}
