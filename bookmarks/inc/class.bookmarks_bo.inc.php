<?php
	/**
	* eGroupWare - Bookmarks                                                   *
	* http://www.egroupware.org                                                *
	* Based on Bookmarker Copyright (C) 1998  Padraic Renaghan                 *
	*                     http://www.renaghan.com/bookmarker                   *
	* Ported to phpgroupware by Joseph Engo                                    *
	* Ported to three-layered design by Michael Totschnig                      *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Vfs;

	/* $Id$ */

	require_once('class.ico.inc.php');
	class bookmarks_bo extends Api\Storage\Tracking
	{
		var $so;
		var $grants;
		var $url_format_check;
		var $validate;
		var $categories;
		//following two are used by the export function
		var $type;
		var $expanded;
		var $error_msg;
		var $msg;

		// History logging from Api\Storage\Tracking
		public $app = 'bookmarks';
		public $id_field = 'bm_id';
		public $creator_field = 'bm_owner';
		public $field2history = array(
			'name'	=>	'bm_name',
			'desc'	=>	'bm_desc',
			'url'	=>	'bm_url',
			'keywords'	=>	'bm_keywords',
			'category'	=>	'bm_category',
			'rating'	=>	'bm_rating',
			'access'	=>	'bm_access',
			'custom'	=>	'custom',
		);
		public $field2label = array(
			'bm_name'	=>	'Name',
			'bm_desc'	=>	'Description',
			'bm_url'	=>	'URL',
			'bm_keywords'	=>	'Keywords',
			'bm_category'	=>	'Category',
			'bm_rating'	=>	'Rating',
			'bm_access'	=>	'Access',
			'custom'	=>	'custom fields',
		);

		function bookmarks_bo()
		{
			$this->so = new bookmarks_so();
			$this->grants      = $GLOBALS['egw']->acl->get_grants('bookmarks');
			$this->categories = $GLOBALS['egw']->categories;
			$this->config = Api\Config::read('bookmarks');
			$this->url_format_check = True;
			$this->validate = new bookmarks_validator();

			$this->translation = $GLOBALS['egw']->translation;
			$this->charset = $this->translation->charset();

			// History logging from Api\Storage\Tracking
			parent::__construct();
		}

		/**
		* Preserve data from a half finished form so it doesn't get
		* lost if the user has to leave and come back.
		*/
		function grab_form_values($returnto,$returnto2,$bookmark)
		{
			$location_info = array(
				'returnto'  => $returnto,
				'returnto2' => $returnto2,
				'bookmark'  => array(
				'url'       => $bookmark['url'],
				'name'      => $bookmark['name'],
				'desc'      => $bookmark['desc'],
				'keywords'  => $bookmark['keywords'],
				'category'  => $bookmark['category'],
				'rating'    => $bookmark['rating'],
				'access' => $bookmark['access']
				)
			);
			$this->save_session_data($location_info);
		}

		public function get_rows(&$query, &$rows, &$readonlys = array())
		{
			if(!$query['order']) $query['order'] = 'bm_name';
			$count = $this->so->get_rows($query, $rows, $readonlys);

			// Add in permissions
			foreach($rows as $key => &$row) {
				$favicon = Link::vfs_path('bookmarks', $row['id'], 'favicon.png', true);

				if(@egw_vfs::stat($favicon))
				{
					$row['favicon'] = Egw::link(Vfs::download_url($favicon));
				}

				$readonlys["edit[{$row['id']}]"] = !$this->check_perms2($row['owner'], $row['access'], Acl::READ) &&
					!$this->check_perms2($row['owner'], $row['access'], Acl::EDIT);
				$readonlys["delete[{$row['id']}]"] = !$this->check_perms2($row['owner'], $row['access'], Acl::DELETE);
			}

			return $count;
		}

		function read($id)
		{
			$bookmark = $this->so->read($id);

			$favicon = Link::vfs_path('bookmarks', $id, 'favicon.png', true);
			if(Vfs::is_dir(Link::vfs_path('bookmarks',$id)) && Vfs::stat($favicon))
			{
				$bookmark['favicon'] = 'vfs://'.$favicon;
			}

			foreach(array(Acl::READ,Acl::EDIT,Acl::DELETE) as $required)
			{
				$bookmark[$required] = $this->check_perms2($bookmark['owner'],$bookmark['access'],$required);
			}

			return $bookmark;
		}

		function get_user_grant_list()
		{
			if (is_array($this->grants))
			{
				reset($this->grants);
				while (list($user) = each($this->grants))
				{
					$public_user_list[] = $user;
				}
				return $public_user_list;
			}
			else
			{
				return False;
			}
		}

		function check_perms2($owner,$access,$required)
		{
			return ($owner == $GLOBALS['egw_info']['user']['account_id']) ||
				($access == 'public' && $required == Acl::READ) || ($this->grants[$owner] & $required);
		}

		function check_perms($id, $required)
		{
			if (!($bookmark = $this->so->read($id)))
			{
				return False;
			}
			else
			{
				return $this->check_perms2($bookmark['owner'],$bookmark['access'],$required);
			}
		}

		function add($values)
		{
			$values['owner'] = (int) $GLOBALS['egw_info']['user']['account_id'];
			$values['added'] = time();
			$values['visits'] = 0;
			if(!$values['favicon'])
			{
				// Import may provide favicon, so don't get it if it's there
				$values['favicon'] = $this->get_favicon($values['url']);
			}
			if ($this->validate($values))
			{
				if ($this->so->exists($values['url']))
				{
					$this->error_msg .= lang('URL "%1" already exists!', $values['url']);
					return False;
				}
				$bm_id = $this->so->add($values);
				if ($bm_id)
				{
					$this->msg .= lang('Bookmark created successfully.');
					$this->fetch_favicon($values['favicon'], $bm_id);
					return $bm_id;
				}
			}
			else
			{
				return false;
			}
		}

		function save($id, $values)
		{
			if ($this->validate($values) && $this->check_perms($id,Acl::EDIT))
			{
				// Update favicon
				$values['favicon'] = $this->get_favicon($values['url']);
				$this->fetch_favicon($values['favicon'], $id);

				// Log history
				$this->track($values, $this->read($id));
				if ($this->so->save($id,$values))
				{
					$this->msg .= lang('Bookmark changed sucessfully');
					return True;
				}
			}
			else
			{
				return false;
			}
		}

		function updatetimestamp($id,$timestamp = null)
		{
			$this->so->updatetimestamp($id,$timestamp);
		}

		function delete($id)
		{
			if ($this->check_perms($id,Acl::DELETE))
			{
				if ($this->so->delete($id))
				{
					$this->msg .= lang('bookmark deleted successfully.');
					return True;
				}
			}
			else
			{
				return false;
			}
		}

		function validate($values)
		{
			$result = True;
			if (! $values['name'])
			{
				$this->error_msg .= '<br>' . lang('Name is required');
				$result = False;
			}

			if (! $values['category'])
			{
				$this->error_msg .= '<br>' . lang('You must select a category');
				$result = False;
			}

			if (! $values['url'] || $values['url'] == 'http://')
			{
				$this->error_msg .= '<br>' . lang('URL is required.');
				$result = False;
			}
			// does the admin want us to check URL format
			elseif ($this->url_format_check)
			{
				if (! $this->validate->is_url($values['url']))
				{
					$this->error_msg = '<br>URL invalid. Format must be <strong>http://</strong> or
						<strong>ftp://</strong> followed by a valid hostname and
						URL!<br><small>' .  $this->validate->ERROR . '</small>';
					$result = False;
				}
			}
			return $result;
		}

		function save_session_data($data)
		{
			Api\Cache::setSession('bookmarks', 'session_data', $data);
		}

		function read_session_data()
		{
			return Api\Cache::getSession('bookmarks', 'session_data');
		}

		/**
		 * Save changes to the history log
		 *
		 * Reimplemented to store all customfields in a single field, as the history-log has only 2-char field-ids
		 *
		 * @param array $data current entry
		 * @param array $old=null old/last state of the entry or null for a new entry
		 * @param int number of log-entries made
		 */
		function save_history($data,$old)
		{
			$data_custom = $old_custom = array();
			foreach($this->so->customfields as $name => $custom)
			{
				if (isset($data['#'.$name]) && (string)$data['#'.$name]!=='') $data_custom[] = $custom['label'].': '.$data['#'.$name];
				if (isset($old['#'.$name]) && (string)$old['#'.$name]!=='') $old_custom[] = $custom['label'].': '.$old['#'.$name];
			}
			$data['custom'] = implode("\n",$data_custom);
			$old['custom'] = implode("\n",$old_custom);

			return parent::save_history($data,$old);
		}

		/**
		*	Participate in eGW linking system
		*	This function gets a string name for a bookmark
		*/
		public function link_title($id) {
			$bookmark = $this->read($id);
			return $bookmark['name'];
		}

		/**
		*	Participate in eGW linking system
		*	This function gets a list of matching bookmarks
		*/
		public function link_query($pattern, Array &$options = array()) {
			$query = array(
				'search' => $pattern,
			);
			$ids = $this->so->search($pattern,true,$options['order']?$options['order'].' '.$options['sort']:'',$extra_cols,
				$wildcard,false,$op,$options['num_rows']?array((int)$options['start'],$options['num_rows']):(int)$options['start']);
			$options['total'] = $this->so->total;
			$content = array();
			if (is_array($ids))
			{
				foreach($ids as $id => $info )
				{
					$content[$info['id']] = $this->link_title($info['id']);
				}
			}
			return $content;
		}

		function get_category($catname,$parent)
		{
			$this->_debug('<br>Testing for category: ' . $catname . ' with parent: \'' . $parent . '\'');

			$catid = $this->categories->exists($parent?'subs':'mains',$catname,0,$parent);
			if ($catid)
			{
				$this->_debug(' - ' . $catname . ' already exists - id: ' . $catid);
			}
			else
			{
				$catid = $this->categories->add(array(
					'name'   => $catname,
					'descr'  => '',
					'parent' => $parent,
					'access' => 'private',
					'data'   => ''
				));
				$this->_debug(' - ' . $Catname . ' does not exist - new id: ' . $catid);
			}
			return $catid;
		}

		function import($bkfile,$parent)
		{
			$this->_debug('<p><b>DEBUG OUTPUT:</b>');
			$this->_debug('<br>file_name: ' . $bkfile['name']);
			$this->_debug('<br>file_size: ' . $bkfile['size']);
			$this->_debug('<br>file_type: ' . $bkfile['type'] . '<p><b>URLs:</b>');
			$this->_debug('<table border="1" width="100%">');
			$this->_debug('<tr><td>cat id</td> <td>sub id</td> <td>name</td> <td>url</td> <td>add date</td> <td>change date</td> <td>vist date</td></tr>');

			if (!$bkfile['name'] || $bkfile['name'] == 'none' || @$bkfile['error'])
			{
				$this->error_msg .= '<br>'.lang('Netscape bookmark filename is required!');
			}
			elseif (!$parent)
			{
				$this->error_msg .= '<br>'.lang('You need to select a category!');
			}
			else
			{
				$fd = @fopen($bkfile['tmp_name'],'r');
				if ($fd)
				{
					$default_rating = 0;
					$inserts = 0;
					$folderstack = array($parent);

					$utf8flag = False;

					//In the bookmark import file, description for site is allowd to be empty
					//The description of folders in the bookmark will be skiped.
					$have_desc = True;
					$dir_desc = False;

					while ($line = @fgets($fd, 2048))
					{
						$from_charset = False;
						if (preg_match('/<META HTTP-EQUIV="Content-Type" CONTENT="text\\/html; charset=([^"]*)/',$line,$matches))
						{
							 $from_charset = $matches[1];
						}
						// URLs are recognized by A HREF tags in the NS file.
						elseif (preg_match('/<A HREF="([^"]*)[^>]*>(.*)<\\/A>/i', $line, $match))
						{
							if(!$have_desc)
							{
								unset($values['desc']);
								if ($this->add($values))
								{
									$inserts++;
								}
							}
							$have_desc = False;
							$dir_desc = False;

							$url_parts = @parse_url($match[1]);
							if
							(
								$url_parts[scheme] == 'http' || $url_parts[scheme] == 'https' ||
								$url_parts[scheme] == 'ftp' || $url_parts[scheme] == 'news'
							)
							{
								$values['category'] = end($folderstack);
								$values['url']      = $match[1];

								$values['name']     = str_replace(array('&amp;','&lt;','&gt;','&quote;','&#039;'),
									array('&','<','>','"',"'"),$this->translation->convert($match[2],$from_charset));
								$values['rating']   = $default_rating;

								preg_match('/ADD_DATE="([^"]*)"/i',$line,$add_info);
								preg_match('/LAST_VISIT="([^"]*)"/i',$line,$vist_info);
								preg_match('/LAST_MODIFIED="([^"]*)"/i',$line,$change_info);
								preg_match('/ICON_URI="([^"]*)"/i',$line,$favicon_info);

								$values['added'] = $add_info[1];
								$values['visited'] = $visit_info[1];
								$values['updated'] = $change_info[1];
								$values['favicon'] = $favicon_info[1];

								unset($keywords);
								preg_match('/SHORTCUTURL="([^"]*)"/i',$line,$keywords);
								$values['keywords']=$keywords[1];

								$this->_debug(sprintf("<tr><td>%s</td> <td>%s</td> <td>%s</td> <td>%s</td> <td>%s</td> <td>%s</td> <td>%s</td> </tr>",$cid,$scid,$match[2],$match[1],$add_info[1],$change_info[1],$vist_info[1]));
							}
						}
						// folders start with the folder name inside an <H3> tag,
						// and end with the close </DL> tag.
						// we use a stack to keep track of where we are in the
						// folder hierarchy.
						elseif (preg_match('/<H3[^>]*>(.*)<\\/H3>/i', $line, $match))
						{
							$folder_name = $this->translation->convert($match[1],$from_charset);
							$current_cat_id = $this->get_category($folder_name,end($folderstack));
							$dir_desc = True;
							array_push($folderstack,$current_cat_id);
						}
						// description start with tag <DD> and the description for folder
						// will be skiped
						elseif (preg_match('/<DD>(.*)/i',$line,$desc))
						{
							if($dir_desc)
							{
								continue;
							}
							else
							{
								$values['desc']     = str_replace(array('&amp;','&lt;','&gt;','&quot;','&#039;'),array('&','<','>','"',"'"),$this->translation->convert($desc[1],$from_charset));
								if ($this->add($values))
								{
									$inserts++;
								}
								$have_desc = True;
							}
						}
						elseif (preg_match('/<\\/DL>/i', $line))
						{
							array_pop($folderstack);
						}
					}

					if(!$have_desc)
					{
						unset($values['desc']);
						if ($this->add($values))
						{
							$inserts++;
						}
					}

					@fclose($fd);
					$this->_debug('</table>');
					$this->msg = '<br>'.lang("%1 bookmarks imported from %2 successfully.", $inserts, $bkfile['name']);
				}
				else
				{
					$this->error_msg .= '<br>'.lang('Unable to open temp file %1 for import.',$bkfile['name']);
				}
			}
		}

		function export($catlist,$type,$expanded=array())
		{
			$this->type = $type;
			$this->expanded = $expanded;

			$t = new Framework\Template(EGW_INCLUDE_ROOT . '/bookmarks/templates/export');
			$t->set_file('export','export_' . $this->type . '.tpl');
			$t->set_block('export','catlist','categs');
			if (is_array($catlist))
			{
				foreach  ($catlist as $catid)
				{
					$t->set_var('categ',$this->gencat($catid));
					$t->fp('categs','catlist',True);
				}
			}
			return $t->fp('out','export');
		}

		function gencat($catid)
		{
			$t = NULL;

			// get the bookmarks for the current category
			$query = array(
				'cat_id'	=>	$catid
			);
			$this->get_rows($query, $bm_list);

			// get the sub-cats for the current category
			$subcats =  $this->categories->return_array('subs',0,False,'','cat_name','',True,$catid);

			if ($subcats)
			{
				foreach($subcats as $subcat)
				{
					// Check if there is actual content (or an empty sub-cat)
					if (!is_null($subcat_content = $this->gencat($subcat['id'])))
					{
						if (is_null($t)){
							$t = new Framework\Template(EGW_INCLUDE_ROOT . '/bookmarks/templates/export');
						}
						$t->set_var('subcat',$subcat_content);
						$t->fp('subcats','subcatlist',True);
					}
				}
			}

			// Omit this category if it is empty.
			if ((!count($bm_list)) && is_null($t)) {
				return NULL;
			}

			// Else, if there are bookmarks or sub-categories, fill the template.
			if (is_null($t)){
				$t = new Framework\Template(EGW_INCLUDE_ROOT . '/bookmarks/templates/export');
			}
			$t->set_file('categ','export_' . $this->type . '_catlist.tpl');
			$t->set_block('categ','subcatlist','subcats');
			$t->set_block('categ','urllist','urls');

			$t->set_var(array(
				'catname' => $this->translation->convert($GLOBALS['egw']->strip_html($this->categories->id2name($catid)),$this->charset,'utf-8'),
				'catid' => $catid,
				'folded' => (in_array($catid,$this->expanded) ? 'no' : 'yes')
			));

			foreach($bm_list as $bookmark) {
				$t->set_var(array(
					'url' => $bookmark['stripped_url'],
					'name' => $this->translation->convert($bookmark['stripped_name'],$this->charset,'utf-8'),
					'desc' => $this->translation->convert($bookmark['stripped_desc'],$this->charset,'utf-8')
				));
				$t->fp('urls','urllist',True);
			}
			return $t->fp('out','categ');
		}

		/**
		* Find the URL of the favicon for the given site
		*
		* @param site_url Address of the site to pull the favicon from
		*/
		public function get_favicon($site_url) {

			$favicon = false;

			$headers = @get_headers($site_url, true);
			if($headers == false || strpos('404', $headers[0])) return $favicon;

			// Check what the page says first
			$timeout = 2;
			$options = array(
				parse_url($site_url, PHP_URL_SCHEME) => array(
					'method' => 'GET',
					'timeout' => $timeout
				)
			);
			$context = stream_context_create($options);
			if(($site = @file_get_contents($site_url, null, $context, -1)) === false) {
				return $favicon;
			}

			// Check for w3c recommended: <link rel="icon" type="image/png" href="http://example.com/image.png">
			$pattern = '|<link rel.?=.?[\'"]icon[\'"][^>]+href=[\'"]([^\'"]+)[\'"]|i';
			preg_match($pattern, $site, $matches);
			if($matches[1]) {
				if(!parse_url($matches[1], PHP_URL_HOST)) {
					// Local URL
					$matches[1] = parse_url($site_url, PHP_URL_SCHEME) . '://' . parse_url($site_url, PHP_URL_HOST) . '/' . $matches[1];
				}
				$headers = @get_headers($matches[1], true);
				if($headers != false && !strpos($headers[0], 404)) {
					return $matches[1];
				}
				return $matches[1];
			}

			// Check for MS style: <link rel="shortcut icon" href="http://example.com/image.png">
			$pattern = '|<link rel.?=.?[\'"]shortcut icon[\'"][^>]+href=[\'"]([^\'"]+)[\'"]|i';
			preg_match($pattern, $site, $matches);
			if($matches[1]) {
				if(!parse_url($matches[1], PHP_URL_HOST)) {
					// Local URL
					$matches[1] = parse_url($site_url, PHP_URL_SCHEME) . '://' . parse_url($site_url, PHP_URL_HOST) . '/' . $matches[1];
				}
				$headers = @get_headers($matches[1], true);

				if($headers != false && !strpos($headers[0], 404)) {
					return $matches[1];
				}
			}

			// Check for .ico in site root
			$parsed_url = parse_url($site_url);
			$ico_url = $parsed_url['scheme'].'://'.$parsed_url['host'].'/favicon.ico';
			$headers = @get_headers($ico_url, true);
			if($headers != false && !strpos($headers[0], 404)) {
				return $ico_url;
			}

			return $favicon;

			// You could also fallback on getFavicon by Jason Cartwright
			//return 'http://getfavicon.appspot.com/' . $site_url;
		}

		/**
		 * Fetch a favicon from remote server, and store it in vfs
		 */
		private function fetch_favicon($url, $id)
		{
			if (ini_get('allow_url_fopen') != '1') return false;
			if($url == Api\Image::find('bookmarks', 'no_favicon')) return false;

			if($url == false)
			{
				$bookmark = $this->read($id);
				// Google's favicon generator (gives PNGs)
				// Doesn't always work, according to some (misses <link />)
				$url_info = parse_url($bookmark['url']);
				$url = 'http://www.google.com/s2/favicons?domain='.$url_info['host'];
			}
			$tmpname = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'favicon_');

			$path_info = parse_url($url);
			$path_info = pathinfo($path_info['path']);
			$headers = @get_headers($url, true);

			if($headers['Content-Type'] == 'image/png') {
				copy($url, $tmpname);
			} else {
				$ico = new ico($url);
				$ico->SetBackgroundTransparent(true);
				if(!($r=$ico->GetIcon(0)))
				{
					return false;
				}

				imagepng($r, $tmpname);
			}
			Link::attach_file('bookmarks',$id, array(
				'name'		=> 'favicon.png',
				'tmp_name'	=> $tmpname
			));
			unlink($tmpname);

			return false;
		}

		function _debug($s)
		{
			echo $s;
		}
	}
