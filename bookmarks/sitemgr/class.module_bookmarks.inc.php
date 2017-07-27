<?php
/**\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

use EGroupware\Api;

	/* $Id: class.module_bookmarks.inc.php 18221 2005-05-02 09:48:28Z ralfbecker $ */

class module_bookmarks extends Module
{
	var $template;
	var $startlevel;
	/**
	 *  @var Api\Categories
	 */
	var $cat;
	/**
	 *  @var bookmarks_bo
	 */
	var $bo;

	function module_bookmarks()
	{
		$this->title = lang('Bookmarks');
		$this->description = lang('This module displays bookmarks in a javascript based tree');
		$this->cookie = array('expanded');
		$this->arguments = array(
			'category' => array(
				'type' => 'select',
				'label' => lang('Choose the Api\Categories to display'),
				'options' => array(),
				'multiple' => True,
			),
			'initial_cats' => array(
				'type' => 'select',
				'label' => lang('Choose the Api\Categories to expand in the initial view'),
				'options' => array(),
				'multiple' => True,
			)
		);
	}

	function get_user_interface()
	{
		$this->cat = createobject('phpgwapi.categories','','bookmarks');
		$cats = $this->cat->return_array('all',0,False,'','cat_name','',True);
		$cat_ids = array();
		while (list(,$category) = @each($cats))
		{
			$cat_ids[$category['id']] = $GLOBALS['egw']->strip_html($category['name']);
		}
		$this->arguments['category']['options'] = $this->arguments['initial_cats']['options'] = $cat_ids;
		return parent::get_user_interface();
	}

	function set_block(&$block,$produce=False)
	{
		parent::set_block($block,$produce);

		if ($produce)
		{
			// Check for cookies, which have not yet been extracted.
			if (isset($_COOKIE['block'][$block->id]['expanded']))
			{
				$expandNow = 'no';
			}
			else
			{
				// Set the unfold cookies if not already set.
				$expandNow = 'yes';
			}

			require_once(EGW_INCLUDE_ROOT . SEP . 'sitemgr' . SEP . 'inc' . SEP . 'class.xslt_transform.inc.php');
			$this->add_transformer(new xslt_transform(
				$this->find_template_dir() . SEP . 'xbel.xsl',
				array(
					'blockid' => $this->block->id,
					'expandNow' => $expandNow,
				)
			));
		}
	}

	function get_content(&$arguments,$properties)
	{
		if ($arguments['expanded'])
		{
			$expandedcats = array_keys($arguments['expanded']);
		}
		else
		{
			// Expand configured Api\Categories if no user-cookie is set
			if ($arguments['initial_cats'])
			{
				$expandedcats = $arguments['initial_cats'];
			}
			else
			{
				$expandedcats = array();
			}
		}
		$this->bo = createobject('bookmarks.bookmarks_bo');
		return $this->bo->export($arguments['category'],'xbel',$expandedcats);
	}
}
