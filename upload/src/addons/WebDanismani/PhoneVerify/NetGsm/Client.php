<?php

namespace WebDanismani\PhoneVerify\NetGsm;

/**
 * NetGSM SMS API v3 ile OTP gönderimi.
 *
 * Akış: POST /auth/v3/token ile erişim token'ı alınır (24 saat geçerli, XenForo veri kaydında
 * saklanır), ardından POST /sms/v3/send/otp ile kod gönderilir.
 *
 * @see https://api-docs.netgsm.com.tr/sms/sending/send-otp/
 */
class Client
{
	const BASE_URL = 'https://ext-api.netgsm.com.tr';
	const SANDBOX_URL = 'https://ext-sandbox.netgsm.com.tr';
	const REGISTRY_KEY = 'wdpvNetGsmToken';

	/** 503 durumunda tekrar denemeden önce beklenecek süreler (sn). 503'te hiçbir şey gönderilmemiştir. */
	protected $retryDelays = [1, 2];

	protected $username;
	protected $password;
	protected $sender;
	protected $baseUrl;
	protected $enterprise;
	protected $timeout = 20;

	public function __construct($username, $password, $sender, $baseUrl = self::BASE_URL, $enterprise = false)
	{
		$this->username = trim((string) $username);
		$this->password = (string) $password;
		$this->sender = trim((string) $sender);
		$this->baseUrl = rtrim((string) $baseUrl, '/');
		$this->enterprise = (bool) $enterprise;
	}

	public static function fromOptions()
	{
		$o = \XF::options();

		return new static(
			$o->wdpvUsercode,
			$o->wdpvPassword,
			$o->wdpvHeader,
			$o->wdpvSandbox ? self::SANDBOX_URL : self::BASE_URL,
			$o->wdpvEnterprise
		);
	}

	public function isConfigured()
	{
		return $this->username !== '' && $this->password !== '' && $this->sender !== '';
	}

	/**
	 * Tek alıcıya OTP gönderir.
	 *
	 * @param string $recipient E.164 biçiminde Türkiye cep numarası: +905XXXXXXXXX
	 * @param string $text
	 *
	 * @return array ok, jobId, errorCode, detail, httpStatus, requestId, mayHaveBeenSent
	 */
	public function sendOtp($recipient, $text)
	{
		if (!$this->isConfigured())
		{
			return $this->failure(0, 'NOT_CONFIGURED', 'NetGSM bilgileri eksik', false);
		}

		$body = [
			'sender' => $this->sender,
			'text' => $text,
			'recipient' => $recipient,
			'appName' => 'WD Telefon Dogrulama',
		];
		$headers = $this->enterprise ? ['x-client' => 'enterprise'] : [];

		$relogged = false;
		$retries = 0;

		while (true)
		{
			$token = $this->getToken();
			if (is_array($token))
			{
				// Token alınamadı: hata dizisi döndü.
				return $token;
			}

			$res = $this->post('/sms/v3/send/otp', $body, $token, $headers);
			if ($res['network'])
			{
				return $this->failure(0, 'NETWORK_ERROR', $res['detail'], $res['maySent']);
			}

			$status = $res['status'];
			$json = $res['json'];

			if ($status === 202 || $status === 200)
			{
				$jobId = isset($json['jobId']) ? (string) $json['jobId'] : '';
				if ($jobId !== '')
				{
					return [
						'ok' => true,
						'jobId' => $jobId,
						'errorCode' => '',
						'detail' => '',
						'httpStatus' => $status,
						'requestId' => '',
						'mayHaveBeenSent' => true,
					];
				}
				return $this->failure($status, 'INVALID_RESPONSE', 'Yanıtta jobId yok', true);
			}

			if ($status === 401 && !$relogged)
			{
				$relogged = true;
				$this->forgetToken($token);
				continue;
			}

			if ($status === 503 && $retries < count($this->retryDelays))
			{
				$this->pause($this->retryDelays[$retries++]);
				continue;
			}

			// 500 ve beklenmeyen 5xx: OTP gitmiş olabilir; otomatik tekrar yapılmaz.
			$maySent = $status >= 500 && $status !== 503;
			return $this->failureFromJson($status, $json, $maySent);
		}
	}

	/**
	 * @return string|array token ya da hata dizisi
	 */
	protected function getToken()
	{
		$key = $this->cacheKey();
		$stored = \XF::registry()->get(self::REGISTRY_KEY);

		if (is_array($stored)
			&& isset($stored['key'], $stored['token'], $stored['renew_at'])
			&& $stored['key'] === $key
			&& $stored['renew_at'] > time()
		)
		{
			return $stored['token'];
		}

		$res = $this->post('/auth/v3/token', [
			'username' => $this->username,
			'password' => $this->password,
			'scope' => 'sms',
		]);

		if ($res['network'])
		{
			return $this->failure(0, 'NETWORK_ERROR', $res['detail'], false);
		}

		$json = $res['json'];
		if ($res['status'] !== 200 || empty($json['access_token']) || !isset($json['expires_in']))
		{
			return $this->failureFromJson($res['status'], $json, false);
		}

		// Süre dolmadan, ömrün son %10'unda (en fazla 5 dk) yeniden giriş yapılır.
		$life = max(60, (int) $json['expires_in']);
		$ttl = max(1, $life - min(300, intdiv($life, 10)));

		\XF::registry()->set(self::REGISTRY_KEY, [
			'key' => $key,
			'token' => (string) $json['access_token'],
			'renew_at' => time() + $ttl,
		]);

		return (string) $json['access_token'];
	}

	protected function forgetToken($staleToken)
	{
		$stored = \XF::registry()->get(self::REGISTRY_KEY);
		if (is_array($stored) && isset($stored['token']) && $stored['token'] === $staleToken)
		{
			\XF::registry()->delete(self::REGISTRY_KEY);
		}
	}

	/** Şifre anahtara katılır: şifre değişirse eski token kullanılmaz. */
	protected function cacheKey()
	{
		return hash_hmac('sha256', $this->baseUrl . '|' . $this->username, $this->password);
	}

	/**
	 * @return array status, json, network, detail, maySent
	 */
	protected function post($path, array $body, $token = null, array $headers = [])
	{
		$headers['Content-Type'] = 'application/json';
		$headers['Accept'] = 'application/json';
		if ($token !== null)
		{
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$isSend = ($path === '/sms/v3/send/otp');

		try
		{
			$client = \XF::app()->http()->client();
			$response = $client->post($this->baseUrl . $path, [
				'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
				'headers' => $headers,
				'http_errors' => false,
				'timeout' => $this->timeout,
				'connect_timeout' => 10,
			]);
		}
		catch (\GuzzleHttp\Exception\ConnectException $e)
		{
			// Bağlantı kurulamadı: istek sunucuya ulaşmadı.
			return ['status' => 0, 'json' => [], 'network' => true, 'detail' => $e->getMessage(), 'maySent' => false];
		}
		catch (\Exception $e)
		{
			// Bağlantı kurulduktan sonra kesildi: OTP gitmiş olabilir.
			return ['status' => 0, 'json' => [], 'network' => true, 'detail' => $e->getMessage(), 'maySent' => $isSend];
		}

		$json = json_decode((string) $response->getBody(), true);

		return [
			'status' => (int) $response->getStatusCode(),
			'json' => is_array($json) ? $json : [],
			'network' => false,
			'detail' => '',
			'maySent' => false,
		];
	}

	protected function pause($seconds)
	{
		sleep($seconds);
	}

	protected function failure($status, $code, $detail, $maySent, $requestId = '')
	{
		return [
			'ok' => false,
			'jobId' => '',
			'errorCode' => (string) $code,
			'detail' => (string) $detail,
			'httpStatus' => (int) $status,
			'requestId' => (string) $requestId,
			'mayHaveBeenSent' => (bool) $maySent,
		];
	}

	/** SMS API ({code, message, requestId}) ya da kimlik ({code, detail, request_id}) hata gövdesinden. */
	protected function failureFromJson($status, array $json, $maySent)
	{
		$code = isset($json['code']) && is_string($json['code']) ? $json['code'] : 'HTTP_' . $status;
		$detail = '';
		if (isset($json['message']) && is_string($json['message']))
		{
			$detail = $json['message'];
		}
		elseif (isset($json['detail']) && is_string($json['detail']))
		{
			$detail = $json['detail'];
		}
		if (!empty($json['violations']) && is_array($json['violations']))
		{
			$parts = [];
			foreach ($json['violations'] as $v)
			{
				if (is_array($v) && isset($v['field'], $v['message']))
				{
					$parts[] = $v['field'] . ': ' . $v['message'];
				}
			}
			if ($parts)
			{
				$detail = trim($detail . ' (' . implode('; ', $parts) . ')');
			}
		}
		$requestId = '';
		if (isset($json['requestId']) && is_string($json['requestId']))
		{
			$requestId = $json['requestId'];
		}
		elseif (isset($json['request_id']) && is_string($json['request_id']))
		{
			$requestId = $json['request_id'];
		}

		return $this->failure($status, $code, $detail, $maySent, $requestId);
	}
}
