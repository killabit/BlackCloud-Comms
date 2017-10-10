<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Activity\Provider;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Room;
use OCP\Activity\IEvent;
use OCP\IL10N;

class Call extends Base {

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		$event = parent::preParse($event);
		$l = $this->languageFactory->get('spreed', $language);

		try {
			$parameters = $event->getSubjectParameters();
			$room = $this->manager->getRoomById((int) $parameters['room']);

			if ($event->getSubject() === 'call') {
				$result = $this->parseCall($event, $l, $room);
				$result['subject'] .= ' ' . $this->getDuration($l, $parameters['duration']);
				$this->setSubjects($event, $result['subject'], $result['params']);
			} else {
				throw new \InvalidArgumentException();
			}
		} catch (RoomNotFoundException $e) {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	protected function getDuration(IL10N $l, $seconds) {
		$hours = floor($seconds / 3600);
		$seconds %= 3600;
		$minutes = floor($seconds / 60);
		$seconds %= 60;

		if ($hours > 0) {
			$duration = sprintf('%1$d:%2$02d:%3$02d', $hours, $minutes, $seconds);
		} else {
			$duration = sprintf('%1$d:%2$02d', $minutes, $seconds);
		}

		return $l->t('(Duration %s)', $duration);
	}

	protected function parseCall(IEvent $event, IL10N $l, Room $room) {
		$parameters = $event->getSubjectParameters();

		$currentUser = array_search($this->activityManager->getCurrentUserId(), $parameters['users'], true);
		if ($currentUser === false) {
			throw new \InvalidArgumentException('Unknown case');
		}
		unset($parameters['users'][$currentUser]);
		sort($parameters['users']);

		if ($room->getType() === Room::ONE_TO_ONE_CALL) {
			$otherUser = array_pop($parameters['users']);

			if ($otherUser === '') {
				throw new \InvalidArgumentException('Unknown case');
			}

			return [
				'subject' => $l->t('You had a private call with {user}'),
				'params' => [
					'user' => $this->getUser($otherUser),
				],
			];
		}

		$numUsers = count($parameters['users']);
		$displayedUsers = $numUsers;
		switch ($numUsers) {
			case 0:
				$subject = $l->t('You had a call with {user1}');
				$subject = str_replace('{user1}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				break;
			case 1:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You had a call with {user1}');
				} else {
					$subject = $l->t('You had a call with {user1} and {user2}');
					$subject = str_replace('{user2}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 2:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You had a call with {user1} and {user2}');
				} else {
					$subject = $l->t('You had a call with {user1}, {user2} and {user3}');
					$subject = str_replace('{user3}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 3:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You had a call with {user1}, {user2} and {user3}');
				} else {
					$subject = $l->t('You had a call with {user1}, {user2}, {user3} and {user4}');
					$subject = str_replace('{user4}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 4:
				if ($parameters['guests'] === 0) {
					$subject = $l->t('You had a call with {user1}, {user2}, {user3} and {user4}');
				} else {
					$subject = $l->t('You had a call with {user1}, {user2}, {user3}, {user4} and {user5}');
					$subject = str_replace('{user5}', $l->n('%n guest', '%n guests', $parameters['guests']), $subject);
				}
				break;
			case 5:
			default:
				$subject = $l->t('You had a call with {user1}, {user2}, {user3}, {user4} and {user5}');
				if ($numUsers === 5 && $parameters['guests'] === 0) {
					$displayedUsers = 5;
				} else {
					$displayedUsers = 4;
					$numOthers = $parameters['guests'] + $numUsers - $displayedUsers;
					$subject = str_replace('{user5}', $l->n('%n other', '%n others', $numOthers), $subject);
				}
		}

		$params = [];
		for ($i = 1; $i <= $displayedUsers; $i++) {
			$params['user' . $i] = $this->getUser($parameters['users'][$i - 1]);
		}

		return [
			'subject' => $subject,
			'params' => $params,
		];
	}

}
