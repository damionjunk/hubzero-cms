<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

/**
 * Members Plugin class for activity
 */
class plgMembersActivity extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = true;

	/**
	 * Event call to determine if this plugin should return data
	 *
	 * @param   object  $user    Current user
	 * @param   object  $member  Member profile
	 * @return  array
	 */
	public function &onMembersAreas($user, $member)
	{
		$areas = array();

		if ($user->get('id') == $member->get('id'))
		{
			$areas['activity'] = Lang::txt('PLG_MEMBERS_ACTIVITY');
			$areas['icon']     = 'f056';
			$areas['icon-class'] = 'icon-activity';
			$areas['menu']     = $this->params->get('display_tab', 1);
		}

		return $areas;
	}

	/**
	 * Event call to return data for a specific member
	 *
	 * @param   object  $user    User
	 * @param   object  $member  Members Profile
	 * @param   string  $option  Component name
	 * @param   string  $areas   Plugins to return data
	 * @return  array
	 */
	public function onMembers($user, $member, $option, $areas)
	{
		$returnhtml = true;

		$arr = array(
			'html'     => '',
			'metadata' => array()
		);

		// Check if our area is in the array of areas we want to return results for
		if (is_array($areas))
		{
			if (!array_intersect($areas, $this->onMembersAreas($user, $member))
			 && !array_intersect($areas, array_keys($this->onMembersAreas($user, $member))))
			{
				$returnhtml = false;
			}
		}

		// Are we returning HTML?
		if ($returnhtml)
		{
			$this->member = $member;

			$action = Request::getCmd('action', 'feed');

			if (!$this->params->get('email_digests'))
			{
				$action = 'feed';
			}

			switch ($action)
			{
				case 'settings':
					$arr['html'] = $this->settingsAction();
					break;
				case 'savesettings':
					$arr['html'] = $this->savesettingsAction();
					break;
				case 'remove':
					$arr['html'] = $this->removeAction();
					break;
				case 'unstar':
					$arr['html'] = $this->starAction();
					break;
				case 'star':
					$arr['html'] = $this->starAction();
					break;
				case 'feed':
				default:
					$arr['html'] = $this->feedAction();
					break;
			}
		}

		$arr['metadata'] = array();

		// Get the number of unread messages
		$unread = Hubzero\Activity\Recipient::all()
			->whereEquals('scope', 'user')
			->whereEquals('scope_id', $member->get('id'))
			->whereEquals('state', 1)
			->where('viewed', 'IS', null)
			->total();

		// Return total message count
		$arr['metadata']['count'] = $unread;

		// Return data
		return $arr;
	}

	/**
	 * Show a feed
	 *
	 * @return  string
	 */
	protected function feedAction()
	{
		// Add lists of scopes and actions to filter by
		$scopes = \Hubzero\Activity\Log::all()
			->select('DISTINCT(scope)')
			->order('scope', 'asc')
			->rows()
			->toArray();
		$categories = array();
		foreach ($scopes as $scope)
		{
			$cat = explode('.', $scope['scope']);

			if ($cat[0] == 'activity')
			{
				continue;
			}

			if (!in_array($cat[0], $categories))
			{
				$categories[] = $cat[0];
			}
		}

		// Incoming filters
		$filters = array(
			'filter' => Request::getWord('filter'),
			'search' => Request::getString('q'),
			'scope'  => Request::getWord('scope'),
			'created_by' => Request::getWord('created_by'),
			'limit'  => Request::getInt('limit', Config::get('list_limit')),
			'start'  => Request::getInt('start', 0)
		);

		// Validate filters
		if (!in_array($filters['filter'], ['starred']))
		{
			$filters['filter'] = '';
		}

		if (!in_array($filters['created_by'], array('me', 'notme')))
		{
			$filters['created_by'] = '';
		}

		if (!in_array($filters['scope'], $categories))
		{
			$filters['scope'] = '';
		}

		// Build query to retrieve records
		$recipient = Hubzero\Activity\Recipient::all();

		$r = $recipient->getTableName();
		$l = Hubzero\Activity\Log::blank()->getTableName();

		$recipient
			->select($r . '.*')
			->including('log')
			->join($l, $l . '.id', $r . '.log_id')
			->whereEquals($r . '.scope', 'user')
			->whereEquals($r . '.scope_id', $this->member->get('id'))
			->whereEquals($r . '.state', Hubzero\Activity\Recipient::STATE_PUBLISHED);

		if ($filters['filter'] == 'starred')
		{
			$recipient->whereEquals($r . '.starred', 1);
		}

		if ($filters['created_by'])
		{
			if ($filters['created_by'] == 'me')
			{
				$recipient->whereEquals($l . '.created_by', $this->member->get('id'));
			}

			if ($filters['created_by'] == 'notme')
			{
				$recipient->where($l . '.created_by', '!=', $this->member->get('id'));
			}
		}

		if ($filters['scope'])
		{
			$recipient->whereLike($l . '.scope', $filters['scope']);
		}

		if ($filters['search'])
		{
			$recipient->whereLike($l . '.description', $filters['search']);
		}

		$total = $recipient->copy()->total();

		$entries = $recipient
			->ordered()
			//->paginated()
			->limit($filters['limit'])
			->start($filters['start'])
			->rows();

		$digests = $this->params->get('email_digests');

		// Build view
		$view = $this->view('default', 'activity')
			->set('digests', $digests)
			->set('member', $this->member)
			->set('categories', $categories)
			->set('filters', $filters)
			->set('total', $total)
			->set('rows', $entries);

		return $view->loadTemplate();
	}

	/**
	 * Unpublish an entry
	 *
	 * @return  string
	 */
	protected function removeAction()
	{
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (User::get('id') != $this->member->get('id'))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_ACTIVITY_NOTAUTH'));
		}

		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		$id      = Request::getInt('activity', 0);
		$no_html = Request::getInt('no_html', 0);

		$entry = Hubzero\Activity\Recipient::oneOrFail($id);

		if (!$entry->markAsUnpublished())
		{
			$this->setError($entry->getError());
		}

		$success = Lang::txt('PLG_MEMBERS_ACTIVITY_RECORD_REMOVED');

		if ($no_html)
		{
			$response = new stdClass;
			$response->success = true;
			$response->message = $success;
			if ($err = $this->getError())
			{
				$response->success = false;
				$response->message = $err;
			}

			ob_clean();
			header('Content-type: text/plain');
			echo json_encode($response);
			exit();
		}

		if ($err = $this->getError())
		{
			Notify::error($err);
		}
		else
		{
			Notify::success($success);
		}

		// Redirect
		App::redirect(
			Route::url($this->member->link() . '&active=activity', false)
		);
	}

	/**
	 * Stop receiving activity of a specific type
	 *
	 * @return  string
	 */
	/*protected function unsubscribeAction()
	{
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (User::get('id') != $this->member->get('id'))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_ACTIVITY_NOTAUTH'));
		}

		$scope   = Request::getCmd('scope');
		$no_html = Request::getInt('no_html', 0);

		$entry = Hubzero\Activity\Subscription::all()
			->whereEquals('scope', $scope)
			->whereEquals('action', $act)
			->whereEquals('user_id', User::get('id'))
			->row();

		$entry->set([
			'scope'   => $scope,
			'action'  => $act,
			'user_id' => User::get('id'),
			'exclude' => 1
		]);

		if (!$entry->save())
		{
			$this->setError($entry->getError());
		}

		$result = Hubzero\Activity\Recipient::blank()
			->update()
			->set('state', Hubzero\Activity\Recipient::STATE_UNPUBLISHED)
			->whereEquals('user_id', User::get('id'))
			->execute();

		if ($no_html)
		{
			$response = new stdClass;
			$response->success = true;
			$response->message = Lang::txt('PLG_MEMBERS_ACTIVITY_RECORDS_REMOVED');
			if ($err = $this->getError())
			{
				$response->success = false;
				$response->message = $err;
			}

			ob_clean();
			header('Content-type: text/plain');
			echo json_encode($response);
			exit();
		}

		if ($err = $this->getError())
		{
			Notify::error($err);
		}
		else
		{
			Notify::success(Lang::txt('PLG_MEMBERS_ACTIVITY_RECORDS_REMOVED'));
		}

		// Redirect
		App::redirect(
			Route::url($this->member->link() . '&active=activity', false)
		);
	}*/

	/**
	 * Star/unstar an entry
	 *
	 * @return  string
	 */
	protected function starAction()
	{
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (User::get('id') != $this->member->get('id'))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_ACTIVITY_NOTAUTH'));
		}

		$id      = Request::getInt('activity', 0);
		$no_html = Request::getInt('no_html', 0);
		$action  = Request::getString('action', 'star');

		$entry = Hubzero\Activity\Recipient::oneOrFail($id);
		$entry->set('starred', ($action == 'star' ? 1 : 0));

		if (!$entry->save())
		{
			$this->setError($entry->getError());
		}

		$success = $action == 'star'
			? Lang::txt('PLG_MEMBERS_ACTIVITY_RECORD_STARRED')
			: Lang::txt('PLG_MEMBERS_ACTIVITY_RECORD_UNSTARRED');

		if ($no_html)
		{
			$response = new stdClass;
			$response->success = true;
			$response->message = $success;
			if ($err = $this->getError())
			{
				$response->success = false;
				$response->message = $err;
			}

			ob_clean();
			header('Content-type: text/plain');
			echo json_encode($response);
			exit();
		}

		if ($err = $this->getError())
		{
			Notify::error($err);
		}
		else
		{
			Notify::success($success);
		}

		// Redirect
		App::redirect(
			Route::url($this->member->link() . '&active=activity', false)
		);
	}

	/**
	 * Show settings form
	 *
	 * @return  string
	 */
	protected function settingsAction()
	{
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (User::get('id') != $this->member->get('id'))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_ACTIVITY_NOTAUTH'));
		}

		if (!$this->params->get('email_digests'))
		{
			return $this->feedAction();
		}

		$settings = Hubzero\Activity\Digest::oneByScope(
			$this->member->get('id'),
			'user'
		);

		$view = $this->view('settings', 'activity')
			->set('member', $this->member)
			->set('settings', $settings);

		return $view->loadTemplate();
	}

	/**
	 * Save settings
	 *
	 * @return  mixed
	 */
	protected function savesettingsAction()
	{
		if (User::isGuest())
		{
			return $this->loginAction();
		}

		if (User::get('id') != $this->member->get('id'))
		{
			App::abort(403, Lang::txt('PLG_MEMBERS_ACTIVITY_NOTAUTH'));
		}

		if (!$this->params->get('email_digests'))
		{
			return $this->feedAction();
		}

		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$settings = Request::getArray('settings', array(), 'post');
		$settings['scope']    = 'user';
		$settings['scope_id'] = $this->member->get('id');

		$row = Hubzero\Activity\Digest::blank()->set($settings);

		// Store new content
		if (!$row->save())
		{
			$this->setError($row->getError());
			return $this->settingsAction();
		}

		// Log the activity
		Event::trigger('system.logActivity', [
			'activity' => [
				'action'      => 'updated',
				'scope'       => 'activity.settings',
				'scope_id'    => $row->get('id'),
				'description' => Lang::txt('PLG_MEMBERS_ACTIVITY_SETTINGS_UPDATED')
			],
			'recipients' => [
				$this->member->get('id')
			]
		]);

		// Redirect
		App::redirect(
			Route::url($this->member->link() . '&active=activity', false),
			Lang::txt('PLG_MEMBERS_ACTIVITY_SETTINGS_SAVED')
		);
	}

	/**
	 * Redirect to the login page
	 *
	 * @return  void
	 */
	protected function loginAction()
	{
		$return = base64_encode(Route::url($this->member->link() . '&active=' . $this->_name, false, true));

		App::redirect(
			Route::url('index.php?option=com_users&view=login&return=' . $return, false),
			Lang::txt('MEMBERS_LOGIN_NOTICE')
		);
	}
}
