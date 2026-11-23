<?php

namespace WebDanismani\PhoneVerify\XF\Pub\Controller;

use WebDanismani\PhoneVerify\Phone;
use XF\Mvc\ParameterBag;

class Register extends XFCP_Register
{
	/**
	 * Kayıt formundaki "Kod gönder" düğmesi: register/wd-phone-send
	 */
	public function actionWdPhoneSend()
	{
		$this->assertPostOnly();

		if (!Phone::isEnabled())
		{
			return $this->wdpvReply(false, \XF::phrase('wdpv_error_unavailable'));
		}
		if (\XF::visitor()->user_id)
		{
			return $this->wdpvReply(false, \XF::phrase('wdpv_error_logged_in'));
		}

		$result = Phone::sendCode(
			$this->session(),
			$this->filter('phone', 'str'),
			$this->request()->getIp()
		);

		return $this->wdpvReply($result['ok'], $result['message'], $result['wait']);
	}

	public function actionRegister()
	{
		$error = $this->wdpvCheck();
		if ($error !== null)
		{
			return $this->error($error);
		}

		return parent::actionRegister();
	}

	public function actionConnectedAccountRegister(ParameterBag $params)
	{
		if (\XF::options()->wdpvConnected)
		{
			$error = $this->wdpvCheck();
			if ($error !== null)
			{
				return $this->error($error);
			}
		}

		return parent::actionConnectedAccountRegister($params);
	}

	/**
	 * @return \XF\Phrase|null
	 */
	protected function wdpvCheck()
	{
		if (!Phone::isEnabled() || !$this->isPost())
		{
			return null;
		}

		return Phone::verify(
			$this->session(),
			$this->filter('wdpv_phone', 'str'),
			$this->filter('wdpv_code', 'str')
		);
	}

	protected function wdpvReply($ok, $message, $wait = 0)
	{
		$reply = $this->message($message);
		$reply->setJsonParams([
			'wdpv' => [
				'ok' => (bool) $ok,
				'message' => (string) $message,
				'wait' => (int) $wait,
			],
		]);

		return $reply;
	}
}
