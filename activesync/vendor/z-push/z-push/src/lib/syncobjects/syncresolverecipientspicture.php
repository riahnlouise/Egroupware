<?php
/***********************************************
* File      :   syncresolverecipentspicture.php
* Project   :   Z-Push
* Descr     :   WBXML appointment entities that can be
*               parsed directly (as a stream) from WBXML.
*               It is automatically decoded
*               according to $mapping,
*               and the Sync WBXML mappings
*
* Created   :   28.10.2012
*
* Copyright 2007 - 2013, 2015 Zarafa Deutschland GmbH
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation with the following additional
* term according to sec. 7:
*
* According to sec. 7 of the GNU Affero General Public License, version 3,
* the terms of the AGPL are supplemented with the following terms:
*
* "Zarafa" is a registered trademark of Zarafa B.V.
* "Z-Push" is a registered trademark of Zarafa Deutschland GmbH
* The licensing of the Program under the AGPL does not imply a trademark license.
* Therefore any rights, title and interest in our trademarks remain entirely with us.
*
* However, if you propagate an unmodified version of the Program you are
* allowed to use the term "Z-Push" to indicate that you distribute the Program.
* Furthermore you may use our trademarks where it is necessary to indicate
* the intended purpose of a product or service provided you use it in accordance
* with honest practices in industrial or commercial matters.
* If you want to propagate modified versions of the Program under the name "Z-Push",
* you may only do so if you have a written permission by Zarafa Deutschland GmbH
* (to acquire a permission please contact Zarafa at trademark@zarafa.com).
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

class SyncResolveRecipientsPicture extends SyncObject {
    public $maxsize;
    public $maxpictures;
    public $status;
    public $data;

    public function __construct() {
        $mapping = array ();

        if (Request::GetProtocolVersion() >= 14.1) {
            $mapping[SYNC_RESOLVERECIPIENTS_MAXSIZE]        = array (  self::STREAMER_VAR      => "maxsize");
            $mapping[SYNC_RESOLVERECIPIENTS_MAXPICTURES]    = array (  self::STREAMER_VAR      => "maxpictures");
            $mapping[SYNC_RESOLVERECIPIENTS_STATUS]         = array (  self::STREAMER_VAR      => "status");
            $mapping[SYNC_RESOLVERECIPIENTS_DATA]           = array (  self::STREAMER_VAR      => "data",
                                                                       self::STREAMER_TYPE     => self::STREAMER_TYPE_STREAM_ASBASE64,
                                                                     );
        }

        parent::__construct($mapping);
    }

}
