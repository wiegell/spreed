<?php


namespace OCA\Talk\Model;


use OCA\Talk\Exceptions\InvitationNotFoundException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * Class InvitationMapper
 *
 * @package OCA\Talk\Model
 *
 * @method Invitation mapRowToEntity(array $row)
 */
class InvitationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_invitations', Invitation::class);
	}

	/**
	 * @throws Exception
	 */
	public function getInvitationById(int $id): Invitation {
		$qb = $this->db->getQueryBuilder();

		$result = $qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->executeQuery();

		if ($row = $result->fetchOne()) {
			return $this->createInvitationFromRow($row);
		}
		throw new InvitationNotFoundException('cannot find invitation with the given id');
	}

	public function createInvitationFromRow(array $row): Invitation {
		return $this->mapRowToEntity([
			'id' => $row['id'],
			'room_id' => $row['room_id'],
			'user_id' => $row['user_id'],
			'access_token' => $row['access_token'],
		]);
	}
}
