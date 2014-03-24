<?php
/*********************************************************************
    profile.php

    Manage client profile. This will allow a logged-in user to manage
    his/her own public (non-internal) information

    Peter Rotich <peter@osticket.com>
    Jared Hancock <jared@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
    $Id: $
**********************************************************************/
require 'client.inc.php';

$inc = 'register.inc.php';

$errors = array();

if (!$cfg || !$cfg->isClientRegistrationEnabled()) {
    Http::redirect('index.php');
}

elseif ($thisclient) {
    // Guest registering for an account
    if ($thisclient->isGuest()) {
        foreach ($thisclient->getForms() as $f)
            if ($f->get('type') == 'U')
                $user_form = $f;
        $user_form->getField('email')->configure('disabled', true);
    }
    // Existing client (with an account) updating profile
    else {
        $user = User::lookup($thisclient->getId());
        $inc = isset($_GET['confirmed'])
            ? 'registration.confirmed.inc.php' : 'profile.inc.php';
    }
}

if ($user && $_POST) {
    if ($acct = $thisclient->getAccount()) {
       $acct->update($_POST, $errors);
    }
    if (!$errors && $user->updateInfo($_POST, $errors))
        Http::redirect('tickets.php');
}

elseif ($_POST) {
    $user_form = UserForm::getUserForm()->getForm($_POST);
    if (!$user_form->isValid(function($f) { return !$f->get('internal'); }))
        $errors['err'] = 'Incomplete client information';
    elseif (!$_POST['passwd1'])
        $errors['passwd1'] = 'New password required';
    elseif ($_POST['passwd2'] != $_POST['passwd1'])
        $errors['passwd1'] = 'Passwords do not match';

    // XXX: The email will always be in use already if a guest is logged in
    // and is registering for an account. Instead,
    elseif (!($user = $thisclient ?: User::fromForm($user_form)))
        $errors['err'] = 'Unable to register account. See messages below';
    elseif (($addr = $user_form->getField('email')->getClean())
            && ClientAccount::lookupByUsername($addr)) {
        $user_form->getField('email')->addError(
            'Email already registered. Would you like to <a href="login.php?e='
            .urlencode($addr).'" style="color:inherit"><strong>sign in</strong></a>?');
        $errors['err'] = 'Unable to register account. See messages below';
    }
    else {
        if (!($acct = ClientAccount::createForUser($user)))
            $errors['err'] = 'Internal error. Unable to create new account';
        elseif (!$acct->update($_POST, $errors))
            $errors['err'] = 'Errors configuring your profile. See messages below';
    }

    if (!$errors) {
        switch ($_POST['do']) {
        case 'create':
            $inc = 'register.confirm.inc.php';
            $acct->sendResetEmail('registration-client');
        }
    }

    if ($errors && $user && $user != $thisclient)
        $user->delete();
}

include(CLIENTINC_DIR.'header.inc.php');
include(CLIENTINC_DIR.$inc);
include(CLIENTINC_DIR.'footer.inc.php');

