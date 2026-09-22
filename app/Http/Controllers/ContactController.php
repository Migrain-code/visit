<?php

namespace App\Http\Controllers;

use App\Support\SchemaOrg;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contact', [
            'metaTitle' => 'İletişim | Telefon ve WhatsApp ile Rezervasyon',
            'metaDescription' => 'Turlarımız hakkında bilgi ve rezervasyon için bize telefon, WhatsApp veya form üzerinden ulaşın. Kalkış noktalarımız: '.setting('service_area_text').'.',
            'canonical' => route('contact'),
            'jsonLd' => [
                SchemaOrg::travelAgency(),
                SchemaOrg::breadcrumbs([
                    ['name' => 'Ana Sayfa', 'url' => url('/')],
                    ['name' => 'İletişim', 'url' => route('contact')],
                ]),
            ],
        ]);
    }
}
