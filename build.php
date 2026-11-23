<?php
// WD Telefon Doğrulama – _data XML üretici
$base = __DIR__ . '/upload/src/addons/WebDanismani/PhoneVerify/_data';
@mkdir($base, 0777, true);
$addon = json_decode(file_get_contents(__DIR__ . '/upload/src/addons/WebDanismani/PhoneVerify/addon.json'), true);
$vid = $addon['version_id'];
$vs = $addon['version_string'];

function xmlDoc($root) { $d = new DOMDocument('1.0', 'utf-8'); $d->formatOutput = true; $r = $d->createElement($root); $d->appendChild($r); return [$d, $r]; }
function cdata(DOMDocument $d, DOMElement $el, $text) { $el->appendChild($d->createCDATASection($text)); }
function save(DOMDocument $d, $file) { global $base; $d->save("$base/$file"); }
function attrs(DOMElement $el, array $a) { foreach ($a as $k => $v) $el->setAttribute($k, (string) $v); }

$phrases = [];

// ---------------- OPTION GROUP ----------------
[$d, $r] = xmlDoc('option_groups');
$g = $d->createElement('group');
attrs($g, ['group_id' => 'wdPhoneVerify', 'icon' => 'fa-mobile-alt', 'display_order' => '9110', 'advanced' => '0', 'debug_only' => '0']);
$r->appendChild($g);
save($d, 'option_groups.xml');
$phrases['option_group.wdPhoneVerify'] = 'WD Telefon Doğrulama';
$phrases['option_group_description.wdPhoneVerify'] = 'Kayıt sırasında NetGSM ile SMS doğrulama kodu gönderme ayarları.';

// ---------------- OPTIONS ----------------
$options = [
 // id, format, dataType, default, params, order, title, explain
 ['wdpvEnabled', 'onoff', 'boolean', '0', '', 10, 'Telefon doğrulamayı etkinleştir',
  'Açıkken kayıt formunda cep telefonu ve doğrulama kodu alanları çıkar; numarasını SMS ile doğrulamayan kayıt olamaz. Önce NetGSM bilgilerini girip Kullanıcılar › Telefon doğrulama sayfasından test SMS\'i gönderin.'],
 ['wdpvUsercode', 'textbox', 'string', '', '', 20, 'NetGSM abone numarası',
  'NetGSM hesabınızın abone numarası (ör. 850XXXXXXX).'],
 ['wdpvPassword', 'textbox', 'string', '', 'type=password', 30, 'NetGSM API şifresi',
  'API alt kullanıcısının şifresi (önerilen). NetGSM panelinde Abonelik İşlemleri › API İşlemleri bölümünden API kullanıcısı oluşturun ve sunucunuzun IP adresine API erişim izni verin.'],
 ['wdpvHeader', 'textbox', 'string', '', '', 40, 'Gönderici adı (SMS başlığı)',
  'NetGSM hesabınıza kayıtlı gönderici adı, ör. OBLIFEX. Büyük/küçük harf fark etmez.'],
 ['wdpvMessage', 'textbox', 'string', '{code} {site} dogrulama kodunuzdur. Bu kodu kimseyle paylasmayiniz.', 'rows=3', 50, 'SMS metni',
  'Kullanılabilir: {code} (doğrulama kodu, zorunlu), {site} (forum adı), {minutes} (kodun geçerlilik süresi). Türkçe karakterler NetGSM tarafından Latin karşılığına çevrilir. Metin gönderici adıyla en fazla 155 karakter olabilir.'],
 ['wdpvCodeLength', 'spinbox', 'positive_integer', '6', "min=4\nmax=8", 60, 'Kod uzunluğu', 'Doğrulama kodunun hane sayısı.'],
 ['wdpvCodeTtl', 'spinbox', 'positive_integer', '5', "min=1\nmax=30", 70, 'Kod geçerlilik süresi (dakika)', ''],
 ['wdpvMaxAttempts', 'spinbox', 'positive_integer', '5', "min=3\nmax=10", 80, 'Yanlış kod deneme hakkı', 'Bu kadar hatalı denemeden sonra yeni kod istenmesi gerekir.'],
 ['wdpvResendDelay', 'spinbox', 'positive_integer', '60', "min=30\nmax=600", 90, 'Yeniden gönderme bekleme süresi (saniye)', 'Aynı ziyaretçi bu süre dolmadan yeni kod isteyemez.'],
 ['wdpvLimitPhone', 'spinbox', 'unsigned_integer', '3', "min=0\nmax=20", 100, 'Numara başına saatlik SMS sınırı', 'Bir numaraya bir saatte en fazla bu kadar kod gönderilir. 0: sınırsız.'],
 ['wdpvLimitIp', 'spinbox', 'unsigned_integer', '5', "min=0\nmax=50", 110, 'IP başına saatlik SMS sınırı', 'Bir IP adresinden bir saatte en fazla bu kadar kod istenebilir. 0: sınırsız.'],
 ['wdpvDailyLimit', 'spinbox', 'unsigned_integer', '300', "min=0", 120, 'Günlük toplam SMS sınırı',
  'Son 24 saatte gönderilebilecek toplam kod sayısı. Bot saldırısında SMS kredinizin tükenmesini önler. 0: sınırsız.'],
 ['wdpvUnique', 'onoff', 'boolean', '1', '', 130, 'Bir numara ile yalnızca bir hesap',
  'Açıkken doğrulanmış bir numara ikinci bir hesapta kullanılamaz.'],
 ['wdpvConnected', 'onoff', 'boolean', '1', '', 140, 'Sosyal hesapla kayıtta da iste',
  'Google, Facebook gibi bağlı hesaplarla kayıt olanlardan da telefon doğrulaması istenir.'],
 ['wdpvSandbox', 'onoff', 'boolean', '0', '', 150, 'Sandbox (test) modu',
  'İstekler NetGSM test ortamına (ext-sandbox.netgsm.com.tr) gider, gerçek SMS gönderilmez ve ücretsizdir. Yalnızca NetGSM demo hesaplarıyla çalışır.'],
 ['wdpvEnterprise', 'onoff', 'boolean', '0', '', 160, 'Kurumsal OTP hesabı',
  'Yalnızca NetGSM hesabınızda kurumsal OTP hizmeti tanımlıysa açın. Hesabınıza uymazsa gönderim FORBIDDEN hatası alır.'],
];

[$d, $r] = xmlDoc('options');
foreach ($options as $o) {
	[$id, $fmt, $type, $def, $params, $order, $title, $explain] = $o;
	$el = $d->createElement('option');
	attrs($el, ['option_id' => $id, 'edit_format' => $fmt, 'data_type' => $type, 'advanced' => '0']);
	$dv = $d->createElement('default_value'); $dv->appendChild($d->createTextNode($def)); $el->appendChild($dv);
	if ($params !== '') { $p = $d->createElement('edit_format_params'); $p->appendChild($d->createTextNode($params)); $el->appendChild($p); }
	$rel = $d->createElement('relation'); attrs($rel, ['group_id' => 'wdPhoneVerify', 'display_order' => $order]); $el->appendChild($rel);
	$r->appendChild($el);
	$phrases["option.$id"] = $title;
	$phrases["option_explain.$id"] = $explain;
}
save($d, 'options.xml');

// ---------------- CLASS EXTENSIONS ----------------
[$d, $r] = xmlDoc('class_extensions');
$el = $d->createElement('extension');
attrs($el, ['from_class' => 'XF\Pub\Controller\Register', 'to_class' => 'WebDanismani\PhoneVerify\XF\Pub\Controller\Register', 'execute_order' => '10', 'active' => '1']);
$r->appendChild($el);
save($d, 'class_extensions.xml');

// ---------------- LISTENERS ----------------
[$d, $r] = xmlDoc('code_event_listeners');
foreach ([
	['entity_post_save', 'userPostSave', 'Yeni üyeye doğrulanmış telefon numarasını bağlar'],
	['entity_post_delete', 'userPostDelete', 'Silinen üyenin telefon numarasını serbest bırakır'],
] as $l) {
	$el = $d->createElement('listener');
	attrs($el, ['event_id' => $l[0], 'execute_order' => '10', 'callback_class' => 'WebDanismani\PhoneVerify\Listener', 'callback_method' => $l[1], 'active' => '1', 'hint' => 'XF\Entity\User', 'description' => $l[2]]);
	$r->appendChild($el);
}
save($d, 'code_event_listeners.xml');

// ---------------- ROUTES ----------------
[$d, $r] = xmlDoc('routes');
$el = $d->createElement('route');
attrs($el, ['route_type' => 'admin', 'route_prefix' => 'wd-phone-verify', 'controller' => 'WebDanismani\PhoneVerify:PhoneVerify', 'context' => 'wdPhoneVerify']);
$r->appendChild($el);
save($d, 'routes.xml');

// ---------------- ADMIN NAV ----------------
[$d, $r] = xmlDoc('admin_navigation');
$el = $d->createElement('navigation');
attrs($el, ['navigation_id' => 'wdPhoneVerify', 'parent_navigation_id' => 'users', 'display_order' => '960', 'link' => 'wd-phone-verify', 'icon' => '', 'admin_permission_id' => 'user', 'debug_only' => '0', 'development_only' => '0', 'hide_no_children' => '0']);
$r->appendChild($el);
save($d, 'admin_navigation.xml');
$phrases['admin_navigation.wdPhoneVerify'] = 'Telefon doğrulama';

// ---------------- TEMPLATES ----------------
[$d, $r] = xmlDoc('templates');
foreach (glob(__DIR__ . '/tpl/*') as $f) {
	[$type, $title] = explode('__', basename($f), 2);
	$title = preg_replace('/\.html$/', '', $title);
	$el = $d->createElement('template');
	attrs($el, ['type' => $type, 'title' => $title, 'version_id' => $vid, 'version_string' => $vs]);
	cdata($d, $el, file_get_contents($f));
	$r->appendChild($el);
}
save($d, 'templates.xml');

// ---------------- TEMPLATE MODS ----------------
// Her form için iki değişiklik: (1) alanları "Kullanım koşulları" satırının önüne ekler
// (XenForo 2.2 ve 2.3 makro yazımlarının ikisini de tanır); (2) birincisi tutmazsa
// alanları formun başına ekler. Böylece telefon alanı hiçbir durumda eksik kalmaz.
$macro = '<xf:macro template="wdpv_register_macros" name="fields" />';
$mods = [];
foreach (['register_form' => 'Kayıt formu', 'register_connected_account' => 'Sosyal hesapla kayıt formu'] as $tpl => $label) {
	$mods[] = [$tpl, "wdpv_{$tpl}_fields", "$label: telefon doğrulama alanları", 10,
		'#^([\s\S]*?)(\s*<xf:macro\s+(?:template="register_macros"\s+name="tos_row"|id="register_macros::tos_row")[^>]*/>)#',
		"\$1\n$macro\$2"];
	$mods[] = [$tpl, "wdpv_{$tpl}_fallback", "$label: telefon doğrulama alanları (yedek konum)", 20,
		'#^(?![\s\S]*wdpv_register_macros)([\s\S]*?<div class="block-body">)#',
		"\$1\n$macro"];
}
[$d, $r] = xmlDoc('template_modifications');
foreach ($mods as $m) {
	$el = $d->createElement('modification');
	attrs($el, ['type' => 'public', 'template' => $m[0], 'modification_key' => $m[1], 'description' => $m[2], 'execution_order' => $m[3], 'enabled' => '1', 'action' => 'preg_replace']);
	$find = $d->createElement('find'); cdata($d, $find, $m[4]); $el->appendChild($find);
	$rep = $d->createElement('replace'); cdata($d, $rep, $m[5]); $el->appendChild($rep);
	$r->appendChild($el);
}
save($d, 'template_modifications.xml');
file_put_contents(__DIR__ . '/mods.json', json_encode($mods, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ---------------- PHRASES ----------------
$phrases += [
 'wdpv_phone_number' => 'Cep telefonu',
 'wdpv_phone_explain' => 'Numaranıza SMS ile bir doğrulama kodu göndereceğiz. Numaranız herkese gösterilmez.',
 'wdpv_code' => 'Doğrulama kodu',
 'wdpv_send_code' => 'Kod gönder',
 'wdpv_send_again' => 'Tekrar gönder',
 'wdpv_send_wait' => 'Tekrar gönder ({s})',
 'wdpv_sending' => 'Gönderiliyor...',
 'wdpv_code_sent' => 'Doğrulama kodu {phone} numarasına gönderildi. Kodu aşağıdaki kutuya yazın.',
 'wdpv_error_invalid_phone' => 'Geçerli bir cep telefonu numarası girin, ör. 0532 123 45 67.',
 'wdpv_error_phone_taken' => 'Bu telefon numarası başka bir hesapta kullanılıyor.',
 'wdpv_error_wait' => 'Yeni kod istemek için {seconds} saniye bekleyin.',
 'wdpv_error_limit' => 'Çok fazla kod istendi. Lütfen bir süre sonra tekrar deneyin.',
 'wdpv_error_unavailable' => 'SMS doğrulaması şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.',
 'wdpv_error_send_failed' => 'Doğrulama kodu gönderilemedi. Lütfen biraz sonra tekrar deneyin.',
 'wdpv_error_logged_in' => 'Zaten giriş yapmışsınız.',
 'wdpv_error_no_code' => 'Önce telefon numaranıza doğrulama kodu gönderin.',
 'wdpv_error_phone_changed' => 'Telefon numarası değişti. Yeni numaraya kod gönderin.',
 'wdpv_error_expired' => 'Doğrulama kodunun süresi doldu. Yeni kod isteyin.',
 'wdpv_error_too_many_attempts' => 'Çok fazla hatalı deneme yapıldı. Yeni kod isteyin.',
 'wdpv_error_enter_code' => 'Telefonunuza gelen doğrulama kodunu girin.',
 'wdpv_error_wrong_code' => 'Doğrulama kodu hatalı. Kalan deneme hakkı: {left}.',
 'wdpv_status_sent' => 'Gönderildi',
 'wdpv_status_failed' => 'Başarısız',
 'wdpv_status_blocked' => 'Sınıra takıldı',
 'wdpv_status_test' => 'Test',
];
[$d, $r] = xmlDoc('phrases');
ksort($phrases);
foreach ($phrases as $t => $text) {
	$el = $d->createElement('phrase');
	attrs($el, ['title' => $t, 'version_id' => $vid, 'version_string' => $vs]);
	cdata($d, $el, $text);
	$r->appendChild($el);
}
save($d, 'phrases.xml');

echo "OK: " . count($options) . " seçenek, " . count($phrases) . " ifade, " . count($mods) . " şablon değişikliği\n";
