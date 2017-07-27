<?php
/**
 * EGroupware - Setup
 *
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package bookmarks
 * @subpackage setup
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_bookmarks' => array(
		'fd' => array(
			'bm_id' => array('type' => 'auto','nullable' => False),
			'bm_owner' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'bm_access' => array('type' => 'varchar','precision' => '255'),
			'bm_url' => array('type' => 'varchar','precision' => '255'),
			'bm_name' => array('type' => 'varchar','precision' => '255'),
			'bm_desc' => array('type' => 'text'),
			'bm_keywords' => array('type' => 'varchar','precision' => '255'),
			'bm_category' => array('type' => 'int','meta' => 'category','precision' => '4'),
			'bm_rating' => array('type' => 'int','precision' => '4'),
			'bm_info' => array('type' => 'varchar','precision' => '255'),
			'bm_visits' => array('type' => 'int','precision' => '4'),
			'bm_favicon' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('bm_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_bookmarks_extra' => array(
		'fd' => array(
			'bm_id' => array('type' => 'int','precision' => '4'),
			'bm_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '64'),
			'bm_value' => array('type' => 'text','meta' => 'cfvalue')
		),
		'pk' => array('bm_id','bm_name'),
		'fk' => array('bm_id' => 'egw_bookmarks.bm_id'),
		'ix' => array(),
		'uc' => array()
	)
);
?>
