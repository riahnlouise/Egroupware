<?php
	/**************************************************************************\
	* eGroupWare - Bookmarks                                                   *
	* http://www.egroupware.org                                                *
	* Based on Bookmarker Copyright (C) 1998  Padraic Renaghan                 *
	*                     http://www.renaghan.com/bookmarker                   *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

use EGroupware\Api;

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'bookmarks',
			'nonavbar'   => True,
			'noheader'   => True
		)
	);
	include('../header.inc.php');
	Api\Auth::check_password_age('bookmarks','index');
	ExecMethod('bookmarks.bookmarks_ui.init');
?>
