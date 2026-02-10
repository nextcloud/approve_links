<?php

namespace OCA\ApproveLinks\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method \int getId()
 * @method \void setId(int $id)
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \string getSignature()
 * @method \void setSignature(string $signature)
 * @method \int getCreatedAt()
 * @method \void setCreatedAt(int $createdAt)
 * @method \int|\null getDoneAt()
 * @method \void setDoneAt(?int $doneAt)
 */
class Link extends Entity implements \JsonSerializable {

	protected $userId;
	protected $signature;
	protected $createdAt;
	protected $doneAt;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('signature', Types::STRING);
		$this->addType('createdAt', Types::INTEGER);
		$this->addType('doneAt', Types::INTEGER);
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'signature' => $this->getSignature(),
			'createdAt' => $this->getCreatedAt(),
			'doneAt' => $this->getDoneAt(),
		];
	}
}
