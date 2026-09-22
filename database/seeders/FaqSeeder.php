<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::query()->exists()) {
            return;
        }

        $faqs = [
            [
                'question' => 'Turlara nereden katılabilirim?',
                'answer' => 'Rize ve Trabzon\'un il ve ilçelerinden katılabilirsiniz. Turun güzergâhı üzerindeki otellerden ve biniş noktalarından misafir alıyoruz; alınış noktanız ve saatiniz rezervasyon sırasında bildirilir.',
            ],
            [
                'question' => 'Otelimden alınıp otelime bırakılıyor muyum?',
                'answer' => 'Evet, oteliniz turun güzergâhı üzerindeyse sabah otelinizden alınır, akşam aynı yere bırakılırsınız. Güzergâh dışındaki konaklamalar için size en yakın biniş noktasını bildiririz.',
            ],
            [
                'question' => 'Nasıl rezervasyon yapabilirim?',
                'answer' => 'Tur sayfasındaki rezervasyon formunu doldurabilir, bizi arayabilir ya da WhatsApp\'tan yazabilirsiniz. Kişi sayınızı ve konakladığınız yeri aldıktan sonra sizi arayıp kaydınızı tamamlıyoruz. Form tek başına koltuk ayırmaz; kaydınız bizimle görüştükten sonra kesinleşir.',
            ],
            [
                'question' => 'Kayıt için hangi bilgiler isteniyor?',
                'answer' => 'Her yolcu için ad, soyad, T.C. kimlik numarası (yabancı uyruklu misafirler için pasaport numarası), yaş, cinsiyet ve bir telefon numarası alıyoruz. Bu bilgiler zorunlu seyahat sigortası ve yolcu listesi için gereklidir; başka amaçla kullanılmaz.',
            ],
            [
                'question' => 'Ailemle ya da arkadaşlarımla aynı araçta olabilir miyiz?',
                'answer' => 'Evet. Birlikte kaydolan gruplar araçlara bölünmeden yerleştirilir. Birden fazla araçla çıktığımız günlerde grubunuzun tamamı aynı araçta yolculuk eder.',
            ],
            [
                'question' => 'Fiyata neler dahil?',
                'answer' => 'Her turun sayfasında "fiyata dahil olanlar" ve "dahil olmayanlar" ayrı ayrı listelenir. Genel olarak ulaşım, rehberlik ve zorunlu seyahat sigortası dahildir; öğle yemeği, müze ve tesis girişleri ile isteğe bağlı aktiviteler dahil değildir.',
            ],
            [
                'question' => 'Yağmurlu ya da sisli havada tur yapılıyor mu?',
                'answer' => 'Evet. Yağmur ve sis Karadeniz\'in doğal hâlidir ve tur iptal nedeni değildir. Yol güvenliğini etkileyen yoğun yağış, heyelan ya da kar durumunda program rehber tarafından eşdeğer bir rotayla değiştirilir. Yanınıza yağmurluk ve kaymayan bir ayakkabı alın.',
            ],
            [
                'question' => 'Batum turuna kimlikle katılabilir miyim?',
                'answer' => 'T.C. vatandaşları yeni tip (çipli) kimlik kartıyla ya da pasaportla Gürcistan\'a girebilir. Eski tip nüfus cüzdanı kabul edilmez. Yurt dışı çıkış harcı yolcu tarafından ödenir. Yabancı uyruklu misafirlerin vize koşulları uyruğa göre değişir.',
            ],
            [
                'question' => 'Rezervasyonumu iptal edersem ne olur?',
                'answer' => 'İptal ve iade koşulları, kayıt sırasında size verilen tur sözleşmesinde yazılıdır ve tur gününe kalan süreye göre değişir. Ayrıntılar için Tur Sözleşmesi ve İptal Koşulları sayfamızı inceleyin.',
            ],
            [
                'question' => 'Kalabalık gruplar için özel tur düzenliyor musunuz?',
                'answer' => 'Evet. Aileler, arkadaş grupları, okullar ve iş yerleri için günü, programı ve araç sayısını grubunuza göre planlıyoruz. İletişim sayfasından grup büyüklüğünüzü ve gitmek istediğiniz yeri yazın.',
            ],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::query()->create($faq + [
                'is_active' => true,
                'show_on_home' => $i < 6,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
