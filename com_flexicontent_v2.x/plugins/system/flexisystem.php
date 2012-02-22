<?php
/**
 * @version 1.5 stable $Id: flexisystem.php 659 2011-07-18 09:06:39Z ggppdk $
 * @plugin 1.1
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * Example system plugin
 */
class plgSystemFlexisystem extends JPlugin
{
	/**
	 * Constructor
	 */
	function plgSystemFlexisystem( &$subject, $config )
	{
		parent::__construct( $subject, $config );

		require_once (JPATH_SITE.DS.'administrator'.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');
		JPlugin::loadLanguage('com_flexicontent', JPATH_SITE);
	}
        
	function onAfterInitialise()
	{
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);
		$fparams 	=& JComponentHelper::getParams('com_flexicontent');
		
		if (!empty($username) && !empty($password) && $fparams->get('autoflogin', 0)) {
			$result = $this->loginUser();
		}
		return;	
	}
	
	function onAfterRoute()
	{
		// ensure the PHP version is correct
		if (version_compare(PHP_VERSION, '5.0.0', '<')) return;
		
		$option = JRequest::getCMD('option');
		$view   = JRequest::getVar('view', '');
		$layout = JRequest::getVar('layout', '');
		$tmpl   = JRequest::getVar('tmpl', '');
		if(($option=='com_content')&&($layout=='pagebreak')) return;
		//if(($option=='com_content')&&($view=='articles')&&($layout=='modal')&&($tmpl=='component')) return;

		$this->trackSaveConf();
		if (FLEXI_SECTION || FLEXI_CAT_EXTENSION) {
			global $globalcats;
			if (FLEXI_CACHE) {
				// add the category tree to categories cache
				$catscache 	=& JFactory::getCache('com_flexicontent_cats');
				$catscache->setCaching(1); 		//force cache
				$catscache->setLifeTime(84600); //set expiry to one day
				$globalcats = $catscache->call(array('plgSystemFlexisystem', 'getCategoriesTree'));
			} else {
				$globalcats = $this->getCategoriesTree();
			}
		}

		$this->redirectAdminComContent();
		$this->redirectSiteComContent();
	}
	
	/*
	 * TODO NOT WORKING PROPERLY !!!!!!!!!!!!!!
	 */
	function redirectAdminComContent()
	{
		$app 				=& JFactory::getApplication();
		$option 			= JRequest::getCMD('option');
		$applicationName 	= $app->getName();
		$user 				=& JFactory::getUser();

		$mincats			= $this->params->get('redirect_cats', array());
		if (!is_array($mincats)) $mincats = array($mincats);
		$minarts			= $this->params->get('redirect_articles', array());
		if (!is_array($minarts)) $minarts = array($minarts);
		$usergroups = array_keys($user->get('groups'));

		if (!empty($option)) {
			// if try to access com_content you get redirected to Flexicontent items
			if ($option == 'com_content' && $applicationName == 'administrator' ) {
				//get task execution
				$task = JRequest::getCMD('task');
				// url to redirect
				$urlItems = 'index.php?option=com_flexicontent';

				if ($task == 'edit') {
					$cid = JRequest::getVar('id');
					$cid = $cid ? $cid : JRequest::getVar('cid');
					$urlItems .= '&controller=items&task=edit&cid='.intval(is_array($cid) ? $cid[0] : $cid);
				} else if ($task == 'element') {
					$urlItems .= '&view=itemelement&tmpl=component&object='.JRequest::getVar('object','');
				} else {
					$urlItems .= '&view=items';
				}
				/*$redirect = false;
				if(in_array('-1', $minarts)) {
					$redirect = false;
				}elseif(in_array('-2', $minarts)) {
					$redirect = true;
				}*/ $redirect = true; // TODO FIX
				if($redirect) {
					$app->redirect($urlItems,'');
				}elseif(count(array_intersect($usergroups, $minarts))>0) {
					$app->redirect($urlItems,'');
				}
				return false;

			} elseif ($option == 'com_categories' && $applicationName == 'administrator' ) {
				// url to redirect
				$urlItems = 'index.php?option=com_flexicontent&view=categories';

				$extension = JRequest::getVar('extension');
				/*$redirect = false;
				if(in_array('-1', $mincats)) {
					$redirect = false;
				}elseif(in_array('-2', $mincats)) {
					$redirect = true;
				}*/ $redirect = true; // TODO FIX
				if($redirect  && ($extension == 'com_content')) {
					$app->redirect($urlItems,'');
				}elseif((count(array_intersect($usergroups, $mincats))>0) && ($extension == 'com_content')) {
					$app->redirect($urlItems,'');
				}
				return false;
			}
		}
	}
	
	function redirectSiteComContent()
	{
		//include the route helper
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		//get the section associated with flexicontent
		$flexiparams 	=& JComponentHelper::getParams('com_flexicontent');
		$flexisection 	= $flexiparams->get('flexi_section');
		
		$app 				=& JFactory::getApplication();
		$option 			= JRequest::getCMD('option');
		$applicationName 	= $app->getName();
		$db 				=& JFactory::getDBO();
		
		if( !empty($option) ){

			if($option == 'com_content' && $applicationName == 'site') {

				$view = JRequest::getCMD('view');

				if( $view == 'article' ){
					$id 		= JRequest::getInt('id');
					$itemslug 	= JRequest::getVar('id');
					$catslug	= JRequest::getVar('catid');
					// Warning current menu item id must not be passed to the routing functions since it points to com_content , and thus it will break FC SEF URLs
					$urlItem 	= $catslug ? FlexicontentHelperRoute::getItemRoute($itemslug, $catslug) : FlexicontentHelperRoute::getItemRoute($itemslug);
					
					$query 	= 'SELECT sectionid FROM #__content'
							. ' WHERE id = ' . $id
							;
					$db->setQuery($query);
					$section = $db->loadResult();

					//if ($section == $flexisection) {
						$app->redirect($urlItem);
						return false;
					//}
				}
			}
		}
	}
	
	function getCategoriesTree()
	{
		global $globalcats;

		$db		=& JFactory::getDBO();

		// get the category tree and append the ancestors to each node		
		$query	= 'SELECT id, parent_id, published, access, title, level, lft, rgt,'
				. ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug'
				. ' FROM #__categories as c'
				. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND lft > ' . FLEXI_LFT_CATEGORY . ' AND rgt < ' . FLEXI_RGT_CATEGORY
				. ' ORDER BY parent_id, lft'
				;
		$db->setQuery($query);
		$cats = $db->loadObjectList();

		//establish the hierarchy of the categories
		$children = array();
		$parents = array();
		
		//set depth limit
   		$levellimit = 10;
		foreach ($cats as $child) {
			$parent = $child->parent_id;
			if ($parent) $parents[] = $parent;
			$list 	= @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
		$parents = array_unique($parents);

		//get list of the items
		$globalcats = plgSystemFlexisystem::_getCatAncestors($ROOT_CATEGORY_ID=1, '', array(), $children, true, max(0, $levellimit-1));

		foreach ($globalcats as $cat) {
			$cat->ancestorsonlyarray	= $cat->ancestors;
			$cat->ancestorsonly			= implode(',', $cat->ancestors);
			$cat->ancestors[] 			= $cat->id;
			$cat->ancestorsarray		= $cat->ancestors;
			$cat->ancestors				= implode(',', $cat->ancestors);
			$cat->descendantsarray		= plgSystemFlexisystem::_getDescendants(array($cat));
			$cat->descendants			= implode(',', $cat->descendantsarray);
		}
		return $globalcats;
	}

	/**
	    * Get the ancestors of each category node
	    *
	    * @access private
	    * @return array
	    */
	function _getCatAncestors( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1, $ancestors=null )
	{
		if (!$ancestors) $ancestors = array();
		
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;
				
				if ((!in_array($v->parent_id, $ancestors)) && $v->parent_id != ($ROOT_CATEGORY_ID=1)) {
					$ancestors[] 	= $v->parent_id;
				} 
				
				if ( $type ) {
					$pre    = '<sup>|_</sup>&nbsp;';
					$spacer = '.&nbsp;&nbsp;&nbsp;';
				} else {
					$pre    = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ( $v->parent_id == 0 ) {
						$txt    = ''.$v->title;
					} else {
						$txt    = $pre.$v->title;
					}
				} else {
					if ( $v->parent_id == 0 ) {
						$txt    = '';
					} else {
						$txt    = $pre;
					}
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename 		= "$indent$txt";
				$list[$id]->title 			= $v->title;
				$list[$id]->slug 			= $v->slug;
				$list[$id]->ancestors 		= $ancestors;
				$list[$id]->childrenarray 	= @$children[$id];

				$list[$id]->children 		= count( @$children[$id] );

				$list = plgSystemFlexisystem::_getCatAncestors( $id, $indent.$spacer, $list, $children, $title, $maxlevel, $level+1, $type, $ancestors );
			}
		}
		return $list;
	}
	
	/**
    * Get the descendants of each category node
    *
    * @access private
    * @return array
    */
	function _getDescendants($arr, &$descendants = array())
	{
		foreach($arr as $k => $v)
		{
			$descendants[] = $v->id;
		
			if ($v->childrenarray) {
				plgSystemFlexisystem::_getDescendants($v->childrenarray, $descendants);
			}
		}
		return $descendants;
	}
	
	/**
    * Detect if the config was altered to clean the category cache
    *
    * @access public
    * @return void
    */
	function trackSaveConf() 
	{
		$option 	= JRequest::getVar('option');
		$component 	= JRequest::getVar('component');
		$task 		= JRequest::getVar('task');
		
		if ($option == 'com_config' && $component == 'com_flexicontent' && $task == 'save') {
			$catscache 	=& JFactory::getCache('com_flexicontent_cats');
			$catscache->clean();
		}
	}
	
	function loginUser() 
	{
		$mainframe =& JFactory::getApplication();
		$username	= JRequest::getVar('fcu', null);
		$password	= JRequest::getVar('fcp', null);

		jimport('joomla.user.helper');
		
		$db =& JFactory::getDBO();
		$query 	= 'SELECT id, password, gid'
				. ' FROM #__users'
				. ' WHERE username = ' . $db->Quote( $username )
				. ' AND password = ' . $db->Quote( $password )
				;
		$db->setQuery( $query );
		$result = $db->loadObject();
		
		if($result)
		{
			JPluginHelper::importPlugin('user');		
			$response->username = $username;
			$response->password = $password;
			$options = isset($options) ? $options : array();
			$result = $mainframe->triggerEvent('onLoginUser', array((array)$response,$options));
		}

		return;
	}

	// Function by decide type of user
	function getUserType() {
    
    // Joomla default user groups
    $author_grp = 3;
    $editor_grp = 4;
    $publisher_grp = 5;
    $manager_grp = 6;
    $admin_grp = 7;
    $super_admin_grp = 8;
    
    $user = &JFactory::getUser();
    $coreUserGroups = $user->getAuthorisedGroups();
    // $coreViewLevels = $user->getAuthorisedViewLevels();
    $aid = max ($user->getAuthorisedViewLevels());
    
    $access = '';
    if ($aid == 1)
    	$access = 'public'; // public
    if ($aid == 2 || $aid > 3)
    	$access = 'registered'; // registered user or member of custom joomla group
    if ($aid == 3
    	|| in_array($author_grp,$coreUserGroups)  	|| in_array($editor_grp,$coreUserGroups)
    	|| in_array($publisher_grp,$coreUserGroups)	|| in_array($manager_grp,$coreUserGroups)
    	|| max($coreUserGroups)>8
    )
    	$access = 'special'; // special user
    if (in_array($admin_grp,$coreUserGroups))
    	$access = 'admin'; // is admin user
    if (in_array($super_admin_grp,$coreUserGroups))
    	$access = 'superadmin'; // is super admin user
    
    return $access;
  }
	
}
