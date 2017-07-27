<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

use EGroupware\Api;

	/* $Id$ */

	foreach(array(
		'mail_footer' => '\n\n--\nThis was sent from eGroupWare\nhttp://www.egroupware.org\n',
	) as $name => $value)
	{
		$oProc->insert($GLOBALS['egw_setup']->config_table,array(
			'config_value' => $value,
		),array(
			'config_app' => 'bookmarks',
			'config_name' => $name,
		),__FILE__,__LINE__);
	}

$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->cats_table,array(
		'cat_owner'  => Api\Categories::GLOBAL_ACCOUNT,
		'cat_access' => 'public',
		'cat_appname'=> 'bookmarks',
		'cat_name'   => 'Bookmarks',
		'cat_description' => 'Added by setup.',
		'cat_data'   => '',
		'last_mod'   => time(),
	),false,__LINE__,__FILE__);