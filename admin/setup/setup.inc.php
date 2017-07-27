<?php
/**
 * EGroupware - Admin
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

$setup_info['admin']['name']      = 'admin';
$setup_info['admin']['version']   = '16.1';
$setup_info['admin']['app_order'] = 1;
$setup_info['admin']['tables']    = array('egw_admin_queue','egw_admin_remote');
$setup_info['admin']['enable']    = 1;
$setup_info['admin']['index']   = 'admin.admin_ui.index&ajax=true';

$setup_info['admin']['author'][] = array(
	'name'  => 'eGroupWare coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net'
);

$setup_info['admin']['maintainer'][] = array(
	'name'  => 'eGroupWare coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net',
	'url'   => 'www.egroupware.org'
);

$setup_info['admin']['license']  = 'GPL';
$setup_info['admin']['description'] = 'EGroupware administration application';

/* The hooks this app includes, needed for hooks registration */
$setup_info['admin']['hooks'] = array(
	'acl_manager',
	'config_validate',
);
$setup_info['admin']['hooks']['admin'] = 'admin_hooks::all_hooks';
$setup_info['admin']['hooks']['sidebox_menu'] = 'admin_hooks::all_hooks';
$setup_info['admin']['hooks']['edit_user'] = 'admin_hooks::edit_user';
$setup_info['admin']['hooks']['config'] = 'admin_hooks::config';

// add account tab to addressbook.edit
$setup_info['admin']['hooks']['addressbook_edit'] = 'admin.admin_account.addressbook_edit';

// Dependencies for this app to work
$setup_info['admin']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('16.1')
);
