<?php
/***********************************************
* File      :   notify.php
* Project   :   Z-Push
* Descr     :   Provides the NOTIFY command
*
* Created   :   16.02.2012
*
* Copyright 2007 - 2013 Zarafa Deutschland GmbH
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

class Notify extends RequestProcessor {

    /**
     * Handles the Notify command
     *
     * @param int       $commandCode
     *
     * @access public
     * @return boolean
     */
    public function Handle($commandCode) {
        if(!self::$decoder->getElementStartTag(SYNC_AIRNOTIFY_NOTIFY))
            return false;

        if(!self::$decoder->getElementStartTag(SYNC_AIRNOTIFY_DEVICEINFO))
            return false;

        if(!self::$decoder->getElementEndTag())
            return false;

        if(!self::$decoder->getElementEndTag())
            return false;

        self::$encoder->StartWBXML();

        self::$encoder->startTag(SYNC_AIRNOTIFY_NOTIFY);
        {
            self::$encoder->startTag(SYNC_AIRNOTIFY_STATUS);
            self::$encoder->content(1);
            self::$encoder->endTag();

            self::$encoder->startTag(SYNC_AIRNOTIFY_VALIDCARRIERPROFILES);
            self::$encoder->endTag();
        }
        self::$encoder->endTag();

        return true;
    }
}
