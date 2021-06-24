<?php


namespace OCA\Talk\Model;


use OCA\Talk\Exceptions\InvitationNotFoundException;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class InvitationMapper
 *
 * @package OCA\Talk\Model
 *
 * @method Invitation mapRowToEntity(array $row)
 * @method Invitation findEntity(IQueryBuilder $query)
 * @method Invitation[] findEntities(IQueryBuilder $query)
 */
class InvitationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_invitations', Invitation::class);
	}

	/**
	 * @throws DBException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getInvitationById(int $id): Invitation {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			throw new InvitationNotFoundException();
		}
	}

	/**
	 * @param Room $room
	 * @return Invitation[]
	 * @throws DBException
	 */
	public function getInvitationsForRoom(Room $room): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('room_id', $qb->createNamedParameter($room->getId())));

		return $this->findEntities($qb);
	}

	/**
	 * @throws DBException
	 */
	public function countInvitationsForRoom(Room $room): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*', 'num_invitations'))
			->from($this->getTableName())
			->where($qb->expr()->eq('room_id', $qb->createNamedParameter($room->getId())));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int) ($row['num_invitations' ?? 0]);
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
