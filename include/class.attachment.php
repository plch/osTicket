<?php
/*********************************************************************
    class.attachment.php

    Attachment Handler - mainly used for lookup...doesn't save!

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'class.file.php');

class Attachment {
    var $id;
    var $file_id;

    var $ht;
    var $object;

    function Attachment($id) {

        $sql = 'SELECT a.* FROM '.ATTACHMENT_TABLE.' a '
             . 'WHERE a.id='.db_input($id);
        if (!($res=db_query($sql)) || !db_num_rows($res))
            return;

        $this->ht = db_fetch_array($res);
        $this->file = $this->object = null;
    }

    function getId() {
        return $this->ht['id'];
    }

    function getFileId() {
        return $this->ht['file_id'];
    }

    function getFile() {
        if(!$this->file && $this->getFileId())
            $this->file = AttachmentFile::lookup($this->getFileId());

        return $this->file;
    }

    function getHashtable() {
        return $this->ht;
    }

    function getInfo() {
        return $this->getHashtable();
    }

    function getObject() {

        if (!isset($this->object))
            $this->object = ObjectModel::lookup(
                    $this->ht['object_id'], $this->ht['type']);

        return $this->object;
    }

    static function getIdByFileHash($hash, $objectId=0) {
        $sql='SELECT a.id FROM '.ATTACHMENT_TABLE.' a '
            .' INNER JOIN '.FILE_TABLE.' f ON(f.id=a.file_id) '
            .' WHERE f.`key`='.db_input($hash);
        if ($objectId)
            $sql.=' AND a.object_id='.db_input($objectId);

        return db_result(db_query($sql));
    }

    static function lookup($var, $objectId=0) {

        $id = is_numeric($var) ? $var : self::getIdByFileHash($var,
                $objectId);

        return ($id
                && is_numeric($id)
                && ($attach = new Attachment($id, $objectId))
                && $attach->getId()==$id
            ) ? $attach : null;
    }
}

class AttachmentModel extends VerySimpleModel {
    static $meta = array(
        'table' => ATTACHMENT_TABLE,
        'pk' => array('id'),
        'joins' => array(
            'thread' => array(
                'constraint' => array(
                    'object_id' => 'ThreadEntryModel.id',
                    'type' => "'H'",
                ),
            ),
        ),
    );
}

class GenericAttachment extends VerySimpleModel {
    static $meta = array(
        'table' => ATTACHMENT_TABLE,
        'pk' => array('id'),
    );
}

class GenericAttachments {

    var $id;
    var $type;

    function GenericAttachments($object_id, $type) {
        $this->id = $object_id;
        $this->type = $type;
    }

    function getId() { return $this->id; }
    function getType() { return $this->type; }

    function upload($files, $inline=false, $lang=false) {
        $i=array();
        if (!is_array($files)) $files=array($files);
        foreach ($files as $file) {
            if (is_numeric($file))
                $fileId = $file;
            elseif (is_array($file) && isset($file['id']))
                $fileId = $file['id'];
            elseif (!($fileId = AttachmentFile::upload($file)))
                continue;

            $_inline = isset($file['inline']) ? $file['inline'] : $inline;

            $sql ='INSERT INTO '.ATTACHMENT_TABLE
                .' SET `type`='.db_input($this->getType())
                .',object_id='.db_input($this->getId())
                .',file_id='.db_input($fileId)
                .',inline='.db_input($_inline ? 1 : 0);
            if ($lang)
                $sql .= ',lang='.db_input($lang);

            // File may already be associated with the draft (in the
            // event it was deleted and re-added)
            if (db_query($sql, function($errno) { return $errno != 1062; })
                    || db_errno() == 1062)
                $i[] = $fileId;
        }

        return $i;
    }

    function save($file, $inline=true) {

        if (is_numeric($file))
            $fileId = $file;
        elseif (is_array($file) && isset($file['id']))
            $fileId = $file['id'];
        elseif (!($fileId = AttachmentFile::save($file)))
            return false;

        $sql ='INSERT INTO '.ATTACHMENT_TABLE
            .' SET `type`='.db_input($this->getType())
            .',object_id='.db_input($this->getId())
            .',file_id='.db_input($fileId)
            .',inline='.db_input($inline ? 1 : 0);
        if (!db_query($sql) || !db_affected_rows())
            return false;

        return $fileId;
    }

    function getInlines($lang=false) { return $this->_getList(false, true, $lang); }
    function getSeparates($lang=false) { return $this->_getList(true, false, $lang); }
    function getAll($lang=false) { return $this->_getList(true, true, $lang); }
    function count($lang=false) { return count($this->getSeparates($lang)); }

    function _getList($separate=false, $inlines=false, $lang=false) {
        if(!isset($this->attachments)) {
            $this->attachments = array();
            $sql='SELECT f.id, f.size, f.`key`, f.signature, f.name '
                .', a.inline, a.lang, a.id as attach_id '
                .' FROM '.FILE_TABLE.' f '
                .' INNER JOIN '.ATTACHMENT_TABLE.' a ON(f.id=a.file_id) '
                .' WHERE a.`type`='.db_input($this->getType())
                .' AND a.object_id='.db_input($this->getId());
            if(($res=db_query($sql)) && db_num_rows($res)) {
                while($rec=db_fetch_array($res)) {
                    $rec['download_url'] = AttachmentFile::generateDownloadUrl(
                        $rec['id'], $rec['key'], $rec['signature']);
                    $this->attachments[] = $rec;
                }
            }
        }
        $attachments = array();
        foreach ($this->attachments as $a) {
            if (($a['inline'] != $separate || $a['inline'] == $inlines)
                    && $lang == $a['lang']) {
                $a['file_id'] = $a['id'];
                $a['hash'] = md5($a['file_id'].session_id().$a['key']);
                $attachments[] = $a;
            }
        }
        return $attachments;
    }

    function delete($file_id) {
        $deleted = 0;
        $sql='DELETE FROM '.ATTACHMENT_TABLE
            .' WHERE object_id='.db_input($this->getId())
            .'   AND `type`='.db_input($this->getType())
            .'   AND file_id='.db_input($file_id);
        return db_query($sql) && db_affected_rows() > 0;
    }

    function deleteAll($inline_only=false){
        $deleted=0;
        $sql='DELETE FROM '.ATTACHMENT_TABLE
            .' WHERE object_id='.db_input($this->getId())
            .'   AND `type`='.db_input($this->getType());
        if ($inline_only)
            $sql .= ' AND inline = 1';
        return db_query($sql) && db_affected_rows() > 0;
    }

    function deleteInlines() {
        return $this->deleteAll(true);
    }
}
?>
