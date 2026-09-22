<?php

/*
 * Örnek tur kataloğu — Doğu Karadeniz (Rize ve Trabzon çıkışlı).
 *
 * Tur seçimi ve duraklar, bölgede fiilen satılan günübirlik programlar incelenerek
 * hazırlandı. FİYATLAR VE SAATLER ÖRNEKTİR: siteyi yayına almadan önce yönetim
 * panelinden (Turlar) kendi program ve fiyatlarınızla değiştirin.
 */

$faq = fn (string $q, string $a) => ['question' => $q, 'answer' => $a];
$day = fn (string $t, string $d) => ['title' => $t, 'description' => $d];

$included = ['Gidiş-dönüş ulaşım (klimalı araç)', 'Otelinizden ya da biniş noktanızdan alınma', 'Profesyonel rehberlik hizmeti', 'Zorunlu seyahat sigortası'];
$excluded = ['Öğle yemeği ve içecekler', 'Müze, ören yeri ve tesis girişleri', 'İsteğe bağlı aktiviteler', 'Kişisel harcamalar'];

return [
    'categories' => [
        ['Yayla Turları', 'fa-solid fa-mountain-sun', 'Ayder, Pokut, Sal ve Huser: bulutların üzerindeki Kaçkar yaylalarına günübirlik turlar.',
            '<p>Rize\'nin Çamlıhemşin ilçesi, Kaçkar Dağları\'nın eteklerindeki yaylalarıyla Doğu Karadeniz\'in en çok ziyaret edilen bölgesidir. Ayder kaplıcası ve şelalesiyle, Pokut ve Sal ahşap yayla evleri ve bulut deniziyle, Huser ise gün batımıyla bilinir.</p><p>Yayla yolları dar, virajlı ve yer yer stabilizedir. Bu yüzden yüksek yaylalara çıkılan turlarda yolun son bölümünde bölgeye uygun yayla araçlarına geçilir. Hava yazın bile hızla değişir: sis ve yağmur yaylanın doğal hâlidir, yanınıza mutlaka yağmurluk ve kaymayan ayakkabı alın.</p>',
            [$faq('Yayla turları hangi aylarda yapılıyor?', 'Ayder yıl boyunca ulaşıma açıktır. Pokut, Sal ve Huser gibi yüksek yaylalara yol durumu uygun olduğunda, genellikle mayıs sonundan ekim ayına kadar çıkılır. Güncel tarihler tur takvimindedir.'), $faq('Sisli havada tur iptal olur mu?', 'Sis yaylalarda olağandır ve tur iptal nedeni değildir. Yol, yoğun yağış ya da heyelan nedeniyle kapanırsa program rehber tarafından eşdeğer bir rotayla değiştirilir.')]],
        ['Göl ve Vadi Turları', 'fa-solid fa-water', 'Uzungöl, Fırtına Vadisi, Zilkale ve Palovit Şelalesi: suyun ve ormanın peşinde günübirlik rotalar.',
            '<p>Doğu Karadeniz\'in vadileri, yaylaları kadar etkileyicidir. Trabzon\'un Çaykara ilçesindeki Uzungöl, heyelan sonucu oluşmuş bir set gölüdür ve çevresindeki ladin ormanlarıyla bölgenin simgesidir. Rize\'deki Fırtına Vadisi ise taş kemer köprüleri, Zilkale\'si ve şelaleleriyle bilinir.</p><p>Bu turlar yayla turlarına göre daha az yürüyüş gerektirir ve küçük çocuklu aileler ile ileri yaştaki misafirler için uygundur.</p>',
            [$faq('Bu turlar çocuklu aileler için uygun mu?', 'Evet. Duraklar araçla ulaşılan noktalardadır ve yürüyüşler kısadır. Bebek arabası için zemin her yerde uygun olmayabilir.')]],
        ['Kültür Turları', 'fa-solid fa-landmark', 'Sümela Manastırı, Karaca Mağarası, Trabzon Ayasofyası ve Atatürk Köşkü: bölgenin tarihî ve kültürel durakları.',
            '<p>Trabzon ve çevresi yalnız doğasıyla değil, tarihiyle de öne çıkar. Maçka\'da Altındere Vadisi\'nin sarp kayalıklarına kurulu Sümela Manastırı, şehir merkezindeki Ayasofya ve Atatürk Köşkü bölgenin en çok ziyaret edilen yapılarıdır.</p><p>Müze ve ören yeri girişleri fiyata dahil değildir; böylece müze kartı olan misafirlerimiz ikinci kez ödeme yapmaz.</p>',
            [$faq('Müze kartı geçerli mi?', 'Müze kartı, Kültür ve Turizm Bakanlığı\'na bağlı müze ve ören yerlerinde geçerlidir. Geçerli olduğu yerleri ve güncel giriş koşullarını rezervasyon sırasında teyit ediniz.')]],
        ['Batum ve Gürcistan Turları', 'fa-solid fa-earth-europe', 'Sarp Sınır Kapısı üzerinden günübirlik Batum ve konaklamalı Batum-Tiflis turları.',
            '<p>Sarp Sınır Kapısı Rize\'ye yaklaşık bir buçuk saat mesafededir; bu sayede Batum günübirlik gezilebilir. Sahil bulvarı, Ali ve Nino heykeli, Alfabe Kulesi ve Avrupa Meydanı turun başlıca duraklarıdır.</p><p>T.C. vatandaşları Gürcistan\'a yeni tip (çipli) kimlik kartıyla ya da pasaportla girebilir. Yabancı uyruklu misafirlerin vize durumu ülkeye göre değişir. Sınır geçiş koşulları değişebileceği için belgelerinizi kayıt sırasında bizimle teyit ediniz.</p>',
            [$faq('Batum\'a kimlikle gidilir mi?', 'T.C. vatandaşları yeni tip (çipli) kimlik kartıyla Gürcistan\'a giriş yapabilir. Eski tip nüfus cüzdanı kabul edilmez; çocukların kimliğinde fotoğraf bulunmalıdır. 2026 itibarıyla girişte seyahat sağlık sigortası da istenmektedir; güncel koşulları kayıt sırasında teyit ediniz.'), $faq('Yurt dışı çıkış harcı fiyata dahil mi?', 'Hayır. Yurt dışı çıkış harcı yolcu tarafından ödenir.')]],
    ],

    'tours' => [
        [
            'title' => 'Ayder Yaylası Turu', 'category' => 'Yayla Turları', 'days' => 1, 'nights' => 0,
            'price' => 1400, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. 0-6 yaş koltuk almadan ücretsizdir.',
            'image' => 'tours/ayder-yaylasi-turu.webp', 'image_alt' => 'Ayder çevresinde ladin ormanlarıyla kaplı vadi',
            'destinations' => 'Fırtına Vadisi, Şenyuva Köprüsü, Zilkale seyir noktası, Ayder Yaylası, Gelintülü Şelalesi', 'transport' => 'Klimalı minibüs / midibüs', 'accommodation' => null,
            'short' => 'Fırtına Vadisi boyunca taş köprüler, Gelintülü Şelalesi ve kaplıcasıyla Ayder: Rize\'nin en çok sevilen günübirlik turu.',
            'description' => '<p>Ayder, Rize\'nin Çamlıhemşin ilçesinde, Kaçkar Dağları Millî Parkı\'nın girişinde yaklaşık 1.350 metre yükseklikte bir yayladır. Ladin ormanları, ahşap yayla evleri, yayla merkezinin karşısından görülen Gelintülü Şelalesi ve 50 derece sıcaklıkta çıkan kaplıcasıyla bilinir.</p><p>Tur, Ardeşen\'den sonra Fırtına Deresi boyunca ilerler. Yolda Osmanlı döneminden kalan taş kemer köprülerde fotoğraf molası veriyor, Ayder\'de serbest zaman bırakıyoruz. Dileyen misafirlerimiz kaplıcaya girebilir ya da yayla çayırında yürüyüş yapabilir.</p>',
            'highlights' => ['Fırtına Vadisi\'nde taş kemer köprüler', 'Şenyuva Köprüsü\'nde fotoğraf molası', 'Gelintülü Şelalesi', 'Ayder\'de serbest zaman', 'İsteğe bağlı kaplıca ve zipline'],
            'included' => $included, 'excluded' => array_merge($excluded, ['Kaplıca girişi']),
            'itinerary' => [
                $day('Sabah: Hareket ve Fırtına Vadisi', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Sahil yolundan Ardeşen\'e, oradan Fırtına Deresi boyunca Çamlıhemşin\'e ilerliyoruz. Taş kemer köprülerde fotoğraf molası.'),
                $day('Öğle: Ayder Yaylası', 'Ayder\'e varış. Gelintülü Şelalesi seyir noktası, yayla çayırı ve öğle yemeği için serbest zaman. Muhlama, alabalık ve laz böreği yöresel seçeneklerdir.'),
                $day('Öğleden sonra: Serbest zaman ve dönüş', 'Kaplıca, yürüyüş ya da alışveriş için serbest zaman. Dönüş yolunda çay molası ve akşam saatlerinde otelinize bırakılış.'),
            ],
            'faqs' => [$faq('Ayder turu kışın da yapılıyor mu?', 'Evet. Ayder yolu kışın da açık tutulur ve karla kaplı yayla ayrı bir güzelliktedir. Yoğun kar yağışında program rehber tarafından güncellenebilir.'), $faq('Kaplıcaya girmek için ne getirmeliyim?', 'Mayo, havlu ve terlik. Kaplıca girişi fiyata dahil değildir ve tesiste ödenir.')],
        ],
        [
            'title' => 'Uzungöl Turu', 'category' => 'Göl ve Vadi Turları', 'days' => 1, 'nights' => 0,
            'price' => 1250, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. 0-6 yaş koltuk almadan ücretsizdir.',
            'image' => 'tours/uzungol-turu.webp', 'image_alt' => 'Uzungöl, göl kıyısındaki cami ve çevresindeki ormanlık dağlar',
            'destinations' => 'Sürmene, Of, çay fabrikası, Çaykara, Uzungöl seyir terası', 'transport' => 'Klimalı minibüs / midibüs', 'accommodation' => null,
            'short' => 'Sürmene bıçakçıları, çay fabrikası ziyareti ve ladin ormanlarının ortasında Uzungöl: aileler için rahat bir gün.',
            'description' => '<p>Uzungöl, Trabzon\'un Çaykara ilçesinde, Haldizen Deresi\'nin önünün bir heyelanla kapanması sonucu oluşmuş bir set gölüdür. Yaklaşık 1.200 metre yükseklikte, ladin ormanlarıyla çevrili bir vadinin tabanında yer alır ve 1989\'dan beri tabiat parkıdır.</p><p>Turda sahil yolundan Of\'a, oradan Solaklı Vadisi boyunca Çaykara\'ya çıkıyoruz. Yol üzerinde bir çay fabrikasında Karadeniz çayının yapraktan demliğe yolculuğunu görüyor, Uzungöl\'de göl çevresi ve seyir terası için serbest zaman veriyoruz.</p>',
            'highlights' => ['Sürmene\'de el yapımı bıçak atölyesi', 'Çay fabrikası ziyareti ve tadım', 'Solaklı Vadisi manzaraları', 'Uzungöl seyir terası', 'Göl çevresinde serbest zaman'],
            'included' => $included, 'excluded' => $excluded,
            'itinerary' => [
                $day('Sabah: Hareket, Sürmene ve çay fabrikası', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Sürmene\'de bıçak atölyesi ve yol üzerinde çay fabrikası ziyareti.'),
                $day('Öğle: Uzungöl', 'Çaykara üzerinden Uzungöl\'e varış. Seyir terasından gölün kuşbakışı manzarası, ardından göl kıyısında öğle yemeği için serbest zaman.'),
                $day('Öğleden sonra: Göl çevresi ve dönüş', 'Göl çevresinde yürüyüş, bisiklet ya da alışveriş için serbest zaman. Akşamüstü dönüş ve otelinize bırakılış.'),
            ],
            'faqs' => [$faq('Uzungöl\'de ne kadar serbest zaman veriliyor?', 'Program akışına göre yaklaşık üç saat. Kesin süre, yol ve hava durumuna göre rehber tarafından duyurulur.'), $faq('Yürüyüş gerekiyor mu?', 'Hayır. Tüm duraklar araçla ulaşılan noktalardadır; göl çevresindeki yürüyüş isteğe bağlıdır.')],
        ],
        [
            'title' => 'Sümela Karaca Mağarası Hamsiköy Turu', 'category' => 'Kültür Turları', 'days' => 1, 'nights' => 0,
            'price' => 1350, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Manastır ve mağara girişleri dahil değildir.',
            'image' => 'tours/sumela-karaca-magarasi-hamsikoy-turu.webp', 'image_alt' => 'Sarp kayalığa kurulu Sümela Manastırı',
            'destinations' => 'Maçka, Altındere Vadisi, Sümela Manastırı, Zigana, Karaca Mağarası, Hamsiköy', 'transport' => 'Klimalı minibüs / midibüs', 'accommodation' => null,
            'short' => 'Kayalığa asılı Sümela Manastırı, sarkıt ve dikitleriyle Karaca Mağarası ve meşhur sütlacıyla Hamsiköy.',
            'description' => '<p>Sümela Manastırı, Trabzon\'un Maçka ilçesinde, Altındere Vadisi Millî Parkı\'nın sarp kayalıklarına kurulmuş tarihî bir manastırdır. Vadiye hâkim konumu ve freskleriyle bölgenin en bilinen yapısıdır.</p><p>Manastırın ardından eski Zigana yolundan Gümüşhane\'nin Torul ilçesindeki Karaca Mağarası\'na geçiyoruz. Dönüşte, sütlacıyla ünlenen Hamsiköy\'de mola veriyoruz.</p>',
            'highlights' => ['Altındere Vadisi Millî Parkı', 'Sümela Manastırı', 'Karaca Mağarası\'nın sarkıt ve dikitleri', 'Zigana geçidi manzaraları', 'Hamsiköy\'de sütlaç molası'],
            'included' => $included, 'excluded' => $excluded,
            'itinerary' => [
                $day('Sabah: Maçka ve Sümela Manastırı', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Maçka üzerinden Altındere Vadisi\'ne çıkıyor, manastırı rehberimizle geziyoruz.'),
                $day('Öğle: Zigana ve Karaca Mağarası', 'Öğle yemeği molasının ardından Zigana üzerinden Torul\'a geçiyor, Karaca Mağarası\'nı ziyaret ediyoruz.'),
                $day('Öğleden sonra: Hamsiköy ve dönüş', 'Hamsiköy\'de sütlaç molası ve yöresel ürünler için serbest zaman. Akşam saatlerinde otelinize bırakılış.'),
            ],
            'faqs' => [$faq('Manastıra çıkış zor mu?', 'Araçtan indikten sonra manastıra orman içinden, merdivenli ve eğimli bir patikayla çıkılır. Rahat ve kaymayan bir ayakkabı giymenizi öneririz. Yürümek istemeyen misafirlerimiz seyir noktasında bekleyebilir.'), $faq('Manastır her zaman ziyarete açık mı?', 'Manastır zaman zaman restorasyon ya da kaya ıslahı çalışmaları nedeniyle kısmen ya da tamamen kapatılabiliyor. Kapalı olduğu günlerde dışarıdan seyir noktasına çıkılır ve programa alternatif bir durak eklenir.')],
        ],
        [
            'title' => 'Günübirlik Batum Turu', 'category' => 'Batum ve Gürcistan Turları', 'days' => 1, 'nights' => 0,
            'price' => 1650, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Yurt dışı çıkış harcı dahil değildir.',
            'image' => 'tours/gunubirlik-batum-turu.webp', 'image_alt' => 'Batum sahilinde Alfabe Kulesi ve gökdelenler, arkada karlı dağlar',
            'destinations' => 'Sarp Sınır Kapısı, Gonio Kalesi, Batum Bulvarı, Ali ve Nino Heykeli, Alfabe Kulesi, Avrupa Meydanı, Piazza', 'transport' => 'Klimalı minibüs / midibüs', 'accommodation' => null,
            'short' => 'Kimlikle Gürcistan: Sarp\'tan geçip sahil bulvarı, Ali ve Nino heykeli ve Avrupa Meydanı ile Batum\'da bir gün.',
            'description' => '<p>Batum, Gürcistan\'ın Karadeniz kıyısındaki liman kentidir ve Sarp Sınır Kapısı\'na yalnızca 20 kilometre uzaklıktadır. Kilometrelerce uzanan sahil bulvarı, eski şehir meydanları ve modern kuleleriyle günübirlik gezilebilecek bir şehirdir.</p><p>T.C. vatandaşları yeni tip (çipli) kimlik kartıyla ya da pasaportla sınırdan geçebilir. Sınırda bekleme süresi güne ve saate göre değiştiği için program akışı rehber tarafından buna göre düzenlenir.</p>',
            'highlights' => ['Sarp Sınır Kapısı\'ndan yaya geçiş', 'Batum Bulvarı ve sahil', 'Ali ve Nino Heykeli, Alfabe Kulesi', 'Avrupa Meydanı ve Piazza', 'Alışveriş ve Gürcü mutfağı için serbest zaman'],
            'included' => $included, 'excluded' => ['Yurt dışı çıkış harcı', 'Gürcistan için zorunlu seyahat sağlık sigortası (dahil değilse kayıt sırasında belirtilir)', 'Öğle yemeği ve içecekler', 'Teleferik ve müze girişleri', 'Kişisel harcamalar'],
            'itinerary' => [
                $day('Sabah: Sarp Sınır Kapısı', 'Erken saatte otelinizden ya da biniş noktanızdan alınıyorsunuz. Hopa ve Kemalpaşa üzerinden Sarp\'a varış ve sınır geçişi.'),
                $day('Öğle: Batum şehir turu', 'Gonio Kalesi\'ni dışarıdan gördükten sonra Batum\'a giriş. Avrupa Meydanı, Piazza ve eski şehir sokaklarını rehberimizle geziyoruz. Öğle yemeği için serbest zaman.'),
                $day('Öğleden sonra: Bulvar ve dönüş', 'Batum Bulvarı, Ali ve Nino Heykeli ve Alfabe Kulesi. Alışveriş için serbest zamanın ardından sınır geçişi ve dönüş.'),
            ],
            'faqs' => [$faq('Hangi belgeyle geçebilirim?', 'T.C. vatandaşları yeni tip (çipli) kimlik kartıyla ya da geçerli bir pasaportla geçebilir. Eski tip nüfus cüzdanı, ehliyet ve kimlik fotokopisi kabul edilmez. Çocukların kimlik kartında fotoğraf bulunmalıdır. Belgeniz sizde değilse tura katılamazsınız ve ücret iadesi yapılmaz.'), $faq('Seyahat sigortası gerekiyor mu?', 'Gürcistan, 2026 itibarıyla kimlik kartı ya da pasaportla giriş yapan T.C. vatandaşlarından sağlık ve kaza teminatlı seyahat sigortası istemektedir. Sigortanın tura dahil olup olmadığı ve güncel koşullar kayıt sırasında size bildirilir.'), $faq('Yabancı uyruklu misafirler katılabilir mi?', 'Katılabilir; ancak Gürcistan\'a giriş ve Türkiye\'ye yeniden giriş için vize koşulları uyruğa göre değişir. Kayıt sırasında pasaport bilgilerinizi bildirin.'), $faq('Hangi para birimi geçiyor?', 'Gürcistan\'ın para birimi laridir. Şehir merkezinde döviz büroları yaygındır; birçok yerde kart da geçer.')],
        ],
        [
            'title' => 'Pokut ve Sal Yaylası Turu', 'category' => 'Yayla Turları', 'days' => 1, 'nights' => 0,
            'price' => 1750, 'old_price' => 1900, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Yayla aracı transferi dahildir.',
            'image' => 'tours/pokut-sal-yaylasi-turu.webp', 'image_alt' => 'Karadeniz yaylasında yamaca dizili ahşap yayla evleri',
            'destinations' => 'Çamlıhemşin, Şenyuva, Sal Yaylası, Pokut Yaylası', 'transport' => 'Minibüs; yayla yolunda arazi tipi yayla aracı', 'accommodation' => null,
            'short' => 'Bulut denizinin üzerinde ahşap yayla evleri: Karadeniz denince akla gelen o manzara, Pokut ve Sal\'da.',
            'description' => '<p>Pokut ve Sal, Çamlıhemşin\'in yaklaşık 2.000 metre yükseklikteki (Pokut 2.032 m) iki komşu yaylasıdır. Yamaca dizili ahşap evleri ve sabah saatlerinde vadiyi dolduran bulut deniziyle Doğu Karadeniz\'in en çok fotoğraflanan noktaları arasındadır.</p><p>Yaylalara çıkan yol dar ve stabilizedir; bu yüzden Çamlıhemşin\'den sonra bölgeye uygun yayla araçlarına geçiyoruz. Tur orta düzeyde yürüyüş içerir ve hava koşullarına bağlıdır.</p>',
            'highlights' => ['Sal Yaylası\'ndan Kaçkar manzarası', 'Pokut\'un ahşap yayla evleri', 'Bulut denizi (hava koşullarına bağlı)', 'Yayla evinde yöresel öğle yemeği seçeneği', 'Yayla aracıyla orman yolu'],
            'included' => array_merge($included, ['Yayla aracı transferi']), 'excluded' => $excluded,
            'itinerary' => [
                $day('Sabah: Çamlıhemşin ve yayla yolu', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Çamlıhemşin\'de yayla araçlarına geçiyor, Şenyuva üzerinden orman yoluyla tırmanıyoruz.'),
                $day('Öğle: Sal Yaylası', 'Sal Yaylası\'nda manzara ve fotoğraf molası. Yayla evinde muhlama ve yöresel yemekler için serbest zaman.'),
                $day('Öğleden sonra: Pokut ve dönüş', 'Kısa bir yürüyüşle Pokut Yaylası\'na geçiş. Serbest zamanın ardından inişe geçiyor, akşam saatlerinde otelinize bırakıyoruz.'),
            ],
            'faqs' => [$faq('Bulut denizini görmek garanti mi?', 'Hayır. Bulut denizi tamamen hava koşullarına bağlıdır; en sık sabah saatlerinde ve yaz sonu ile sonbaharda görülür.'), $faq('Yürüyüş ne kadar zor?', 'Sal ile Pokut arası yaklaşık yarım saatlik, yer yer eğimli bir patikadır. Kaymayan, bileği saran bir ayakkabı giyin.')],
        ],
        [
            'title' => 'Huser Yaylası Gün Batımı Turu', 'category' => 'Yayla Turları', 'days' => 1, 'nights' => 0,
            'price' => 1650, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Yayla aracı transferi dahildir.',
            'image' => 'tours/huser-yaylasi-gun-batimi-turu.webp', 'image_alt' => 'Çiçekli yayla çayırından görünen Karadeniz dağları ve vadi',
            'destinations' => 'Fırtına Vadisi, Ayder, Huser Yaylası', 'transport' => 'Minibüs; yayla yolunda arazi tipi yayla aracı', 'accommodation' => null,
            'short' => 'Öğleden sonra yola çıkılan tek tur: Ayder\'in üzerindeki Huser\'de bulutların üstünde gün batımı.',
            'description' => '<p>Huser, Ayder\'in yaklaşık 8 kilometre üzerinde, 2.700 metre yükseklikte bir yayladır. Batıya açık konumu sayesinde gün batımı, hava uygun olduğunda bulut denizinin üzerinde izlenir.</p><p>Bu tur diğerlerinden farklı olarak öğleden sonra başlar ve gün batımından sonra döner. Dönüş yolculuğu gece saatlerine sarkar; yanınıza mutlaka kalın bir üst alın, güneş battıktan sonra yayla hızla soğur.</p>',
            'highlights' => ['Bulutların üzerinde gün batımı', 'Ayder\'de kısa mola', 'Yayla aracıyla orman yolu', 'Yayla kafesinde çay ve manzara'],
            'included' => array_merge($included, ['Yayla aracı transferi']), 'excluded' => ['Yemekler ve içecekler', 'İsteğe bağlı aktiviteler', 'Kişisel harcamalar'],
            'itinerary' => [
                $day('Öğleden sonra: Hareket ve Ayder', 'Öğle saatlerinde otelinizden ya da biniş noktanızdan alınıyorsunuz. Fırtına Vadisi üzerinden Ayder\'e varış ve kısa mola.'),
                $day('Akşamüstü: Huser Yaylası', 'Ayder\'den yayla araçlarıyla Huser\'e çıkış. Seyir noktalarında fotoğraf ve yayla kafesinde serbest zaman.'),
                $day('Gün batımı ve dönüş', 'Gün batımını izledikten sonra inişe geçiyoruz. Gece saatlerinde otelinize bırakılış.'),
            ],
            'faqs' => [$faq('Tur saat kaçta bitiyor?', 'Gün batımı saatine göre değişir; yaz aylarında otelinize dönüş gece yarısına yaklaşabilir.'), $faq('Hava kapalıysa ne olur?', 'Sis ve bulut yaylanın doğal hâlidir; gün batımı görülemeyebilir. Yol güvenli olduğu sürece tur yapılır.')],
        ],
        [
            'title' => 'Zilkale ve Palovit Şelalesi Turu', 'category' => 'Göl ve Vadi Turları', 'days' => 1, 'nights' => 0,
            'price' => 1500, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Kale girişi dahil değildir.',
            'image' => 'tours/zilkale-palovit-selalesi-turu.webp', 'image_alt' => 'Fırtına Vadisi\'ne hâkim tepede Zilkale',
            'destinations' => 'Fırtına Vadisi, Şenyuva Köprüsü, Zilkale, Palovit Şelalesi, Çamlıhemşin', 'transport' => 'Klimalı minibüs', 'accommodation' => null,
            'short' => 'Fırtına Vadisi\'nin derinlikleri: uçurumun kenarındaki Zilkale, şimşir ormanları ve gürül gürül akan Palovit Şelalesi.',
            'description' => '<p>Zilkale, Fırtına Vadisi\'ne hâkim sarp bir kayalığın üzerine kurulmuş bir Orta Çağ kalesidir. Çamlıhemşin\'den sonra vadinin içine doğru ilerleyen yol, taş kemer köprülerden ve şimşir ormanlarından geçer.</p><p>Kalenin ardından vadinin daha yukarısındaki Palovit Şelalesi\'ne çıkıyoruz. Şelale, özellikle kar sularının eridiği ilkbahar ve yaz başında en gür hâlindedir.</p>',
            'highlights' => ['Şenyuva Köprüsü', 'Zilkale ve vadi manzarası', 'Şimşir ormanı içinden geçen yol', 'Palovit Şelalesi', 'İsteğe bağlı rafting ve zipline'],
            'included' => $included, 'excluded' => $excluded,
            'itinerary' => [
                $day('Sabah: Fırtına Vadisi', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Ardeşen\'den vadiye giriş, Şenyuva Köprüsü\'nde fotoğraf molası.'),
                $day('Öğle: Zilkale', 'Zilkale\'yi geziyor, surlardan vadiyi izliyoruz. Çamlıhemşin\'de öğle yemeği için serbest zaman.'),
                $day('Öğleden sonra: Palovit Şelalesi ve dönüş', 'Palovit Şelalesi\'ne çıkış ve fotoğraf molası. Dönüş yolunda dileyenler için rafting ya da zipline imkânı.'),
            ],
            'faqs' => [$faq('Rafting fiyata dahil mi?', 'Hayır. Rafting ve zipline isteğe bağlıdır, ücreti tesiste ödenir ve su seviyesine göre yapılır.')],
        ],
        [
            'title' => 'Trabzon Şehir Turu', 'category' => 'Kültür Turları', 'days' => 1, 'nights' => 0,
            'price' => 1100, 'old_price' => null, 'currency' => 'TRY', 'price_note' => 'Kişi başı. Müze girişleri dahil değildir.',
            'image' => 'tours/trabzon-sehir-turu.webp', 'image_alt' => 'Trabzon Ayasofyası ve çan kulesi',
            'destinations' => 'Ayasofya, Atatürk Köşkü, Boztepe, Trabzon Meydanı, Kemeraltı Çarşısı, Akçaabat', 'transport' => 'Klimalı minibüs / midibüs', 'accommodation' => null,
            'short' => 'Ayasofya, Atatürk Köşkü, Boztepe\'den şehir manzarası ve Akçaabat köftesi: Trabzon\'un görülmesi gerekenleri tek günde.',
            'description' => '<p>Trabzon, İpek Yolu\'nun Karadeniz\'e açılan kapısı olmuş tarihî bir liman kentidir. Şehir turunda deniz kıyısındaki Ayasofya\'yı, çam ormanı içindeki Atatürk Köşkü\'nü ve şehri tepeden gören Boztepe\'yi geziyoruz.</p><p>Öğleden sonra Kemeraltı Çarşısı\'nda telkâri ve hasır bilezik ustalarını görebilir, günü Akçaabat\'ta köfte molasıyla tamamlarsınız.</p>',
            'highlights' => ['Trabzon Ayasofyası', 'Atatürk Köşkü', 'Boztepe\'de çay ve şehir manzarası', 'Kemeraltı Çarşısı\'nda telkâri ve hasır bilezik', 'Akçaabat köftesi molası'],
            'included' => $included, 'excluded' => $excluded,
            'itinerary' => [
                $day('Sabah: Ayasofya ve Atatürk Köşkü', 'Otelinizden ya da biniş noktanızdan alınıyorsunuz. Ayasofya\'yı ve ardından Soğuksu\'daki Atatürk Köşkü\'nü geziyoruz.'),
                $day('Öğle: Boztepe ve meydan', 'Boztepe\'de şehir manzarası eşliğinde çay molası. Trabzon Meydanı ve Kemeraltı Çarşısı\'nda serbest zaman.'),
                $day('Öğleden sonra: Akçaabat ve dönüş', 'Akçaabat\'ta köfte molası ve Orta Mahalle\'nin tarihî evleri. Akşamüstü otelinize bırakılış.'),
            ],
            'faqs' => [$faq('Bu tur Rize\'den de kalkıyor mu?', 'Evet. Rize\'den katılan misafirlerimiz sahil yolundan yaklaşık bir saatte Trabzon\'a ulaşır; kalkış saati buna göre daha erkendir.')],
        ],
        [
            'title' => 'Batum Tiflis Turu', 'category' => 'Batum ve Gürcistan Turları', 'days' => 3, 'nights' => 2,
            'price' => 9800, 'old_price' => 10500, 'currency' => 'TRY', 'price_note' => 'Kişi başı, çift kişilik odada. Yurt dışı çıkış harcı dahil değildir.',
            'image' => 'tours/batum-tiflis-turu.webp', 'image_alt' => 'Tiflis eski şehri, Narikala yamaçları ve kırmızı çatılı evler',
            'destinations' => 'Sarp Sınır Kapısı, Batum, Kutaisi, Tiflis eski şehri, Narikala, Barış Köprüsü', 'transport' => 'Klimalı tur otobüsü', 'accommodation' => 'Batum\'da 1 gece, Tiflis\'te 1 gece; otelde oda kahvaltı',
            'short' => 'Karadeniz kıyısından Kafkasya\'nın başkentine: Batum\'un bulvarı ve Tiflis\'in eski şehri iki gece üç günde.',
            'description' => '<p>Bu turda Gürcistan\'ı boydan boya geçiyoruz: ilk gün Karadeniz kıyısındaki Batum, ikinci gün ülkenin başkenti Tiflis. Tiflis; Kura Nehri kıyısındaki eski şehri, kükürtlü hamamları, Narikala Kalesi ve cam Barış Köprüsü ile bilinir.</p><p>T.C. vatandaşları yeni tip (çipli) kimlik kartıyla ya da pasaportla katılabilir. Batum ile Tiflis arası uzun bir kara yolculuğudur; program buna göre molalarla planlanmıştır.</p>',
            'highlights' => ['Batum Bulvarı ve Avrupa Meydanı', 'Tiflis eski şehri ve kükürtlü hamamlar', 'Narikala Kalesi\'ne teleferik', 'Barış Köprüsü ve Rike Parkı', 'Gürcü mutfağı: haçapuri ve hinkali'],
            'included' => ['Gidiş-dönüş otobüs ulaşımı', '2 gece otel konaklaması', '2 kahvaltı', 'Türkçe rehberlik hizmeti', 'Gürcistan girişinde istenen seyahat sağlık sigortası'],
            'excluded' => ['Yurt dışı çıkış harcı', 'Öğle ve akşam yemekleri', 'Müze, teleferik ve hamam girişleri', 'Kişisel harcamalar'],
            'itinerary' => [
                $day('1. Gün: Sarp ve Batum', 'Sabah erken saatte hareket, Sarp Sınır Kapısı\'ndan geçiş. Batum şehir turu: Avrupa Meydanı, Piazza, bulvar, Ali ve Nino Heykeli. Konaklama Batum\'da.'),
                $day('2. Gün: Tiflis', 'Kahvaltının ardından Kutaisi üzerinden Tiflis\'e yolculuk. Öğleden sonra eski şehir, kükürtlü hamamlar bölgesi ve Barış Köprüsü. Konaklama Tiflis\'te.'),
                $day('3. Gün: Narikala ve dönüş', 'Sabah teleferikle Narikala Kalesi. Ardından dönüş yolculuğuna başlıyoruz; gece saatlerinde sınır geçişi ve varış.'),
            ],
            'faqs' => [$faq('Pasaport şart mı?', 'Hayır. T.C. vatandaşları yeni tip (çipli) kimlik kartıyla da Gürcistan\'a girebilir. Yabancı uyruklu misafirlerin vize koşulları uyruğa göre değişir.'), $faq('Tek kişilik oda farkı var mı?', 'Evet. Tek kişilik oda farkı kayıt sırasında bildirilir.')],
        ],
    ],
];
