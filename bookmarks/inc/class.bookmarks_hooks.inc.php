<?php
/**
 * Bookmarks - Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package bookmarks
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 */
class bookmarks_hooks
{
	/**
	 * Hook called by link-class to include bookmarks in link system
	 *
	 * @return array with method-names
	 */
	static function search_link() {
		return array(
			'query'      => 'bookmarks.bookmarks_bo.link_query',
			'title'      => 'bookmarks.bookmarks_bo.link_title',
			//'titles'     => 'infolog.infolog_bo.link_titles',
			'view'       => array(
				'menuaction' => 'bookmarks.bookmarks_ui.view',
			),
			'view_id'    => 'bm_id',
			'view_list'	=>	'bookmarks.bookmarks_ui.list',
			'view_popup'  => '750x300',
			'add' => array(
				'menuaction' => 'bookmarks.bookmarks_ui.create',
			),
			'add_app'    => 'bookmarks',
			'add_id'     => 'bm_id',
			'add_popup'  => '750x300',
		);
	}

	/**
	 * hooks to build sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = 'bookmarks';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			// Magic etemplate2 favorites menu (from nextmatch widget)
			display_sidebox($appname, lang('Favorites'), Framework\Favorites::list_favorites('bookmarks'));

			$file = Array(
				'Tree view'        => $GLOBALS['egw']->link('/index.php','menuaction=bookmarks.bookmarks_ui.tree&ajax=true'),
				'List view'        => $GLOBALS['egw']->link('/index.php','menuaction=bookmarks.bookmarks_ui._list&ajax=true'),
				'Add bookmark'     => "javascript:egw_openWindowCentered2('".Egw::link('/index.php',array(
						'menuaction' => 'bookmarks.bookmarks_ui.create'
					),false)."','_blank',750,300,'yes');",
				'Import Bookmarks' => "javascript:egw.openPopup('".Egw::link('/index.php',array(
						'menuaction'=>'bookmarks.bookmarks_ui.import'
					),false)."',500,150,'_blank',false,false,'yes');",
				'Export Bookmarks' => "javascript:egw.openPopup('".Egw::link('/index.php',array(
						'menuaction'=>'bookmarks.bookmarks_ui.export'
					),false)."',500,150,'_blank',false,false,'yes');"
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['bookmarks']['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site Configuration' => Egw::link('/index.php','menuaction=admin.admin_config.index&appname=' . $appname.'&ajax=true'),
				'Global Categories' => Egw::link('/index.php','menuaction=admin.admin_categories.index&appname=' . $appname),
				'Custom fields' => Egw::link('/index.php','menuaction=admin.customfields.index&appname=' . $appname),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				$GLOBALS['egw']->framework->sidebox($appname,lang('Admin'),$file,'admin');
			}
		}
	}

	/**
	 * populates $settings for the preferences
	 *
	 * @return array
	 */
	static function settings()
	{
		/* Settings array for this app */
		$settings = array(
			'defaultview' => array(
				'type'   => 'select',
				'label'  => 'Default view for bookmarks',
				'name'   => 'defaultview',
				'values' => array(
					'list'	=>	lang('List view'),
					'tree'	=>	lang('Tree view')
				),
				'help'   => 'This is the view Bookmarks uses when you enter the application. ',
				'xmlrpc' => True,
				'admin'  => False
			),
		);
		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'bookmarks'
			));
			$options = array(
				'~nextmatch~'	=>	lang('Old fixed definition')
			);
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try
				{
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e)
				{
					unset($e);
					// permission error
					continue;
				}
				if (($title = $definition->get_title()))
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$default_def = 'export-bookmarks';
			$settings['nextmatch-export-definition'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export',
				'name'   => 'nextmatch-export-definition',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
		}
		return $settings;
	}

	/**
	 * ACL rights and labels used by Calendar
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected Acl owner
	 */
	public static function acl_rights($params)
	{
		unset($params);	// not used, but required by function signature

		return array(
			// ACL works differently in bookmarks, we change the label to ease confusion
			Acl::READ    => 'private',
			Acl::EDIT    => 'edit',
			Acl::DELETE  => 'delete',
		);
	}

	/**
	 * Hook to tell framework we use standard Api\Categories method
	 *
	 * @param string|array $data hook-data or location
	 * @return boolean
	 */
	public static function categories($data)
	{
		unset($data);	// not used, but required by function signature

		return true;
	}

	/**
	 * Hook called when a category is deleted.  Since all bookmarks are a child
	 * of a category, we need either move or delete them.
	 *
	 * @param type $data array(
	 *		'cat_id'  => $cat_id,
	 *		'cat_name' => self::id2name($cat_id),
	 *		'drop_subs' => $drop_subs,
	 *		'modify_subs' => $modify_subs,
	 *		'location'    => 'delete_category'
	 *	);
	 */
	public static function delete_category($data)
	{
		$cats = new Api\Categories('', 'bookmarks');

		// If we can, we'll move to the parent
		$new_cat = $cats->id2name($data['cat_id'],'parent') || $cats->id2name($data['cat_id'], 'main');

		$drop_subs = ($data['drop_subs'] && !$data['modify_subs']);
		if($drop_subs)
		{
			$cat_ids = $cats->return_all_children($data['cat_id']);
		}
		else
		{
			$cat_ids = array($data['cat_id']);
		}
		// Get bookmarks that use the category
		@set_time_limit( 0 );
		$bo = new bookmarks_bo();
		$ids = $bo->so->search(array('bm_category' => $cat_ids),true);

		if($ids && (!$new_cat || $new_cat == $data['cat_id']))
		{
			// Do not have a parent.  Try 'Bookmarks', added by setup
			$new_cat = $cats->name2id('Bookmarks');
			if(!$new_cat || $new_cat == $data['cat_id'])
			{
				// Deleted it? Well then, we just add it back in.
				$new_cat = $cats->add(array('name' => 'Bookmarks'));
			}
		}
		foreach($ids as $id)
		{
			$entry = $bo->read($id);
			$entry['category'] = $new_cat;
			$bo->save($entry['id'],$entry);
		}
	}
}
