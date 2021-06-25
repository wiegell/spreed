<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
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
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DBException;
use OCP\IConfig;
use OCP\IUser;

/**
 * Class FederationManager
 *
 * @package OCA\Talk\Federation
 *
 * FederationManager handles incoming federated rooms
 */
class FederationManager {
	/** @var IConfig */
	private IConfig $config;

	/** @var Manager */
	private Manager $manager;

	/** @var ParticipantService */
	private ParticipantService $participantService;

	/** @var InvitationMapper */
	private InvitationMapper $invitationMapper;

	/** @var AttendeeMapper  */
	private AttendeeMapper $attendeeMapper;

	public function __construct (
		IConfig $config,
		Manager $manager,
		ParticipantService $participantService,
		InvitationMapper $invitationMapper,
		AttendeeMapper $attendeeMapper
	) {
		$this->config = $config;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->invitationMapper = $invitationMapper;
		$this->attendeeMapper = $attendeeMapper;
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
		} catch (RoomNotFoundException $ex) {
			$room = $this->manager->createRemoteRoom($roomType, $roomName, $roomToken, $remoteUrl);
		}

		$participant = [
			[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'accessToken' => $sharedSecret,
				'joined' => false,
			]
		];

		$attendees = $this->participantService->addUsers($room, $participant);

		return $attendees[0]->getId();
	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function acceptRemoteRoomShare(IUser $user, int $shareId) {
		$attendee = $this->attendeeMapper->getById($shareId);
		if ($attendee->getActorType() !== Attendee::ACTOR_USERS || $attendee->getActorId() !== $user->getUID()) {
			throw new UnauthorizedException('invitation is for a different user');
		}

		$room = $this->manager->getRoomById($attendee->getRoomId());
		if (!$room->getServerUrl()) {
			throw new UnauthorizedException('room is not a remote room');
		}

		$attendee->setJoined(true);
		$this->attendeeMapper->update($attendee);
	}

	/**
	 * @throws DBException
	 * @throws UnauthorizedException
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 */
	public function rejectRemoteRoomShare(IUser $user, int $shareId) {
		$invitation = $this->invitationMapper->getInvitationById($shareId);
		if ($invitation->getUserId() !== $user->getUID()) {
			throw new UnauthorizedException('invitation is for a different user');
		}
		$this->invitationMapper->delete($invitation);
	}

	/**
	 * @throws DBException
	 */
	public function getNumberOfInvitations(Room $room): int {
		return $this->invitationMapper->countInvitationsForRoom($room);
	}
}
