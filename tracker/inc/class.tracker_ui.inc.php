<?php
/**
 * Tracker - Universal tracker (bugs, feature requests, ...) with voting and bounties
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2006-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * User Interface of the tracker
 */
class tracker_ui extends tracker_bo
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'edit'  => true,
		'index' => true,
		'tprint'=> true,
		'mail_import' => True,
	);
	/**
	 * Displayed instead of the '@' in email-addresses
	 *
	 * @var string
	 */
	var $mangle_at = ' -at- ';
	/**
	 * reference to the preferences of the user
	 *
	 * @var array
	 */
	var $prefs;

	/**
	 * allowed units and hours per day, can be overwritten by the projectmanager configuration, default all units, 8h
	 *
	 * @var string
	 */
	var $duration_format = ',';	// comma is necessary!

	/**
	 * Constructor
	 *
	 * @return tracker_ui
	 */
	function __construct()
	{
		parent::__construct();
		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['tracker'];

		// read the duration format from project-manager
		if ($GLOBALS['egw_info']['apps']['projectmanager'])
		{
			$pm_config = Api\Config::read('projectmanager');
			$this->duration_format = str_replace(',','',implode('', (array)$pm_config['duration_units'])).','.$pm_config['hours_per_workday'];
			unset($pm_config);
		}
	}

	/**
	 * Print a tracker item
	 *
	 * @return string html-content, if sitemgr otherwise null
	 */
	function tprint()
	{
		// Check if exists
		if ((int)$_GET['tr_id'])
		{
			if (!$this->read($_GET['tr_id']))
			{
				return lang('Tracker item not found !!!');
			}
		}
		else	// new item
		{
			return lang('Tracker item not found !!!');
		}
		if (!is_object($this->tracking))
		{
			$this->tracking = new tracker_tracking($this);
		}

		if ($this->data['tr_edit_mode'] == 'html')
		{
			$this->tracking->html_content_allow = true;
		}

		$details = $this->tracking->get_body(true,$this->data,$this->data);
		if (!$details)
		{
			return implode(', ',$this->tracking->errors);
		}
		$GLOBALS['egw']->framework->render($details,'',false);
	}

	/**
	 * Edit a tracker item in a popup
	 *
	 * @param array $content =null eTemplate content
	 * @param string $msg =''
	 * @param boolean $popup =true use or not use a popup
	 * @return string html-content, if sitemgr otherwise null
	 */
	function edit($content=null,$msg='',$popup=true)
	{
		if ($this->htmledit || (isset($content['tr_edit_mode']) && $content['tr_edit_mode']=='html'))
		{
			$rte_features = $GLOBALS['egw_info']['user']['preferences']['common']['rte_features'];
			$tr_description_options = $rte_features.',240px,100%,false';
			$tr_reply_options = $rte_features.',215px,100%,false';
		}
		else
		{
			$tr_description_options = 'ascii,230px,99%';
			$tr_reply_options = 'ascii,205px,99%';
		}

		//_debug_array($content);
		if (!is_array($content))
		{
			if ($_GET['msg']) $msg = strip_tags($_GET['msg']);

			// edit or new?
			if ((int)$_GET['tr_id'])
			{
				$own_referer = Api\Header\Referer::get();
				if (!$this->read($_GET['tr_id']))
				{
					Framework::window_close(lang('Tracker item not found !!!'));
				}
				else
				{
					// Set the ticket as seen by this user
					self::seen($this->data, true);

					// editing, preventing/fixing mixed ascii-html
					if ($this->data['tr_edit_mode'] == 'ascii' && $this->htmledit)
					{
						// non html items edited by html (add nl2br)
						$tr_description_options = 'simple,240px,100%,false,,1';
					}
					if ($this->data['tr_edit_mode'] == 'html' && !$this->htmledit)
					{
						// html items edited in ascii mode (prevent changing to html)
						$tr_description_options = 'simple,240px,100%,false';
						$tr_reply_options = 'simple,215px,100%,false';
					}
					//echo "<p>data[tr_edit_mode]={$this->data['tr_edit_mode']}, this->htmledit=".array2string($this->htmledit)."</p>\n";
					// Ascii Replies are converted to html, if htmledit is disabled (default), we allways convert, as this detection is weak
					foreach ($this->data['replies'] as &$reply)
					{
						if (!($this->htmledit || $this->data['tr_edit_mode'] == 'html')|| (strlen($reply['reply_message'])==strlen(strip_tags($reply['reply_message'])))) //(stripos($reply['reply_message'], '<br') === false && stripos($reply['reply_message'], '<p>') === false))
						{
							$reply['reply_message'] = Api\Html::htmlspecialchars($reply['reply_message']);
						}
					}
				}
				$needInit = false;
			}
			else	// new item
			{
				$needInit = true;
				$regardInInit = array();
			}
			// for new items we use the session-state or $_GET['tracker']
			if (!$this->data['tr_id'])
			{
				$regardInInit = array();
				if (($state = Api\Cache::getSession('tracker','index'.
					(isset($this->trackers[(int)$_GET['only_tracker']]) ? '-'.$_GET['only_tracker'] : ''))))
				{
					$this->data['tr_tracker'] = $regardInInit['tr_tracker'] = $state['col_filter']['tr_tracker'] ? $state['col_filter']['tr_tracker'] : $this->data['tr_tracker'];
					$this->data['cat_id']     = $regardInInit['cat_id'] = $state['cat_id'];
					$this->data['tr_version'] = $regardInInit['tr_version'] = $state['filter2'] ? $state['filter2'] : $GLOBALS['egw_info']['user']['preferences']['tracker']['default_version'];
				}
				if (isset($this->trackers[(int)$_GET['tracker']]))
				{
					$this->data['tr_tracker'] = $regardInInit['tr_tracker'] = (int)$_GET['tracker'];
				}
				$this->data['tr_priority'] = $regardInInit['tr_priority'] = 5;
			}
			// initialize and try to merge what we already have
			if ($needInit)
			{
				$this->init($regardInInit);
			}
			if ($_GET['no_popup'] || $_GET['nopopup']) $popup = false;

			// check if user has rights to create new entries and fail if not
			if (!$this->data['tr_id'] && !$this->check_rights($this->field_acl['add'],null,null,null,'add'))
			{
				$msg = lang('Permission denied !!!');
				if ($popup)
				{
					$GLOBALS['egw']->framework->render('<h1 style="color: red;">'.$msg."</h1>\n",null,true);
					exit();
				}
				else
				{
					unset($_GET['tr_id']);	// in case it's still set
					return $this->index(null,$this->data['tr_tracker'],$msg);
				}
			}
			// on resticted trackers, check if the user has read access, OvE, 20071012
			$restrict = false;
			if($this->data['tr_id'])
			{
				if (!$this->is_staff($this->data['tr_tracker']) &&	// user has to be staff or
					!array_intersect($this->data['tr_assigned'],	// he or a group he is a member of is assigned
						array_merge((array)$this->user,$GLOBALS['egw']->accounts->memberships($this->user,true))))
				{
					// if we have group OR creator restrictions
					if ($this->restrictions[$this->data['tr_tracker']]['creator'] ||
						$this->restrictions[$this->data['tr_tracker']]['group'])
					{
						// we need to be creator OR group member
						if (!($this->restrictions[$this->data['tr_tracker']]['creator'] &&
								$this->data['tr_creator'] == $this->user ||
							$this->restrictions[$this->data['tr_tracker']]['group'] &&
								in_array($this->data['tr_group'], $GLOBALS['egw']->accounts->memberships($this->user,true))))
						{
							$restrict = true;	// if not --> no access
						}
					}
					// Check queue access if enabled and that no has access to queue 0 (All)
					if ($this->enabled_queue_acl_access && !$this->trackers[$this->data['tr_tracker']] && !$this->is_user(0,$this->user))
					{
						$restrict = true;
					}
				}
			}
			if ($restrict)
			{
				$msg = lang('Permission denied !!!');
				if ($popup)
				{
					$GLOBALS['egw']->framework->render('<h1 style="color: red;">'.$msg."</h1>\n",null,false);
					exit();
				}
				else
				{
					unset($_GET['tr_id']);	// in case it's still set
					return $this->index(null,$this->data['tr_tracker'],$msg);
				}
			}
		}
		else	// submitted form
		{
			//_debug_array($content);
			list($button) = @each($content['button']); unset($content['button']);
			if ($content['bounties']['bounty']) $button = 'bounty'; unset($content['bounties']['bounty']);
			$popup = $content['popup']; unset($content['popup']);
			$own_referer = $content['own_referer']; unset($content['own_referer']);

			$this->data = $content;
			unset($this->data['bounties']['new']);
			switch($button)
			{
				case 'save':
				case 'apply':
					if (is_array($this->data['tr_cc']))
					{
						foreach($this->data['tr_cc'] as $i => $value)
						{
							//imap_rfc822 should not be used, but it works reliable here, until we have some regex solution or use horde stuff
							$addresses = imap_rfc822_parse_adrlist($value, '');
							//error_log(__METHOD__.__LINE__.$value.'->'.array2string($addresses[0]));
							$this->data['tr_cc'][$i]=$addresses[0]->host ? $addresses[0]->mailbox.'@'.$addresses[0]->host : $addresses[0]->mailbox;
						}
						$this->data['tr_cc'] = implode(',',$this->data['tr_cc']);
					}
					if (!$this->data['tr_id'] && !$this->check_rights($this->field_acl['add'],null,null,null,'add'))
					{
						$msg = lang('Permission denied !!!');
						break;
					}

					$readonlys = $this->readonlys_from_acl();

					// Save Current edition mode preventing mixed types
					if ($this->data['tr_edit_mode'] == 'html' && !$this->htmledit)
					{
						$this->data['tr_edit_mode'] = 'html';
					}
					elseif ($this->data['tr_edit_mode'] == 'ascii' && $this->htmledit && $readonlys['tr_description'])
					{
						$this->data['tr_edit_mode'] = 'ascii';
					}
					else
					{
						$this->htmledit ? $this->data['tr_edit_mode'] = 'html' : $this->data['tr_edit_mode'] = 'ascii';
					}
					$ret = $this->save();
					if ($ret === false)
					{
						$msg = lang('Nothing to save.');
						$state = Api\Cache::getSession('tracker', 'index');
						Framework::refresh_opener($msg,'tracker',$this->data['tr_id'],'edit');

						// only change to current tracker, if not all trackers displayed
						($state['col_filter']['tr_tracker'] ? '&tracker='.$this->data['tr_tracker'] : '')."';";
					}
					elseif ($ret === 'tr_modifier' || $ret === 'tr_modified')
					{
						$msg .= ($msg ? ', ' : '') .lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars(Egw::link('/index.php',array(
									'menuaction' => 'tracker.tracker_ui.edit',
									'tr_id'    => $this->data['tr_id'],
									//'referer'    => $referer,
								))).'">','</a>');
						break;
					}
					elseif ($ret == 0)
					{
						$msg = lang('Entry saved');
						//apply defaultlinks
						usort($this->all_cats,function($a, $b)
						{
							return strcasecmp($a['name'], $b['name']);
						});
						foreach($this->all_cats as $cat)
						{
							if (!is_array($data = $cat['data'])) $data = array('type' => $data);
							//echo "<p>".$this->data['tr_tracker'].": $cat[name] ($cat[id]/$cat[parent]/$cat[main]): ".print_r($data,true)."</p>\n";

							if ($cat['parent'] == $this->data['tr_tracker'] && $data['type'] != 'tracker' && $data['type']=='project')
							{
								if (!Link::get_link('tracker',$this->data['tr_id'],'projectmanager',$data['projectlist']))
								{
									Link::link('tracker',$this->data['tr_id'],'projectmanager',$data['projectlist']);
								}
							}
						}
						if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
						{
							Link::link('tracker',$this->data['tr_id'],$content['link_to']['to_id']);

							// check if we have dragged in images and fix their image urls
							if (Etemplate\Widget\Vfs::fix_html_dragins('tracker', $this->data['tr_id'],
								$content['link_to']['to_id'], $content['tr_description']))
							{
								$this->update(array(
									'tr_description' => $content['tr_description'],
								));
							}
						}
						$state = Api\Cache::getSession('tracker', 'index');
						Framework::refresh_opener($msg, 'tracker',$this->data['tr_id'],'edit');
					}
					else
					{
						$msg = lang('Error saving the entry!!!');
						break;
					}
					if ($button == 'apply')
					{
						$_GET['tr_id'] = $this->data['tr_id'];
						return $this->edit($_GET['tr_id'], $msg, $popup);
					}
					// fall-through for save
				case 'cancel':
					if ($popup)
					{
						Framework::window_close();
						exit();
					}
					unset($_GET['tr_id']);	// in case it's still set
					if($own_referer && strpos($own_referer,'cd=yes') === false &&
						strpos($own_referer,'tr_id='.$this->data['tr_id']) === FALSE)
					{
						// Go back to where you came from
						Egw::redirect_link($own_referer);
					}
					if (Api\Json\Response::isJSONResponse())
					{
						Api\Json\Response::get()->call('egw.open_link','tracker.tracker_ui.index&ajax=true','_self',false,'tracker');
						exit();
					}
					return $this->index(null,$this->data['tr_tracker'],$msg);

				case 'vote':
					if ($this->cast_vote())
					{
						$msg = lang('Thank you for voting.');
						if ($popup)
						{
							Framework::refresh_opener($msg, 'tracker',$this->data['tr_id'], 'edit');
						}
					}
					break;

				case 'bounty':
					if (!$this->allow_bounties) break;
					$bounty = $content['bounties']['new'];
					if (!$this->is_anonymous())
					{
						if (!$bounty['bounty_name']) $bounty['bounty_name'] = $GLOBALS['egw_info']['user']['account_fullname'];
						if (!$bounty['bounty_email']) $bounty['bounty_email'] = $GLOBALS['egw_info']['user']['account_email'];
					}
					if (!$bounty['bounty_amount'] || !$bounty['bounty_name'] || !$bounty['bounty_email'])
					{
						$msg = lang('You need to specify amount, donators name AND email address!');
					}
					elseif ($this->save_bounty($bounty))
					{
						$msg = lang('Thank you for setting this bounty.').
							' '.lang('The bounty will NOT be shown, until the money is received.');
						array_unshift($this->data['bounties'],$bounty);
						unset($content['bounties']['new']);
					}
					break;

				default:
					if (!$this->allow_bounties) break;
					// check delete bounty
					list($id) = @each($this->data['bounties']['delete']);
					if ($id)
					{
						unset($this->data['bounties']['delete']);
						if ($this->delete_bounty($id))
						{
							$msg = lang('Bounty deleted');
							foreach($this->data['bounties'] as $n => $bounty)
							{
								if ($bounty['bounty_id'] == $id)
								{
									unset($this->data['bounties'][$n]);
									break;
								}
							}
						}
						else
						{
							$msg = lang('Permission denied !!!');
						}
					}
					else
					{
						// check confirm bounty
						list($id) = @each($this->data['bounties']['confirm']);
						if ($id)
						{
							unset($this->data['bounties']['confirm']);
							foreach($this->data['bounties'] as $n => $bounty)
							{
								if ($bounty['bounty_id'] == $id)
								{
									if ($this->save_bounty($this->data['bounties'][$n]))
									{
										$msg = lang('Bounty confirmed');
										Framework::refresh_opener($msg, 'tracker',$this->data['tr_id'], 'edit');
									}
									else
									{
										$msg = lang('Permission denied !!!');
									}
									break;
								}
							}
						}
					}
					break;
			}
		}
		$tr_id = $this->data['tr_id'];
		if (!($tracker = $this->data['tr_tracker']))
		{
			reset($this->trackers);
			list($tracker) = @each($this->trackers);
		}
		if (!$readonlys) $readonlys = $this->readonlys_from_acl();

		if ($this->data['tr_edit_mode'] == 'ascii' && $this->data['tr_description'] && $readonlys['tr_description'])
		{
			// non html view in a readonly htmlarea (div) needs nl2br
			$tr_description_options = 'simple,240px,100%,false,,1';
		}

		$preserv = $content = $this->data;
		if ($content['num_replies']) array_unshift($content['replies'],false);	// need array index starting with 1!
		if ($this->allow_bounties)
		{
			if (is_array($content['bounties']))
			{
				$total = 0;
				foreach($content['bounties'] as $bounty)
				{
					$total += $bounty['bounty_amount'];
					// confirmed bounties cant be deleted and need no confirm button
					$readonlys['delete['.$bounty['bounty_id'].']'] =
						$readonlys['confirm['.$bounty['bounty_id'].']'] = !$this->is_admin($tracker) || $bounty['bounty_confirmed'];
				}
				$content['bounties']['num_bounties'] = count($content['bounties']);
				array_unshift($content['bounties'],false);	// we need the array index to start with 2!
				array_unshift($content['bounties'],false);
				$content['bounties']['total'] = $total ? sprintf('%4.2lf',$total) : '';
			}
			$content['bounties']['currency'] = $this->currency;
			$content['bounties']['is_admin'] = $this->is_admin($tracker);
		}
		$statis = $this->get_tracker_stati($tracker);
		$content += array(
			'msg' => $msg,
			'tr_description_options' => $tr_description_options,
			'tr_description_mode'    => $readonlys['tr_description'],
			'tr_reply_options' => $tr_reply_options,
			'on_cancel' => $popup ? 'egw(window).close();' : 'egw.open_link("tracker.tracker_ui.index&ajax=true","_self",false,"tracker")',
			'no_vote' => '',
			'show_dates' => $this->show_dates,
			'link_to' => array(
				'to_id' => $tr_id,
				'to_app' => 'tracker',
			),
			'status_help' => !$this->pending_close_days ? lang('Pending items never get close automatic.') :
				lang('Pending items will be closed automatic after %1 days without response.',$this->pending_close_days),
			'history' => array(
				'id'  => $tr_id,
				'app' => 'tracker',
				'status-widgets' => array(
					'Co' => 'select-percent',
					'St' => &$statis,
					'Ca' => 'select-cat',
					'Tr' => 'select-cat',
					'Ve' => 'select-cat',
					'As' => 'select-account',
					'Cr' => 'select-account',
					'pr' => array('Public','Private'),
					'Cl' => 'date-time',
					'tr_startdate' => 'date-time',
					'tr_duedate' => 'date-time',
					'Re' => self::$resolutions + $this->get_tracker_labels('resolution',$tracker),
					'Gr' => 'select-account',
				),
			),
		);
		if ($this->allow_bounties && !$this->is_anonymous())
		{
			$content['bounties']['user_name'] = $GLOBALS['egw_info']['user']['account_fullname'];
			$content['bounties']['user_email'] = $GLOBALS['egw_info']['user']['account_email'];
		}
		$preserv['popup'] = $popup;
		$preserv['own_referer'] = $own_referer;

		if (!$tr_id && isset($_REQUEST['link_app']) && isset($_REQUEST['link_id']) && !is_array($content['link_to']['to_id']))
		{
			$link_ids = is_array($_REQUEST['link_id']) ? $_REQUEST['link_id'] : array($_REQUEST['link_id']);
			foreach(is_array($_REQUEST['link_app']) ? $_REQUEST['link_app'] : array($_REQUEST['link_app']) as $n => $link_app)
			{
				$link_id = $link_ids[$n];
				if (preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$link_app.':'.$link_id))	// gard against XSS
				{
					switch($link_app)
					{
						case 'infolog':
							static $infolog_bo=null;
							if(!$infolog_bo) $infolog_bo = new infolog_bo();
							$infolog = $app_entry = $infolog_bo->read($link_id);
							$content = array_merge($content, array(
								'tr_owner'	=> $infolog['info_owner'],
								'tr_private'	=> $infolog['info_access'] == 'private',
								'tr_summary'	=> $infolog['info_subject'],
								'tr_description'	=> $infolog['info_des'],
								'tr_cc'		=> $infolog['info_cc'],
								'tr_created'	=> $infolog['info_startdate']
							));

							// Categories are different, no globals.  Match by name.
							$match = array(
								$infolog_bo->enums['type'][$infolog['info_type']] => array(
									'field'	=> 'tr_tracker',
									'source'=> $this->trackers
								),
								Api\Categories::id2name($infolog['info_cat']) => array(
									'field'	=> 'cat_id',
									'source'=> $this->get_tracker_labels('cat',$tracker)
								)
							);
							foreach($match as $info_field => $info)
							{
								$content[$info['field']] = array_search($info_field,$info['source']);
							}

							// Try to match priorities
							foreach($this->get_tracker_priorities($content['tr_tracker'], $content['cat_id']) as $p => $label)
							{
								if(stripos($label, $infolog_bo->enums['priority'][$infolog['info_priority']]) !== false)
								{
									$content['tr_priority'] = $p;
									break;
								}
							}

							// Add responsible as participant - filtered later
							foreach($infolog['info_responsible'] as $responsible) {
								$content['tr_assigned'][] = $responsible;
							}

							// Copy infolog's links
							foreach(Link::get_links('infolog',$link_id) as $copy_link)
							{
								Link::link('tracker', $content['link_to']['to_id'], $copy_link['app'], $copy_link['id'],$copy_link['remark']);
							}
							break;

					}
					// Copy same custom fields
					$_cfs = Api\Storage\Customfields::get('tracker');
					$link_app_cfs = Api\Storage\Customfields::get($link_app);
					foreach($_cfs as $name => $settings)
					{
						unset($settings);
						if($link_app_cfs[$name]) $content['#'.$name] = $app_entry['#'.$name];
					}
					Link::link('tracker',$content['link_to']['to_id'],$link_app,$link_id);
				}
			}
		}
		// options for creator selectbox (allways add current selected user!)
		if ($readonlys['tr_creator'])
		{
			$creators = array();
		}
		else
		{
			$creators = $this->get_staff($tracker,0,'usersANDtechnicians');
		}
		if ($content['tr_creator'] && !isset($creators[$content['tr_creator']]))
		{
			$creators[$content['tr_creator']] = Api\Accounts::username($content['tr_creator']);
		}

		// Comment visibility
		if (is_array($content['replies']))
		{
			foreach($content['replies'] as $key => &$reply)
			{
				if (isset($content['replies'][$key]['reply_visible'])) {
					$reply['reply_visible_class'] = 'reply_visible_'.$reply['reply_visible'];
				}
			}
		}
		$content['no_comment_visibility'] = !$this->check_rights(TRACKER_ADMIN|TRACKER_TECHNICIAN|TRACKER_ITEM_ASSIGNEE,null,null,null,'no_comment_visibility') ||
			!$this->allow_restricted_comments;

		$account_select_pref = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];
		$sel_options = array(
			'tr_tracker'  => &$this->trackers,
			'cat_id'      => $this->get_tracker_labels('cat',is_array($tracker) && count($tracker) == 1?$tracker[0]:$tracker),
			'tr_version'  => $this->get_tracker_labels('version',$tracker),
			'tr_priority' => $this->get_tracker_priorities($tracker,$content['cat_id']),
			'tr_status'   => &$statis,
			'tr_resolution' => $this->get_tracker_labels('resolution',$tracker),
			'tr_assigned' => $account_select_pref == 'none' ? array() : $this->get_staff($tracker,$this->allow_assign_groups,$this->allow_assign_users?'usersANDtechnicians':'technicians'),
			'tr_creator'  => $creators,
			// New items default to primary group is no right to change the group
			'tr_group' => $account_select_pref == 'none' ? array() : $this->get_groups(!$this->check_rights($this->field_acl['tr_group'],$tracker,null,null,'tr_group') && !$this->data['tr_id']),
			'canned_response' => $this->get_tracker_labels('response'),
		);

		foreach($this->field2history as $field => $status)
		{
			$sel_options['status'][$status] = $this->field2label[$field];
		}
		$sel_options['status']['xb'] = 'Bounty deleted';
		$sel_options['status']['bo'] = 'Bounty set';
		$sel_options['status']['Bo'] = 'Bounty confirmed';

		$readonlys['tabs'] = array(
			'comments' => !$tr_id || !$content['num_replies'],
			'add_comment' => !$tr_id || $readonlys['reply_message'],
			'history'  => !$tr_id,
			'bounties' => !$this->allow_bounties,
			'custom'   => !Api\Storage\Customfields::get('tracker', false, $content['tr_tracker']),
		);
		// Make link_to readonly if the user has no EDIT access
		$readonlys['link_to'] = !$this->file_access($tr_id, Acl::EDIT);

		if ($tr_id && $readonlys['reply_message'])
		{
			$readonlys['button[save]'] = true;
		}
		if (!$tr_id && $readonlys['add'])
		{
			$msg = lang('Permission denied !!!');
			$readonlys['button[save]'] = true;
		}
		// Assigned & group are not select-account widgets, so we need to apply
		// none preference (no value, no options) here.
		if($account_select_pref == 'none')
		{
			$readonlys['tr_assigned'] = true;
			$readonlys['tr_group'] = true;
		}
		if (!$this->allow_voting || !$tr_id || $readonlys['vote'] || ($voted = $this->check_vote()))
		{
			$readonlys['button[vote]'] = true;
			if ($tr_id && $this->allow_voting)
			{
				$content['no_vote'] = is_int($voted) ? lang('You voted %1.',
					date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].
					($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?' h:i a':' H:i'),$voted)) :
					lang('You need to login to vote!');
			}
		}
		if ($readonlys['canned_response'])
		{
			$content['no_canned'] = true;
		}
		$content['no_links'] = $readonlys['link_to'];
		$content['bounties']['no_set_bounties'] = $readonlys['bounty'];
		//error_log(__METHOD__.__LINE__.':'.is_array($tracker)?$tracker[0]:$tracker);
		$what = ($tracker && isset($this->trackers[(is_array($tracker)?$tracker[0]:$tracker)]) ? $this->trackers[(is_array($tracker)?$tracker[0]:$tracker)] : lang('Tracker'));
		$GLOBALS['egw_info']['flags']['app_header'] = $tr_id ? lang('Edit %1',$what) : lang('New %1',$what);

		$tpl = new Etemplate('tracker.edit');
		// use a type-specific template (tracker.edit.xyz), if one exists, otherwise fall back to the generic one
		if (!$tpl->read('tracker.edit'.(isset($this->trackers[(is_array($tracker)?$tracker[0]:$tracker)])?'.'.trim($this->trackers[(is_array($tracker)?$tracker[0]:$tracker)]):'')))
		{
			$tpl->read('tracker.edit');
		}

		if ($this->tracker_has_cat_specific_priorities($tracker))
		{
			$tpl->set_cell_attribute('cat_id','onchange','1');
		}
		// No notifications needs label hidden too
		if($readonlys['no_notifications'])
		{
			$tpl->set_cell_attribute('no_notifications', 'disabled', true);
		}

		if ($content['tr_assigned'] && !is_array($content['tr_assigned']))
		{
			$content['tr_assigned'] = explode(',',$content['tr_assigned']);
		}
		if (count($content['tr_assigned']) > 1)
		{
			$tpl->set_cell_attribute('tr_assigned','size','3+');
		}
		if (!empty($content['tr_cc'])&&!is_array($content['tr_cc']))$content['tr_cc'] = explode(',',$content['tr_cc']);
		return $tpl->exec('tracker.tracker_ui.edit',$content,$sel_options,$readonlys,$preserv,$popup ? 2 : 0);
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 * @return int total number of rows
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		if (!$this->allow_voting && $query_in['order'] == 'votes' ||	// in case the tracker-config changed in that session
			!$this->allow_bounties && $query_in['order'] == 'bounties') $query_in['order'] = 'tr_id';

		$query = $query_in;
		if (!$query['csv_export'])	// do not store query for csv-export in session
		{
			Api\Cache::setSession('tracker',$query['session_for'] ? $query['session_for'] : 'index'.($query_in['only_tracker'] ? '-'.$query_in['only_tracker'] : ''),$query);
		}
		// save the state of the index page (filters) in the user prefs
		// need to save state, before resolving diverse col-filters, eg. to all group-members or sub-cats
		$state = serialize(array(
			'cat_id'     => $query['cat_id'],	// cat
			'filter'     => $query['filter'],	// dates
			'filter2'    => $query['filter2'],	// version
			'order'      => $query['order'],
			'sort'       => $query['sort'],
			'num_rows'   => $query['num_rows'],
			'col_filter' => array(
				'tr_tracker'  => $query['col_filter']['tr_tracker'],
				'tr_creator'  => $query['col_filter']['tr_creator'],
				'tr_assigned' => $query['col_filter']['tr_assigned'],
				'tr_status'   => $query['col_filter']['tr_status'],
			),
		));
		if (!$query['csv_export'] && !$query['action'] && $GLOBALS['egw']->session->session_flags != 'A' &&	// store the current state of non-anonymous users in the prefs
			$state != $GLOBALS['egw_info']['user']['preferences']['tracker']['index_state'])
		{
			//$msg .= "save the index state <br>";
			$GLOBALS['egw']->preferences->add('tracker','index_state',$state);
			// save prefs, but do NOT invalid the cache (unnecessary)
			$GLOBALS['egw']->preferences->save_repository(false,'user',false);
		}

		$tracker = $query['col_filter']['tr_tracker'];

		// handle action and linked filter (show only entries linked to a certain other entry)
		$link_filters = array();
		$links = array();
		if ($query['col_filter']['linked'])
		{
			$link_filters['linked'] = $query['col_filter']['linked'];
			$links['linked'] = array();
			unset($query['col_filter']['linked']);
		}
		if($query['action'] && in_array($query['action'], array_keys($GLOBALS['egw_info']['apps'])) && $query['action_id'])
		{
			$link_filters['action'] = array('app'=>$query['action'], 'id' => $query['action_id']);
			$links['action'] = array();
		}
		foreach($link_filters as $key => $link)
		{
			if(!is_array($link))
			{
				// Legacy string style
				list($app,$id) = explode(':',$link);
			}
			else
			{
				// Full info
				$app = $link['app'];
				$id = $link['id'];
			}
			if(!is_array($id)) $id = explode(',',$id);
			if (!($linked = Link::get_links_multiple($app,$id,true,'tracker')))
			{
				$rows = array();	// no entries linked to selected link --> no rows to return
				$this->get_rows_options($rows, $tracker);
				return 0;
			}


			foreach($linked as $infos)
			{
				$links[$key] = array_merge($links[$key],$infos);
			}
			$links[$key] = array_unique($links[$key]);
			if($key == 'linked')
			{
				$linked = array('app' => $app, 'id' => $id, 'title' => (count($id) == 1 ? Link::title($app, $id) : lang('multiple')));
			}
		}
		if(count($links))
		{
			$query['col_filter']['tr_id'] = count($links) > 1 ? call_user_func_array('array_intersect', $links) : $links[$key];
		}

		// Explode multiples into array
		if(!is_array($tracker) && strpos($tracker,',') !== false)
		{
			$tracker = $query['col_filter']['tr_tracker'] = explode(',',$query['col_filter']['tr_tracker']);
		}
		if (!($query['col_filter']['cat_id'] = $query['cat_id'])) unset($query['col_filter']['cat_id']);
		if (!($query['col_filter']['tr_version'] = $query['filter2'])) unset($query['col_filter']['tr_version']);

		if (!($query['col_filter']['tr_creator'])) unset($query['col_filter']['tr_creator']);

		if ($query['col_filter']['tr_assigned'] < 0)	// resolve groups with it's members
		{
			$query['col_filter']['tr_assigned'] = $GLOBALS['egw']->accounts->members($query['col_filter']['tr_assigned'],true);
			$query['col_filter']['tr_assigned'][] = $query_in['col_filter']['tr_assigned'];
		}
		elseif($query['col_filter']['tr_assigned'] === 'not')
		{
			$query['col_filter']['tr_assigned'] = null;
		}
		elseif(!$query['col_filter']['tr_assigned'])
		{
			unset($query['col_filter']['tr_assigned']);
		}

		if (empty($query['col_filter']['tr_tracker']))
		{
			$tracker = $query['col_filter']['tr_tracker'] = array_keys($this->trackers);
		}

		// Get list of currently displayed trackers, so we can get all valid statuses
		if ($tracker)
		{
			$trackers = is_array($tracker) ? $tracker : array($tracker);
		}
		else
		{
			$trackers = array();
		}

		//echo "<p align=right>uitracker::get_rows() order='$query[order]', sort='$query[sort]', search='$query[search]', start=$query[start], num_rows=$query[num_rows], col_filter=".print_r($query['col_filter'],true)."</p>\n";
		$total = parent::get_rows($query,$rows,$readonlys,$this->allow_voting||$this->allow_bounties);	// true = count votes and/or bounties
		$prio_labels = $prio_tracker = $prio_cat = null;
		foreach($rows as $n => $row)
		{
			// Check if this is a new (unseen) ticket for the current user
			if (self::seen($row, false))
			{
				$rows[$n]['seen_class'] = 'tracker_seen';
			}
			else
			{
				$rows[$n]['seen_class'] = 'tracker_unseen';
			}

			$trackers[] = $row['tr_tracker'];

			// show the right tracker and/or cat specific priority label
			if ($row['tr_priority'])
			{
				if (is_null($prio_labels) || $this->priorities && ($row['tr_tracker'] != $prio_tracker || $row['cat_id'] != $prio_cat))
				{
					$prio_labels = $this->get_tracker_priorities($prio_tracker=$row['tr_tracker'],$prio_cat = $row['cat_id']);
					if ($prio_labels === self::$stock_priorities)	// show only the numbers for the stock priorities
					{
						$prio_labels = array_combine(array_keys(self::$stock_priorities),array_keys(self::$stock_priorities));
					}
				}
				$rows[$n]['prio_label'] = $prio_labels[$row['tr_priority']];
			}
			if (isset($rows[$n]['tr_description'])) $rows[$n]['tr_description'] = nl2br($rows[$n]['tr_description']);
			if ($row['overdue']) $rows[$n]['overdue_class'] = 'tracker_overdue';
			if ($row['bounties']) $rows[$n]['currency'] = $this->currency;

			if (isset($GLOBALS['egw_info']['user']['apps']['timesheet']))
			{
				unset($links);
				if (($links = Link::get_links('tracker',$row['tr_id'])) &&
					isset($GLOBALS['egw_info']['user']['apps']['timesheet']))
				{
					// loop through all links of the entries
					$timesheets = array();
					foreach ($links as $link)
					{
						if ($link['app'] == 'projectmanager')
						{
							//$info['pm_id'] = $link['id'];
						}
						if ($link['app'] == 'timesheet') $timesheets[] = $link['id'];
					}
					if (isset($GLOBALS['egw_info']['user']['apps']['timesheet']))
					{
						$sum = ExecMethod('timesheet.timesheet_bo.sum',$timesheets);
						$rows[$n]['tr_sum_timesheets'] = $sum['duration'];
					}
				}
			}
			// do NOT display public tickets with "No", just display "Yes" for private ticktes
			if ((string)$row['tr_private'] === '0') $rows[$n]['tr_private'] = '';

			//_debug_array($rows[$n]);
			//echo "<p>".$this->trackers[$row['tr_tracker']]."</p>";
			$id=$row['tr_id'];
		}

		$this->get_rows_options($rows,$tracker,$trackers);

		// disable start date / due date column, if disabled in config
		if(!$this->show_dates)
		{
			$rows['no_tr_startdate_tr_duedate'] = true;
		}

		return $total;
	}

	/**
	 * Selectbox options vary depending on the selected tracker.
	 *
	 * @param Array $rows List of rows, we'll add the sel_options in
	 * @param String[] $tracker List of tracker IDs
	 */
	protected function get_rows_options(&$rows, $selected_trackers, $visible_trackers=array())
	{
		if(!is_array($selected_trackers) && strpos($selected_trackers,',') !== false)
		{
			$tracker = explode(',',$selected_trackers);
		}
		else
		{
			$tracker = $selected_trackers;
		}
		$rows['sel_options']['tr_assigned'] = array('not' => lang('Not assigned'));

		// Add allowed staff
		foreach((array)$tracker as $tr_id)
		{
			$rows['sel_options']['tr_assigned'] += $this->get_staff($tr_id,2,$this->allow_assign_users?'usersANDtechnicians':'technicians');
		}
		$rows['sel_options']['assigned'] = $rows['sel_options']['tr_assigned']; // For context menu popup
		unset($rows['sel_options']['assigned']['not']);

		$cats =array('' => lang('All categories'));
		$versions =  $resolutions = $statis = array();
		foreach((array)$tracker as $tr_id)
		{
			$versions += $this->get_tracker_labels('version',$tr_id);
			$cats += $this->get_tracker_labels('cat',$tr_id);
			$resolutions += $this->get_tracker_labels('resolution',$tr_id);
			$statis += $this->get_tracker_stati($tr_id);
		}

		$trackers = array_unique($visible_trackers);
		if($trackers)
		{
			foreach($trackers as $tracker_id)
			{
				$statis += $this->get_tracker_stati($tracker_id);
				$resolutions += $this->get_tracker_labels('resolution',$tracker_id);
			}
		}

		$rows['sel_options']['tr_status'] = $this->filters+$statis;
		$rows['sel_options']['cat_id'] = $cats;
		$rows['sel_options']['filter2'] = array(lang('All versions'))+$versions;
		$rows['sel_options']['tr_version'] =& $versions;
		$rows['sel_options']['tr_resolution'] =& $resolutions;

		$rows['is_admin'] = $this->is_admin($tracker);
		if ($this->is_admin($tracker))
		{
			$rows['sel_options']['canned_response'] = $this->get_tracker_labels('response',$tracker);
			$rows['sel_options']['tr_status_admin'] =& $statis;
		}
		$rows['no_votes'] = !$this->allow_voting;
		if (!$this->allow_voting)
		{
			$query_in['options-selectcols']['votes'] = false;
		}
		$rows['no_bounties'] = !$this->allow_bounties;
		if (!$this->allow_bounties)
		{
			$query_in['options-selectcols']['bounties'] = false;
		}

		$rows['no_cat_id'] = !!$rows['col_filter']['cat_id'];

		// enable tracker column if all trackers are shown
		$rows['no_tr_tracker'] = ($tracker && count($tracker) == 1);
	}

	/**
	 * Hook for timesheet to set some extra data and links
	 *
	 * @param array $data
	 * @param int $data[id] tracker_id
	 * @return array with key => value pairs to set in new timesheet and link_app/link_id arrays
	 */
	function timesheet_set($data)
	{
		$set = array();
		if ((int)$data['id'] && ($ticket = $this->read($data['id'])))
		{
			//error_log(__METHOD__.__LINE__.$this->exclude_app_on_timesheetcreation);
			foreach(Link::get_links('tracker',$ticket['tr_id'],'','link_lastmod DESC',true) as $link)
			{
				//if ($link['app'] != 'timesheet' && $link['app'] != Link::VFS_APPNAME)
				if (stripos($this->exclude_app_on_timesheetcreation.','.'timesheet'.','.Link::VFS_APPNAME,$link['app'])===false)
				{
					$set['link_app'][] = $link['app'];
					$set['link_id'][]  = $link['id'];
				}
			}
		}
		return $set;
	}

	/**
	 * Hook for InfoLog to set some extra data and links
	 *
	 * @param array $data
	 * @param int $data[id] tracker_id
	 * @return array with key => value pairs to set in new infolog and link_app/link_id arrays
	 */
	function infolog_set($data)
	{
		if (!($tracker = $this->read($data['id'])))
		{
			return array();
		}
		$set = array(
			'info_subject' => $tracker['tr_summary'],
			'info_des'     => $tracker['tr_description'],
			'info_contact' => 'tracker:'.$tracker['tr_id'],
		);
		// copy links
		foreach(Link::get_links('tracker',$tracker['tr_id'],'','link_lastmod DESC',true) as $link)
		{
			$set['link_app'][] = $link['app'];
			$set['link_id'][]  = $link['id'];

			// prefer addressbook or projectmanager link as primary contact over default of this ticket
			if (in_array($link['app'], array('addressbook','projectmanager')) &&
				strpos($set['info_contact'], 'addressbook:') !== 0)
			{
				$set['info_contact'] = $link['app'].':'.$link['id'];
			}
		}
		// copy same named customfields
		foreach(Api\Storage\Customfields::get('infolog') as $name => $nul)
		{
			unset($nul);
			if(array_key_exists('#'.$name, $tracker))
			{
				$set['#'.$name] = $tracker['#'.$name];
			}
		}
		return $set;
	}
	/**
	 * Check if a ticket has already been seen
	 *
	 * @param array $data =null Ticket data
	 * @param boolean $update =false Set ticket as seen when true
	 * @param boolean $been_seen =true Mark the ticket as seen/unseen by current user
	 * @return boolean true=seen before false=new ticket
	 */
	function seen(&$data, $update=false, $been_seen = true)
	{
		$seen = array();
		if ($data['tr_seen']) $seen = unserialize($data['tr_seen']);
		if ($update === false)
		{
			return in_array($this->user, $seen);
		}
		if($been_seen)
		{
			$seen[] = $this->user;
		}
		else
		{
			$key = array_search($this->user,$seen);
			if($key !== false)
			{
				unset($seen[$key]);
			}
		}
		$this->db->update('egw_tracker', array('tr_seen' => serialize(array_unique($seen))),
			array('tr_id' => $data['tr_id']),__LINE__,__FILE__,'tracker');
		return false; // This time still false...
	}

	/**
	 * Show a tracker
	 *
	 * @param array $content =null eTemplate content
	 * @param int $tracker =null id of tracker
	 * @param string $msg =''
	 * @param int $only_tracker =null show only the given tracker and not tracker-selection
	 * @param boolean $return_html =false if set to true, html content returned
	 * @return string html-content, if sitemgr otherwise null
	 */
	function index($content=null,$tracker=null,$msg='',$only_tracker=null, $return_html=false)
	{
		//_debug_array($this->trackers);
		if (!is_array($content))
		{
			if ($_GET['tr_id'])
			{
				if (!$this->read($_GET['tr_id']))
				{
					$msg = lang('Tracker item not found !!!');
				}
				else
				{
					return $this->edit(null,'',false);	// false = use no popup
				}
			}
			if (!$msg && $_GET['msg']) $msg = $_GET['msg'];
			if ($only_tracker && isset($this->trackers[$only_tracker]))
			{
				$tracker = $only_tracker;
			}
			else
			{
				$only_tracker = null;
			}
			// if there is no tracker specified, try the tracker submitted
			if (!$tracker && (int)$_GET['tracker']) $tracker = $_GET['tracker'];
			// if there is still no tracker, use the last tracker that was applied and saved to/with the view with the appsession
			if (!$tracker && ($state=  Api\Cache::getSession('tracker','index'.($only_tracker ? '-'.$only_tracker : ''))))
			{
			      $tracker=$state['col_filter']['tr_tracker'];
			}
		}
		else
		{
			$only_tracker = $content['only_tracker']; unset($content['only_tracker']);
			$tracker = $content['nm']['col_filter']['tr_tracker'];
			$this->called_by = $content['called_by']; unset($content['called_by']);

			if (is_array($content) && isset($content['nm']['rows']['document']))  // handle insert in default document button like an action
			{
				list($id) = @each($content['nm']['rows']['document']);
				$content['nm']['action'] = 'document';
				$content['nm']['selected'] = array($id);
			}
			if ($content['admin_popup'] && $content['nm']['action'] == 'admin')
			{
				$content['nm']['action'] = $content['admin_popup'];
			}
			// Clear multiple action popup
			unset($content['admin']);

			if($content['nm']['action'])
			{
				if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
				{
					$msg = lang('You need to select some entries first');
				}
				else
				{
					// Some processing to add values in for links and cats
					$multi_action = $content['nm']['action'];
					// Action has an additional action - add / delete, etc.  Buttons named <multi-action>_action[action_name]
					if(in_array($multi_action, array('link', 'assigned','group')))
					{
						$action = $content[$multi_action.'_popup'];
						$content['nm']['action'] .= '_' . key($action[$multi_action . '_action']);

						// Action handling function wants a single string value, so mush it together
						if(is_array($action[$multi_action]))
						{
							if($multi_action == 'link')
							{
								$action[$multi_action] = $action[$multi_action]['app'] . ':' . $action[$multi_action]['id'];
							}
							else
							{
								$action[$multi_action] = implode(',',$action[$multi_action]);
							}
						}
						$content['nm']['action'] .= '_' . $action[$multi_action];
						unset($content[$multi_action]);
						unset($content[$multi_action.'_popup']);
					}
					$success = $failed = $action_msg = null;
					if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
						$success,$failed,$action_msg,'index',$msg,$content['nm']['checkboxes']['no_notifications']))
					{
						$msg .= lang('%1 entries %2',$success,$action_msg);
					}
					else
					{
						if(is_null($msg) || $msg == '')
						{
							$msg = lang('%1 entries %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
						}
					}
				}
			}
		}

		if (!$tracker) $tracker = $content['nm']['col_filter']['tr_tracker'];
		$sel_options = array(
			'tr_tracker'  => $this->trackers,
			'tr_status'   => $this->filters + $this->get_tracker_stati($tracker),
			'tr_priority' => $this->get_tracker_priorities($tracker,$content['cat_id']),
			'tr_resolution' => $this->get_tracker_labels('resolution',$tracker),
			// Still need to provide options for the column filter
			'tr_private'  => array('No', 'Yes'),
		);
		if (($escalations = ExecMethod2('tracker.tracker_escalations.query_list','esc_title','esc_id')))
		{
			$sel_options['esc_id']['already escalated'] = $escalations;
			foreach($escalations as $esc_id => $label)
			{
				$sel_options['esc_id']['matching filter']['-'.$esc_id] = $label;
			}
		}
		// Merge print
		if ($GLOBALS['egw_info']['user']['preferences']['tracker']['document_dir'])
		{
			$documents = tracker_merge::get_documents($GLOBALS['egw_info']['user']['preferences']['tracker']['document_dir']);
			if($documents)
			{
				$sel_options['action'][lang('Insert in document').':'] = $documents;
			}
		}

		if (!is_array($content)) $content = array();
		$content['nm'] = Api\Cache::getSession('tracker', $this->called_by ? $this->called_by : 'index'.($only_tracker ? '-'.$only_tracker : ''));
		$content['msg'] = $msg;
		$content['status_help'] = !$this->pending_close_days ? lang('Pending items never get close automatic.') :
				lang('Pending items will be closed automatic after %1 days without response.',$this->pending_close_days);

		if (!is_array($content['nm']) || !$content['nm']['get_rows'])
		{
			$date_filters = array(lang('Date filter'));
			foreach(array_keys($this->date_filters) as $name)
			{
				$date_filters[$name] = lang($name);
			}
			$date_filters['custom'] = 'custom';
			$content['nm'] = array(
				'get_rows'       =>	'tracker.tracker_ui.get_rows',
				'cat_is_select'  => 'no_lang',
				'filter'         => 0,  // all
				'options-filter' => $date_filters,
				'filter_onchange' => "app.tracker.filter_change();",
				//'filter_label'   => lang('Date filter'),
				'filter_no_lang'=> true,
				'filter2'        => 0,	// all
				//'filter2_label'  => lang('Version'),
				'filter2_no_lang'=> true,
				'order'          =>	$this->allow_bounties ? 'bounties' : ($this->allow_voting ? 'votes' : 'tr_id'),// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'options-tr_assigned' => array('not' => lang('Noone')),
				'col_filter'     => array(
					'tr_status'  => 'not-closed',	// default filter: not closed
				),
	 			'only_tracker'   => $only_tracker,
	 			'default_cols'   => '!esc_id,legacy_actions,tr_summary_tr_description,tr_resolution,tr_completion,tr_sum_timesheets,votes,bounties',
				'row_id'         => 'tr_id',
				'row_modified'   => 'tr_modified',
				'placeholder_actions' => array('add')
			);
			// use the state of the last session stored in the user prefs
			if (!$this->called_by && ($state = @unserialize($GLOBALS['egw_info']['user']['preferences']['tracker']['index_state'])))
			{
				unset($state['header_left']); unset($state['header_right']);
				$content['nm'] = array_merge($content['nm'],$state);
				$tracker = $content['nm']['col_filter']['tr_tracker'];
			}
			elseif (!$this->called_by && !$tracker)
			{
				reset($this->trackers);
				list($tracker) = @each($this->trackers);
			}
			// disable times column, if no timesheet rights
			if (!isset($GLOBALS['egw_info']['user']['apps']['timesheet']))
			{
				$content['nm']['options-selectcols']['tr_sum_timesheets'] = false;
			}
			// disable start date / due date column, if disabled in config
			if(!$this->show_dates)
			{
				// Need to set each field so parser takes the whole column
				$content['nm']['options-selectcols']['tr_startdate'] = false;
				$content['nm']['options-selectcols']['tr_duedate'] = false;
			}
			$content['nm']['no_votes'] = !$this->allow_voting;
			$content['nm']['no_bounties'] = !$this->allow_bounties;
			$content['nm']['no_tr_sum_timesheets'] = false;
		}
		if (!$content['nm']['session_for'] && $this->called_by) $content['nm']['session_for'] = $this->called_by;
		if($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}
		// if there is only one tracker, use that one and do NOT show the selectbox
		if (count($this->trackers) == 1)
		{
			reset($this->trackers);
			list($tracker) = @each($this->trackers);
			$readonlys['nm']['col_filter[tr_tracker]'] = true;
		}
		if (!$tracker)
		{
			$tracker = $content['nm']['col_filter']['tr_tracker'] = '';
		}
		else
		{
			$content['nm']['col_filter']['tr_tracker'] = $tracker;
		}

		//
		// disable favories dropdown button, if not running as infolog
		if ($this->called_as || $content['nm']['session_for'])
		{
			$content['nm']['favorites'] = false;
		}
		else
		{
			$content['nm']['favorites'] = true; // Enable favorites
		}
		$content['duration_format'] = ','.$this->duration_format;

		$content['is_admin'] = $this->is_admin($tracker);
		//_debug_array($content);
		$readonlys['add'] = $readonlys['nm']['add'] = !$this->check_rights($this->field_acl['add'],$tracker,null,null,'add');
		$tpl = new Etemplate();
		if (!$tpl->sitemgr || !$tpl->read('tracker.index.sitemgr'))
		{
			$tpl->read('tracker.index');
		}

		// Apply link / avoid DOM conflicts
		if($this->called_by)
		{
			$content['nm'] = array_merge($content['nm'], Api\Cache::getSession('tracker', $this->called_by));
			$tpl->set_dom_id("{$tpl->name}-{$this->called_by}");
		}

		$content['nm']['actions'] = $this->get_actions($tracker, $content['cat_id']);

		// disable filemanager icon, if user has no access to it
		$readonlys['filemanager/navbar'] = !isset($GLOBALS['egw_info']['user']['apps']['filemanager']);

		// Disable actions if there are none
		if(count($sel_options['action']) == 0)
		{
			$tpl->disable_cells('action', true);
			$tpl->disable_cells('use_all', true);
		}

		// Show only own groups in group popup if queue Acl
		if($this->enabled_queue_acl_access)
		{
			$group = explode(',',$tpl->get_cell_attribute('group', 'size'));
			$group[1] = 'owngroups';
			$tpl->set_cell_attribute('group', 'size', implode(',',$group));
		}
		Framework::includeJS('.','app','tracker');
		// add scrollbar to long description, if user choose so in his prefs
		/* @kl: why is an if used, if it is effectily commented by a semicolon?
		if ($this->prefs['limit_des_lines'] > 0 || (string)$this->prefs['limit_des_lines'] == '');
		*/
		{
			$content['css'] .= '<style type="text/css">@media screen { .trackerDes {  '.
				($this->prefs['limit_des_width']?'max-width:'.$this->prefs['limit_des_width'].'em;':'').' max-height: '.
				(($this->prefs['limit_des_lines'] ? $this->prefs['limit_des_lines'] : 5) * 1.35).	// dono why em is not real lines
				'em; overflow: auto; }}
@media screen { .colfullWidth {
width:100%;
}</style>';
		}

		$preserve = array(
			'only_tracker' => $only_tracker,
			'called_by' => $this->called_by
		);

		return $tpl->exec('tracker.tracker_ui.index',$content,$sel_options,$readonlys,$preserve,$return_html);
	}

	/**
	 * Get actions / context menu items
	 *
	 * @param int $tracker =null
	 * @param int $cat_id =null
	 * @return array see nextmatch_widget::get_actions()
	 */
	public function get_actions($tracker=null, $cat_id=null)
	{
		for($i = 0; $i <= 100; $i += 10)
		{
			$percent[$i] = $i.'%';
		}
		$actions = array(
			'open' => array(
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=tracker.tracker_ui.edit&tr_id=$id',
				'popup' => Link::get_registry('tracker', 'add_popup'),
				'group' => $group=1,
				'onExecute' => Api\Header\UserAgent::mobile()?'javaScript:app.tracker.viewEntry':'',
				'mobileViewTemplate' => 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/tracker/templates/mobile/view.xet'))
			),
			'print' => array(
				'caption' => 'Print',
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.tracker.tprint',
				'group' => $group,
				'hideOnMobile' => true
			),
			'add' => array(
				'caption' => 'Add',
				'group' => $group,
				'url' => 'menuaction=tracker.tracker_ui.edit',
				'popup' => Link::get_registry('tracker', 'add_popup'),
				'hideOnMobile' => true
			),
			'no_notifications' => array(
				'caption' => 'Do not notify',
				'checkbox' => true,
				'hint' => 'Do not notify of these changes',
				'group' => $group,
			),
			// modifying content of one or multiple infolog(s)
			'change' => array(
				'caption' => 'Change',
				'group' => ++$group,
				'icon' => 'edit',
				'disableClass' => 'rowNoEdit',
				'children' => array(
					'seen' => array(
						'caption' => 'Mark as read',
						'group' => 1,
					),
					'unseen' => array(
						'caption' => 'Mark as unread',
						'group' => 1,
					),
					'tracker' => array(
						'caption' => 'Tracker Queue',
						'prefix' => 'tracker_',
						'children' => $this->trackers,
						'disabled' => count($this->trackers) <= 1,
						'hideOnDisabled' => true,
						'icon' => 'tracker/navbar',
					),
					'cat' => array(
						'caption' => 'Category',
						'prefix' => 'cat_',
						'children' => $items=$this->get_tracker_labels('cat',$tracker),
						'disabled' => count($items) <= 1,
						'hideOnDisabled' => true,
					),
					'version' => array(
						'caption' => 'Version',
						'prefix' => 'version_',
						'children' => $items=$this->get_tracker_labels('version',$tracker),
						'disabled' => count($items) <= 1,
						'hideOnDisabled' => true,
					),
					'assigned' => array(
						'caption' => 'Assigned to',
						'icon' => 'users',
						'nm_action' => 'open_popup',
						'onExecute' => 'javaScript:app.tracker.change_assigned'
					),
					'priority' => array(
						'caption' => 'Priority',
						'prefix' => 'priority_',
						'children' => $items=$this->get_tracker_priorities($tracker,$cat_id),
						'disabled' => count($items) <= 1,
						'hideOnDisabled' => true,
					),
					'status' => array(
						'caption' => 'Status',
						'prefix' => 'status_',
						'children' => $items=$this->get_tracker_stati($tracker),
						'disabled' => count($items) <= 1,
						'hideOnDisabled' => true,
						'icon' => 'check',
					),
					'resolution' => array(
						'caption' => 'Resolution',
						'prefix' => 'resolution_',
						'children' => $items=$this->get_tracker_labels('resolution',$tracker), // ToDo: get tracker specific solutions as well, have them available only when applicable
						'disabled' => count($items) <= 1,
						'hideOnDisabled' => true,
					),
					'completion' => array(
						'caption' => 'Completed',
						'prefix' => 'completion_',
						'children' => $percent,
						'icon' => 'completed',
					),
					'group' => array(
						'caption' => 'Group',
						'nm_action' => 'open_popup',
					),
					'link' => array(
						'caption' => 'Links',
						'nm_action' => 'open_popup',
					),
				),
				'hideOnMobile' => true
			),
			'close' => array(
				'caption' => 'Close',
				'icon' => 'check',
				'group' => $group,
				'disableClass' => 'rowNoClose',
			),

			'admin' => array(
				'caption' => 'Multiple changes',
				'group' => $group,
				'enabled' => isset($GLOBALS['egw_info']['user']['apps']['admin']),
				'hideOnDisabled' => true,
				'nm_action' => 'open_popup',
				'icon' => 'user',
			),
		);
		++$group;	// integration with other apps
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/tracker/$id',
				'allowOnMultiple' => false,
				'group' => $group,
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['timesheet'])
		{
			$actions['timesheet'] = array(	// interactive add for a single event
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'url' => 'menuaction=timesheet.timesheet_ui.edit&link_app[]=tracker&link_id[]=$id',
				'group' => $group,
				'allowOnMultiple' => false,
				'popup' => Link::get_registry('timesheet', 'add_popup'),
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['infolog'] && $this->allow_infolog)
		{
			$actions['infolog'] = array(
				'icon' => 'infolog/navbar',
				'caption' => 'InfoLog',
				'url' => 'menuaction=infolog.infolog_ui.edit&action=tracker&action_id=$id',
				'group' => $group,
				'allowOnMultiple' => false,
				'popup' => Link::get_registry('infolog', 'add_popup'),
			);
		}

		$actions['documents'] = tracker_merge::document_action(
			$this->prefs['document_dir'], ++$group, 'Insert in document', 'document_',
			$this->prefs['default_document']
		);

		//echo "<p>".__METHOD__."($do_email, $tid_filter, $org_view)</p>\n"; _debug_array($actions);
		return $actions;
	}

	/**
	 * imports a mail as Tracker
	 *
	 * @param array $mailContent = null mail content
	 * @return  array
	 */
	function mail_import(array $mailContent=null)
	{
		// It would get called from compose as a popup with egw_data
		if (!is_array($mailContent) && ($_GET['egw_data']))
		{
			// get the mail raw data
			Link::get_data ($_GET['egw_data']);
			return false;
		}
		// Wrap a pre tag if we are using html editor
		$message = $this->htmledit? "<pre>".$mailContent['message']."</pre>": $mailContent['message'];

		$this->edit($this->prepare_import_mail($mailContent['addresses'],
				$mailContent['subject'],
				$message,
				$mailContent['attachments'],
				$mailContent['entry_id']));
	}

	/**
	 * apply an action to multiple tracker entries
	 *
	 * @param string|int $action 'status_to',set status of entries
	 * @param array $checked tracker id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 entries 'deleted'
	 * @param string|array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @param string &$msg
	 * @param boolean $no_notification
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg,$no_notification)
	{
		//echo '<p>'.__METHOD__."('$action',".array2string($checked).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all)
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('tracker', $session_name);

			if ($use_all)
			{
				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$readonlys = null;
				$this->get_rows($query,$checked,$readonlys);
				// $this->get_rows gives some extra data.
				foreach($checked as $row => $data)
				{
					unset($data);
					if(!is_numeric($row))
					{
						unset($checked[$row]);
					}
				}
			}
		}

		if (is_array($action) && $action['update'])
		{
			unset($action['update']);
			// remove all 'No change'
			foreach($action as $name => $value)
			{
				if ($value === '') unset($action[$name]);
			}
			if (!count($checked) || !count($action))
			{
				$msg = lang('You need to select something to change AND some tracker items!');
				$failed = true;
			}
			else
			{
				foreach($checked as $tr_id)
				{
					if (!$this->read($tr_id)) continue;
					foreach($action as $name => $value)
					{
						if ($name == 'tr_status_admin') $name = 'tr_status';
						$this->data[$name] = $name == 'tr_assigned' && $value === 'not' ? NULL : $value;
					}
					if($no_notification) $this->data['no_notifications'] = true;
					if (!$this->save())
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				$action_msg = lang('updated');
			}
		}
		else
		{
			// Dialogs to get options
			list($action, $settings) = explode('_', $action, 2);

			switch($action)
			{
				case 'close':
					$action_msg = lang('closed');
					foreach($checked as $tr_id)
					{
						if (!$this->read($tr_id)) continue;
						$this->data['tr_status'] = tracker_bo::STATUS_CLOSED;
						if($no_notification) $this->data['no_notifications'] = true;
						if (!$this->save())
						{
							$success++;
						}
						else
						{
							$failed++;
						}
					}
					break;
				case 'seen':
				case 'unseen':
					$action_msg = lang($action);
					foreach($checked as $tr_id)
					{
						if (!$this->read($tr_id)) continue;
						self::seen($this->data, true, $action == 'seen');
						$success++;
					}
					break;
				case 'group':
					// Popup adds an extra param (add/delete) that group doesn't need
					list(,$settings) = explode('_',$settings);
				case 'tracker':
				case 'cat':
				case 'version':
				case 'priority':
				case 'status':
				case 'resolution':
				case 'completion':
					$action_msg = lang('updated');
					foreach($checked as $tr_id)
					{
						if (!$this->read($tr_id)) continue;
						$this->data[($action == 'cat' ? 'cat_id' : 'tr_'.$action)] = $settings;
						if($no_notification) $this->data['no_notifications'] = true;
						if (!$this->save())
						{
							$success++;
						}
						else
						{
							$failed++;
						}
					}
					break;
				case 'assigned':
					$action_msg = lang('updated');
					foreach($checked as $tr_id)
					{
						if (!$this->read($tr_id)) continue;
						list($add_remove, $idstr) = explode('_', $settings, 2);
						$ids = explode(',',$idstr);
						if($add_remove == 'ok')
						{
							$this->data['tr_assigned'] = $ids;
						}
						else
						{
							$this->data['tr_assigned'] = $add_remove == 'add' ?
								array_merge($this->data['tr_assigned'],$ids) :
								array_diff($this->data['tr_assigned'],$ids);
						}
						// No 0 allowed
						$this->data['tr_assigned'] = array_unique(array_diff($this->data['tr_assigned'], array(0)));
						if($no_notification) $this->data['no_notifications'] = true;
						if (!$this->save())
						{
							$success++;
						}
						else
						{
							$failed++;
						}
					}
					break;

				case 'link':
					list($add_remove, $link) = explode('_', $settings, 2);
					list($app, $link_id) = explode(':', $link);
					if(!$link_id)
					{
						$msg = lang('You need to select an entry for linking.');
						break;
					}
					error_log("APp: $app ID: $link_id");
					$title = Link::title($app, $link_id);
					foreach($checked as $id)
					{
						if (!$this->read($id))
						{
							$failed++;
							continue;
						}
						if($add_remove == 'add')
						{
							$action_msg = lang('linked to %1', $title);
							if(Link::link('tracker', $id, $app, $link_id))
							{
								$success++;
							}
							else
							{
								$failed++;
							}
						}
						else
						{
							$action_msg = lang('unlinked from %1', $title);
							$count = Link::unlink(0, 'tracker', $id, '', $app, $link_id);
							$success += $count;
						}
					}
					return $failed == 0;

				case 'document':
					if (!$settings) $settings = $GLOBALS['egw_info']['user']['preferences']['tracker']['default_document'];
					$document_merge = new tracker_merge();
					$msg = $document_merge->download($settings, $checked, '', $GLOBALS['egw_info']['user']['preferences']['tracker']['document_dir']);
					$failed = count($checked);
					return false;
			}
		}
		return !$failed;
	}

	/**
	 * Fill in canned comment
	 *
	 * @param id Canned comment ID
	 */
	public function ajax_canned_comment($id, $ckeditor=true)
	{
		$response = Api\Json\Response::get();

		if($ckeditor)
		{
			$response->call('app.tracker.canned_comment_response',nl2br($this->get_canned_response($id)));
		}
		else
		{
			$response->call('app.tracker.canned_comment_response', $this->get_canned_response($id));
		}
	}

	/**
	 * shows tracker in other applications
	 *
	 * @param $args['location'] location of hooks: {addressbook|projects|calendar}_view
	 * @param $args['view']     menuaction to view, if location == 'infolog'
	 * @param $args['app']      app-name, if location == 'infolog'
	 * @param $args['view_id']  name of the id-var for location == 'infolog'
	 * @param $args[$args['view_id']] id of the entry
	 * this function can be called for any app, which should include infolog: \
	 * 	Api\Hooks::process(array( \
	 * 		 * 'location' => 'infolog', \
	 * 		 * 'app'      => <your app>, \
	 * 		 * 'view_id'  => <id name>, \
	 * 		 * <id name>  => <id value>, \
	 * 		 * 'view'     => <menuaction to view an entry in your app> \
	 * 	));
	 */
	public function hook_view($args)
	{
		// Load JS for tracker actions
		Framework::includeJS('.','app','tracker');

		switch ($args['location'])
		{
			case 'addressbook_view':
				$app     = 'addressbook';
				$view_id = 'ab_id';
				// Just set the filter
				$state['action'] = $app;
				$state['action_id'] = $args[$view_id];
				Api\Cache::setSession('tracker', $app, $state);
				break;
		}
		if (!isset($app) || !isset($args[$view_id]))
		{
			return False;
		}
		$this->called_by = $app;	// for read/save_sessiondata, to have different sessions for the hooks

		// Set to calling app, so actions wind up in the correct place client side
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;

		Api\Translation::add_app('tracker');

		$this->index(null);
	}
}
