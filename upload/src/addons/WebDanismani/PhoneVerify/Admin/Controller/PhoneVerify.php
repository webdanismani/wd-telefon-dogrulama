<?php

namespace WebDanismani\PhoneVerify\Admin\Controller;

use WebDanismani\PhoneVerify\Phone;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class PhoneVerify extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('user');
	}

	public function actionIndex()
	{
		return $this->renderIndex();
	}

	public function actionTest()
	{
		$this->assertPostOnly();

		$raw = $this->filter('phone', 'str');
		$phone = Phone::normalize($raw);
		$result = null;

		if (!$phone)
		{
			$result = ['ok' => false, 'errorCode' => '', 'detail' => (string) \XF::phrase('wdpv_error_invalid_phone'), 'jobId' => '', 'requestId' => '', 'httpStatus' => 0];
		}
		else
		{
			$text = 'Test: ' . \XF::options()->boardTitle . ' telefon dogrulama calisiyor. Ornek kod: ' . Phone::generateCode(\XF::options()->wdpvCodeLength);
			$result = Phone::getClient()->sendOtp($phone, $text);
			Phone::log(
				$phone,
				Phone::ipToBinary($this->request()->getIp()),
				'test',
				$result['ok'] ? '' : $result['errorCode'],
				$result['jobId'],
				$result['ok'] ? '' : $result['detail']
			);
		}

		return $this->renderIndex($raw, $result);
	}

	public function actionUnlink()
	{
		$userId = $this->filter('user_id', 'uint');
		$row = \XF::db()->fetchRow(
			'SELECT p.*, u.username FROM xf_wd_phone AS p LEFT JOIN xf_user AS u ON (u.user_id = p.user_id) WHERE p.user_id = ?',
			$userId
		);
		if (!$row)
		{
			return $this->error(\XF::phrase('requested_user_not_found'));
		}

		if ($this->isPost())
		{
			\XF::db()->query('DELETE FROM xf_wd_phone WHERE user_id = ?', $userId);
			return $this->redirect($this->buildLink('wd-phone-verify'));
		}

		$row['phone_formatted'] = Phone::format($row['phone']);

		return $this->view('WebDanismani\PhoneVerify:Unlink', 'wdpv_unlink_confirm', ['row' => $row]);
	}

	protected function renderIndex($testPhone = '', $testResult = null)
	{
		$db = \XF::db();
		$now = \XF::$time;
		$perPage = 30;
		$page = max(1, $this->filterPage());
		$q = trim($this->filter('q', 'str'));

		$where = '1 = 1';
		$args = [];
		if ($q !== '')
		{
			$normalized = Phone::normalize($q);
			$digits = preg_replace('/\D+/', '', $q);
			if ($normalized)
			{
				$where = 'p.phone = ?';
				$args[] = $normalized;
			}
			elseif ($digits !== '' && strlen($digits) >= 3)
			{
				$where = '(p.phone LIKE ? OR u.username LIKE ?)';
				$args[] = '%' . $digits . '%';
				$args[] = '%' . $db->escapeLike($q) . '%';
			}
			else
			{
				$where = 'u.username LIKE ?';
				$args[] = '%' . $db->escapeLike($q) . '%';
			}
		}

		$total = (int) $db->fetchOne(
			"SELECT COUNT(*) FROM xf_wd_phone AS p LEFT JOIN xf_user AS u ON (u.user_id = p.user_id) WHERE $where",
			$args
		);
		$records = $db->fetchAll(
			"SELECT p.*, u.username FROM xf_wd_phone AS p LEFT JOIN xf_user AS u ON (u.user_id = p.user_id)
			WHERE $where ORDER BY p.verified_date DESC LIMIT " . (($page - 1) * $perPage) . ', ' . $perPage,
			$args
		);
		foreach ($records as &$r)
		{
			$r['phone_formatted'] = Phone::format($r['phone']);
		}
		unset($r);

		$logs = $db->fetchAll(
			'SELECT l.*, u.username FROM xf_wd_phone_log AS l LEFT JOIN xf_user AS u ON (u.user_id = l.user_id)
			ORDER BY l.log_id DESC LIMIT 50'
		);
		foreach ($logs as &$l)
		{
			$l['ip_string'] = Phone::ipToString($l['ip']);
			$l['phone_formatted'] = Phone::format($l['phone']);
			$l['status_label'] = \XF::phrase('wdpv_status_' . $l['status']);
		}
		unset($l);

		$stats = [
			'verified' => (int) $db->fetchOne('SELECT COUNT(*) FROM xf_wd_phone'),
			'sent24' => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_wd_phone_log WHERE status = 'sent' AND log_date > ?", $now - 86400),
			'failed24' => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_wd_phone_log WHERE status = 'failed' AND log_date > ?", $now - 86400),
			'blocked24' => (int) $db->fetchOne("SELECT COUNT(*) FROM xf_wd_phone_log WHERE status = 'blocked' AND log_date > ?", $now - 86400),
		];

		$o = \XF::options();

		return $this->view('WebDanismani\PhoneVerify:Index', 'wdpv_index', [
			'enabled' => (bool) $o->wdpvEnabled,
			'configured' => Phone::getClient()->isConfigured(),
			'sandbox' => (bool) $o->wdpvSandbox,
			'dailyLimit' => (int) $o->wdpvDailyLimit,
			'stats' => $stats,
			'records' => $records,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			'q' => $q,
			'logs' => $logs,
			'testPhone' => $testPhone,
			'testResult' => $testResult,
		]);
	}
}
