<?php
/**
 * Attachments plugins for content
 *
 * @package     Attachments
 * @subpackage  Attachments_Plugin_For_Simplecalendar
 *
 * @author      Fabrizio Albonico <software@albonico.ch>
 * @copyright   Copyright (C) 2009-2015 Fabrizio Albonico, All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @link        http://software.albonico.ch
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/** Load the attachments plugin class */
if (!JPluginHelper::importPlugin('attachments', 'attachments_plugin_framework'))
{
	// Fail gracefully if the Attachments plugin framework plugin is disabled
	return;
}


/**
 * The class for the Attachments plugin for regular Joomla! content (events, categories)
 *
 * @package  Attachments
 * @since    3.0
 */
class AttachmentsPlugin_Com_Simplecalendar extends AttachmentsPlugin
{
	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *
	 * Recognized key values include 'name', 'group', 'params', 'language'
	 * (this list is not meant to be comprehensive).
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		// Configure the plugin
		$this->_name          = 'attachments_for_simplecalendar';

		// Set basic attachments defaults
		$this->parent_type    = 'com_simplecalendar';
		$this->default_entity = 'event';

		// Add the information about the default entity (event)
		$this->entities[]                   = 'event';
		$this->entity_name['event']			= 'event';
		$this->entity_name['default']       = 'event';
		$this->entity_table['event']      	= 'simplecalendar';
		$this->entity_id_field['event']    	= 'id';
		$this->entity_title_field['event'] 	= 'name';

		// Add information about the category description entity
		$this->entities[]                     = 'category';
		$this->entity_name['category']        = 'category';
		$this->entity_table['category']       = 'categories';
		$this->entity_id_field['category']    = 'id';
		$this->entity_title_field['category'] = 'title';

		// Always load the language
		$this->loadLanguage();
	}

	/**
	 * Determine the parent entity
	 *
	 * From the view and the class of the parent (row of onPrepareContent plugin),
	 * determine what the entity type is for this entity.
	 *
	 * @param   &object  &$parent  The object for the parent (row) that onPrepareContent gets
	 *
	 * @return the correct parent entity (eg, 'event', 'category')
	 */
	public function determineParentEntity(&$parent)
	{
		$view = JRequest::getCmd('view');

 		// Handle category calls
		// ??? Harmless until we enable categories
		if (($view == 'category') && (get_class($parent) == 'JTableContent'))
		{
			return 'category';
		}

		// Handle everything else (events)
		// (apparently this is called before parents are displayed so ignore those calls)
		if (isset($parent->id))
		{
			return 'event';
		}

		return false;
	}

 	/**
 	 * Return an array of entity items (with id,title pairs for each item)
 	 *
 	 * @param   string  $parent_entity  the type of entity to search for
 	 * @param   string  $filter         filter the results for matches for this filter string
 	 *
 	 * @return the array of entity id,title pairs
 	 */
 	public function getEntityItems($parent_entity = 'default', $filter = '')
 	{
		// The default(event) is handled cleanly by the base class
		if ( $parent_entity == 'default' OR $parent_entity == 'event' ) {
			return parent::getEntityItems($parent_entity, $filter);
			}

 		$db = JFactory::getDBO();
 
 		$parent_entity      = $this->getCanonicalEntityId($parent_entity);
 		$parent_entity_name = JText::_('ATTACH_' . $parent_entity);
 
		// Note that event is handled separately
		// ??? Harmless for now
		if (JString::strtolower($parent_entity) != 'category')
		{
			$errmsg = JText::sprintf('ATTACH_ERROR_GETTING_LIST_OF_ENTITY_S_ITEMS', $parent_entity_name) . ' (ERR 1400)';
			JError::raiseError(500, $errmsg);
		}
 
 		$entity_table       = $this->entity_table[$parent_entity];
 		$entity_title_field = $this->entity_title_field[$parent_entity];
 		$entity_id_field    = $this->entity_id_field[$parent_entity];
 
 		// Get the ordering information
 		$app       = JFactory::getApplication();
 		$order     = $app->getUserStateFromRequest('com_attachments.selectEntity.filter_order', 'filter_order', '', 'cmd');
 		$order_Dir = $app->getUserStateFromRequest('com_attachments.selectEntity.filter_order_Dir', 'filter_order_Dir', '', 'word');
 
 		// Get all the items
 		$query = $db->getQuery(true);
 		$query->select('*')->from('#__categories');
 
 		// Filter
 		if ($filter)
 		{
 			$filter = $db->quote('%' . $db->escape($filter, true) . '%', false);
 			$query->where('title LIKE ' . $filter);
 		}
 		$query->where('extension=' . $db->quote('com_simplecalendar'));
 
 		// NOTE: Ignore any requested order since only ordering by lft makes the hierarchy work
 		$query->order('lft');
 
 		// Do the query
 		$db->setQuery($query);
 		$items = $db->loadObjectList();
 		if ($db->getErrorNum())
 		{
 			$errmsg = JText::sprintf('ATTACH_ERROR_GETTING_LIST_OF_ENTITY_S_ITEMS', $parent_entity_name) .
 				                     ' (ERR 1401) <br/>' . $db->stderr();
 			JError::raiseError(500, $errmsg);
 		}
 
 		if ($items == null)
 		{
 			return null;
 		}
 
 		// Set up the hierarchy indenting
 		foreach ($items as &$item)
 		{
 			$repeat      = ($item->level - 1 >= 0) ? $item->level - 1 : 0;
 			$item->title = str_repeat('- ', $repeat) . $item->title;
 		}
 
 		return $items;
 	}

	/**
	 * Return the ID of the creator/owner of the parent entity
	 *
	 * @param   int     $parent_id      the ID for the parent object
	 * @param   string  $parent_entity  the type of entity for this parent type
	 *
	 * @return creators id if found, 0 otherwise
	 */
	public function getParentCreatorId($parent_id, $parent_entity = 'default')
	{
		$parent_entity = $this->getCanonicalEntityId($parent_entity);

		$db    = JFactory::getDBO();
		$query = $db->getQuery(true);

		$result = 0;

		// Return the right thing for each entity
		switch ($parent_entity)
		{

			// ??? Disabled
			// 	case 'category':
			// 		$query->select('created_user_id')->from('#__categories')->where('id = ' . (int) $parent_id);
			// 		$db->setQuery($query, 0, 1);
			// 		$result = $db->loadResult();
			// 		if ($db->getErrorNum())
			// 		{
			// 			$errmsg = JText::_('ATTACH_ERROR_CHECKING_CATEGORY_PERMISSIONS') . ' (ERR 1402)';
			// 			JError::raiseError(500, $errmsg);
			// 		}
			// 		break;

			default: // event
				$query->select('created_by')->from('#__simplecalendar')->where('id = ' . (int) $parent_id);
				$db->setQuery($query, 0, 1);
				$result = $db->loadResult();
				if ($db->getErrorNum())
				{
					$errmsg = JText::_('ATTACH_ERROR_CHECKING_EVENT_PERMISSIONS') . ' (ERR 1403)';
					JError::raiseError(500, $errmsg);
				}
		}

		if (is_numeric($result))
		{
			return (int) $result;
		}

		return 0;
	}

	/**
	 * Get a URL to view the content event
	 *
	 * @param   int     $parent_id      the ID for this parent object
	 * @param   string  $parent_entity  the type of parent element/entity
	 *
	 * @return a URL to view the entity (non-SEF form)
	 */
	public function getEntityViewURL($parent_id, $parent_entity = 'default')
	{
		$uri = JFactory::getURI();

		$base_url = $uri->root(true) . '/';

		// Return the right thing for each entity
		switch ($parent_entity)
		{
			// ??? This should eventually work (harmless for now)
			case 'category':
				return $base_url . 'index.php?option=com_simplecalendar&view=events&catid[]=' . $parent_id;
				break;

			default:
				return $base_url . 'index.php?option=com_simplecalendar&view=event&id=' . $parent_id;
		}
	}

	/**
	 * Get a URL to add an attachment to a specific entity
	 *
	 * @param   int     $parent_id      the ID for the parent entity object (null if the parent does not exist)
	 * @param   string  $parent_entity  the type of entity for this parent type
	 * @param   string  $from           where the call should return to
	 *
	 * @return the url to add a new attachments to the specified entity
	 */
	public function getEntityAddUrl($parent_id, $parent_entity = 'default', $from = 'closeme')
	{
		$app = JFactory::getApplication();

		$parent_entity = $this->getCanonicalEntityId($parent_entity);

		// Determine the task
		if ($app->isAdmin())
		{
			$task = 'attachment.add';
		}
		else
		{
			$task = 'upload';
		}

		// Handle event creation
		$url = "index.php?option=com_attachments&task=$task";
		if ($parent_id == null)
		{
			$url .= "&parent_id=$parent_id,new";
		}
		else
		{
			$url .= "&parent_id=$parent_id";
		}

		// Build the right URL for each entity
		switch ($parent_entity)
		{
			// ??? NOT IMPLEMENTED FOR CATEGORY YET
			// case 'category':
			// 				$url .= "&parent_type=com_simplecalendar.$parent_entity&from=$from";
			// 				break;

			default:
				$url .= "&parent_type=com_simplecalendar.event&from=$from";
		}

		return $url;
	}

	/**
	 * Check to see if a custom title applies to this parent
	 *
	 * Note: this function assumes that the parent_id's match
	 *
	 * @param   string  $parent_entity         parent entity for the parent of the list
	 * @param   string  $rtitle_parent_entity  the entity of the candidate attachment list title (from params)
	 *
	 * @return true if the custom title should be used
	 */
	public function checkAttachmentsListTitle($parent_entity, $rtitle_parent_entity)
	{
		if ((($parent_entity == 'default') || ($parent_entity == 'event'))
			&& (($rtitle_parent_entity == 'default') || ($rtitle_parent_entity == 'event')))
		{
			return true;
		}

		// ??? CATEGORY NOT IMPLEMENTED YET
		//  if (($parent_entity == 'category') && ($parent_entity == $rtitle_parent_entity))
		//  {
		//   	return true;
		//  }

		return false;
	}

	/**
	 * Check to see if the parent is published
	 *
	 * @param   int     $parent_id      is the ID for this parent object
	 * @param   string  $parent_entity  the type of entity for this parent type
	 *
	 * @return true if the parent is published
	 */
	public function isParentPublished($parent_id, $parent_entity = 'default')
	{
		$db = JFactory::getDBO();

		$published = false;

		$parent_entity      = $this->getCanonicalEntityId($parent_entity);
		$parent_entity_name = JText::_('ATTACH_' . $parent_entity);

		// Return the right thing for each entity
		switch ($parent_entity)
		{
			// ??? Not implemented yet for categories
			// 	case 'category':
			// 		$entity_table = $this->entity_table[$parent_entity];
			// 		$query        = $db->getQuery(true);
			// 		$query->select('published')->from("#__$entity_table")->where('id = ' . (int) $parent_id);
			// 		$db->setQuery($query, 0, 1);
			// 		$obj = $db->loadObject();
			// 		if ($db->getErrorNum())
			// 		{
			// 			$errmsg = JText::sprintf('ATTACH_ERROR_INVALID_PARENT_S_ID_N', $parent_entity_name, $parent_id) . ' (ERR 1404)';
			// 			JError::raiseError(500, $errmsg);
			// 		}
			// 		if (is_object($obj))
			// 		{
			// 			$published = $obj->published == 1;
			// 		}
			// 		else
			// 		{
			// 			$published = false;
			// 		}
			// 		break;

		default:

			// Check the state for the event (state==1 => published)
			$query = $db->getQuery(true);
			$query->select('state')->from('#__simplecalendar');
			$query->where('id = ' . (int) $parent_id);
			$db->setQuery($query, 0, 1);
			$event = $db->loadObject();
			if ($db->getErrorNum())
			{
				$errmsg = JText::sprintf('ATTACH_ERROR_INVALID_PARENT_S_ID_N', $parent_entity_name, $parent_id) . ' (ERR 1405)';
				JError::raiseError(500, $errmsg);
			}
			else
			{
				if ($event)
				{
					$published =  ($event->state == 1);
				}
				else
				{
					$published = false;
				}
			}
		}

		return $published;
	}

	/**
	 * Return a string of the where clause for filtering the the backend list of attachments
	 *
	 * @param   string  $parent_state   the state ('ALL', 'PUBLISHED', 'UNPUBLISHED', 'ARCHIVED', 'NONE')
	 * @param   string  $filter_entity  the entity filter ('ALL', 'EVENT', 'CATEGORY')
	 *
	 * @return an array of where clauses
	 */
	public function getParentPublishedFilter($parent_state, $filter_entity)
	{
		// If we want all attachments, do no filtering
		if ($parent_state == 'ALL')
		{
			return array();
		}

		$db = JFactory::getDBO();

		$where = array();

		$filter_entity = JString::strtoupper($filter_entity);

		// ??? Disable now, fix later
		return array();


		// NOTE: These WHERE clauses will be combined by OR

		if ($parent_state == 'PUBLISHED')
		{

			if (($filter_entity == 'ALL') || ($filter_entity == 'EVENT'))
			{
				$now      = JFactory::getDate()->toSql();
				$nullDate = $db->getNullDate();
				$where[]  = "EXISTS (SELECT * FROM #__simplecalendar AS c1 " .
					"WHERE (a.parent_entity = 'event' AND c1.id = a.parent_id AND c1.state=1 AND " .
					'(c1.publish_up = ' . $db->quote($nullDate) . ' OR c1.publish_up <= ' . $db->quote($now) . ') AND ' .
					'(c1.publish_down = ' . $db->quote($nullDate) . ' OR c1.publish_down >= ' . $db->quote($now) . ')))';
			}

			if (($filter_entity == 'ALL') || ($filter_entity == 'CATEGORY'))
			{
				$where[] = "EXISTS (SELECT * FROM #__categories AS c2 " .
					"WHERE (a.parent_entity = 'category' AND c2.id = a.parent_id AND c2.published=1))";
			}
		}
		elseif ($parent_state == 'UNPUBLISHED')
		{
			// These WHERE clauses will be combined by OR
			if (($filter_entity == 'ALL') || ($filter_entity == 'EVENT'))
			{
				$where[] = "EXISTS (SELECT * FROM #__simplecalendar AS c1 " .
					"WHERE (a.parent_entity = 'event' AND c1.id = a.parent_id AND c1.state=0))";
				$where[] = "(a.parent_entity = 'event' AND NOT EXISTS (select * from #__simplecalendar as c1 where c1.id = a.parent_id))";
			}

			if (($filter_entity == 'ALL') || ($filter_entity == 'CATEGORY'))
			{
				$where[] = "EXISTS (SELECT * FROM #__categories AS c2 " .
					"WHERE (a.parent_entity = 'category' AND c2.id = a.parent_id AND c2.published=0))";
				$where[] = "(a.parent_entity = 'category' AND NOT EXISTS (select * from #__categories as c1 where c1.id = a.parent_id))";
			}
		}
		elseif ($parent_state == 'ARCHIVED')
		{
			// These WHERE clauses will be combined by OR
			if (($filter_entity == 'ALL') || ($filter_entity == 'EVENT'))
			{
				$where[] = "EXISTS (SELECT * FROM #__simplecalendar AS c1 " .
					"WHERE (a.parent_entity = 'event' AND c1.id = a.parent_id AND c1.state=2))";
			}

			if (($filter_entity == 'ALL') || ($filter_entity == 'CATEGORY'))
			{
				$where[] = "EXISTS (SELECT * FROM #__categories AS c2 " .
					"WHERE (a.parent_entity = 'category' AND c2.id = a.parent_id AND c2.published=2))";
			}
		}
		elseif ($parent_state == 'TRASHED')
		{
			// These WHERE clauses will be combined by OR
			if (($filter_entity == 'ALL') || ($filter_entity == 'EVENT'))
			{
				$where[] = "EXISTS (SELECT * FROM #__simplecalendar AS c1 " .
					"WHERE (a.parent_entity = 'event' AND c1.id = a.parent_id AND c1.state=-2))";
			}

			if (($filter_entity == 'ALL') || ($filter_entity == 'CATEGORY'))
			{
				$where[] = "EXISTS (SELECT * FROM #__categories AS c2 " .
					"WHERE (a.parent_entity = 'category' AND c2.id = a.parent_id AND c2.published=-2))";
			}
		}
		elseif ($parent_state == 'NONE')
		{
			// NOTE: The 'NONE' clauses will be combined with AND (with other tests for a.parent_id)
			$where[] = "(NOT EXISTS( SELECT * FROM #__simplecalendar as c1 " .
				"WHERE a.parent_entity = 'event' AND c1.id = a.parent_id ))";

			$where[] = "(NOT EXISTS( SELECT * FROM #__categories as c2 " .
				"WHERE a.parent_entity = 'category' AND c2.id = a.parent_id ))";
		}
		else
		{
			$errmsg = JText::sprintf('ATTACH_ERROR_UNRECOGNIZED_PARENT_STATE_S', $parent_state);
			JError::raiseError(500, $errmsg);
		}

		return $where;
	}


	/**
	 * May the parent be viewed by the user?
	 *
	 * @param   int     $parent_id      the ID for this parent object
	 * @param   string  $parent_entity  the type of entity for this parent type
	 * @param   object  $user_id        the user_id to check (optional, primarily for testing)
	 *
	 * @return true if the parent may be viewed by the user
	 */
	public function userMayViewParent($parent_id, $parent_entity = 'default', $user_id = null)
	{
		$parent_entity = $this->getCanonicalEntityId($parent_entity);

		// Return the right thing for each entity
		$table = null;
		switch ($parent_entity)
		{
			// ??? Harmless for now
		    case 'category':
				return false;
				$table = 'categories';
				break;

			default:
				// event
				$table = 'simplecalendar';
				break;
		}

		// Get the user's permitted access levels
		$user        = JFactory::getUser($user_id);
		$user_levels = array_unique($user->getAuthorisedViewLevels());

		// See if the parent's access level is permitted for the user
		$db    = JFactory::getDBO();
		$query = $db->getQuery(true);
		$query->select('id')->from("#__$table");
		$query->where('id = ' . (int) $parent_id . ' AND access in (' . implode(',', $user_levels) . ')');
		$db->setQuery($query, 0, 1);
		$obj = $db->loadObject();
		if ($db->getErrorNum())
		{
			$parent_entity_name = JText::_('ATTACH_' . strtoupper($parent_entity));
			$errmsg             = JText::sprintf('ATTACH_ERROR_INVALID_PARENT_S_ID_N', $parent_entity_name, $parent_id) . ' (ERR 1408)';
			JError::raiseError(500, $errmsg);
		}

		return !empty($obj);
	}

	/** Return true if the attachments should be hidden for this parent
	 *
	 * @param   &object  &$parent        the object for the parent that onPrepareContent gives
	 * @param   int      $parent_id      the ID of the parent the attachment is attached to
	 * @param   string   $parent_entity  the type of entity for this parent type
	 *
	 * @return true if the attachments should be hidden for this parent
	 */
	public function attachmentsHiddenForParent(&$parent, $parent_id, $parent_entity)
	{
		$app = JFactory::getApplication('site');
		
		// Check for generic options
		if (parent::attachmentsHiddenForParent($parent, $parent_id, $parent_entity))
		{
			return true;
		}

		$parent_entity      = $this->getCanonicalEntityId($parent_entity);
		$parent_entity_name = JText::_('ATTACH_' . strtoupper($parent_entity));

		// Make sure we have a valid parent ID
		if ( $parent_entity == 'category' )
		{
			// ??? CATEGORIES NOT ENABLED YET
			// ??? $parent_id = JRequest::getInt('catid');
			return true;
		}

		if ($parent_id !== 0)
		{
			// Note: parent_id of 0 may be allowed for categories, so don't abort
			if (($parent_id == null) || ($parent_id == '') || !is_numeric($parent_id))
			{
				$errmsg = JText::sprintf('ATTACH_ERROR_BAD_ENTITY_S_ID', $parent_entity_name) . ' (ERR 1409)';
				JError::raiseError(500, $errmsg);
			}
		}

		// Check to see if it should be hidden with readmore
		// ??? PROBABLY NOT APPLICABLE  (JMC)
		$aparams = $this->attachmentsParams();
		$hide_with_readmore = $aparams->get('hide_with_readmore', false);
		if ($hide_with_readmore && isset($parent->readmore) && $parent->readmore)
		{
			return true;
		}

		// Get the options
		$all_but_event_views = $aparams->get('hide_except_event_views', false);

		// Make sure the parent is valid and get info about it
		$db = JFactory::getDBO();

		if ($parent_entity == 'category')
		{
 			// ??? Handle categories (harmless for now)
			$always_show_category_attachments = $aparams->get('always_show_category_attachments', false);
			if ($always_show_category_attachments)
			{
				return false;
			}
			if ($all_but_event_views)
			{
				return true;
			}

			// Check to see whether the attachments should be hidden for this category
			$hide_attachments_for_categories = $aparams->get('hide_attachments_for_categories', Array());
			if (in_array($parent_id, $hide_attachments_for_categories))
			{
				return true;
			}

			// Double-check that there is no mismatch of category ID (??? May not be necessary)
			$description = $parent->text;
			$query       = $db->getQuery(true);
			$query->select('id')->from('#__categories');
			$query->where('description=' . $db->quote($description) . ' AND id = ' . (int) $parent_id);
			$db->setQuery($query, 0, 1);
			if ((int) $parent_id != (int) $db->loadResult())
			{
				return true;
			}
		}
		else
		{
			// Handle events
			if ($parent_id == 0)
			{
				return false;
			}

			// Make sure we have a valid event
			$query = $db->getQuery(true);
			$query->select('created_by, catid')->from('#__simplecalendar')->where('id = ' . (int) $parent_id);
			$db->setQuery($query);
			$attachments = $db->loadObjectList();
			if ($db->getErrorNum() || (count($attachments) == 0))
			{
				$errmsg = JText::sprintf('ATTACH_ERROR_INVALID_PARENT_S_ID_N', $parent_entity_name, $parent_id) . ' (ERR 1410)';
				JError::raiseError(500, $errmsg);
			}

			// Honor all_but_event_view option
			$view = JRequest::getCmd('view');
			if ($all_but_event_views)
			{
				if ($view != 'event')
				{
					return true;
				}
			}

			// Check to see whether the attachments should be hidden for this category
			$catid  = (int) $attachments[0]->catid;
			$hide_attachments_for_categories = $aparams->get('hide_attachments_for_categories', Array());
			if (in_array($catid, $hide_attachments_for_categories))
			{
				return true;
			}
		}

		// The default is: attachments are not hidden
		return false;
	}


	/**
	 * Determine if a user can edit a specified event
	 *
	 * Partially based on allowEdit() in com_content/controllers/event.php
	 *
	 * @param  integer $event_id the ID for the event to be tested
	 * @param  integer $id	The id of the user to load (defaults to null)
	 */
	public static function userMayEditEvent($event_id, $user_id = null)
	{
		$user = JFactory::getUser($user_id);

		// Check general edit permission first.
		if ($user->authorise('core.edit', 'com_simplecalendar')) {
			return true;
		}

		// Check specific edit permission.
		if ($user->authorise('core.edit', 'com_simplecalendar.event.'.$event_id)) {
			return true;
		}

		// Check for event being created.
		// NOTE: we must presume that the event is being created by this user!
		if ( ((int)$event_id == 0) && $user->authorise('core.edit.own', 'com_simplecalendar') ) {
			return true;
			}

		// No general permissions, see if 'edit own' is permitted for this event
		if ( $user->authorise('core.edit.own', 'com_simplecalendar') ||
			 $user->authorise('core.edit.own', 'com_simplecalendar.event.'.$event_id) ) {

			// Yes user can 'edit.own', Find out if the user created the event
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			$query->select('id')->from('#__simplecalendar');
			$query->where('id = '.(int)$event_id.' AND created_by = '.(int)$user->id);
			$db->setQuery($query, 0, 1);
			$results = $db->loadObject();
			if ($db->getErrorNum()) {
				$errmsg = JText::_('ATTACH_ERROR_CHECKING_EVENT_OWNERSHIP') . ' (ERR 107)';
				JError::raiseError(500, $errmsg);
				}

			if ( !empty($results) ) {
				// The user did actually create the event
				return true;
				}
			}

		return false;
	}


	/**
	 * Return true if the user may add an attachment to this parent
	 *
	 * (Note that all of the arguments are assumed to be valid; no sanity checking is done.
	 *	It is up to the caller to validate these objects before calling this function.)
	 *
	 * @param   int     $parent_id      the ID of the parent the attachment is attached to
	 * @param   string  $parent_entity  the type of entity for this parent type
	 * @param   bool    $new_parent     if true, the parent is being created and does not exist yet
	 * @param   object  $user_id        the user_id to check (optional, primarily for testing)
	 *
	 * @return true if this user may add attachments to this parent
	 */
	public function userMayAddAttachment($parent_id, $parent_entity, $new_parent = false, $user_id = null)
	{
		require_once JPATH_ADMINISTRATOR . '/components/com_attachments/permissions.php';

		$user = JFactory::getUser($user_id);

		// Handle each entity type
		$parent_entity = $this->getCanonicalEntityId($parent_entity);

		switch ($parent_entity)
		{
			case 'category':

				// First, determine if the user can edit this category
				if (!AttachmentsPermissions::userMayEditCategory($parent_id))
				{
					return false;
				}

				// Finally, see if the user has permissions to create attachments
				return $user->authorise('core.create', 'com_attachments');

				break;

			default:
				// For events

				// First, determine if the user can edit this event
				if (!$this->userMayEditEvent($parent_id))
				{
					return false;
				}

				// Finally, see if the user has general permissions to create attachments
				return $user->authorise('core.create', 'com_attachments');
		}

		// No one else is allowed to add attachments
		return false;
	}

	/**
	 * Return true if this user may edit (modify/update/delete) this attachment for this parent
	 *
	 * (Note that all of the arguments are assumed to be valid; no sanity checking is done.
	 *	It is up to the caller to validate these objects before calling this function.)
	 *
	 * @param   &record  &$attachment  database reocrd for the attachment
	 * @param   object   $user_id      the user_id to check (optional, primarily for testing)
	 *
	 * @return true if this user may edit this attachment
	 */
	public function userMayEditAttachment(&$attachment, $user_id = null)
	{
		// If the user generally has permissions to edit all content, they
		// may edit this attachment (editor, publisher, admin, etc)
		$user = JFactory::getUser($user_id);
		if ($user->authorise('com_simplecalendar', 'edit', 'content', 'all'))
		{
			return true;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_attachments/permissions.php';

		// Handle each entity type

		switch ($attachment->parent_entity)
		{

			case 'category':

				// ?? Deal with parents being created (parent_id == 0)

				// First, determine if the user can edit this category
				if (!AttachmentsPermissions::userMayEditCategory($attachment->parent_id))
				{
					return false;
				}

				// See if the user can edit any attachment
				if ($user->authorise('core.edit', 'com_attachments'))
				{
					return true;
				}

				// See if the user has permissions to edit their own attachments
				if ($user->authorise('core.edit.own', 'com_attachments') && ((int) $user->id == (int) $attachment->created_by))
				{
					return true;
				}

				// See if the user has permission to edit attachments on their own cateogory
				if ($user->authorise('attachments.edit.ownparent', 'com_attachments'))
				{
					$category_creator_id = $this->getParentCreatorId($attachment->parent_id, 'category');
					return (int) $user->id == (int) $category_creator_id;
				}

				break;

			default:
				// events

				// ??? Deal with parents being created (parent_id == 0)

				// First, determine if the user can edit this event
				if (!$this->userMayEditEvent($attachment->parent_id))
				{
					return false;
				}

				// See if the user can edit any attachment
				if ($user->authorise('core.edit', 'com_attachments'))
				{
					return true;
				}

				// See if the user has permissions to edit their own attachments
				if ($user->authorise('core.edit.own', 'com_attachments') && ((int) $user->id == (int) $attachment->created_by))
				{
					return true;
				}

				// See if the user has permission to edit attachments on their own event
				if ($user->authorise('attachments.edit.ownparent', 'com_attachments'))
				{
					$event_creator_id = $this->getParentCreatorId($attachment->parent_id, 'event');
					return (int) $user->id == (int) $event_creator_id;
				}
		}

		return false;
	}

	/**
	 * Return true if this user may delete this attachment for this parent
	 *
	 * (Note that all of the arguments are assumed to be valid; no sanity checking is done.
	 *	It is up to the caller to validate the arguments before calling this function.)
	 *
	 * @param   &record  &$attachment  database record for the attachment
	 * @param   object   $user_id      the user_id to check (optional, primarily for testing)
	 *
	 * @return true if this user may delete this attachment
	 */
	public function userMayDeleteAttachment(&$attachment, $user_id = null)
	{
		// If the user generally has permissions to edit ALL content, they
		// may edit this attachment (editor, publisher, admin, etc)
		$user = JFactory::getUser($user_id);
		if ($user->authorise('com_simplecalendar', 'edit', 'event', 'all'))
		{
			return true;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_attachments/permissions.php';

		// Handle each entity type

		switch ($attachment->parent_entity)
		{

			case 'category':

				// First, determine if the user can edit this category
				if (!AttachmentsPermissions::userMayEditCategory($attachment->parent_id))
				{
					return false;
				}

				// Ok if the user can delete any attachment
				if ($user->authorise('core.delete', 'com_attachments'))
				{
					return true;
				}

				// See if the user has edit.own and created it
				if ($user->authorise('attachments.delete.own', 'com_attachments') && ((int) $user->id == (int) $attachment->created_by))
				{
					return true;
				}

				// See if the user has permission to delete any attachments for categories they created
				if ($user->authorise('attachments.delete.ownparent', 'com_attachments'))
				{
					$category_creator_id = $this->getParentCreatorId($attachment->parent_id, 'category');
					return (int) $user->id == (int) $category_creator_id;
				}

				break;

			default: // events

				// ?? Deal with parents being created (parent_id == 0)

				// First, determine if the user can edit this event
				if (!$this->userMayEditEvent($attachment->parent_id))
				{
					return false;
				}

				// Ok if the user can delete any attachment
				if ($user->authorise('core.delete', 'com_attachments'))
				{
					return true;
				}

				// See if the user has permissions to delete their own attachments
				if ($user->authorise('attachments.delete.own', 'com_attachments') && ((int) $user->id == (int) $attachment->created_by))
				{
					return true;
				}

				// See if the user has permission to delete any attachments for events they created
				if ($user->authorise('attachments.delete.ownparent', 'com_attachments'))
				{
					$event_creator_id = $this->getParentCreatorId($attachment->parent_id, 'event');
					return (int) $user->id == (int) $event_creator_id;
				}
		}

		return false;
	}

	/**
	 * Return true if this user may change the state of this attachment
	 *
	 * (Note that all of the arguments are assumed to be valid; no sanity checking is done.
	 *	It is up to the caller to validate the arguments before calling this function.)
	 *
	 * @param   int     $parent_id              the ID for the parent object
	 * @param   string  $parent_entity          the type of entity for this parent type
	 * @param   int     $attachment_creator_id  the ID of the creator of the attachment
	 * @param   object  $user_id                the user_id to check (optional, primarily for testing)
	 *
	 * @return true if this user may change the state of this attachment
	 */
	public function userMayChangeAttachmentState($parent_id, $parent_entity, $attachment_creator_id, $user_id = null)
	{
		// If the user generally has permissions to edit all content, they
		// may change this attachment state (editor, publisher, admin, etc)
		$user = JFactory::getUser($user_id);
		if ($user->authorise('com_simplecalendar', 'edit', 'event', 'all'))
		{
			return true;
		}

		require_once JPATH_ADMINISTRATOR . '/components/com_attachments/permissions.php';

		// Handle each entity type

		switch ($parent_entity)
		{

			case 'category':

				// ?? Deal with parents being created (parent_id == 0)

				// First, determine if the user can edit this category
				if (!AttachmentsPermissions::userMayEditCategory($parent_id))
				{
					return false;
				}

				// See if the user can change the state of any attachment
				if ($user->authorise('core.edit.state', 'com_attachments'))
				{
					return true;
				}

				// See if the user has permissions to change the state of their own attachments
				if ($user->authorise('attachments.edit.state.own', 'com_attachments') && ((int) $user->id == (int) $attachment_creator_id))
				{
					return true;
				}

				// See if the user has permission to change the state of any attachments for categories they created
				if ($user->authorise('attachments.edit.state.ownparent', 'com_attachments'))
				{
					$category_creator_id = $this->getParentCreatorId($parent_id, 'category');
					return (int) $user->id == (int) $category_creator_id;
				}

				break;

			default:
				// events

				// ?? Deal with parents being created (parent_id == 0)

				// First, determine if the user can edit this event
				if (!$this->userMayEditEvent($parent_id))
				{
					return false;
				}

				// See if the user can change the state of any attachment
				if ($user->authorise('core.edit.state', 'com_attachments'))
				{
					return true;
				}

				// See if the user has permissions to change the state of their own attachments
				if ($user->authorise('attachments.edit.state.own', 'com_attachments') && ((int) $user->id == (int) $attachment_creator_id))
				{
					return true;
				}

				// See if the user has permission to edit the state of any attachments for events they created
				if ($user->authorise('attachments.edit.state.ownparent', 'com_attachments'))
				{
					$event_creator_id = $this->getParentCreatorId($parent_id, 'event');
					return (int) $user->id == (int) $event_creator_id;
				}
		}

		return false;
	}

	/** Check to see if the user may access (see/download) the attachments
	 *
	 * @param   &record  &$attachment  database record for the attachment
	 * @param   object   $user_id      the user_id to check (optional, primarily for testing)
	 *
	 * @return true if access is okay (false if not)
	 */
	public function userMayAccessAttachment(&$attachment, $user_id = null)
	{
		$user = JFactory::getUser($user_id);
		return in_array($attachment->access, $user->getAuthorisedViewLevels());
	}

	/** See if the attachments list should be displayed in its content description editor
	 *
	 * @param   string  $parent_entity  the type of entity for this parent type
	 * @param   string  $view           the view
	 * @param   string  $layout         the layout on the view
	 *
	 * @return  true if the attachments list should be added to the editor
	 */
	public function showAttachmentsInEditor($parent_entity, $view, $layout)
	{
		$app = JFactory::getApplication();
		if ($app->isAdmin())
		{
			return ($view == 'event') AND ($layout =='edit');
		}
		else
		{
			return ($view == 'form') AND (($layout =='list') OR ($layout='edit'));
		}
	}


	/** Get the parent_id in the component content item editor
	 *  (the article or category editor)
	 *
	 * @param  string  $parent_entity  the type of entity for this parent type
	 *
	 * @return the parent ID, null if the content item is being created, and false if there is no match
	 *
	 * @since    Attachments 3.2
	 */
	public function getParentIdInEditor($parent_entity, $view, $layout)
	{
		$app = JFactory::getApplication();
		if ($app->isAdmin()) {
			// The default works fine for the back end
			return parent::getParentIdInEditor($parent_entity, $view, $layout);
			}
		// Note categories cannot be created or edited from the frontend
		if ($parent_entity == 'category') {
			return false;
			}

		// Deal with articles (in frontend)
		$id = null;
		if (($view == 'event') OR ($view == 'form')) {
			$id = JRequest::getInt('id', $default=null);
			}
		else {
			$id = false;
			}

		// If we got one, convert it to an int
		if (is_numeric($id)) {
			$id = (int)$id;
			}

		return $id;
	}


}


/** Register this attachments type */
$apm = getAttachmentsPluginManager();
$apm->addParentType('com_simplecalendar');
