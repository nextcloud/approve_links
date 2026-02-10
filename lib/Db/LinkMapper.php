<?php

namespace OCA\ApproveLinks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Link>
 */
class LinkMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'approve_links_links', Link::class);
	}

	public function findById(int $id): Link {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $signature
	 * @return Link
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findBySignature(string $signature): Link {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('signature', $qb->createNamedParameter($signature, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}
}
