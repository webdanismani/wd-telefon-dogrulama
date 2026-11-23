<?php

namespace WebDanismani\PhoneVerify;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		$this->schemaManager()->createTable('xf_wd_phone', function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('phone', 'varchar', 20);
			$table->addColumn('verified_date', 'int')->setDefault(0);
			$table->addPrimaryKey('user_id');
			$table->addUniqueKey('phone');
		});
	}

	public function installStep2()
	{
		$this->schemaManager()->createTable('xf_wd_phone_log', function (Create $table)
		{
			$table->addColumn('log_id', 'int')->autoIncrement();
			$table->addColumn('phone', 'varchar', 20)->setDefault('');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('ip', 'varbinary', 16)->setDefault('');
			$table->addColumn('log_date', 'int')->setDefault(0);
			$table->addColumn('status', 'varchar', 10)->setDefault('');
			$table->addColumn('error_code', 'varchar', 50)->setDefault('');
			$table->addColumn('job_id', 'varchar', 100)->setDefault('');
			$table->addColumn('detail', 'varchar', 255)->setDefault('');
			$table->addPrimaryKey('log_id');
			$table->addKey(['phone', 'log_date']);
			$table->addKey(['ip', 'log_date']);
			$table->addKey('log_date');
		});
	}

	public function uninstallStep1()
	{
		$this->schemaManager()->dropTable('xf_wd_phone');
	}

	public function uninstallStep2()
	{
		$this->schemaManager()->dropTable('xf_wd_phone_log');
	}

	public function uninstallStep3()
	{
		\XF::registry()->delete('wdpvNetGsmToken');
	}
}
