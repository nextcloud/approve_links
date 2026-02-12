<?php

namespace OCA\ApproveLinks\Cron;

use OCA\ApproveLinks\Db\LinkMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class CleanupLinks extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private LinkMapper $linkMapper,
	) {
		parent::__construct($time);
		// Run every week
		$this->setInterval(24 * 60 * 60 * 7);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @param $argument
	 * @return void
	 */
	protected function run($argument): void {
		$now = time();
		// cleanup links older than 6 months (approximately)
		$before = $now - (24 * 60 * 60 * 30 * 6);
		$this->linkMapper->cleanUpLinksCreatedBefore($before);
	}
}
