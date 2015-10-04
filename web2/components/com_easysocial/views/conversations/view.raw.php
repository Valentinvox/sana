<?php
/**
* @package		Social
* @copyright	Copyright (C) 2010 Stack Ideas Private Limited. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* EasyBlog is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined( '_JEXEC' ) or die( 'Unauthorized Access' );

FD::import( 'site:/views/views' );

class EasySocialViewConversations extends EasySocialSiteView
{
	/**
	 * Handle the output after a reply is posted.
	 */
	public function reply( $data )
	{
		echo FD::json()->encode( $data );

		parent::display();
	}
}
