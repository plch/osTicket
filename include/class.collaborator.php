<?php
/*********************************************************************
    class.collaborator.php

    Ticket collaborator

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once(INCLUDE_DIR . 'class.user.php');
require_once(INCLUDE_DIR . 'class.client.php');

class Collaborator
extends VerySimpleModel
implements EmailContact, ITicketUser {

    static $meta = array(
        'table' => THREAD_COLLABORATOR_TABLE,
        'pk' => array('id'),
        'select_related' => array('user'),
        'joins' => array(
            'thread' => array(
                'constraint' => array('thread_id' => 'Thread.id'),
            ),
            'user' => array(
                'constraint' => array('user_id' => 'User.id'),
            ),
        ),
    );

    const FLAG_ACTIVE = 0x0001;
    const FLAG_CC = 0x0002;

    function __toString() {
        return Format::htmlchars($this->toString());
    }
    function toString() {
        return sprintf('%s <%s>', $this->getName(), $this->getEmail());
    }

    function getId() {
        return $this->id;
    }

    function isActive() {
        return !!($this->flags & self::FLAG_ACTIVE);
    }

    function getCreateDate() {
        return $this->created;
    }

    function getThreadId() {
        return $this->thread_id;
    }

    function getTicketId() {
        if ($this->thread->object_type == ObjectModel::OBJECT_TYPE_TICKET)
            return $this->thread->object_id;
    }

    function getTicket() {
        // TODO: Change to $this->thread->ticket when Ticket goes to ORM
        if ($id = $this->getTicketId())
            return Ticket::lookup($id);
    }

    function getUser() {
        return $this->user;
    }

    // EmailContact interface
    function getEmail() {
        return $this->user->getEmail();
    }
    function getName() {
        return $this->user->getName();
    }

    static function getIdByUserId($userId, $threadId) {
        $row = Collaborator::objects()
            ->filter(array('user_id'=>$userId, 'thread_id'=>$threadId))
            ->values_flat('id')
            ->first();

        return $row ? $row[0] : 0;
    }

    // VariableReplacer interface
    function getVar($what) {
        global $cfg;

        switch (strtolower($what)) {
        case 'ticket_link':
            if ($this->getTicket()->getAuthToken($this)
                && ($ticket=$this->getTicket())
                && !$ticket->getThread()->getNumCollaborators()) {
                  $qstr['auth'] = $ticket->getAuthToken($this);
                  return sprintf('%s/view.php?%s',
                          $cfg->getBaseUrl(),
                          Http::build_query($qstr, false)
                          );
                }
                else {
                  return sprintf('%s/tickets.php?id=%s',
                          $cfg->getBaseUrl(),
                          $ticket->getId()
                          );
                }
        }
    }

    // ITicketUser interface
    var $_isguest;

    function isOwner() {
        return false;
    }
    function flagGuest() {
        $this->_isguest = true;
    }
    function isGuest() {
        return $this->_isguest;
    }
    function getUserId() {
        return $this->user_id;
    }

    function hasFlag($flag) {
        return ($this->get('flags', 0) & $flag) != 0;
    }

    public function setFlag($flag, $val) {
        if ($val)
            $this->flags |= $flag;
        else
            $this->flags &= ~$flag;
    }

    public function setCc() {
      $this->setFlag(Collaborator::FLAG_ACTIVE, true);
      $this->setFlag(Collaborator::FLAG_CC, true);
      $this->save();
    }

    public function setBcc() {
      $this->setFlag(Collaborator::FLAG_ACTIVE, true);
      $this->setFlag(Collaborator::FLAG_CC, false);
      $this->save();
    }

    function isCc() {
        return !!($this->flags & self::FLAG_CC);
    }

    function getCollabList($collabs) {
      $collabList = array();
      foreach ($collabs as $c) {
        $u = User::lookup($c);
        if ($u) {
          $email = $u->getEmail()->address;
          $collabList[$c] = $email;
        }
      }
      return $collabList;
    }

    static function create($vars=false) {
        $inst = new static($vars);
        $inst->created = SqlFunction::NOW();
        return $inst;
    }

    function save($refetch=false) {
        if ($this->dirty)
            $this->updated = SqlFunction::NOW();
        return parent::save($refetch || $this->dirty);
    }

    static function add($info, &$errors) {
        if (!$info || !$info['threadId'] || !$info['userId'])
            $errors['err'] = __('Invalid or missing information');
        elseif ($c = Collaborator::lookup(array(
            'thread_id' => $info['threadId'],
            'user_id' => $info['userId'],
        )))
          $errors['err'] = sprintf(__('%s is already a collaborator'),
                      $c->getName());

        if ($errors) return false;

        $collab = static::create(array(
            'thread_id' => $info['threadId'],
            'user_id' => $info['userId'],
        ));
        if ($collab->save(true))
            return $collab;

        $errors['err'] = __('Unable to add collaborator.')
            .' '.__('Internal error occurred');

        return false;
    }

}
?>
