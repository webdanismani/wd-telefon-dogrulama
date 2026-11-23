<?php

namespace WebDanismani\PhoneVerify;

use WebDanismani\PhoneVerify\NetGsm\Client;

/**
 * Telefon doğrulama mantığı: numara biçimi, kod gönderme/doğrulama, sınırlar ve kayıtlar.
 * Durum oturumda tutulur; doğrulanmış numaralar xf_wd_phone tablosuna yazılır.
 */
class Phone
{
	const STATE_KEY = 'wdpvState';
	const VERIFIED_KEY = 'wdpvVerified';
	const VERIFIED_TTL = 1800;

	/** @var Client|null testlerde değiştirilebilir */
	public static $client = null;

	/**
	 * Türkiye cep numarasını +905XXXXXXXXX biçimine çevirir; geçersizse null.
	 * Kabul edilenler: 05321234567, 5321234567, +90 532 123 45 67, 0090..., (0532) 123-45-67
	 */
	public static function normalize($raw)
	{
		$digits = preg_replace('/\D+/', '', (string) $raw);

		if (strlen($digits) === 14 && strpos($digits, '0090') === 0)
		{
			$digits = substr($digits, 4);
		}
		elseif (strlen($digits) === 12 && strpos($digits, '90') === 0)
		{
			$digits = substr($digits, 2);
		}
		elseif (strlen($digits) === 11 && $digits[0] === '0')
		{
			$digits = substr($digits, 1);
		}

		if (!preg_match('/^5\d{9}$/', $digits))
		{
			return null;
		}

		return '+90' . $digits;
	}

	/** +905321234567 → 0532 *** ** 67 */
	public static function mask($phone)
	{
		$p = (string) $phone;
		if (!preg_match('/^\+90(5\d{2})\d{5}(\d{2})$/', $p, $m))
		{
			return $p;
		}
		return '0' . $m[1] . ' *** ** ' . $m[2];
	}

	/** +905321234567 → 0532 123 45 67 */
	public static function format($phone)
	{
		$p = (string) $phone;
		if (!preg_match('/^\+90(5\d{2})(\d{3})(\d{2})(\d{2})$/', $p, $m))
		{
			return $p;
		}
		return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
	}

	public static function isEnabled()
	{
		return (bool) \XF::options()->wdpvEnabled;
	}

	public static function getClient()
	{
		return self::$client ?: Client::fromOptions();
	}

	public static function generateCode($length)
	{
		$length = max(4, min(8, (int) $length));
		return str_pad((string) random_int(0, (int) pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
	}

	public static function hashCode($phone, $code)
	{
		$salt = (string) \XF::config('globalSalt');
		return hash_hmac('sha256', $phone . '|' . $code, $salt !== '' ? $salt : 'wdpv');
	}

	public static function buildMessage($code)
	{
		$template = trim((string) \XF::options()->wdpvMessage);
		if ($template === '' || strpos($template, '{code}') === false)
		{
			$template = '{code} {site} dogrulama kodunuzdur. Bu kodu kimseyle paylasmayiniz.';
		}

		return strtr($template, [
			'{code}' => $code,
			'{site}' => (string) \XF::options()->boardTitle,
			'{minutes}' => (string) max(1, (int) \XF::options()->wdpvCodeTtl),
		]);
	}

	public static function isTaken($phone)
	{
		if (!\XF::options()->wdpvUnique)
		{
			return false;
		}
		return (bool) \XF::db()->fetchOne('SELECT user_id FROM xf_wd_phone WHERE phone = ?', $phone);
	}

	/* ------------------------------------------------------------------
	 * Kod gönderme
	 * ------------------------------------------------------------------ */

	/**
	 * @param \XF\Session\Session $session
	 * @param string $rawPhone
	 * @param string $ip
	 *
	 * @return array ok (bool), message (string), wait (int, yeniden gönderme için kalan sn)
	 */
	public static function sendCode($session, $rawPhone, $ip)
	{
		$o = \XF::options();
		$now = \XF::$time;

		$phone = self::normalize($rawPhone);
		if (!$phone)
		{
			return self::reply(false, \XF::phrase('wdpv_error_invalid_phone'));
		}

		if (self::isTaken($phone))
		{
			return self::reply(false, \XF::phrase('wdpv_error_phone_taken'));
		}

		// Aynı oturumdan çok sık istek.
		$state = $session->get(self::STATE_KEY);
		$delay = max(30, (int) $o->wdpvResendDelay);
		if (is_array($state) && isset($state['sent']) && $now - $state['sent'] < $delay)
		{
			$wait = $delay - ($now - $state['sent']);
			return self::reply(false, \XF::phrase('wdpv_error_wait', ['seconds' => $wait]), $wait);
		}

		$db = \XF::db();
		$binIp = self::ipToBinary($ip);

		$phoneCount = (int) $db->fetchOne(
			"SELECT COUNT(*) FROM xf_wd_phone_log WHERE phone = ? AND status = 'sent' AND log_date > ?",
			[$phone, $now - 3600]
		);
		if ($o->wdpvLimitPhone && $phoneCount >= (int) $o->wdpvLimitPhone)
		{
			self::log($phone, $binIp, 'blocked', 'LIMIT_PHONE');
			return self::reply(false, \XF::phrase('wdpv_error_limit'));
		}

		$ipCount = (int) $db->fetchOne(
			"SELECT COUNT(*) FROM xf_wd_phone_log WHERE ip = ? AND status = 'sent' AND log_date > ?",
			[$binIp, $now - 3600]
		);
		if ($o->wdpvLimitIp && $ipCount >= (int) $o->wdpvLimitIp)
		{
			self::log($phone, $binIp, 'blocked', 'LIMIT_IP');
			return self::reply(false, \XF::phrase('wdpv_error_limit'));
		}

		if ($o->wdpvDailyLimit)
		{
			$dayCount = (int) $db->fetchOne(
				"SELECT COUNT(*) FROM xf_wd_phone_log WHERE status = 'sent' AND log_date > ?",
				$now - 86400
			);
			if ($dayCount >= (int) $o->wdpvDailyLimit)
			{
				self::log($phone, $binIp, 'blocked', 'LIMIT_DAILY');
				return self::reply(false, \XF::phrase('wdpv_error_unavailable'));
			}
		}

		$code = self::generateCode($o->wdpvCodeLength);
		$result = self::getClient()->sendOtp($phone, self::buildMessage($code));

		if (!$result['ok'] && !$result['mayHaveBeenSent'])
		{
			self::log($phone, $binIp, 'failed', $result['errorCode'], '', $result['detail'] . ($result['requestId'] ? ' [requestId ' . $result['requestId'] . ']' : ''));
			\XF::logError('WD Telefon Doğrulama: NetGSM ' . $result['httpStatus'] . ' ' . $result['errorCode'] . ' ' . $result['detail']);
			return self::reply(false, \XF::phrase('wdpv_error_send_failed'));
		}

		// Gönderildi ya da gitmiş olabilir (bağlantı yarıda kesildi). İkinci durumda kodu yine de
		// saklarız; yeni kod istemek için kullanıcı bekleme süresini bekler, iki farklı kod gitmez.
		$session->set(self::STATE_KEY, [
			'phone' => $phone,
			'hash' => self::hashCode($phone, $code),
			'sent' => $now,
			'expires' => $now + max(1, (int) $o->wdpvCodeTtl) * 60,
			'attempts' => 0,
		]);
		$session->remove(self::VERIFIED_KEY);

		self::log($phone, $binIp, 'sent', $result['ok'] ? '' : $result['errorCode'], $result['jobId'], $result['ok'] ? '' : $result['detail']);

		return self::reply(true, \XF::phrase('wdpv_code_sent', ['phone' => self::mask($phone)]), $delay);
	}

	/* ------------------------------------------------------------------
	 * Doğrulama (kayıt gönderilirken)
	 * ------------------------------------------------------------------ */

	/**
	 * @return \XF\Phrase|null hata ya da null (doğrulandı)
	 */
	public static function verify($session, $rawPhone, $rawCode)
	{
		$o = \XF::options();
		$now = \XF::$time;

		$phone = self::normalize($rawPhone);
		if (!$phone)
		{
			return \XF::phrase('wdpv_error_invalid_phone');
		}

		if (self::isTaken($phone))
		{
			return \XF::phrase('wdpv_error_phone_taken');
		}

		// Aynı numara bu oturumda zaten doğrulandıysa (ör. kullanıcı adı alınmış diye form
		// tekrar gönderildi) kod yeniden istenmez.
		$verified = $session->get(self::VERIFIED_KEY);
		if (is_array($verified) && isset($verified['phone'], $verified['until'])
			&& $verified['phone'] === $phone && $verified['until'] > $now
		)
		{
			return null;
		}

		$state = $session->get(self::STATE_KEY);
		if (!is_array($state) || !isset($state['phone'], $state['hash']))
		{
			return \XF::phrase('wdpv_error_no_code');
		}
		if ($state['phone'] !== $phone)
		{
			return \XF::phrase('wdpv_error_phone_changed');
		}
		if ($state['expires'] < $now)
		{
			return \XF::phrase('wdpv_error_expired');
		}

		$max = max(3, (int) $o->wdpvMaxAttempts);
		if ($state['attempts'] >= $max)
		{
			return \XF::phrase('wdpv_error_too_many_attempts');
		}

		$code = preg_replace('/\D+/', '', (string) $rawCode);
		if ($code === '')
		{
			return \XF::phrase('wdpv_error_enter_code');
		}

		if (!hash_equals($state['hash'], self::hashCode($phone, $code)))
		{
			$state['attempts']++;
			$session->set(self::STATE_KEY, $state);
			$left = $max - $state['attempts'];
			return $left > 0
				? \XF::phrase('wdpv_error_wrong_code', ['left' => $left])
				: \XF::phrase('wdpv_error_too_many_attempts');
		}

		$session->remove(self::STATE_KEY);
		$session->set(self::VERIFIED_KEY, ['phone' => $phone, 'until' => $now + self::VERIFIED_TTL]);

		return null;
	}

	/**
	 * Kayıt tamamlanınca (yeni üye kaydedildiğinde) doğrulanmış numarayı üyeye bağlar.
	 */
	public static function attachToUser($session, $userId)
	{
		$verified = $session->get(self::VERIFIED_KEY);
		if (!is_array($verified) || empty($verified['phone']) || $verified['until'] < \XF::$time)
		{
			return false;
		}

		$db = \XF::db();
		$db->query(
			'INSERT IGNORE INTO xf_wd_phone (user_id, phone, verified_date) VALUES (?, ?, ?)',
			[(int) $userId, $verified['phone'], \XF::$time]
		);
		$db->query(
			"UPDATE xf_wd_phone_log SET user_id = ? WHERE phone = ? AND user_id = 0 AND status = 'sent' AND log_date > ?",
			[(int) $userId, $verified['phone'], \XF::$time - 86400]
		);

		$session->remove(self::VERIFIED_KEY);
		$session->remove(self::STATE_KEY);

		return true;
	}

	/* ------------------------------------------------------------------
	 * Yardımcılar
	 * ------------------------------------------------------------------ */

	public static function log($phone, $binIp, $status, $errorCode = '', $jobId = '', $detail = '')
	{
		$db = \XF::db();
		$db->query(
			'INSERT INTO xf_wd_phone_log (phone, user_id, ip, log_date, status, error_code, job_id, detail) VALUES (?, 0, ?, ?, ?, ?, ?, ?)',
			[
				(string) $phone,
				(string) $binIp,
				\XF::$time,
				(string) $status,
				substr((string) $errorCode, 0, 50),
				substr((string) $jobId, 0, 100),
				substr((string) $detail, 0, 255),
			]
		);

		// Ara sıra 90 günden eski kayıtları temizle.
		if (random_int(1, 100) === 1)
		{
			$db->query('DELETE FROM xf_wd_phone_log WHERE log_date < ?', \XF::$time - 90 * 86400);
		}
	}

	public static function ipToBinary($ip)
	{
		$bin = @inet_pton((string) $ip);
		return $bin === false ? '' : $bin;
	}

	public static function ipToString($bin)
	{
		if ($bin === '' || $bin === null)
		{
			return '';
		}
		$str = @inet_ntop($bin);
		return $str === false ? '' : $str;
	}

	protected static function reply($ok, $message, $wait = 0)
	{
		return [
			'ok' => (bool) $ok,
			'message' => (string) $message,
			'wait' => (int) $wait,
		];
	}
}
