<?php

namespace WebDanismani\PhoneVerify;

class Listener
{
	/**
	 * entity_post_save (XF\Entity\User): yeni kayıt olan üyeye doğrulanmış numarayı bağlar.
	 * Yalnızca ön yüzdeki kayıtta çalışır; yönetim panelinden üye eklemek etkilenmez.
	 */
	public static function userPostSave(\XF\Mvc\Entity\Entity $entity)
	{
		if (!$entity->isInsert() || !(\XF::app() instanceof \XF\Pub\App))
		{
			return;
		}

		try
		{
			Phone::attachToUser(\XF::app()->session(), $entity->getEntityId());
		}
		catch (\Exception $e)
		{
			// Numara kaydı başarısız olsa bile üyelik tamamlanmalı.
			\XF::logException($e, false, 'WD Telefon Doğrulama: ');
		}
	}

	/**
	 * entity_post_delete (XF\Entity\User): silinen üyenin numarasını serbest bırakır.
	 */
	public static function userPostDelete(\XF\Mvc\Entity\Entity $entity)
	{
		\XF::db()->query('DELETE FROM xf_wd_phone WHERE user_id = ?', $entity->getEntityId());
	}
}
