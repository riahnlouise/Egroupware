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

/**
 * Storage Object of the tracker
 */
class tracker_so extends Api\Storage
{
	/**
	 * Table-name of the main tracker table
	 */
	const TRACKER_TABLE = 'egw_tracker';
	/**
	 * Table-name for the replies
	 */
	const REPLIES_TABLE = 'egw_tracker_replies';
	/**
	 * Table-name for the votes
	 */
	const VOTES_TABLE = 'egw_tracker_votes';
	/**
	 * Table-name for the bounties
	 */
	const BOUNTIES_TABLE = 'egw_tracker_bounties';
	/**
	 * Table-name for the assignee
	 */
	const ASSIGNEE_TABLE = 'egw_tracker_assignee';
	/**
	 * Table-name for the already done escalations
	 */
	const ESCALATED_TABLE = 'egw_tracker_escalated';
	/**
	 * Table-name for custom fields
	 */
	const EXTRA_TABLE = 'egw_tracker_extra';

	/**
	 * Tracker's default stati (they are strings as some php versions have problems with negative array indexes)
	 */
	const STATUS_OPEN    = '-100';
	const STATUS_CLOSED  = '-101';
	const STATUS_DELETED = '-102';
	const STATUS_PENDING = '-103';
	const SQL_NOT_CLOSED = "(tr_status != '-101' AND tr_status != '-102')";
	/**
	 * Fields which are not part of the main table, but need to be merged and init()
	 *
	 * @var array
	 */
	var $non_db_cols = array('tr_assigned','reply_message','reply_visible');

	/**
	 * Constructor
	 *
	 * @return tracker_so
	 */
	function __construct()
	{
		// Set columns to search here so self::TRACKER_TABLE is available
		$this->columns_to_search = array(self::TRACKER_TABLE.'.tr_id','tr_summary','tr_description','tr_budget','reply_message');
		parent::__construct('tracker',self::TRACKER_TABLE,self::EXTRA_TABLE,'','tr_extra_name','tr_extra_value','tr_id');

		if ($this->customfields)	// add custom fields, if configured
		{
			$this->columns_to_search[] = self::EXTRA_TABLE.'.tr_extra_value';
		}
	}

	/**
	 * Read replies and bounties (non-admin only confirmed ones) of current entry ($this->data)
	 *
	 * @param boolean $is_admin =false
	 * @param boolean $is_technician =false
	 * @param int $user =null
	 * @return array $this->data incl. replies, bounties, etc.
	 */
	function read_extra($is_admin=false, $is_technician=false, $user=null)
	{
		$read_restricted = $is_admin || $is_technician;
		if (!$user) $user = $this->user;

		$this->data2db();

		$bounty_where = array('tr_id' => $this->data['tr_id']);
		if (!$is_admin)
		{
			$bounty_where[] = 'bounty_confirmed IS NOT NULL';
		}
		$this->data['bounties'] = $this->read_bounties($bounty_where);

		$this->data['tr_assigned'] = array();
		foreach($this->db->select(self::ASSIGNEE_TABLE,'tr_assigned',array('tr_id' => $this->data['tr_id']),
			__LINE__,__FILE__,false,'','tracker') as $row)
		{
			$this->data['tr_assigned'][] = $row['tr_assigned'];
			$read_restricted = $read_restricted || $row['tr_assigned'] == $user ||
				// if assigned to a group, we need to check memberships of $user
				$GLOBALS['egw']->accounts->get_type($row['tr_assigned']) == 'g' &&
					in_array($row['tr_assigned'], $GLOBALS['egw']->accounts->memberships($user, true));
		}

		$this->data['replies'] = array();
		$filter = array('tr_id' => $this->data['tr_id']);
		if(!$read_restricted)
		{
			$filter['reply_visible'] = 0;
		}
		foreach($this->db->select(self::REPLIES_TABLE,'*',$filter,
			__LINE__,__FILE__,false,'ORDER BY reply_id DESC','tracker') as $row)
		{
			$this->data['replies'][] = $row;
		}
		$this->data['num_replies'] = count($this->data['replies']);
		$this->db2data();

		return $this->data;
	}

	/**
	 * Save a tracker item
	 *
	 * Reimplemented to save the reply too
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if ($keys)
		{
			$this->data_merge($keys);
		}
		if (($ret = parent::save(null, $extra_where)) == 0)
		{
			$this->data2db();
			if ($this->data['reply_message'])
			{
				$this->data['reply_visible'] = is_null($this->data['reply_visible']) ? 0 : $this->data['reply_visible'];
				$this->db->insert(self::REPLIES_TABLE,$this->data,false,__LINE__,__FILE__,'tracker');
				// add the new replies to this->data[replies]
				if (!is_array($this->data['replies'])) $this->data['replies'] = array();
				array_unshift($this->data['replies'],array(
					'reply_id'      => $this->db->get_last_insert_id(self::REPLIES_TABLE,'reply_id'),
					'tr_id'         => $this->data['tr_id'],
					'reply_creator' => $this->data['reply_creator'],
					'reply_created' => $this->data['reply_created'],
					'reply_message' => $this->data['reply_message'],
					'reply_visible' => $this->data['reply_visible'],
				));
				$this->data['num_replies'] = (int)$this->data['num_replies'] + 1;
			}
			$this->db->delete(self::ASSIGNEE_TABLE,array('tr_id'=>$this->data['tr_id']),__LINE__,__FILE__,'tracker');
			if ($this->data['tr_assigned'])
			{
				if (!is_array($this->data['tr_assigned']))
				{
					$this->data['tr_assigned'] = explode(',',$this->data['tr_assigned']);
				}
				//_debug_array($this->data['tr_assigned']);
				foreach($this->data['tr_assigned'] as $assignee)
				{
					$this->db->insert(self::ASSIGNEE_TABLE,array(
						'tr_id' => $this->data['tr_id'],
						'tr_assigned' => $assignee,
					),false,__LINE__,__FILE__,'tracker');
				}
			}
			$this->db2data();
		}
		return $ret;
	}

	/**
	 * Searches / lists tracker items
	 *
	 * Reimplemented to join with the votes table and respect the private attribute
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join_in ='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join_in=true,$need_full_no_count=false)
	{
		unset($need_full_no_count);	// not used

		if (!$this->trackers)
		{
			return array();	// no access to any tracker queue, but tracker app
		}
		$join = $join_in && $join_in != 1 ? $join_in : '';

		// private ACL: private items are only visible for create, assiged or tracker admins
		foreach ((array)$filter['tr_tracker'] as $tr)
		{
			$need_private_acl = $this->user && method_exists($this,'is_admin') && !$this->is_admin($tr);
		}

		if (is_array($filter) && array_key_exists('tr_assigned',$filter))
		{
			if (is_null($filter['tr_assigned']))
			{
				$join .= ' LEFT JOIN '.self::ASSIGNEE_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::ASSIGNEE_TABLE.'.tr_id';
				$filter[] = 'tr_assigned IS NULL';
			}
			else
			{
				$join .= ' JOIN '.self::ASSIGNEE_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::ASSIGNEE_TABLE.'.tr_id AND '.
					$this->db->expression(self::ASSIGNEE_TABLE,array('tr_assigned' => $filter['tr_assigned']));
			}
			unset($filter['tr_assigned']);
		}
		elseif($need_private_acl)
		{
			$join .= ' LEFT JOIN '.self::ASSIGNEE_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::ASSIGNEE_TABLE.'.tr_id';
		}
		// check if we order by tr_id, replace it with egr_tracker.tr_id, as tr_id is ambigues
		if (strpos($order_by,'tr_id') !== false && strpos($order_by,self::TRACKER_TABLE.'.tr_id') === false)
		{
			$order_by = str_replace('tr_id',self::TRACKER_TABLE.'.tr_id',$order_by);
		}

		// Check if we order by tr_modified, and use tr_created for null rows
		static $order_replace = 'CASE WHEN tr_modified IS NULL THEN tr_created ELSE tr_modified END';
		if (strpos($order_by,'tr_modified') !== false && strpos($order_by,$order_replace) === false)
		{
			$order_by = str_replace('tr_modified',$order_replace,$order_by);
		}

		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();

		if (is_array($filter) && array_key_exists('esc_id',$filter))
		{
			if ($filter['esc_id'])
			{
				// we left join with the escalated timestamp table, to check if the escalation is not alread done
				$filter[] = self::escalated_filter(abs($filter['esc_id']),$join,$filter['esc_id'] > 0);
				if($filter['esc_id'] > 0)
				{
					$extra_cols[] = self::ESCALATED_TABLE . '.esc_created AS esc_start';
				}
				else
				{
					$escalation = new tracker_escalations(abs($filter['esc_id']));
					$filter[] = $fs = $this->db->expression(self::TRACKER_TABLE,$f = $escalation->get_filter());
					//echo "filter($filter[esc_id])='$fs'="; _debug_array($f);
					$extra_cols[] = $escalation->get_time_col().' AS esc_start';
				}
			}
			unset($filter['esc_id']);
		}
		if ($need_private_acl)
		{
			$filter[] = '(tr_private=0 OR tr_creator='.$this->user.' OR tr_assigned IN ('.$this->user.','.
				implode(',',$GLOBALS['egw']->accounts->memberships($this->user,true)).'))';
		}
		if (is_string($criteria) && $criteria)
		{
			$criteria = $this->search2criteria($criteria, $wildcard, $op);
			$join .= ' LEFT JOIN '.self::REPLIES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id';
		}
		elseif(isset($criteria['tr_id']))
		{
			$criteria[self::TRACKER_TABLE.'.tr_id'] = $criteria['tr_id'];
			unset($criteria['tr_id']);
		}

		if ($join_in === true || $join_in == 1)
		{
			if ($this->db->capabilities['sub_queries'])	// everything, but old MySQL
			{
				$extra_cols[] = '(SELECT COUNT(*) FROM '.self::VOTES_TABLE.' WHERE '.self::TRACKER_TABLE.'.tr_id='.self::VOTES_TABLE.'.tr_id) AS votes';
				$extra_cols[] = '(SELECT SUM(bounty_amount) FROM '.self::BOUNTIES_TABLE.' WHERE '.self::TRACKER_TABLE.'.tr_id='.self::BOUNTIES_TABLE.'.tr_id AND bounty_confirmed IS NOT NULL) AS bounties';
			}
			else	// MySQL < 4.1
			{
				// join with votes
				$join .= ' LEFT JOIN '.self::VOTES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::VOTES_TABLE.'.tr_id';
				$extra_cols[] = 'COUNT(vote_time) AS votes';
				// join with bounties
				$join .= ' LEFT JOIN '.self::BOUNTIES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::BOUNTIES_TABLE.'.tr_id AND bounty_confirmed IS NOT NULL';
				$extra_cols[] = 'SUM(bounty_amount) AS bounties';
				// fixes to get tr_id non-ambigues
				if (is_bool($only_keys)) $only_keys = self::TRACKER_TABLE.($only_keys ? '.tr_id' : '.*');
			}
			// default sort is after bountes and votes, only adding them if they are not already there, as doublicate order gives probs on MsSQL
			if (strpos($order_by,'bounties') === false) $order_by .= ($order_by ? ',' : '').'bounties DESC';
			if (strpos($order_by,'votes') === false) $order_by .= ($order_by ? ',' : '').'votes DESC';
		}

		// check for queue specific restrictions
		if ($this->user) // Skip this in the cron-runs (close_pending(), OvE, 20071124)
		{
			// we need to check all trackers for restrictions or (read) ACL (if enabled)
			$need_restrictions = false;
			$no_restrictions = $no_access = $creator_restrictions = $group_restrictions = array();
			foreach($filter['tr_tracker'] ? (array)$filter['tr_tracker'] : array_keys($this->trackers) as $tracker)
			{
				// technicians (and admins) allways have access to restricted queues
				if ($this->is_technician($tracker))
				{
					$no_restrictions[] = $tracker;
				}
				// non users have no access if (read) ACL is turned on
				elseif($this->enabled_queue_acl_access && !$this->is_user($tracker))
				{
					$no_access[] = $tracker;
					$need_restrictions = true;
				}
				// check creator or group restrictions
				// tickets only visible to creator or specified group (or technicians and admins)
				elseif ($this->restrictions[$tracker]['group'] || $this->restrictions[$tracker]['creator'])
				{
					if ($this->restrictions[$tracker]['creator']) $creator_restrictions[] = $tracker;
					if ($this->restrictions[$tracker]['group']) $group_restrictions[] = $tracker;
					$need_restrictions = true;
				}
				// neither restrictions nor read ACL
				else
				{
					$no_restrictions[] = $tracker;
				}
			}
			//error_log('no_restrictions='.array2string($no_restrictions).', no_access='.array2string($no_access).', creator_restrictions='.$creator_restrictions.', group_restrictions='.array2string($group_restrictions).', need_restrictions='.array2string($need_restrictions));
			// do we need to restrict access to cretain queues
			if ($need_restrictions)
			{
				if ($no_access)
				{
					$filter[] = $this->db->expression(self::TRACKER_TABLE,'NOT ',array(
						'tr_tracker' => $no_access,
					));
				}
				$to_or = array();
				if ($no_restrictions)
				{
					$to_or[] = $this->db->expression(self::TRACKER_TABLE,array(
						'tr_tracker' => $no_restrictions,
					));
				}
				if ($creator_restrictions)
				{
					$to_or[] = $this->db->expression(self::TRACKER_TABLE,array(
						'tr_tracker' => $creator_restrictions,
						'tr_creator' => $this->user,
					));
				}
				if ($group_restrictions)
				{
					$to_or[] = $this->db->expression(self::TRACKER_TABLE,array(
						'tr_tracker' => $group_restrictions,
						'tr_group' => $GLOBALS['egw']->accounts->memberships($this->user,true),
					));
				}
				// allow assignee access if we are no admin ($need_private_acl) AND we have group/creator restrictions
				if ($need_private_acl && ($creator_restrictions || $group_restrictions))
				{
					$to_or[] = 'tr_assigned IN ('.$this->user.','.
						implode(',',$GLOBALS['egw']->accounts->memberships($this->user,true)).')';
				}
				if ($to_or)
				{
					$filter[] = '('.implode(' OR ',$to_or).')';
				}
				//_debug_array($filter);
			}
			//error_log('filter='.array2string($filter));
		}
		//$this->debug = 4;


		// Add in custom filters that = closed
		$custom_closed = array();
		$stati = ExecMethod('tracker.tracker_bo.get_tracker_stati', $tracker);
		foreach(array_keys($stati) as $stati_id)
		{
			$data = Api\Categories::id2name($stati_id, 'data');
			if($data['closed']) $custom_closed[] = $stati_id;
		}
		$not_closed = substr(self::SQL_NOT_CLOSED,0,-1) . ' AND tr_status != \'' . implode('\' AND tr_status != \'', $custom_closed) . '\')';
		if (empty($custom_closed)) $not_closed = self::SQL_NOT_CLOSED;

		// Handle the special filters
		switch ($filter['tr_status'])
		{
			case 'closed':
				unset($filter['tr_status']);
				$filter[] = str_replace(array('!=', 'AND'), array('=','OR'),$not_closed);
				break;
			case 'not-closed':
				unset($filter['tr_status']);
				$filter[] = $not_closed;
				break;
			case 'own-not-closed':
				unset($filter['tr_status']);
				$filter['tr_creator'] = $this->user;
				$filter[] = $not_closed;
				break;
			case 'ownorassigned-not-closed':
				unset($filter['tr_status']);
				$filter[] = '('.$this->db->expression(self::TRACKER_TABLE,array('tr_creator' => $this->user)).' OR '.
					self::TRACKER_TABLE.'.tr_id in (SELECT tr_id from '.self::ASSIGNEE_TABLE.' WHERE '. $this->db->expression(self::ASSIGNEE_TABLE,'tr_assigned IN ('.$this->user.','.implode(',',$GLOBALS['egw']->accounts->memberships($this->user,true)).')').'))';
				$filter[] = $not_closed;
				break;
			case 'without-reply-not-closed':
				unset($filter['tr_status']);
				if ($this->db->capabilities['sub_queries'])     // everything, but old MySQL
				{
					$filter[] = '((SELECT COUNT(*) FROM '.self::REPLIES_TABLE.' WHERE '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id) = 0)';
				}
				else    // MySQL < 4.1
				{
					// Not allready join comments tables
					if (!$criteria and !$this->db->capabilities['sub_queries'])
					{
						$join .= ' LEFT JOIN '.self::REPLIES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id';
					}
					$extra_cols[] = 'COUNT(reply_id) AS replies';
					$filter[] = 'replies=0';
				}
				$filter[] = $not_closed;
				break;
			case 'own-without-reply-not-closed':
				unset($filter['tr_status']);
				if ($this->db->capabilities['sub_queries'])     // everything, but old MySQL
				{
					$filter[] = '((SELECT COUNT(*) FROM '.self::REPLIES_TABLE.' WHERE '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id) = 0)';
				}
				else    // MySQL < 4.1
				{
					// Not allready join comments tables
					if (!$criteria && !$this->db->capabilities['sub_queries'])
					{
						$join .= ' LEFT JOIN '.self::REPLIES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id';
					}
					$extra_cols[] = 'COUNT(reply_id) AS replies';
					$filter[] = 'replies=0';
				}
				$filter['tr_creator'] = $this->user;
				$filter[] = $not_closed;
				break;
			case 'without-30-days-reply-not-closed':
				unset($filter['tr_status']);
				if ($this->db->capabilities['sub_queries'])     // everything, but old MySQL
				{
					$filter[] = '((SELECT COUNT(*) FROM '.self::REPLIES_TABLE.' WHERE '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id and reply_created > '.mktime(0, 0, 0, date('m')-1, date('d'),date('Y')).') = 0)';
				}
				else    // MySQL < 4.1
				{
					// Not allready join comments tables
					if (!$criteria and !$this->db->capabilities['sub_queries'])
					{
						$join .= ' LEFT JOIN '.self::REPLIES_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::REPLIES_TABLE.'.tr_id';
					}
					$extra_cols[] = 'COUNT(reply_id) AS replies';
					$filter[] = 'replies=0';
				}
				$filter[] = $not_closed;
				break;
			case 'open':
				$filter['tr_status'] = self::STATUS_OPEN;
				break;
			case 'closed':
				$filter['tr_status'] = self::STATUS_CLOSED;
				break;
			case 'pending':
				$filter['tr_status'] = self::STATUS_PENDING;
				break;
			case 'deleted':
				$filter['tr_status'] = self::STATUS_DELETED;
				break;
		}
		// avoid ambigues tr_id
		if (is_bool($only_keys))
		{
			$only_keys = $only_keys ? self::TRACKER_TABLE.'.tr_id' : self::TRACKER_TABLE.'.*';
			$extra_cols[] = self::TRACKER_TABLE.'.tr_id AS tr_id';	// otherwise the joined tables tr_id, which might be NULL, can hide tr_id
		}
		else
		{
			if(!is_array($only_keys)) $only_keys = explode(',',$only_keys);
			if (($id_key = array_search('tr_id',$only_keys)) !== false)
			{
				$only_keys[] = self::TRACKER_TABLE.'.tr_id AS tr_id';
				unset($only_keys[$id_key]);
			}
		}
		// if we have a join (searching by extra_value also adds a join), we have to group by tr_id, to avoid getting rows multiple times
		if (($join || isset($criteria[$this->extra_value])) && stristr($order_by,'GROUP BY') === false)	// group by tr_id, as we get one row per assignee!
		{
			$order_by = ' GROUP BY '.self::TRACKER_TABLE.'.tr_id, '.self::TRACKER_TABLE.'.tr_summary, '.self::TRACKER_TABLE.'.tr_tracker, '.self::TRACKER_TABLE.'.cat_id, '.self::TRACKER_TABLE.'.tr_version, '.self::TRACKER_TABLE.'.tr_status , '.self::TRACKER_TABLE.'.tr_description, '.self::TRACKER_TABLE.'.tr_private, '.self::TRACKER_TABLE.'.tr_budget, '.self::TRACKER_TABLE.'.tr_completion, '.self::TRACKER_TABLE.'.tr_creator , '.self::TRACKER_TABLE.'.tr_created, '.self::TRACKER_TABLE.'. tr_modifier, '.self::TRACKER_TABLE.'.tr_modified, '.self::TRACKER_TABLE.'.tr_startdate, '.self::TRACKER_TABLE.'.tr_duedate, '.self::TRACKER_TABLE.'.tr_closed, '.self::TRACKER_TABLE.'.tr_priority, '.self::TRACKER_TABLE.'.tr_resolution, '.self::TRACKER_TABLE.'.tr_cc, '.self::TRACKER_TABLE.'.tr_group, '.self::TRACKER_TABLE.'.tr_edit_mode, '.self::TRACKER_TABLE.'.tr_seen ORDER BY '.
					($order_by ? $order_by : (($join_in === true || $join_in == 1) ? 'bounties DESC' : self::TRACKER_TABLE.'.tr_id'));
		}

		$rows =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);

		if ($rows)
		{
			foreach($rows as $key => &$row)
			{
				if (isset($row['tr_id'])) $ids[$key] = $row['tr_id'];
				$row['tr_assigned'] = array();
			}
			if ($ids)
			{
				$id2key = array_flip($ids);
				foreach($this->db->select(self::ASSIGNEE_TABLE,'tr_id,tr_assigned',array('tr_id' => $ids),__LINE__,__FILE__,false,'','tracker') as $assignee)
				{
					$rows[$id2key[$assignee['tr_id']]]['tr_assigned'][] = $assignee['tr_assigned'];
				}
			}
		}
		return $rows;
	}

	/**
	 * Filter by a certain escalation (either done or not done)
	 *
	 * @param int $esc_id
	 * @param string& $join join with escalation table is added there
	 * @param boolean|timetamp $escalated =true default true=return only escalated tickets, false = return not escalated ticktes,
	 * 	timestamp = return tickets escalated before the time
	 * @return string filter
	 */
	function escalated_filter($esc_id,&$join,$escalated=true)
	{
		$join .= ' LEFT JOIN '.self::ESCALATED_TABLE.' ON '.self::TRACKER_TABLE.'.tr_id='.self::ESCALATED_TABLE.'.tr_id AND esc_id='.(int)$esc_id;

		if($escalated && $escalated !== true)
		{
			return '(esc_created IS NULL or esc_created < ' . $this->db->quote($this->db->to_timestamp($escalated)).')';
		}
		return $escalated ? 'esc_created IS NOT NULL' : 'esc_created IS NULL';
	}

	/**
	 * Delete tracker items with the given keys
	 *
	 * @param array|int $keys =null if given array with col => value pairs to characterise the rows to delete, or integer autoinc id
	 * @param boolean $only_return_ids =false return $ids of delete call to db object, but not run it (can be used by extending classes!)
	 * @return int|array affected rows, should be 1 if ok, 0 if an error or array with id's if $only_return_ids
	 */
	function delete($keys=null,$only_return_ids=false)
	{
		if (!$keys)
		{
			$keys = array('tr_id' => $this->data['tr_id']);
		}
		elseif (!is_array($keys))
		{
			$keys = array('tr_id' => $keys);
		}
		if (!$only_return_ids)
		{
			$where = array();
			if (($where['tr_id'] = parent::delete($keys,true)))
			{
				$this->db->delete(self::REPLIES_TABLE,$where,__LINE__,__FILE__,'tracker');
				$this->db->delete(self::VOTES_TABLE,$where,__LINE__,__FILE__,'tracker');
				$this->db->delete(self::BOUNTIES_TABLE,$where,__LINE__,__FILE__,'tracker');
				$this->db->delete(self::ASSIGNEE_TABLE,$where,__LINE__,__FILE__,'tracker');
				$this->db->delete(self::ESCALATED_TABLE,$where,__LINE__,__FILE__,'tracker');
			}
		}
		return parent::delete($keys,$only_return_ids);
	}

	/**
	 * changes or deletes entries with a spezified owner (for hook_delete_account)
	 *
	 * @param array $args hook arguments
	 * @param int $args['account_id'] account to delete
	 * @param int $args['new_owner'] =0 new owner
	 */
	function change_delete_owner(array $args)  // new_owner=0 means delete
	{
		if (!(int) $args['new_owner'])
		{
			foreach($this->db->select(self::TRACKER_TABLE,'tr_id',array('tr_creator'=>$args['account_id']),__LINE__,__FILE__,false,'','tracker') as $row)
			{
				$this->delete($row['tr_id'],False);
			}
		}
		else
		{
			$this->db->update(self::TRACKER_TABLE,array('tr_creator'=>$args['new_owner']),array('tr_creator'=>$args['account_id']),__LINE__,__FILE__,'tracker');
			$this->db->update(self::TRACKER_TABLE,array('tr_modifier'=>$args['new_owner']),array('tr_modifier'=>$args['account_id']),__LINE__,__FILE__,'tracker');
			$toDelete = array();
			$toUpdate = array();
			foreach($this->db->select(self::ASSIGNEE_TABLE,'tr_id,tr_assigned',array('tr_assigned'=>$args['account_id']),__LINE__,__FILE__,false,'','tracker') as $row)
			{
				//error_log(__METHOD__.__LINE__.array2string($row));
				$toUpdate[$row['tr_id']] = $row['tr_assigned'];
			}
			foreach($this->db->select(self::ASSIGNEE_TABLE,'tr_id,tr_assigned',array('tr_assigned'=>$args['new_owner']),__LINE__,__FILE__,false,'','tracker') as $row)
			{
				//error_log(__METHOD__.__LINE__.array2string($row));
				if (isset($toUpdate[$row['tr_id']]))
				{
					$toDelete[$row['tr_id']] = $toUpdate[$row['tr_id']];
					unset($toUpdate[$row['tr_id']]);
				}
			}
			foreach ($toDelete as $trid => $who)
			{
				$this->db->delete(self::ASSIGNEE_TABLE,array('tr_id'=>$trid,'tr_assigned'=>$who),__LINE__,__FILE__,'tracker');
			}
			foreach ($toUpdate as $trid => $who)
			{
				$this->db->update(self::ASSIGNEE_TABLE,array('tr_assigned'=>$args['new_owner']),array('tr_id'=>$trid,'tr_assigned'=>$args['account_id']),__LINE__,__FILE__,'tracker');
			}
		}
	}

	/**
	 * Check if users is allowed to vote - has not already voted
	 *
	 * @param int $tr_id tracker-id
	 * @param int $user account_id
	 * @param string $ip =null IP, if it should be checked too
	 */
	function check_vote($tr_id,$user,$ip=null)
	{
		$where = array(
			'tr_id'    => $tr_id,
			'vote_uid' => $user,
		);
		if ($ip) $where['vote_ip'] = $ip;

		return $this->db->select(self::VOTES_TABLE,'vote_time',$where,__LINE__,__FILE__,false,'','tracker')->fetchColumn();
	}

	/**
	 * Cast vote for given tracker-item
	 *
	 * @param int $tr_id tracker-id
	 * @param int $user account_id
	 * @param string $ip IP
	 * @return boolean true=vote casted, false=already voted before
	 */
	function cast_vote($tr_id,$user,$ip)
	{
		return !!$this->db->insert(self::VOTES_TABLE,array(
			'tr_id'     => $tr_id,
			'vote_uid'  => $user,
			'vote_ip'   => $ip,
			'vote_time' => time(),
		),false,__LINE__,__FILE__,'tracker');
	}

	/**
	 * Save or update a bounty
	 *
	 * @param array $data
	 * @return int|boolean integer bounty_id or false on error
	 */
	function save_bounty($data)
	{
		if ((int) $data['bounty_id'])
		{
			$where = array('bounty_id' => $data['bounty_id']);
			unset($data['bounty_id']);
			if ($this->db->update(self::BOUNTIES_TABLE,$data,$where,__LINE__,__FILE__,'tracker'))
			{
				return $where['bounty_id'];
			}
		}
		else
		{
			if ($this->db->insert(self::BOUNTIES_TABLE,$data,false,__LINE__,__FILE__,'tracker'))
			{
				return $this->db->get_last_insert_id(self::BOUNTIES_TABLE,'bounty_id');
			}
		}
		return false;
	}

	/**
	 * Delete a bounty
	 *
	 * @param int $id
	 * @return int number of deleted rows: 1 = success, 0 = failure
	 */
	function delete_bounty($id)
	{
		return $this->db->delete(self::BOUNTIES_TABLE,array('bounty_id' => $id),__LINE__,__FILE__,'tracker');
	}

	/**
	 * Read bounties specified by the given keys
	 *
	 * @param array|int $keys array with key(s) or integer bounty-id
	 * @return array with bounties
	 */
	function read_bounties($keys)
	{
		if (!is_array($keys)) $keys = array('bounty_id' => $keys);

		$bounties = array();
		foreach($this->db->select(self::BOUNTIES_TABLE,'*',$keys,__LINE__,__FILE__,false,'ORDER BY bounty_created DESC','tracker') as $row)
		{
			$bounties[] = $row;
		}
		return $bounties;
	}
}
