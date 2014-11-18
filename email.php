<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.email
 *
 * @copyright   Copyright (C) 2014 Ryan Demmer. All rights reserved.
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('JPATH_BASE') or die;

/**
 * Email as Username.
 *
 * @package     Joomla.Plugin
 * @subpackage  System.email
 * @since       1.6
 */
class PlgSystemEmail extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 * @since   1.5
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}
	
	protected function getUserId($email) {
		
		// Initialise some variables
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = ' . $db->quote($email));
		$db->setQuery($query, 0, 1);

		return $db->loadResult();
	}
	
	protected function createUserName($name) {
		$search  = array('#[^a-zA-Z0-9_\.\-\p{L}\p{N}\s ]#u');
		
		// remove multiple . characters
        	$search[] = '#(\.){2,}#';

        	// strip leading period
        	$search[] = '#^\.#';

        	// strip trailing period
        	$search[] = '#\.$#';

        	// strip whitespace
        	$search[] = '#^\s*|\s*$#';

		$name = preg_replace('#[\s ]#', '_', $name);		
		$name = preg_replace($search, '', $name);
		
		while(JUserHelper::getUserId($name)) {
			$name = JString::increment($name);
		}
		
		return $name;
	}

	public function onAfterRoute() {
		$app = JFactory::getApplication();
		
		if($app->isAdmin()) return;
		
		$task = $app->input->getCmd('task');

		if ($app->input->getCmd('option') === "com_users" &&  ($task === "user.login" || $task === "reset.confirm")) {
			JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
			
			$input  = $app->input;
			$method = $input->getMethod();
			
			$data = array();
			
			if ($task === "reset.confirm") {
				$data = $app->input->get('jform', array(), 'array');
				// reset this
				$data['username'] = null; 
			}
			
			if ($task === "user.login") {
				$data['username']  	= $input->$method->get('username', '', 'USERNAME');
				$data['email']  	= $input->$method->get('email', '', 'EMAIL');
			}

			if (array_key_exists('email', $data)) {
				$db = JFactory::getDbo();
				$query = $db->getQuery(true)
				->select('username')
				->from($db->quoteName('#__users'))
				->where($db->quoteName('email') . ' = ' . $db->quote($data['email']));

				// Get the username.
				$db->setQuery($query);

				try
				{
					$username = $db->loadResult();
				}
				catch (RuntimeException $e)
				{
					return new JException(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
				}

				if ($task === "reset.confirm") {
					// set username
					$data['username'] = $username;
				
					// set new jform data
					$app->input->set('jform', $data);
				}
				
				if ($task === "user.login") {
					$input->$method->set('username', $username);
					$input->$method->set('email', '');		
				}
			}
		}
	}

	/**
	 * adds additional fields to the user editing form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');

			return false;
		}
		
		$app = JFactory::getApplication();

		// Check we are manipulating a valid form.
		$name = $form->getName();

		if (!in_array($name, array('com_users.login', 'com_users.reset_confirm')))
		{
			return true;
		}

		// Add the registration fields to the form.
		JForm::addFormPath(__DIR__ . '/form');

		$input  = $app->input;
		$method = $input->getMethod();
		
		// don't need username if there is no data submitted (form)
		$username = $input->$method->get('username', '', 'USERNAME');

		if ($name === "com_users.login") {
			$form->removeField('username');
			$form->removeField('password');
			$form->loadFile('login', false);

			// return passed as $_GET
			$return = base64_decode($app->input->get('return', '', 'BASE64'));
			
			if (!empty($return)) {				
				// set user state to remember return
				$app->setUserState('users.login.form.return', $return);
				
				$menus 	= $app->getMenu();
				$menu 	= $menus->getActive();
				
				// reset menu login
				if ($menu && $menu->params && $menu->params->get('login_return_url')) {
					$menu->params->set('login_return_url', $return);
				}
			}
		}

		if ($name === "com_users.reset_confirm") {
			$form->removeField('username');
			$form->removeField('token');
			$form->loadFile('reset_confirm', false);
		}
		
		// remove if no data (form not submitted)
		if (empty($username)) {
			$form->removeField('username');
		}

		return true;
	}
}
