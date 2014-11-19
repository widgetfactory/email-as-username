<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.email
 *
 * @copyright   Copyright (C) 2014 Ryan Demmer. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */
class plg_systememail_as_usernameInstallerScript {
	
	protected function getTemplates() {
        	$db = JFactory::getDBO();
        	$app = JFactory::getApplication();
        	$id = 0;

        	if ($app->isSite()) {
        		$menus = $app->getMenu();
            		$menu = $menus->getActive();

            		if ($menu) {
                		$id = isset($menu->template_style_id) ? $menu->template_style_id : $menu->id;
            		}
        	}

        	$query = $db->getQuery(true);
        	$query->select('id, template')->from('#__template_styles')->where(array("client_id = 0", "home = '1'"));

        	$db->setQuery($query);
        	$templates = $db->loadObjectList();

        	$assigned = array();

        	foreach ($templates as $template) {
            		if ($id == $template->id) {
                		array_unshift($assigned, $template->template);
            		} else {
                		$assigned[] = $template->template;
            		}
        	}

        	// return templates
        	return $assigned;
    	}

	public function install($parent) {
		$path = $parent->getPath('source');
		
		// get template
		$templates = $this->getTemplates();
		
		if (!empty($templates)) {
			if (is_dir($path . '/com_users')) {
				JFolder::copy($path . '/com_users', $templates[0] . '/html/com_users');
			}
			
			if (is_dir($path . '/mod_login')) {
				JFolder::copy($path . '/mod_login', $templates[0] . '/html/mod_login');
			}
		}
	}
	
	public function update($parent) {
		$this->install($parent);
	}
	
}
