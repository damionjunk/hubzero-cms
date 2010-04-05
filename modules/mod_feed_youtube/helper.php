<?php
/**
* @version		$Id: helper.php 10381 2008-06-01 03:35:53Z pasamio $
* @package		Joomla
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Direct Access to this location is not allowed.');

class modFeedYoutubeHelper
{
	function getFeed($params)
	{
		// module params
		$rssurl			= $params->get('rssurl', '');
		$limit			= (int) $params->get('rssitems', 5);
		$pick_random	= $params->get('pick_random', 0);

		// if ordered, get only limited number of items
		//$rssurl			.= $pick_random ? '' : '&max-results='.$limit;
		
		//  get RSS parsed object
		$options = array();
		$options['rssUrl'] 		= $rssurl;
		$options['cache_time'] = null;
		
		$rssDoc =& JFactory::getXMLparser('RSS', $options);

		$feed = new stdclass();

		if ($rssDoc != false)
		{
			// channel header and link
			$feed->title = $rssDoc->get_title();
			$feed->link = $rssDoc->get_link();
			$feed->description = $rssDoc->get_description();

			// channel image if exists
			$feed->image->url = $rssDoc->get_image_url();
			$feed->image->title = $rssDoc->get_image_title();

			// items
			$items = $rssDoc->get_items();

			// feed elements
			if($pick_random) {
				// randomize items
				shuffle($items);
			}
			$feed->items = array_slice($items, 0, $limit);
		} else {
			$feed = false;
		}

		return $feed;
	}	
}