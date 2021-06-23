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

namespace OCA\Talk\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Talk\AppInfo\Application;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Federation\FederationManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception as DBException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class FederationController extends OCSController {
	/** @var FederationManager */
	private $federationManager;

	/** @var IUserSession */
	private $userSession;

	public function __construct(IRequest $request, FederationManager $federationManager, IUserSession $userSession) {
		parent::__construct(Application::APP_ID, $request);
		$this->federationManager = $federationManager;
		$this->userSession = $userSession;
	}

	/**
	 * @param int $id
	 * @return DataResponse
	 * @throws NotLoggedInException
	 * @throws UnauthorizedException
	 * @throws DBException
	 */
	public function acceptShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new NotLoggedInException();
		}
		$this->federationManager->acceptRemoteRoomShare($user, $id);
		return new DataResponse();
	}

	/**
	 * @param int $id
	 * @return DataResponse
	 * @throws NotLoggedInException
	 * @throws UnauthorizedException
	 * @throws DBException
	 */
	public function rejectShare(int $id): DataResponse {
		$user = $this->userSession->getUser();
		if (!$user instanceof IUser) {
			throw new NotLoggedInException();
		}
		$this->federationManager->rejectRemoteRoomShare($user, $id);
		return new DataResponse();
	}
}
