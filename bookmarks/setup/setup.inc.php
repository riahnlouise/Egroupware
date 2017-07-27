<?php
/**
 * EGroupware - Bookmarks
 *
 * Based on Bookmarker Copyright (C) 1998  Padraic Renaghan
 *                     http://www.renaghan.com/bookmarker
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

/* Basic information about this app */
$setup_info['bookmarks']['name']      = 'bookmarks';
$setup_info['bookmarks']['title']     = 'Bookmarks';
$setup_info['bookmarks']['version']   = '16.1';
$setup_info['bookmarks']['app_order'] = '12';
$setup_info['bookmarks']['enable']    = 1;
$setup_info['bookmarks']['index']     = 'bookmarks.bookmarks_ui.init&ajax=true';

$setup_info['bookmarks']['author'] = 'Joseph Engo';
$setup_info['bookmarks']['license']  = 'GPL';
$setup_info['bookmarks']['description'] =
	'Manage your bookmarks with EGroupware.';
$setup_info['bookmarks']['maintainer'] = array(
	'name' => 'eGroupWare Developers',
	'email' => 'egroupware-developers@lists.sourceforge.net'
);

/* The tables this app creates */
$setup_info['bookmarks']['tables'][] = 'egw_bookmarks';
$setup_info['bookmarks']['tables'][] = 'egw_bookmarks_extra';

/* The hooks this app includes, needed for hooks registration */
$setup_info['bookmarks']['hooks']['preferences'] = 'bookmarks_hooks::all_hooks';
$setup_info['bookmarks']['hooks']['settings'] = 'bookmarks_hooks::settings';
$setup_info['bookmarks']['hooks']['admin'] = 'bookmarks_hooks::all_hooks';
$setup_info['bookmarks']['hooks']['sidebox_menu'] = 'bookmarks_hooks::all_hooks';
$setup_info['bookmarks']['hooks']['search_link'] = 'bookmarks_hooks::search_link';
$setup_info['bookmarks']['hooks']['acl_rights'] = 'bookmarks_hooks::acl_rights';
$setup_info['bookmarks']['hooks']['categories'] = 'bookmarks_hooks::categories';
$setup_info['bookmarks']['hooks']['delete_category'] = 'bookmarks_hooks::delete_category';

/* Dependencies for this app to work */
$setup_info['bookmarks']['depends'][] = array(
	'appname'  => 'api',
	'versions' => Array('16.1')
);
