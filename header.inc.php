<?php
/**
 * EGroupware - configuration file
 *
 * Use EGroupware's setup to create or edit this configuration file.
 * You do NOT need to copy and edit this file manually!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker@outdoor-training.de
 * (This file was originaly written by Dan Kuykendall)
 * @version $Id$
 */

// eGW install dir, need to be changed if you copy the server to an other directory
define('EGW_SERVER_ROOT','/home/tcbccomp/maria.tcbc.com.ph');

// other pathes depending on the one above
define('EGW_INCLUDE_ROOT',EGW_SERVER_ROOT);
define('EGW_API_INC',EGW_INCLUDE_ROOT.'/phpgwapi/inc');

// who is allowed to make changes to THIS config file via eGW's setup
$GLOBALS['egw_info']['server']['header_admin_user'] = 'mariaadmin';
$GLOBALS['egw_info']['server']['header_admin_password'] = '00baffc35e0760f52e63a58a04a3f006';

// restrict the access to setup to certain (comma separated) IPs or domains
$GLOBALS['egw_info']['server']['setup_acl'] = '';

/* eGroupWare domain-specific db settings */
$GLOBALS['egw_domain']['default'] = array(
	'db_host' => 'localhost',
	'db_port' => '3306',
	'db_name' => 'tcbccomp_grwmari',
	'db_user' => 'tcbccomp_grwmari',
	'db_pass' => 'V[W1S87-Yp',
	// Look at the README file
	'db_type' => 'mysqli',
	// This will limit who is allowed to make configuration modifications
	'config_user'   => 'mariaadmin',
	'config_passwd' => '00baffc35e0760f52e63a58a04a3f006'
);


/*
** If you want to have your domains in a select box, change to True
** If not, users will have to login as user@domain
** Note: This is only for virtual domain support, default domain users (that's everyone
** form the first domain or if you have only one) can login only using just there loginid.
*/
$GLOBALS['egw_info']['server']['show_domain_selectbox'] = false;

$GLOBALS['egw_info']['server']['db_persistent'] = true;

/* This is used to control mcrypt's use */
$GLOBALS['egw_info']['server']['mcrypt_enabled'] = false;

/*
** This is a random string used as the initialization vector for mcrypt
** feel free to change it when setting up eGrouWare on a clean database,
** but you must not change it after that point!
** It should be around 30 bytes in length.
*/
$GLOBALS['egw_info']['server']['mcrypt_iv'] = '2llvbj0xucosmfuo2bgz2dj27dwbi9';

$GLOBALS['egw_info']['flags']['page_start_time'] = microtime(true);

include(EGW_SERVER_ROOT.'/api/setup/setup.inc.php');
$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = $GLOBALS['egw_info']['server']['versions']['api'] = $setup_info['api']['version'];
$GLOBALS['egw_info']['server']['versions']['current_header'] = $setup_info['api']['versions']['current_header'];
unset($setup_info);
$GLOBALS['egw_info']['server']['versions']['header'] = '1.29';

if(!isset($GLOBALS['egw_info']['flags']['noapi']) || !$GLOBALS['egw_info']['flags']['noapi'])
{
	if (substr($_SERVER['SCRIPT_NAME'],-7) != 'dav.php' &&	// dont do it for webdav/groupdav, as we can not safely switch it off again
		(!isset($_GET['menuaction']) || substr($_GET['menuaction'],-10) != '_hooks.log') &&
		substr($_SERVER['SCRIPT_NAME'],-10) != '/share.php')
	{
		ob_start();	// to prevent error messages to be send before our headers
	}
	require_once(EGW_SERVER_ROOT.'/api/src/loader.php');
}
else
{
	require_once(EGW_SERVER_ROOT.'/api/src/loader/common.php');
}

/*
  Leave off the final php closing tag, some editors will add
  a \n or space after which will mess up cookies later on
*/
