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
class PlgSystemEmail_As_Username extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 * @since   1.5
	 */
	public function __construct(& $subject, $config) {
		if(JFactory::getApplication()->isAdmin()) {
			return;
		}
		
		parent::__construct($subject, $config);
	}
	
	protected function getUserName($email) {
		$db = JFactory::getDbo();
		
		$username = null;
		
		$query = $db->getQuery(true)
		->select('username')
		->from($db->quoteName('#__users'))
		->where($db->quoteName('email') . ' = ' . $db->quote($email));

		// Get the username.
		$db->setQuery($query);

		try {
			$username = $db->loadResult();
		} catch (RuntimeException $e) {
			return new JException(JText::sprintf('COM_USERS_DATABASE_ERROR', $e->getMessage()), 500);
		}
		
		return $username;
	}
	
	protected function createUserName($name) {
		// email address
		if (strpos($name, '@') !== false) {
			$search  = array('#[<>"\'%;()&\\\\]|\\.\\./#');
		// name
		} else {
			$search  = array('#[^a-zA-Z0-9_\.\-\p{L}\p{N}\s ]#u');
		}

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

		$task = $app->input->getCmd('task');
		
		if ($app->input->getCmd('option') === "com_users") {
		
			// quick check to make sure we are in the right task
			if (!in_array($task, array('registration.register', 'user.login', 'reset.confirm'))) {
				return true;
			}
		
			$input  = $app->input;
			$method = $input->getMethod();
			
			$data 	= $input->get('jform', array(), 'array');

			switch($task) {
				case "registration.register":
				case "profile.save":
					$source = $this->params->get('username_source', 'name');
					
					if (!empty($data) && isset($data[$source])) {
						// get unique username
						$data['username'] = $this->createUserName($data[$source]);
						
						// set new jform data
						$input->post->set('jform', $data); 
					}

					break;	
				case "user.login":					
					$email = $input->get('email', '', 'EMAIL');
					
					// get username from email
					if (!empty($email)) {
						$username = $this->getUserName($email);
						$input->$method->set('username', $username);
					}

					$input->set('email', '');
					
					break;
				case "reset.confirm":	
								
					// get username from email
					if (isset($data['email'])) {
						$data['username'] = $this->getUserName($data['email']);
					}
				
					// set new jform data
					$input->set('jform', $data);
				
					break;
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

		// quick check to make sure we are in the right form
		if (!in_array($name, array('com_users.login', 'com_users.reset_confirm', 'com_users.registration'))) {
			return true;
		}

		// Add the registration fields to the form.
		JForm::addFormPath(__DIR__ . '/form');

		$input  = $app->input;
		$method = $input->getMethod();
		
		// get submitted username
		$username 	= $input->$method->get('username', '', 'USERNAME');
		$data		= array();
		
		switch($name) {
			case "com_users.registration":
				$form->removeField('username');
				$form->removeField('password1');
				$form->removeField('password2');
				$form->loadFile('registration', false);
				
				// get jform data if any
				$data = $input->post->get('jform', array(), 'array');
				
				break;
			case "com_users.login":
				$form->removeField('username');
				$form->removeField('password');
				$form->loadFile('login', false);

				// return passed as $_GET
				$return = base64_decode($input->get('return', '', 'BASE64'));
			
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
				break;
			case "com_users.reset_confirm":
				$form->removeField('username');
				$form->removeField('token');
				$form->loadFile('reset_confirm', false);
				
				// get jform data if any
				$data = $input->get('jform', array(), 'array');
				
				break;
		}

		// remove only if no data (form not submitted)
		if (empty($username) && !isset($data['username'])) {
			$form->removeField('username');
		}

		return true;
	}
}
