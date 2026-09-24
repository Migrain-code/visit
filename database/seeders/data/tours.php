<?php

/*
 * Örnek turlar — RTEÜ Geziyor (Rize çıkışlı öğrenci turları).
 *
 * FİYATLAR VE TARİHLER ÖRNEKTİR: tarihler önümüzdeki hafta sonlarına göre
 * kurulumda hesaplanır. Yayına almadan önce panelden (Tüm Turlar) kendi program
 * ve fiyatlarınızla değiştirin. Görseller Wikimedia Commons'tan, CC0 / kamu malı
 * (database/seeders/images/CREDITS.md).
 *
 * Her satır: [başlık, rozet, kaç gün sonra (hafta sonu), saat, fiyat, görsel, kısa açıklama, uzun açıklama, kalkış yeri]
 */

return [
    [
        'title' => 'Batum', 'badge' => 'Popüler', 'weekend' => 0, 'time' => '07:00', 'price' => 1250,
        'image' => 'tours/gunubirlik-batum-turu.webp', 'image_alt' => 'Batum sahil bulvarı ve dönme dolap',
        'short' => 'Karadeniz\'in incisi, farklı bir ülkede yeni bir deneyim.',
        'description' => '<p>Sarp Sınır Kapısı\'ndan geçip Batum\'da tam bir gün: sahil bulvarı, Ali ve Nino heykeli, Alfabe Kulesi, Avrupa Meydanı ve Piazza. Akşam dönüş.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li><li>Yemek ve kişisel harcamalar hariç</li><li>Çipli T.C. kimlik kartı ya da pasaport şart</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
    [
        'title' => 'Sümela & Akvaryum', 'badge' => 'Doğa & Tarih', 'weekend' => 1, 'time' => '08:00', 'price' => 1100,
        'image' => 'tours/sumela-karaca-magarasi-hamsikoy-turu.webp', 'image_alt' => 'Kayalığa kurulu Sümela Manastırı',
        'short' => 'Tarih, doğa ve deniz bir arada! Unutulmaz bir Karadeniz rotası.',
        'description' => '<p>Altındere Vadisi\'nde Sümela Manastırı, ardından Trabzon\'da akvaryum ziyareti ve sahilde serbest zaman.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li><li>Müze ve akvaryum girişleri hariç (müze kartı geçerli)</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
    [
        'title' => 'Huser Yaylası', 'badge' => 'Manzara', 'weekend' => 2, 'time' => '12:00', 'price' => 950,
        'image' => 'tours/huser-yaylasi-gun-batimi-turu.webp', 'image_alt' => 'Huser Yaylası\'nda bulut denizi',
        'short' => 'Bulutların üzerinde, eşsiz bir doğa deneyimi.',
        'description' => '<p>Öğleden sonra hareket, gün batımını bulutların üzerinde Huser Yaylası\'nda izliyoruz. Yayla yolunun son bölümünde bölge araçlarına geçilir.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li><li>Kalın bir mont ve kaymayan ayakkabı önerilir</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
    [
        'title' => 'Ayder & Zilkale', 'badge' => 'Doğa Kaçamağı', 'weekend' => 3, 'time' => '08:30', 'price' => 1100,
        'image' => 'tours/zilkale-palovit-selalesi-turu.webp', 'image_alt' => 'Fırtına Vadisi\'nde Zilkale',
        'short' => 'Şelaleler, yaylalar ve tarihi kalelerle dolu bir gün.',
        'description' => '<p>Fırtına Vadisi boyunca taş köprüler, Zilkale, Palovit Şelalesi ve Ayder Yaylası\'nda serbest zaman. İsteğe bağlı kaplıca ve zipline.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li><li>Yemek, kaplıca ve zipline hariç</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
    [
        'title' => 'Pokut & Sal', 'badge' => 'Keşif Rotaları', 'weekend' => 4, 'time' => '08:00', 'price' => 1000,
        'image' => 'tours/pokut-sal-yaylasi-turu.webp', 'image_alt' => 'Pokut Yaylası\'nda ahşap yayla evleri',
        'short' => 'Karadeniz\'in en büyüleyici yaylalarında eşsiz manzaralar seni bekliyor.',
        'description' => '<p>Çamlıhemşin üzerinden Pokut ve Sal yaylaları: ahşap yayla evleri, bulut denizi ve Kaçkar manzarası. Yolun son bölümünde yayla araçlarına geçilir.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li><li>Yayla kahvaltısı isteğe bağlı</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
    [
        'title' => 'Uzungöl', 'badge' => 'Yeni', 'weekend' => 5, 'time' => '08:30', 'price' => 900,
        'image' => 'tours/uzungol-turu.webp', 'image_alt' => 'Uzungöl ve göl kıyısındaki cami',
        'short' => 'Ladin ormanlarının ortasında göl kıyısında rahat bir gün.',
        'description' => '<p>Çaykara üzerinden Uzungöl: seyir terası, göl çevresinde yürüyüş ve serbest zaman. Yolda çay fabrikası molası.</p><ul><li>Gidiş-dönüş ulaşım ve rehber dahil</li></ul>',
        'meeting_point' => 'RTEÜ Zihni Derin Yerleşkesi önü',
    ],
];
