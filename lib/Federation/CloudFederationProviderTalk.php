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

use Exception;
use OC\AppFramework\Http;
use OC\HintException;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Manager;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Participant;
use OCA\Talk\Service\ParticipantService;
use OCP\DB\Exception as DBException;
use OCP\Federation\Exceptions\ActionNotSupportedException;
use OCP\Federation\Exceptions\AuthenticationFailedException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationShare;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\Exceptions\ShareNotFound;

class CloudFederationProviderTalk implements ICloudFederationProvider {

	/** @var IUserManager */
	private IUserManager $userManager;

	/** @var AddressHandler */
	private AddressHandler $addressHandler;

	/** @var FederationManager */
	private FederationManager $federationManager;

	/** @var INotificationManager */
	private INotificationManager $notificationManager;

	/** @var IURLGenerator */
	private IURLGenerator $urlGenerator;

	/** @var ParticipantService  */
	private ParticipantService $participantService;

	/** @var AttendeeMapper  */
	private AttendeeMapper $attendeeMapper;

	/** @var Manager  */
	private Manager $manager;

	public function __construct(
		IUserManager $userManager,
		AddressHandler $addressHandler,
		FederationManager $federationManager,
		INotificationManager $notificationManager,
		IURLGenerator $urlGenerator,
		ParticipantService $participantService,
		AttendeeMapper $attendeeMapper,
		Manager $manager
	) {
		$this->userManager = $userManager;
		$this->addressHandler = $addressHandler;
		$this->federationManager = $federationManager;
		$this->notificationManager = $notificationManager;
		$this->urlGenerator = $urlGenerator;
		$this->participantService = $participantService;
		$this->attendeeMapper = $attendeeMapper;
		$this->manager = $manager;
	}

	/**
	 * @inheritDoc
	 */
	public function getShareType(): string {
		return 'talk-room';
	}

	/**
	 * @inheritDoc
	 * @throws HintException
	 * @throws DBException
	 */
	public function shareReceived(ICloudFederationShare $share): string {
		if (!$this->federationManager->isEnabled()) {
			throw new ProviderCouldNotAddShareException('Server does not support talk federation', '', Http::STATUS_SERVICE_UNAVAILABLE);
		}
		if ($share->getShareType() !== 'user') {
			throw new ProviderCouldNotAddShareException('support for sharing with non-groups not implemented yet', '', Http::STATUS_NOT_IMPLEMENTED);
		}

		if (!is_numeric($share->getShareType())) {
			throw new ProviderCouldNotAddShareException('RoomType is not a number', '', Http::STATUS_BAD_REQUEST);
		}

		$shareSecret = $share->getShareSecret();
		$shareWith = $share->getShareWith();
		$roomToken = $share->getProviderId();
		$roomName = $share->getResourceName();
		$roomType = (int) $share->getShareType();
		$sharedBy = $share->getSharedByDisplayName();
		$sharedByFederatedId = $share->getSharedBy();
		$owner = $share->getOwnerDisplayName();
		$ownerFederatedId = $share->getOwner();
		[, $remote] = $this->addressHandler->splitUserRemote($ownerFederatedId);

		// if no explicit information about the person who created the share was send
		// we assume that the share comes from the owner
		if ($sharedByFederatedId === null) {
			$sharedBy = $owner;
			$sharedByFederatedId = $ownerFederatedId;
		}

		if ($remote && $shareSecret && $shareWith && $roomToken && $roomName && $owner) {
			$shareWith = $this->userManager->get($shareWith);
			if ($shareWith === null) {
				throw new ProviderCouldNotAddShareException('User does not exist', '',Http::STATUS_BAD_REQUEST);
			}

			$shareId = (string) $this->federationManager->addRemoteRoom($shareWith, $roomType, $roomName, $roomToken, $remote, $shareSecret);

			$this->notifyAboutNewShare($shareWith, $shareId, $sharedByFederatedId, $sharedBy, $roomName, $roomToken, $remote);
			return $shareId;
		}
		throw new ProviderCouldNotAddShareException('required request data not found', '', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @inheritDoc
	 */
	public function notificationReceived($notificationType, $providerId, array $notification): array {
		if (!is_numeric($providerId)) {
			throw new BadRequestException(['providerId']);
		}
		switch ($notificationType) {
			case 'SHARE_ACCEPTED':
				return $this->shareAccepted((int) $providerId, $notification);
			case 'SHARE_DECLINED':
				return $this->shareDeclined((int) $providerId, $notification);
			case 'SHARE_UNSHARED':
				return []; // TODO: Implement
			case 'REQUEST_RESHARE':
				return []; // TODO: Implement
			case 'RESHARE_UNDO':
				return []; // TODO: Implement
			case 'RESHARE_CHANGE_PERMISSION':
				return []; // TODO: Implement
		}
		// TODO: Implement notificationReceived() method.
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareAccepted(int $id, array $notification): array {
		if (!$this->federationManager->isEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		try {
			$attendee = $this->attendeeMapper->getById($id);
		} catch (Exception) {
			throw new ShareNotFound();
		}
		if (!isset($notification['sharedSecret']) || $attendee->getAccessToken() !== $notification['sharedSecret']) {
			throw new AuthenticationFailedException();
		}

		// TODO: Add activity for share accepted

		return [];
	}

	/**
	 * @throws ActionNotSupportedException
	 * @throws ShareNotFound
	 * @throws AuthenticationFailedException
	 */
	private function shareDeclined(int $id, array $notification): array {
		if (!$this->federationManager->isEnabled()) {
			throw new ActionNotSupportedException('Server does not support Talk federation');
		}

		try {
			$attendee = $this->attendeeMapper->getById($id);
		} catch (Exception) {
			throw new ShareNotFound();
		}
		if (!isset($notification['sharedSecret']) || $attendee->getAccessToken() !== $notification['sharedSecret']) {
			throw new AuthenticationFailedException();
		}

		$room = $this->manager->getRoomById($attendee->getRoomId());
		$participant = new Participant($room, $attendee, null);
		$this->participantService->removeAttendee($room, $participant, 'Left Room');
		return [];
	}

	private function notifyAboutNewShare(IUser $shareWith, string $shareId, string $sharedByFederatedId, string $sharedByName, string $roomName, string $roomToken, string $serverUrl) {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($shareWith->getUID())
			->setDateTime(new \DateTime())
			->setObject('remote_talk_share', $shareId)
			->setSubject('remote_talk_share', [
				'sharedByDisplayName' => $sharedByName,
				'sharedByFederatedId' => $sharedByFederatedId,
				'roomName' => $roomName,
				'serverUrl' => $serverUrl,
				'roomToken' => $roomToken,
			]);

		$declineAction = $notification->createAction();
		$declineAction->setLabel('decline')
			->setLink($this->urlGenerator->linkToOCSRouteAbsolute('spreed.Federation.rejectShare', ['id' => $shareId]), 'DELETE');
		$notification->addAction($declineAction);

		$acceptAction = $notification->createAction();
		$acceptAction->setLabel('accept')
			->setLink($this->urlGenerator->linkToOCSRouteAbsolute('spreed.Federation.acceptShare', ['id' => $shareId]), 'POST');
		$notification->addAction($acceptAction);

		$this->notificationManager->notify($notification);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedShareTypes() {
		return ['user'];
	}
}
