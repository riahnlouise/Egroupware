<?php
/**
 * Tracker - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package tracker
 * @copyright (c) 2007-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;

/**
 * Tracker - document merge object
 */
class tracker_merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'	=> true,
		'show_replacements'		=> true,
		'tracker_replacements'	=> true,
	);

	/**
	 * Business object to pull records from
	 */
	protected $bo = null;

	/**
	 * Cache comments per ticket to reduce database hits
	 */
	protected $comment_cache = array();

	/**
	 * Allow to set comments to avoid re-reading from DB
	 */
	protected $preset_comments = array();

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
		$this->table_plugins['comment'] = 'comment';
		$this->table_plugins['comment/-1'] = 'comment';
		$this->bo = new tracker_bo();

		// switch of handling of html formated content, if html is not used
		$this->parse_html_styles = $this->bo->htmledit || Api\Storage\Customfields::use_html('tracker');

	}

	/**
	 * Get replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->tracker_replacements($id,'', $content)))
		{
			return false;
		}
		if(strpos($content,'all_comments') !== false) {
			$this->bo->read($id);
			$tracker = $this->bo->data;
			$replies = array();
			foreach($tracker['replies'] as $id => $reply) {
				// User date format
				$date = Api\DateTime::to($reply['reply_created']);
				$name = Api\Accounts::username($reply['reply_creator']);
				$message = str_replace("\r\n", "\n", $reply['reply_message']);
				if($reply['reply_visible'] > 0) {
					$message = '['.$message.']';
				}
				$restricted = $reply['reply_visible']=='0' ? '' : lang('restricted');
				$replies[$id] = "$date \t$name \t$restricted\n$message";
			}
			$replacements['$$all_comments$$'] = implode("\n",$replies);
		}
		return $replacements;
	}

	/**
	 * Get tracker replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function tracker_replacements($id,$prefix='', &$content='')
	{
		$record = new tracker_egw_record($id);
		$info = array();

		// Convert to human friendly values
		$types = tracker_egw_record::$types;
		// Get lookups for human-friendly values
		$lookups = array(
			'tr_tracker'    => $this->bo->trackers,
			'tr_version'    => $this->bo->get_tracker_labels('version', null),
			'tr_status'     => $this->bo->get_tracker_stati(null),
			'tr_resolution' => $this->bo->get_tracker_labels('resolution',null),
			'tr_private'	=> array(false => lang('no'),'1'=>lang('yes'))
		);
		foreach($lookups['tr_tracker'] as $t_id => $name) {
			$lookups['tr_version'] += $this->bo->get_tracker_labels('version', $t_id);
			$lookups['tr_status'] += $this->bo->get_tracker_stati($t_id);
			$lookups['tr_resolution'] += $this->bo->get_tracker_labels('resolution', $t_id);
		}
		$lookups['tr_priority'] = $this->bo->get_tracker_priorities($record->tr_tracker, $record->cat_id);

		$array = array();

		// Signature
		if($this->bo->notification[$record->tr_tracker]['use_signature'] || $this->bo->notification[0]['use_signature'])
		{
			if(trim(strip_tags($this->bo->notification[$record->tr_tracker]['signature'])) &&
				$this->bo->notification[$record->tr_tracker]['use_signature'])
			{
				$array['signature'] = $this->bo->notification[$record->tr_tracker]['signature'];
			}
			else
			{
				$array['signature'] = $this->bo->notification[0]['signature'];
			}
		}

		// Expand custom field links
		if($content && strpos($content, '#') !== 0)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $info);
		}

		importexport_export_csv::convert($record, $types, 'tracker', $lookups);
		$array += $record->get_record_array();

		$array['tr_completion'] = (int)$array['tr_completion'] . '%';

		// HTML link to ticket
		$tracker = new tracker_tracking($this->bo);
		$array['tr_link'] = Api\Html::a_href($array['tr_summary'], $tracker->get_link($array, array()));

		// Set any missing custom fields, or the marker will stay
		foreach(array_keys($this->bo->customfields) as $name)
		{
			if(!$array['#'.$name]) $array['#'.$name] = '';
		}

		// Links
		$array += $this->get_all_links('tracker', $id, $prefix, $content);

		// Timesheet time
		if(strpos($content, 'tr_sum_timesheets'))
		{
			$links = Link::get_links('tracker',$id,'timesheet');
			$sum = ExecMethod('timesheet.timesheet_bo.sum',$links);
			$info['$$tr_sum_timesheets$$'] = $sum['duration'];
		}

		// Add markers
		foreach($array as $key => &$value)
		{
			if(!$value) $value = '';
			$info['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}
		// Special comments - already have $$
		$comments = $this->get_comments($id);
		foreach($comments[-1] as $key => $comment)
		{
			$info += $comment;
		}
		return $info;
	}

	/**
	 * Table plugin for comments
	 *
	 * @param string $plugin
	 * @param int $id
	 * @param int $n
	 * @return array
	*/
	public function comment($plugin,$id,$n)
	{
		unset($plugin);	// not used, but required by function signature

		$comments = $this->get_comments($id);

		return $comments[$n];
	}

	/**
	 * Get the comments for this tracker entry
	 */
	protected function get_comments($tr_id)
	{
		if($this->comment_cache[$tr_id]) return $this->comment_cache[$tr_id];

		// Clear it to keep memory down - just this ticket
		$this->comment_cache[$tr_id] = array();
		$last_creator_comment = array();
		$last_assigned_comment = array();

		$this->bo->read($tr_id);
		$tracker = $this->bo->data;

		if(array_key_exists($tr_id, $this->preset_comments))
		{
			$replies = $this->preset_comments[$tr_id];
		}
		else
		{
			$replies = $tracker['replies'];
		}
		foreach($replies as $reply) {
			if($reply['reply_visible'] > 0) {
				$reply['reply_message'] = '['.$reply['reply_message'].']';
			}
			$this->comment_cache[$tr_id][] = array(
				'$$comment/date$$' => Api\DateTime::to($reply['reply_created']),
				'$$comment/message$$' => $reply['reply_message'],
				'$$comment/restricted$$' => $reply['reply_visible'] ? ('[' .lang('restricted comment').']') : '',
				'$$comment/user$$' => Api\Accounts::username($reply['reply_creator'])
			);
			if($reply['reply_creator'] == $tracker['tr_creator'] && !$last_creator_comment) $last_creator_comment = $reply;
			if(is_array($tracker['tr_assigned']) && in_array($reply['reply_creator'], $tracker['tr_assigned']) && !$last_assigned_comment) $last_assigned_comment = $reply;
		}

		// Special comments
		foreach(array('' => $replies[0], '/creator' => $last_creator_comment, '/assigned_to' => $last_assigned_comment) as $key => $comment) {
			$this->comment_cache[$tr_id][-1][$key] = array(
				'$$comment/-1'.$key.'/date$$' => $comment ? Api\DateTime::to($comment['reply_created']) : '',
				'$$comment/-1'.$key.'/message$$' => $comment['reply_message'],
				'$$comment/-1'.$key.'/restricted$$' => $comment['reply_visible'] ? ('[' .lang('restricted comment').']') : '',
				'$$comment/-1'.$key.'/user$$' => $comment ? Api\Accounts::username($comment['reply_creator']) : ''
			);
		}

		return $this->comment_cache[$tr_id];
	}

	/**
	 * Limit to only certain comments by pre-setting the cache
	 *
	 * This avoids reading comments from database.  Used by notifications
	 * to forceably remove restricted comments without considering ACL (eg CC)
	 *
	 * @param int $tr_id Tracker ticket ID
	 * @param Array $comments List of comment info
	 */
	public function set_comments($tr_id, Array $comments)
	{
		$this->preset_comments[$tr_id] = $comments;
	}

	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('tracker').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Tracker fields:')."</h3></td></tr>";

		$n = 0;
		$fields = array('tr_id' => lang('Tracker ID')) + $this->bo->field2label + array(
			'tr_modifier' => lang('Last modified by'),
			'tr_modified' => lang('last modified'),
		);
		$fields['bounty'] = lang('bounty');
		$fields['tr_link'] = lang('Link to ticket');
		$fields['all_comments'] = lang("All comments together, User\tDate\tMessage");
		$fields['signature'] = lang('Notification signature');
		$fields['comment/-1/...'] = 'Only the last comment';
		$fields['comment/-1/creator/...'] = 'Only the last comment by the creator';
		$fields['comment/-1/assigned_to/...'] = 'Only the last comment by one of the assigned users';
		$fields['tr_sum_timesheets'] = lang('Used time');
		foreach($fields as $name => $label)
		{
			if (in_array($name,array('link_to','canned_response','reply_message','add','vote','no_notifications','num_replies','customfields'))) continue;	// dont show them

			if (in_array($name,array('tr_summary', 'tr_description')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.lang($label).'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('Comments').":</h3></td></tr>";
		echo '<tr><td colspan="4">{{table/comment}}</td></tr>';
		foreach(array(
			'date' => 'date',
			'user' => 'Username',
			'message' => 'Message',
			'restricted' => 'If the message was restricted',
		) as $name => $label) {
			echo '<tr><td /><td>{{comment/'.$name.'}}</td><td>'.lang($label).'</td></tr>';
		}
 		echo '<tr><td>{{endtable}}</td></tr>';

		echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
		foreach($this->bo->customfields as $name => $field)
		{
			echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label']."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'link' => lang('HTML link to the current record'),
			'links' => lang('Titles of any entries linked to the current record, excluding attached files'),
 			'attachments' => lang('List of files linked to the current record'),
			'links_attachments' => lang('Links and attached files'),
			'links/[appname]' => lang('Links to specified application.  Example: {{links/infolog}}'),
			'links/href' => lang('Links wrapped in an HREF tag with download link'),
			'links/link' => lang('Download url for links'),
			'date' => lang('Date'),
			'user/n_fn' => lang('Name of current user, all other contact fields are valid too'),
			'user/account_lid' => lang('Username'),
			'pagerepeat' => lang('For serial letter use this tag. Put the content, you want to repeat between two Tags.'),
			'label' => lang('Use this tag for addresslabels. Put the content, you want to repeat, between two tags.'),
			'labelplacement' => lang('Tag to mark positions for address labels'),
			'IF fieldname' => lang('Example {{IF n_prefix~Mr~Hello Mr.~Hello Ms.}} - search the field "n_prefix", for "Mr", if found, write Hello Mr., else write Hello Ms.'),
			'NELF' => lang('Example {{NELF role}} - if field role is not empty, you will get a new line with the value of field role'),
			'NENVLF' => lang('Example {{NELFNV role}} - if field role is not empty, set a LF without any value of the field'),
			'LETTERPREFIX' => lang('Example {{LETTERPREFIX}} - Gives a letter prefix without double spaces, if the title is empty for example'),
			'LETTERPREFIXCUSTOM' => lang('Example {{LETTERPREFIXCUSTOM n_prefix title n_family}} - Example: Mr Dr. James Miller'),
			) as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}
}
