<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Alissa Nedossekina <alisa@purdue.edu>
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

error_reporting(E_ALL);
@ini_set('display_errors','1');

// Ensure user has access to this function
$jacl =& JFactory::getACL();
$jacl->addACL($option, 'manage', 'users', 'super administrator');
$jacl->addACL($option, 'manage', 'users', 'administrator');
$jacl->addACL($option, 'manage', 'users', 'manager');

// Authorization check
$user = & JFactory::getUser();
if (!$user->authorize($option, 'manage')) 
{
	$mainframe->redirect('index.php', JText::_('ALERTNOTAUTH'));
}

// Include scripts
require_once(JPATH_COMPONENT . DS . 'tables' . DS . 'store.php');
require_once(JPATH_COMPONENT . DS . 'tables' . DS . 'order.php');
require_once(JPATH_COMPONENT . DS . 'tables' . DS . 'orderitem.php');
require_once(JPATH_COMPONENT . DS . 'tables' . DS . 'cart.php');
require_once(JPATH_COMPONENT . DS . 'helpers' . DS . 'html.php');
//require_once( JPATH_ADMINISTRATOR.DS.'components'.DS.$option.DS.'controller.php' );
ximport('Hubzero_Filter');

$controllerName = JRequest::getCmd('controller', 'orders');

if ($controllerName == 'items')
{
	JSubMenuHelper::addEntry(JText::_('Orders'), 'index.php?option=' .  $option . '&controller=orders');
	JSubMenuHelper::addEntry(JText::_('Store Items'), 'index.php?option=' .  $option . '&controller=items', true);
}
else 
{
	JSubMenuHelper::addEntry(JText::_('Orders'), 'index.php?option=' .  $option . '&controller=orders', true);
	JSubMenuHelper::addEntry(JText::_('Store Items'), 'index.php?option=' .  $option . '&controller=items');
}

require_once(JPATH_COMPONENT . DS . 'controllers' . DS . $controllerName . '.php');
$controllerName = 'StoreController' . ucfirst($controllerName);

// Instantiate controller
$controller = new $controllerName();
$controller->execute();
$controller->redirect();

