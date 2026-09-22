<?php

/*
 * Kalkış bölgeleri: Rize ve Trabzon il ve ilçeleri.
 *
 * Her ilçe için [ad, kısa açıklama, ilçeye özgü konum notu, tura katılım notu].
 * Konum notları coğrafi gerçeklerdir. BİNİŞ NOKTALARI bilerek boş bırakılmıştır:
 * yolcuları nereden aldığınızı panelden (Bölgeler → İlçeler → Biniş noktaları) siz girin.
 */
return [
    [
        'name' => 'Rize',
        'description' => 'Rize\'nin 12 ilçesinden yayla, göl, kültür ve Batum turlarımıza katılabilirsiniz.',
        'meta_description' => 'Rize çıkışlı günübirlik turlar: Ayder, Pokut, Huser, Zilkale, Uzungöl, Sümela ve Batum. Otelinizden alınma, güncel tarihler ve kişi başı fiyatlar.',
        'content' => '<p>Rize, Kaçkar Dağları ile Karadeniz arasında, çay bahçeleriyle kaplı dik yamaçlara kurulu bir ildir. Ayder, Pokut, Sal ve Huser yaylaları ile Fırtına Vadisi ve Zilkale ilin Çamlıhemşin ilçesindedir; bu yüzden yayla turlarımızın tamamı Rize\'den kısa sürede ulaşılan rotalardır.</p><p>Sarp Sınır Kapısı Rize\'ye yaklaşık bir buçuk saat, Trabzon ise sahil yolundan yaklaşık bir saat mesafededir. Rize\'de konaklayan misafirlerimiz Batum, Uzungöl ve Sümela turlarına da günübirlik katılabilir.</p>',
        'faqs' => [
            ['question' => 'Rize\'nin hangi ilçelerinden tura katılabilirim?', 'answer' => 'Rize Merkez, Ardeşen, Çamlıhemşin, Çayeli, Derepazarı, Fındıklı, Güneysu, Hemşin, İkizdere, İyidere, Kalkandere ve Pazar olmak üzere 12 ilçenin tamamından katılabilirsiniz.'],
            ['question' => 'Otelimden alınıyor muyum?', 'answer' => 'Turun güzergâhı üzerindeki otellerden ve biniş noktalarından misafir alıyoruz. Konakladığınız yeri rezervasyon sırasında bildirin; alınış saatiniz size ayrıca iletilir.'],
        ],
        'districts' => [
            ['Rize Merkez', 'Rize şehir merkezinden yayla, göl ve Batum turlarına katılım.', 'Rize Merkez, sahil yolu üzerinde, çay bahçeleriyle çevrili il merkezidir. Rize Kalesi, Ziraat Botanik Çay Bahçesi ve sahil parkı şehrin bilinen noktalarıdır.', 'Yayla ve Batum turlarımız doğuya, Uzungöl ve Sümela turlarımız batıya gider; Rize Merkez her iki yönde de güzergâhın üzerindedir.'],
            ['Ardeşen', 'Fırtına Vadisi\'nin girişindeki Ardeşen\'den turlara katılım.', 'Ardeşen, Fırtına Deresi\'nin Karadeniz\'e döküldüğü yerde kurulu bir sahil ilçesidir. Çamlıhemşin ve Ayder\'e giden yol buradan ayrılır.', 'Ayder, Pokut, Huser ve Zilkale turlarımızın tamamı Ardeşen\'den geçer; bu turlara katılmak için en elverişli ilçelerden biridir.'],
            ['Çamlıhemşin', 'Yaylaların ilçesi Çamlıhemşin\'den tur kaydı.', 'Çamlıhemşin, Fırtına Vadisi\'nin içinde, Kaçkar Dağları Millî Parkı\'nın girişindeki ilçedir. Ayder, Pokut, Sal ve Huser yaylaları ile Zilkale ve Palovit Şelalesi bu ilçenin sınırları içindedir.', 'Çamlıhemşin\'de konaklayan misafirlerimiz yayla turlarına ilçe merkezinden, yayla araçlarına geçilen noktadan katılabilir.'],
            ['Çayeli', 'Çayeli\'nden günübirlik turlara katılım.', 'Çayeli, Rize Merkez\'in doğusunda, sahil yolu üzerinde bir ilçedir. Kuru fasulyesi ve bakır madeniyle bilinir.', 'Doğu yönündeki yayla ve Batum turlarımızda Çayeli, Rize\'den sonraki ilk biniş noktalarından biridir.'],
            ['Derepazarı', 'Derepazarı\'ndan tur kaydı ve biniş bilgileri.', 'Derepazarı, Rize Merkez\'in hemen batısında, sahil yolu üzerinde küçük bir ilçedir.', 'Trabzon yönündeki Uzungöl ve Sümela turlarımızda güzergâh üzerindedir; yayla turları için biniş Rize Merkez ile birlikte planlanır.'],
            ['Fındıklı', 'Rize\'nin en doğusundaki Fındıklı\'dan turlara katılım.', 'Fındıklı, Rize\'nin Artvin sınırındaki sahil ilçesidir. Çağlayan Vadisi\'ndeki tarihî taş konakları ve kemer köprüleriyle bilinir.', 'Batum turlarımız Fındıklı\'dan geçer; Sarp Sınır Kapısı\'na en yakın Rize ilçesi olduğu için bu tura katılım çok pratiktir.'],
            ['Güneysu', 'Güneysu\'dan tur kaydı.', 'Güneysu, Rize Merkez\'in güneyinde, çay bahçeleriyle kaplı bir vadi içinde yer alan ilçedir.', 'Güneysu\'dan katılan misafirlerimiz Rize Merkez\'deki biniş noktasından turlara katılır.'],
            ['Hemşin', 'Hemşin ilçesinden turlara katılım.', 'Hemşin, Pazar ilçesinin güneyinde, Hemşin Deresi vadisinde kurulu küçük bir ilçedir.', 'Hemşin\'den katılım, sahil yolundaki Pazar biniş noktası üzerinden planlanır.'],
            ['İkizdere', 'İkizdere\'den tur kaydı ve biniş bilgileri.', 'İkizdere, Rize\'nin iç kesiminde, Ovit Geçidi yolu üzerinde bir dağ ilçesidir. Anzer Yaylası ve balı bu ilçededir.', 'Sahil güzergâhına uzak olduğu için İkizdere\'den katılımda biniş noktası rezervasyon sırasında birlikte belirlenir.'],
            ['İyidere', 'İyidere\'den günübirlik turlara katılım.', 'İyidere, Rize\'nin batısında, Trabzon sınırına yakın bir sahil ilçesidir.', 'Trabzon yönündeki Uzungöl ve Sümela turlarımızda güzergâh üzerinde bir biniş noktasıdır.'],
            ['Kalkandere', 'Kalkandere\'den tur kaydı.', 'Kalkandere, İyidere\'nin güneyinde, İkizdere yolu üzerinde bir iç kesim ilçesidir.', 'Kalkandere\'den katılım, sahil yolundaki İyidere ya da Rize Merkez biniş noktası üzerinden planlanır.'],
            ['Pazar', 'Pazar ilçesinden yayla ve Batum turlarına katılım.', 'Pazar, Çayeli ile Ardeşen arasında bir sahil ilçesidir. Deniz kıyısındaki Kız Kalesi ile bilinir.', 'Doğu yönündeki bütün turlarımız Pazar\'dan geçer; Ayder yol ayrımına yalnızca birkaç kilometre uzaklıktadır.'],
        ],
    ],
    [
        'name' => 'Trabzon',
        'description' => 'Trabzon\'un 18 ilçesinden Uzungöl, Sümela, yayla ve Batum turlarımıza katılabilirsiniz.',
        'meta_description' => 'Trabzon çıkışlı günübirlik turlar: Uzungöl, Sümela Manastırı, Karaca Mağarası, Ayder ve Batum. Otelinizden alınma, güncel tarihler ve kişi başı fiyatlar.',
        'content' => '<p>Trabzon, Doğu Karadeniz\'in en büyük kenti ve bölgeye gelen ziyaretçilerin çoğunun konakladığı yerdir. Havalimanı şehir merkezine çok yakındır; Uzungöl ilin Çaykara, Sümela Manastırı ise Maçka ilçesindedir.</p><p>Trabzon\'da konaklayan misafirlerimiz Uzungöl, Sümela ve şehir turlarına kısa yolculukla; Ayder ve Batum turlarına ise sahil yolundan günübirlik katılabilir. Otelinizin bulunduğu ilçeyi rezervasyon sırasında bildirmeniz yeterlidir.</p>',
        'faqs' => [
            ['question' => 'Trabzon\'un hangi ilçelerinden tura katılabilirim?', 'answer' => 'Ortahisar, Akçaabat, Yomra, Arsin, Araklı, Sürmene, Of, Çaykara, Maçka ve diğer ilçeler olmak üzere Trabzon\'un 18 ilçesinin tamamından katılabilirsiniz.'],
            ['question' => 'Trabzon\'dan Ayder ve Batum turlarına günübirlik gidilir mi?', 'answer' => 'Evet. Sahil yolu sayesinde her iki tur da günübirlik yapılır; kalkış saati Rize\'den katılanlara göre daha erkendir.'],
        ],
        'districts' => [
            ['Ortahisar', 'Trabzon şehir merkezi Ortahisar\'dan günübirlik turlara katılım.', 'Ortahisar, Trabzon\'un merkez ilçesidir. Trabzon Meydanı, Ayasofya, Atatürk Köşkü ve Boztepe bu ilçededir; şehirdeki otellerin büyük bölümü de buradadır.', 'Bütün turlarımız için Ortahisar\'daki otellerden ve meydandan misafir alıyoruz.'],
            ['Akçaabat', 'Akçaabat\'tan turlara katılım.', 'Akçaabat, Trabzon\'un batısında, köftesi ve Orta Mahalle\'deki tarihî evleriyle bilinen bir sahil ilçesidir.', 'Akçaabat\'tan katılan misafirlerimiz, doğu yönündeki turlarda güzergâhın ilk biniş noktasını oluşturur.'],
            ['Araklı', 'Araklı\'dan tur kaydı ve biniş bilgileri.', 'Araklı, Trabzon\'un doğusunda, Arsin ile Sürmene arasında bir sahil ilçesidir.', 'Uzungöl, Ayder ve Batum turlarımız Araklı\'dan geçer.'],
            ['Arsin', 'Arsin\'den günübirlik turlara katılım.', 'Arsin, Yomra\'nın doğusunda, organize sanayi bölgesinin bulunduğu bir sahil ilçesidir.', 'Doğu yönündeki turlarımızda güzergâh üzerinde bir biniş noktasıdır.'],
            ['Beşikdüzü', 'Beşikdüzü\'nden tur kaydı.', 'Beşikdüzü, Trabzon\'un batı ucunda, denizin üzerinden geçen teleferiğiyle bilinen bir sahil ilçesidir.', 'Şehir merkezine uzak olduğu için Beşikdüzü\'nden katılımda alınış saati rezervasyon sırasında ayrıca bildirilir.'],
            ['Çarşıbaşı', 'Çarşıbaşı\'ndan turlara katılım.', 'Çarşıbaşı, Akçaabat ile Vakfıkebir arasında küçük bir sahil ilçesidir.', 'Çarşıbaşı\'ndan katılım, sahil yolu üzerinden Akçaabat ile birlikte planlanır.'],
            ['Çaykara', 'Uzungöl\'ün ilçesi Çaykara\'dan tur kaydı.', 'Çaykara, Solaklı Vadisi\'nin içinde bir dağ ilçesidir. Uzungöl bu ilçenin sınırları içindedir.', 'Uzungöl\'de konaklayan misafirlerimiz yayla ve Batum turlarına sahildeki Of biniş noktasından katılabilir.'],
            ['Dernekpazarı', 'Dernekpazarı\'ndan turlara katılım.', 'Dernekpazarı, Of ile Çaykara arasında, Solaklı Vadisi\'nde küçük bir ilçedir.', 'Uzungöl turumuz ilçeden geçer; diğer turlar için biniş Of üzerinden planlanır.'],
            ['Düzköy', 'Düzköy\'den tur kaydı ve biniş bilgileri.', 'Düzköy, Akçaabat\'ın güneyinde bir yayla ilçesidir. Çal Mağarası bu ilçededir.', 'Düzköy\'den katılan misafirlerimiz Akçaabat ya da Ortahisar\'daki biniş noktasından turlara katılır.'],
            ['Hayrat', 'Hayrat\'tan günübirlik turlara katılım.', 'Hayrat, Of\'un güneyinde, vadi içinde kurulu küçük bir ilçedir.', 'Hayrat\'tan katılım sahildeki Of biniş noktası üzerinden planlanır.'],
            ['Köprübaşı', 'Köprübaşı\'ndan tur kaydı.', 'Köprübaşı, Sürmene\'nin güneyinde, Manahoz Deresi vadisinde bir iç kesim ilçesidir.', 'Köprübaşı\'ndan katılım sahildeki Sürmene biniş noktası üzerinden planlanır.'],
            ['Maçka', 'Sümela\'nın ilçesi Maçka\'dan turlara katılım.', 'Maçka, Trabzon\'un güneyinde, Gümüşhane yolu üzerinde bir ilçedir. Sümela Manastırı ve Altındere Vadisi Millî Parkı buradadır.', 'Sümela, Karaca Mağarası ve Hamsiköy turumuz Maçka\'dan geçer; ilçede konaklayanlar tura buradan katılabilir.'],
            ['Of', 'Of ilçesinden Uzungöl, yayla ve Batum turlarına katılım.', 'Of, Trabzon\'un Rize sınırındaki sahil ilçesidir. Uzungöl\'e çıkan Solaklı Vadisi yolu buradan ayrılır.', 'Hem Uzungöl hem de doğu yönündeki Ayder ve Batum turlarımız Of\'tan geçer.'],
            ['Sürmene', 'Sürmene\'den tur kaydı ve biniş bilgileri.', 'Sürmene, el yapımı bıçakları ve tarihî Memişağa Konağı ile bilinen bir sahil ilçesidir.', 'Uzungöl turumuzda Sürmene bıçakçıları programın duraklarından biridir; ilçeden katılım güzergâh üzerindedir.'],
            ['Şalpazarı', 'Şalpazarı\'ndan turlara katılım.', 'Şalpazarı, Beşikdüzü\'nün güneyinde, Ağasar Vadisi\'nde bir iç kesim ilçesidir.', 'Şalpazarı\'ndan katılım sahildeki Beşikdüzü biniş noktası üzerinden planlanır.'],
            ['Tonya', 'Tonya\'dan tur kaydı.', 'Tonya, Vakfıkebir\'in güneyinde, tereyağı ve yaylalarıyla bilinen bir dağ ilçesidir.', 'Tonya\'dan katılım sahildeki Vakfıkebir biniş noktası üzerinden planlanır.'],
            ['Vakfıkebir', 'Vakfıkebir\'den günübirlik turlara katılım.', 'Vakfıkebir, Trabzon\'un batısında, taş fırın ekmeğiyle bilinen bir sahil ilçesidir.', 'Vakfıkebir\'den katılan misafirlerimiz sahil yolu üzerinden, Akçaabat ve Ortahisar\'dan önce alınır.'],
            ['Yomra', 'Havalimanına komşu Yomra\'dan turlara katılım.', 'Yomra, Trabzon Havalimanı\'nın hemen doğusunda, otellerin ve alışveriş merkezlerinin yoğunlaştığı bir sahil ilçesidir.', 'Doğu yönündeki Uzungöl, Ayder ve Batum turlarımızda Yomra\'daki otellerden misafir alıyoruz.'],
        ],
    ],
];
