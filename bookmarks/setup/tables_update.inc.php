<?php
/**
 * EGroupware - Bookmarks
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

function bookmarks_upgrade0_8_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_owner', array('type' => 'int', 'precision' => 4,'nullable' => True));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_category', array('type' => 'int', 'precision' => 4,'nullable' => True));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_subcategory', array('type' => 'int', 'precision' => 4,'nullable' => True));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_rating', array('type' => 'int', 'precision' => 4,'nullable' => True));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_visits', array('type' => 'int', 'precision' => 4,'nullable' => True));

	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '0.8.2';
}


function bookmarks_upgrade0_8_2()
{
	$GLOBALS['egw_setup']->oProc->query("update phpgw_bookmarks SET bm_category = bm_subcategory WHERE bm_subcategory != 0");

	$newtbldef = array(
		'fd' => array(
			'bm_id' => array('type' => 'auto','nullable' => False),
			'bm_owner' => array('type' => 'int', 'precision' => 4,'nullable' => True),
			'bm_access' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_url' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_name' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_desc' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_keywords' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_category' => array('type' => 'int', 'precision' => 4,'nullable' => True),
			'bm_rating' => array('type' => 'int', 'precision' => 4,'nullable' => True),
			'bm_info' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
			'bm_visits' => array('type' => 'int', 'precision' => 4,'nullable' => True)
		),
		'pk' => array('bm_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	);
	$GLOBALS['egw_setup']->oProc->DropColumn('phpgw_bookmarks',$newtbldef,'bm_subcategory');

	return $setup_info['bookmarks']['currentver'] = '0.9.1';
}


function bookmarks_upgrade0_9_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_bookmarks','bm_desc',array('type' => 'text', 'nullable' => True));

	return $setup_info['bookmarks']['currentver'] = '0.9.2';
}


function bookmarks_upgrade0_9_2()
{
	return $setup_info['bookmarks']['currentver'] = '1.0.0';
}


function bookmarks_upgrade1_0_0()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_bookmarks','egw_bookmarks');

	return $setup_info['bookmarks']['currentver'] = '1.2';
}


function bookmarks_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_bookmarks_extra',array(
		'fd' => array(
			'bm_id' => array('type' => 'int','precision' => '4'),
			'bm_name' => array('type' => 'varchar','precision' => '64'),
			'bm_value' => array('type' => 'text')
		),
		'pk' => array('bm_id','bm_name'),
		'fk' => array('bm_id' => 'egw_bookmarks.bm_id'),
		'ix' => array(),
		'uc' => array()
	));
/*
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_bookmarks_extra','name','bm_name');
	$GLOBALS['egw_setup']->oProc->RenameColumn('egw_bookmarks_extra','value','bm_value');
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_bookmarks_extra',array(
		'fd' => array(
			'bm_id' => array('type' => 'int','precision' => '4'),
			'bm_name' => array('type' => 'varchar','precision' => '64'),
			'bm_value' => array('type' => 'text')
		),
		'pk' => array('bm_id','bm_name'),
		'fk' => array('bm_id' => 'egw_bookmarks.bm_id'),
		'ix' => array(),
		'uc' => array()
	));
*/

	$GLOBALS['egw_setup']->oProc->AddColumn('egw_bookmarks','bm_favicon',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '1.9.001';	// was 1.7.001
}


function bookmarks_upgrade1_7_001()
{
	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '1.9.001';
}


function bookmarks_upgrade1_8()
{
	bookmarks_upgrade1_2();

	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '1.9.001';
}


function bookmarks_upgrade1_9_001()
{
	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '14.1';
}

function bookmarks_upgrade14_1()
{
	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '14.2';
}

function bookmarks_upgrade14_2()
{
	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '14.2.001';
}

function bookmarks_upgrade14_2_001()
{
	return $GLOBALS['setup_info']['bookmarks']['currentver'] = '16.1';
}