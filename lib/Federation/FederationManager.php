<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Federation;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;

class FederationManager {
	/** @var IDBConnection */
	private $db;

	/** @var IConfig */
	private $config;

	/** @var Manager */
	private $manager;

	/** @var ParticipantService */
	private $participantService;

	/** @var AttendeeMapper */
	private $attendeeMapper;

	/** @var RoomService */
	private $roomService;

	public function __construct (
		IDBConnection $db,
		IConfig $config,
		Manager $manager,
		ParticipantService $participantService,
		AttendeeMapper $attendeeMapper,
		RoomService $roomService
	) {
		$this->db = $db;
		$this->config = $config;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->attendeeMapper = $attendeeMapper;
		$this->roomService = $roomService;
	}

	/**
	 * Determine if Talk federation is enabled on this instance
	 * @return bool
	 */
	public function isEnabled(): bool {
		// TODO: Set to default true once implementation is complete
		return $this->config->getSystemValueBool('talk_federation_enabled', false);
	}

	/**
	 * @param IUser $user
	 * @param int $roomType
	 * @param string $roomName
	 * @param string $roomToken
	 * @param string $remoteUrl
	 * @param string $sharedSecret
	 * @return int share id for this specific remote room share
	 */
	public function addRemoteRoom(IUser $user, int $roomType, string $roomName, string $roomToken, string $remoteUrl, string $sharedSecret): int {
		try {
			$room = $this->manager->getRoomByToken($roomToken, null, $remoteUrl);
		} catch (RoomNotFoundException $e) {
			$room = $this->manager->createRemoteRoom($roomType, $roomName, $roomToken, $remoteUrl);
		}
		$participant = [
			[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'accessToken' => $sharedSecret,
			]
		];
		$this->participantService->addUsers($room, $participant);
		return $room->getId();
	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 */
	public function acceptRemoteRoomShare(IUser $user, int $shareId) {

	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 */
	public function rejectRemoteRoomShare(IUser $user, int $shareId) {
		// TODO: do we require a room id to be able to remove the user and reject the share?
	}
}
