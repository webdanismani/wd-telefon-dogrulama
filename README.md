<div align="center">

# WD Telefon Doğrulama

**XenForo kayıt formuna NetGSM ile SMS doğrulaması ekler.**<br>
**Adds NetGSM SMS phone verification to the XenForo registration form.**

[![XenForo](https://img.shields.io/badge/XenForo-2.2%20%2F%202.3-1d5f8e)](https://xenforo.com)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![NetGSM](https://img.shields.io/badge/NetGSM-SMS%20API%20v3-e30613)](https://www.netgsm.com.tr)
[![License: CC BY-NC-SA 4.0](https://img.shields.io/badge/Lisans-CC%20BY--NC--SA%204.0-116b5b)](LICENSE)
[![Ücretsiz / Free](https://img.shields.io/badge/%C3%9Ccretsiz-sat%C4%B1lamaz%20%2F%20not%20for%20sale-c9372c)](#lisans)

[**Türkçe**](#türkçe) · [**English**](#english)

Geliştirici / Developer: **[Web Danışmanı](https://webdanismani.com)**<br>
Destek / Support: **[oblifex.com](https://oblifex.com)** webmaster destek forumu / webmaster support forum

</div>

---

<a id="türkçe"></a>

## Türkçe

### Nedir?

WD Telefon Doğrulama, XenForo forumunuzun kayıt formuna cep telefonu doğrulaması ekleyen ücretsiz bir eklentidir. Ziyaretçi numarasını yazıp **Kod gönder** düğmesine basar, NetGSM üzerinden gelen SMS kodunu girer ve ancak bundan sonra kayıt olabilir.

Sahte ve toplu hesap açılmasını, spam botlarını ve aynı kişinin birden fazla hesap açmasını büyük ölçüde engeller. NetGSM'in güncel **SMS API v3**'ünü (token ile kimlik doğrulama ve OTP gönderimi) kullanır.

### Özellikler

- **Kayıt formuna doğrulama:** "Cep telefonu" ve "Doğrulama kodu" alanları, satır içi uyarılar, geri sayımlı "Tekrar gönder" düğmesi. XenForo'nun açılır kayıt penceresinde de çalışır.
- **Sosyal hesapla kayıt:** Google, Facebook gibi bağlı hesaplarla kayıt olanlardan da doğrulama istenebilir (ayarlanabilir).
- **Bir numara, bir hesap:** Doğrulanmış bir numara ikinci bir hesapta kullanılamaz (ayarlanabilir). Üye silinince numarası serbest kalır.
- **Esnek numara girişi:** `0532 123 45 67`, `532...`, `+90 532...` gibi her yazım kabul edilir. Yalnızca Türkiye cep numaraları.
- **SMS kredisi koruması:** Numara başına ve IP başına saatlik sınır, günlük toplam sınır ve yeniden gönderme bekleme süresi. Bot saldırısında krediniz tükenmez.
- **Güvenli kod:** Kod oturumda yalnızca şifrelenmiş özet olarak tutulur. Süre sınırı ve yanlış deneme hakkı vardır.
- **Doğru hata yönetimi:** NetGSM geçici olarak yanıt vermezse otomatik tekrar dener. Bağlantı yarıda kesilip kodun gidip gitmediği belli değilse ikinci bir kod göndermez.
- **Yönetim paneli:** Test SMS'i gönderme, doğrulanmış numaraları arama ve kaldırma, son gönderimleri NetGSM hata kodu ve `requestId` ile görme.
- **Sandbox modu:** NetGSM test ortamıyla gerçek SMS göndermeden deneme.
- **Uyumluluk:** XenForo 2.2 ve 2.3, PHP 7.2 ve üstü. jQuery gerektirmez.

### Gereksinimler

- XenForo 2.2.0 veya üstü
- PHP 7.2 veya üstü
- OTP SMS hizmeti tanımlı bir [NetGSM](https://www.netgsm.com.tr) hesabı

### NetGSM hazırlığı

1. NetGSM hesabınızda **OTP SMS** paketi veya kredisi olmalı.
2. NetGSM panelinde **Abonelik İşlemleri › API İşlemleri** bölümüne gidin:
   - Bir **API alt kullanıcısı** oluşturun (önerilir) ve şifresini not edin.
   - **API Erişim İzinleri** bölümüne forum sunucunuzun IP adresini ekleyin.
3. Gönderici adınız (SMS başlığı) hesabınızda kayıtlı ve onaylı olmalı.

### Kurulum

1. [Releases](../../releases) sayfasından son sürümün `wd-telefon-dogrulama.zip` dosyasını indirin.
2. **ACP › Eklentiler › Arşivden yükle/güncelle** ile zip'i yükleyin.
3. **ACP › Seçenekler › WD Telefon Doğrulama** sayfasına NetGSM abone numaranızı, API şifrenizi ve gönderici adınızı girin. "Telefon doğrulamayı etkinleştir" şimdilik kapalı kalsın.
4. **ACP › Kullanıcılar › Telefon doğrulama** sayfasından kendi numaranıza **Test SMS'i gönder**in. SMS geldiyse bağlantı tamamdır.
5. Seçeneklerden **Telefon doğrulamayı etkinleştir**i açın ve gizli pencerede kayıt formunu deneyin.

### Ayarlar

| Ayar | Varsayılan |
|---|---|
| Kod uzunluğu | 6 hane |
| Kod geçerlilik süresi | 5 dakika |
| Yanlış kod deneme hakkı | 5 |
| Yeniden gönderme bekleme süresi | 60 saniye |
| Numara başına saatlik SMS | 3 |
| IP başına saatlik SMS | 5 |
| Günlük toplam SMS | 300 |
| Bir numara ile yalnızca bir hesap | Açık |
| Sosyal hesapla kayıtta da iste | Açık |

SMS metni de düzenlenebilir: `{code}` (kod), `{site}` (forum adı) ve `{minutes}` (geçerlilik süresi) kullanılabilir.

### Sık karşılaşılan NetGSM hataları

| Kod | Anlamı | Çözüm |
|---|---|---|
| `invalid_credentials` | Abone numarası veya şifre hatalı | Bilgileri kontrol edin |
| `ip_not_allowed` | Sunucu IP'sinin API izni yok | NetGSM panelinde API erişim iznine IP'nizi ekleyin |
| `REJECTED_HEADER` | Gönderici adı kayıtlı değil | Hesabınızdaki onaylı başlığı yazın |
| `INSUFFICIENT_CREDIT` | OTP paketi yok veya bitmiş | Paket veya kredi tanımlayın |
| `FORBIDDEN` | OTP hizmeti veya API kullanıcısının SMS yetkisi yok | NetGSM'den hizmeti açtırın |

### Sorun giderme

Kayıt formunda telefon alanı görünmüyorsa **ACP › Görünüm › Şablon değişiklikleri** sayfasında `wdpv_` ile başlayan değişikliklere bakın. Eklenti alanları önce "Kullanım koşulları" satırının önüne eklemeyi dener; bu tutmazsa yedek değişiklik alanları formun başına ekler. Bu sayede alanlar hiçbir temada eksik kalmaz.

### Destek

Sorularınız, hata bildirimleriniz ve önerileriniz için **[oblifex.com](https://oblifex.com) webmaster destek forumunu** kullanın.

XenForo eklenti geliştirme ve forum kurulumu hizmeti için: **[webdanismani.com](https://webdanismani.com)**

<a id="lisans"></a>

### Lisans

Bu eklenti **ücretsiz paylaşılır ve satılamaz.** [CC BY-NC-SA 4.0](LICENSE) lisansıyla yayınlanmıştır.

- ✅ Ücretsiz indirebilir, kullanabilir ve paylaşabilirsiniz.
- ✅ Kendi forumlarınızda ve müşterilerinizin forumlarında kullanabilirsiniz.
- ✅ Değiştirebilirsiniz. Değiştirilmiş sürümü paylaşırsanız aynı lisansla ve Web Danışmanı'nı kaynak göstererek paylaşmanız gerekir.
- ❌ Eklentiyi veya değiştirilmiş bir sürümünü **satamazsınız**.
- ❌ Ücretli bir pakete, temaya veya üyelik içeriğine ekleyerek **dağıtamazsınız**.

---

<a id="english"></a>

## English

### What is it?

WD Telefon Doğrulama (*phone verification*) is a free XenForo add-on that adds mobile phone verification to your registration form. Visitors enter their number, press **Send code**, type the SMS code delivered through NetGSM, and only then can they register.

It greatly reduces fake and bulk accounts, spam bots and duplicate accounts. It uses NetGSM's current **SMS API v3** (token authentication and OTP sending).

> NetGSM is a Turkish SMS provider and its OTP service delivers to Turkish mobile numbers only, so this add-on accepts Turkish mobile numbers (+90 5XX). The interface phrases are in Turkish and can be translated in the XenForo phrase editor.

### Features

- **Verification on registration:** "Mobile phone" and "Verification code" fields, inline messages and a "Send again" button with a countdown. Also works in XenForo's registration overlay.
- **Connected account registration:** Optionally requires verification for Google, Facebook and other connected-account sign-ups.
- **One number, one account:** A verified number cannot be used for a second account (configurable). The number is released when the member is deleted.
- **Flexible input:** Accepts `0532 123 45 67`, `532...`, `+90 532...` and similar formats.
- **SMS credit protection:** Hourly limits per number and per IP, a daily total limit and a resend cooldown, so bots cannot drain your credit.
- **Secure codes:** Only a hash of the code is stored in the session. Codes expire and attempts are limited.
- **Careful error handling:** Retries automatically when NetGSM is briefly unavailable. If a connection drops and it is unclear whether a code was delivered, no second code is sent.
- **Admin page:** Send a test SMS, search and remove verified numbers, and review recent sends with NetGSM error codes and `requestId`.
- **Sandbox mode:** Test against NetGSM's sandbox without sending real SMS.
- **Compatibility:** XenForo 2.2 and 2.3, PHP 7.2 or later. No jQuery required.

### Requirements

- XenForo 2.2.0 or later
- PHP 7.2 or later
- A [NetGSM](https://www.netgsm.com.tr) account with the OTP SMS service

### Installation

1. In the NetGSM portal, create an API sub-user under **Subscription › API**, allow your forum server's IP address, and make sure your sender name is approved.
2. Download `wd-telefon-dogrulama.zip` from the latest [release](../../releases).
3. Upload it under **ACP › Add-ons › Install/upgrade from archive**.
4. Enter your NetGSM subscriber number, API password and sender name under **ACP › Options › WD Telefon Doğrulama**. Leave verification disabled for now.
5. Send yourself a test SMS from **ACP › Users › Telefon doğrulama**. If it arrives, the connection works.
6. Enable verification in the options and try the registration form in a private window.

### Support

For questions, bug reports and suggestions, use the **[oblifex.com](https://oblifex.com) webmaster support forum**.

XenForo add-on development and forum setup services: **[webdanismani.com](https://webdanismani.com)**

### License

This add-on is **free and not for sale.** It is released under [CC BY-NC-SA 4.0](LICENSE).

- ✅ You may download, use and share it for free.
- ✅ You may use it on your own forums and your clients' forums.
- ✅ You may modify it. If you share a modified version, it must use the same license and credit Web Danışmanı.
- ❌ You may **not sell** the add-on or a modified version of it.
- ❌ You may **not bundle** it into paid packages, themes or membership content.

---

<div align="center">

Geliştirici / Developer: **[Web Danışmanı](https://webdanismani.com)** · Destek / Support: **[oblifex.com](https://oblifex.com)**

© 2026 Web Danışmanı

</div>
