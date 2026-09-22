<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Yasal sayfalar. Metinler ŞABLONDUR: yayına almadan önce firma unvanı, adres ve
 * iptal koşullarını kendi sözleşmenize göre güncelleyin; gerekirse hukuk danışmanınıza
 * gösterin.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->firstOrCreate(
            ['slug' => 'kvkk-aydinlatma-metni'],
            [
                'title' => 'KVKK Aydınlatma Metni',
                'meta_description' => 'Kişisel verilerin korunması hakkında aydınlatma metni: hangi verileri neden işliyoruz, kimlerle paylaşıyoruz ve haklarınız.',
                'show_in_footer' => true,
                'sort_order' => 1,
                'content' => <<<'HTML'
<p>Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında, web sitemiz ve rezervasyon sürecimiz aracılığıyla paylaştığınız kişisel verilerin işlenmesine ilişkin olarak veri sorumlusu sıfatıyla hazırlanmıştır.</p>
<h2>İşlenen Kişisel Veriler</h2>
<p><strong>Rezervasyon talebinde:</strong> ad soyad, telefon numarası, e-posta adresi, katılacağınız il/ilçe, kişi sayısı, mesajınız ile IP adresi ve tarayıcı bilgileri.</p>
<p><strong>Tur kaydında (her yolcu için):</strong> ad, soyad, T.C. kimlik numarası veya pasaport numarası, yaş, cinsiyet ve telefon numarası.</p>
<h2>İşleme Amaçları</h2>
<ul>
<li>Rezervasyon talebinizin değerlendirilmesi ve sizinle iletişime geçilmesi,</li>
<li>Tur kaydınızın oluşturulması, araç ve koltuk planlamasının yapılması,</li>
<li>Zorunlu seyahat sigortasının düzenlenmesi ve yolcu listesinin hazırlanması,</li>
<li>Konaklama ve ulaşım hizmetlerinin sizin adınıza ayırtılması,</li>
<li>Yasal yükümlülüklerin yerine getirilmesi.</li>
</ul>
<h2>Aktarım</h2>
<p>Kişisel verileriniz, yalnızca hizmetin sunulması için gerekli olduğu ölçüde sigorta şirketi, konaklama tesisi ve taşıma hizmeti sağlayıcılarıyla; yasal zorunluluk hâlinde yetkili kamu kurumlarıyla paylaşılır. Bunların dışında üçüncü kişilere aktarılmaz.</p>
<h2>Saklama Süresi</h2>
<p>Verileriniz, tur hizmetinin tamamlanmasından sonra yasal saklama süreleri boyunca muhafaza edilir; sürenin sonunda silinir veya anonim hâle getirilir.</p>
<h2>Google reCAPTCHA</h2>
<p>Sitemizdeki rezervasyon formu, otomatik ve kötü amaçlı gönderimleri engellemek amacıyla Google reCAPTCHA hizmetiyle korunabilir. Bu hizmet çalışırken IP adresiniz ve sayfa üzerindeki etkileşim bilgileriniz Google LLC'ye aktarılır. Ayrıntılar için Google'ın <a href="https://policies.google.com/privacy" rel="nofollow noopener" target="_blank">Gizlilik Politikası</a> ve <a href="https://policies.google.com/terms" rel="nofollow noopener" target="_blank">Hizmet Şartları</a> geçerlidir.</p>
<h2>Haklarınız</h2>
<p>KVKK'nın 11. maddesi kapsamında; kişisel verilerinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme, düzeltilmesini veya silinmesini isteme haklarına sahipsiniz. Taleplerinizi iletişim sayfamızdaki kanallar üzerinden bize iletebilirsiniz.</p>
HTML,
            ]
        );

        Page::query()->firstOrCreate(
            ['slug' => 'tur-sozlesmesi-ve-iptal-kosullari'],
            [
                'title' => 'Tur Sözleşmesi ve İptal Koşulları',
                'meta_description' => 'Tur kaydı, ödeme, iptal ve iade koşulları ile tur programında değişiklik hâlinde haklarınız.',
                'show_in_footer' => true,
                'sort_order' => 2,
                'content' => <<<'HTML'
<p>Bu sayfa, turlarımıza katılım koşullarını genel hatlarıyla açıklar. Her kayıtta size ayrıca yazılı bir paket tur sözleşmesi verilir; bu sayfa ile sözleşme arasında fark olması hâlinde sözleşme geçerlidir.</p>
<h2>Kayıt ve ödeme</h2>
<p>Web sitesindeki rezervasyon formu bir ön taleptir ve koltuk ayırmaz. Kaydınız, bizimle görüşmenizin ve ön ödemenizin ardından kesinleşir. Kalan tutarın ödeme tarihi kayıt sırasında bildirilir.</p>
<h2>Yolcu bilgileri</h2>
<p>Zorunlu seyahat sigortası ve yolcu listesi için her yolcunun ad, soyad, T.C. kimlik numarası (yabancı uyruklular için pasaport numarası), yaş ve cinsiyet bilgisi alınır. Bilgilerin doğruluğu yolcunun sorumluluğundadır.</p>
<h2>İptal ve iade</h2>
<p>İptal taleplerinde iade tutarı, kalkış tarihine kalan süreye göre belirlenir. Geçerli oranlar kayıt sırasında size verilen sözleşmede yazılıdır. Uçak bileti, vize ve benzeri üçüncü taraf hizmetlerinde ilgili sağlayıcının koşulları geçerlidir.</p>
<h2>Turun acente tarafından iptali</h2>
<p>Yeterli katılım sağlanamaması veya mücbir sebepler nedeniyle tur iptal edilirse, ödediğiniz tutarın tamamı iade edilir ya da dilerseniz başka bir tarihe aktarılır.</p>
<h2>Programda değişiklik</h2>
<p>Hava koşulları, yol durumu veya ziyaret edilecek yerlerin kapalı olması gibi nedenlerle programın sırası ve içeriği rehber tarafından değiştirilebilir. Bu durumda eşdeğer bir alternatif sunulur.</p>
<h2>Batum ve Gürcistan turları</h2>
<p>Sınır geçişi için geçerli belge (T.C. vatandaşları için yeni tip çipli kimlik kartı ya da pasaport), yabancı uyruklular için vize ve yurt dışı çıkış harcı yolcunun sorumluluğundadır. Belge eksikliği nedeniyle sınırdan geçemeyen yolcuya ücret iadesi yapılmaz.</p>
<h2>Yayla turları ve hava koşulları</h2>
<p>Yayla yollarının kar, heyelan ya da yoğun yağış nedeniyle kapanması hâlinde program rehber tarafından eşdeğer bir rotayla değiştirilir; bu durum iptal ve iade hakkı doğurmaz. Yüksek yaylalara çıkılan bölümlerde bölgeye uygun yayla araçları kullanılır.</p>
HTML,
            ]
        );
    }
}
