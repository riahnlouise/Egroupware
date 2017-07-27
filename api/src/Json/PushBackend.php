<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Json;

/**
 * Interface for push backends
 */
interface PushBackend
{
	/**
	 * Adds any type of data to the message
	 *
	 * @param int $account_id account_id to push message too
	 * @param string $key
	 * @param mixed $data
	 * @throws Exception\NotOnline if $account_id is not online
	 */
	public function addGeneric($account_id, $key, $data);
}
