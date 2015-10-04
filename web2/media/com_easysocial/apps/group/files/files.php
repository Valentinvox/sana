<?php
/**
* @package		EasySocial
* @copyright	Copyright (C) 2010 - 2014 Stack Ideas Sdn Bhd. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* EasySocial is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined( '_JEXEC' ) or die( 'Unauthorized Access' );

FD::import( 'admin:/includes/apps/apps' );

/**
 * Friends application for EasySocial.
 *
 * @since	1.0
 * @author	Mark Lee <mark@stackideas.com>
 */
class SocialGroupAppFiles extends SocialAppItem
{
	/**
	 * Class constructor.
	 *
	 * @since	1.0
	 * @access	public
	 */
	public function __construct()
	{
		// We need the router
		require_once( JPATH_ROOT . '/components/com_content/helpers/route.php' );

		parent::__construct();
	}

	/**
	 * Responsible to return the favicon object
	 *
	 * @since	1.2
	 * @access	public
	 * @param	string
	 * @return
	 */
	public function getFavIcon()
	{
		$obj 			= new stdClass();
		$obj->color		= '#2969B0';
		$obj->icon 		= 'ies-stack';
		$obj->label 	= 'COM_EASYSOCIAL_STREAM_CONTEXT_TITLE_FILES_TOOLTIP';

		return $obj;
	}

	/**
	 * Determines if the app should be displayed in the list
	 *
	 * @since	1.2
	 * @access	public
	 * @param	string
	 * @return
	 */
	public function appListing( $view , $id , $type )
	{
		$group	= FD::group( $id );

		// Determines if this group has access to files
		$access = $group->getAccess();

		if( !$access->get( 'files.enabled' , true ) )
		{
			return false;
		}

		if( $group->isMember() )
		{
			return true;
		}



		return false;
	}

	/**
	 * Triggered to validate the stream item whether should put the item as valid count or not.
	 *
	 * @since	1.2
	 * @access	public
	 * @param	jos_social_stream, boolean
	 * @return  0 or 1
	 */
	public function onStreamCountValidation( &$item, $includePrivacy = true )
	{
		// If this is not it's context, we don't want to do anything here.
		if( $item->context_type != SOCIAL_TYPE_FILES )
		{
			return false;
		}

		// if this is a cluster stream, let check if user can view this stream or not.
		$params 	= FD::registry( $item->params );
		$group 		= FD::group( $params->get( 'group' ) );

		if( !$group )
		{
			return;
		}

		$item->cnt = 1;

		if( $group->type != SOCIAL_GROUPS_PUBLIC_TYPE )
		{
			if( !$group->isMember( FD::user()->id ) )
			{
				$item->cnt = 0;
			}
		}

		return true;
	}

	/**
	 * Processes notifications for files
	 *
	 * @since	1.2
	 * @access	public
	 * @param	string
	 * @return
	 */
	public function onNotificationLoad(&$item)
	{
		$allowed 	= array('files.group.uploaded');

		if (!in_array($item->context_type, $allowed)) {
			return;
		}

		if ($item->type == 'likes' && $item->context_type == 'files.group.uploaded') {

			$hook	= $this->getHook('notification', 'likes');
			$hook->execute($item);
			return;
		}

		if ($item->type == 'comments' && $item->context_type == 'files.group.uploaded') {

			$hook	= $this->getHook('notification', 'comments');
			$hook->execute($item);
			return;
		}
	}

	/**
	 * Processes when user likes a file
	 *
	 * @since	1.2
	 * @access	public
	 * @param	string
	 * @return
	 */
	public function onAfterLikeSave(&$likes)
	{
		$allowed 	= array('files.group.uploaded');

		if (!in_array($likes->type, $allowed)) {
			return;
		}

        // Set the default element.
        $uid        = $likes->uid;
        $data       = explode( '.', $likes->type);
        $element    = $data[0];
        $verb       = $data[2];

		if ($likes->type == 'files.group.uploaded') {

	        // Get the owner of the post.
	        $stream     = FD::table('Stream');
	        $stream->load($likes->stream_id);

	        // Since we have the stream, we can get the group id
	        $group 		= FD::group($stream->cluster_id);

	        // Get the actor
	        $actor      = FD::user($likes->created_by);

	        $emailOptions   = array(
	            'title'     	=> 'APP_GROUP_FILES_EMAILS_LIKE_ITEM_SUBJECT',
	            'template'  	=> 'apps/group/files/like.file.item',
	            'permalink' 	=> $stream->getPermalink(true, true),
	            'actor'     	=> $actor->getName(),
	            'group'			=> $group->getName(),
	            'actorAvatar'   => $actor->getAvatar(SOCIAL_AVATAR_SQUARE),
	            'actorLink'     => $actor->getPermalink(true, true)
	        );

	        $systemOptions  = array(
	            'context_type'  => $likes->type,
	            'context_ids'	=> $stream->cluster_id,
	            'url'           => $stream->getPermalink(false, false, false),
	            'actor_id'      => $likes->created_by,
	            'uid'           => $likes->uid,
	            'aggregate'     => true
	        );

	        // Notify the owner first
	        if ($likes->created_by != $stream->actor_id) {
	        	FD::notify('likes.item', array($stream->actor_id), $emailOptions, $systemOptions);
	        }


	        // Get a list of recipients to be notified for this stream item
	        // We exclude the owner of the note and the actor of the like here
	        $recipients     = $this->getStreamNotificationTargets($likes->uid, $element, 'group', $verb, array(), array($stream->actor_id, $likes->created_by));

	        $emailOptions['title']      = 'APP_GROUP_FILES_EMAILS_LIKE_INVOLVED_SUBJECT';
	        $emailOptions['template']   = 'apps/group/files/like.file.involved';

	        // Notify other participating users
	        FD::notify('likes.involved', $recipients, $emailOptions, $systemOptions);

	        return;
		}
	}

	/**
	 * Processes when user comments on a file
	 *
	 * @since	1.2
	 * @access	public
	 * @param	string
	 * @return
	 */
	public function onAfterCommentSave(&$comment)
	{
		$allowed 	= array('files.group.uploaded');


		if (!in_array($comment->element, $allowed)) {
			return;
		}

		if ($comment->element == 'files.group.uploaded') {

			// Get the stream object
			$stream 	= FD::table('Stream');
			$stream->load($comment->uid);


			$segments 	= explode( '.' , $comment->element );
			$element 	= $segments[0];
	        $verb       = $segments[2];

			// Load up the stream object
			$stream 		= FD::table('Stream');
			$stream->load($comment->stream_id);

			// Get the group object
			$group 			= FD::group($stream->cluster_id);

	        // Get the comment actor
	        $actor          = FD::user($comment->created_by);

	        $emailOptions   = array(
	            'title'     => 'APP_GROUP_FILES_EMAILS_COMMENT_ITEM_SUBJECT',
	            'template'  => 'apps/group/files/comment.file.item',
	            'comment'   => $comment->comment,
	            'group'		=> $group->getName(),
	            'permalink' => $stream->getPermalink(true, true),
	            'actor'    	=> $actor->getName(),
	            'actorAvatar'  => $actor->getAvatar(SOCIAL_AVATAR_SQUARE),
	            'actorLink'    => $actor->getPermalink(true, true)
	        );

	        $systemOptions  = array(
	            'content'       => $comment->comment,
	            'context_type'  => $comment->element,
	            'context_ids'	=> $stream->cluster_id,
	            'url'           => $stream->getPermalink(false, false, false),
	            'actor_id'      => $comment->created_by,
	            'uid'           => $comment->uid,
	            'aggregate'     => true
	        );


	        // Notify the note owner
	        if ($comment->created_by != $stream->actor_id) {
	        	FD::notify('comments.item', array($stream->actor_id), $emailOptions, $systemOptions);
	        }

	        // Get a list of recipients to be notified for this stream item.
	        // We exclude the owner of the note and the actor of the like here
	        $recipients = $this->getStreamNotificationTargets($comment->uid, $element, 'group', $verb, array(), array($stream->actor_id, $comment->created_by));

	        $emailOptions['title']      = 'APP_GROUP_FILES_EMAILS_COMMENT_INVOLVED_SUBJECT';
	        $emailOptions['template']   = 'apps/group/files/comment.file.involved';

	        // Notify participating users
	        FD::notify('comments.involved', $recipients, $emailOptions, $systemOptions);

	        return;
		}
	}

	/**
	 * Prepares the stream item
	 *
	 * @since	1.0
	 * @access	public
	 * @param	SocialStreamItem	The stream object.
	 * @param	bool				Determines if we should respect the privacy
	 */
	public function onPrepareStream( SocialStreamItem &$item, $includePrivacy = true )
	{
		if ($item->context != SOCIAL_TYPE_FILES) {
			return;
		}

		// group access checking
		$group	= FD::group($item->cluster_id);

		if (!$group) {
			return;
		}

		if (!$group->canViewItem()) {
			return;
		}

		// Define standard stream looks
		$item->display 	= SOCIAL_STREAM_DISPLAY_FULL;
		$item->color 	= '#2969B0';
		$item->fonticon = 'ies-stack';
		$item->label 	= JText::_( 'COM_EASYSOCIAL_STREAM_CONTEXT_TITLE_FILES_TOOLTIP' );

		if( $item->verb == 'uploaded' )
		{
			$this->prepareUploadedStream($item);
		}
	}

	/**
	 * Prepares the stream item for new file uploads
	 *
	 * @since	1.0
	 * @access	public
	 * @param	SocialStreamItem	The stream item.
	 * @return
	 */
	private function prepareUploadedStream( &$item )
	{
		$params 	= FD::registry( $item->params );

		// Get the group object
		$group 		= FD::group( $params->get( 'group' ) );

		// Do not allow user to repost files
		$item->repost 	= false;

		// NOTE
		// We do not need to override the likes and comments to pass in the stream id
		// since the stream id is already passed to the likes and comments.

		// Get the file object
		$file 		= FD::table('File');
		$exists 	= $file->load($params->get('file')->id);

		if (!$exists) {
			return;
		}


		// Get the actor
		$actor 		= $item->actor;

		$this->set( 'actor' , $actor );
		$this->set( 'file'	, $file );
		$this->set( 'group'	, $group );

		// Load up the contents now.
		$item->title 	= parent::display( 'streams/uploaded.title' );
		$item->content 	= parent::display( 'streams/uploaded.content' );
	}
}
