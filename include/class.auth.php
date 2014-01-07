<?php
require(INCLUDE_DIR.'class.ostsession.php');
require(INCLUDE_DIR.'class.usersession.php');


abstract class AuthenticatedUser {
    //Authorization key returned by the backend used to authorize the user
    private $authkey;

    // Get basic information
    abstract function getId();
    abstract function getUsername();
    abstract function getRole();

    function setAuthKey($key) {
        $this->authkey = $key;
    }

    function getAuthKey() {
        return $this->authkey;
    }
}

interface AuthDirectorySearch {
    /**
     * Indicates if the backend can be used to search for user information.
     * Lookup is performed to find user information based on a unique
     * identifier.
     */
    function lookup($id);

    /**
     * Indicates if the backend supports searching for usernames. This is
     * distinct from information lookup in that lookup is intended to lookup
     * information based on a unique identifier
     */
    function search($query);
}

/**
 * Authentication backend
 *
 * Authentication provides the basis of abstracting the link between the
 * login page with a username and password and the staff member,
 * administrator, or client using the system.
 *
 * The system works by allowing the AUTH_BACKENDS setting from
 * ost-config.php to determine the list of authentication backends or
 * providers and also specify the order they should be evaluated in.
 *
 * The authentication backend should define a authenticate() method which
 * receives a username and optional password. If the authentication
 * succeeds, an instance deriving from <User> should be returned.
 */
abstract class AuthenticationBackend {
    static protected $registry = array();
    static $name;
    static $id;


    /* static */
    static function register($class) {
        if (is_string($class) && class_exists($class))
            $class = new $class();

        if (!is_object($class)
                || !($class instanceof AuthenticationBackend))
            return false;

        return static::_register($class);
    }

    static function _register($class) {
        // XXX: Raise error if $class::id is already in the registry
        static::$registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return static::$registry;
    }

    static function getBackend($id) {

        if ($id
                && ($backends = static::allRegistered())
                && isset($backends[$id]))
            return $backends[$id];
    }

    static function process($username, $password=null, &$errors) {

        if (!$username)
            return false;

        $backends =  static::getAllowedBackends($username);
        foreach (static::allRegistered() as $bk) {
            if ($backends //Allowed backends
                    && $bk->supportsAuthentication()
                    && in_array($bk::$id, $backends))
                // User cannot be authenticated against this backend
                continue;

            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            $result = $bk->authenticate($username, $password);

            if ($result instanceof AuthenticatedUser
                    && (static::login($result, $bk)))
                return $result;
            // TODO: Handle permission denied, for instance
            elseif ($result instanceof AccessDenied) {
                $errors['err'] = $result->reason;
                break;
            }
        }

        $info = array('username'=>$username, 'password'=>$password);
        Signal::send('auth.login.failed', null, $info);
    }

    function singleSignOn(&$errors) {
        global $ost;

        foreach (static::allRegistered() as $bk) {
            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            $result = $bk->signOn();
            if ($result instanceof AuthenticatedUser) {
                //Perform further Object specific checks and the actual login
                if (!static::login($result, $bk))
                    continue;

                return $result;
            }
            // TODO: Handle permission denied, for instance
            elseif ($result instanceof AccessDenied) {
                $errors['err'] = $result->reason;
                break;
            }
        }
    }

    static function searchUsers($query) {
        $users = array();
        foreach (static::$registry as $bk) {
            if ($bk instanceof AuthDirectorySearch) {
                $users += $bk->search($query);
            }
        }
        return $users;
    }

    /**
     * Fetches the friendly name of the backend
     */
    function getName() {
        return static::$name;
    }

    /**
     * Indicates if the backed supports authentication. Useful if the
     * backend is used for logging or lockout only
     */
    function supportsAuthentication() {
        return true;
    }

    /**
     * Indicates if the backend supports changing a user's password. This
     * would be done in two fashions. Either the currently-logged in user
     * want to change its own password or a user requests to have their
     * password reset. This requires an administrative privilege which this
     * backend might not possess, so it's defined in supportsPasswordReset()
     */
    function supportsPasswordChange() {
        return false;
    }

    function supportsPasswordReset() {
        return false;
    }

    function signOn() {
        return null;
    }

    abstract function authenticate($username, $password);
    abstract function login($user, $bk);
    abstract function getAllowedBackends($userid);
    abstract protected function getAuthKey($user);
}

class RemoteAuthenticationBackend {
    var $create_unknown_user = false;
}

abstract class StaffAuthenticationBackend  extends AuthenticationBackend {

    static private $_registry = array();

    static function _register($class) {
        static::$_registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return array_merge(self::$_registry, parent::allRegistered());
    }

    function isBackendAllowed($staff, $bk) {

        if (!($backends=self::getAllowedBackends($staff->getId())))
            return true;  //No restrictions

        return in_array($bk::$id, array_map('strtolower', $backends));
    }

    function getAllowedBackends($userid) {

        $backends =array();
        //XXX: Only one backend can be specified at the moment.
        $sql = 'SELECT backend FROM '.STAFF_TABLE
              .' WHERE backend IS NOT NULL ';
        if (is_numeric($userid))
            $sql.= ' AND staff_id='.db_input($userid);
        else {
            $sql.= ' AND (username='.db_input($userid) .' OR email='.db_input($userid).')';
        }

        if (($res=db_query($sql)) && db_num_rows($res))
            $backends[] = db_result($res);

        return array_filter($backends);
    }

    function login($staff, $bk) {
        global $ost;

        if (!$bk || !($staff instanceof Staff))
            return false;

        // Ensure staff is allowed for realz to be authenticated via the backend.
        if (!static::isBackendAllowed($staff, $bk)
            || !($authkey=$bk->getAuthKey($staff)))
            return false;

        //Log debug info.
        $ost->logDebug('Staff login',
            sprintf("%s logged in [%s], via %s", $staff->getUserName(),
                $_SERVER['REMOTE_ADDR'], get_class($bk))); //Debug.

        $sql='UPDATE '.STAFF_TABLE.' SET lastlogin=NOW() '
            .' WHERE staff_id='.db_input($staff->getId());
        db_query($sql);

        //Tag the authkey.
        $authkey = $bk::$id.':'.$authkey;

        //Now set session crap and lets roll baby!
        $_SESSION['_auth']['staff'] = array(); //clear.
        $_SESSION['_auth']['staff']['id'] = $staff->getId();
        $_SESSION['_auth']['staff']['key'] =  $authkey;

        $staff->setAuthKey($authkey);
        $staff->refreshSession(); //set the hash.

        $_SESSION['TZ_OFFSET'] = $staff->getTZoffset();
        $_SESSION['TZ_DST'] = $staff->observeDaylight();

        //Regenerate session id.
        $sid = session_id(); //Current id
        session_regenerate_id(true);
        // Destroy old session ID - needed for PHP version < 5.1.0
        // DELME: remove when we move to php 5.3 as min. requirement.
        if(($session=$ost->getSession()) && is_object($session)
                && $sid!=session_id())
            $session->destroy($sid);

        Signal::send('auth.login.succeeded', $staff);

        $staff->cancelResetTokens();

        return true;
    }

    protected function getAuthKey($staff) {
        return null;
    }
}

abstract class UserAuthenticationBackend  extends AuthenticationBackend {

    static private $_registry = array();

    static function _register($class) {
        static::$_registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return array_merge(self::$_registry, parent::allRegistered());
    }

    function getAllowedBackends($userid) {
        // White listing backends for specific user not supported.
        return array();
    }

    function login($user, $bk) {
        global $ost;

        if (!$user || !$bk
                || !$bk::$id //Must have ID
                || !($authkey = $bk->getAuthKey($user)))
            return false;

        //Tag the authkey.
        $authkey = $bk::$id.':'.$authkey;
        //Set the session goodies
        $_SESSION['_auth']['user'] = array(); //clear.
        $_SESSION['_auth']['user']['id'] = $user->getId();
        $_SESSION['_auth']['user']['key'] = $authkey;
        $_SESSION['TZ_OFFSET'] = $ost->getConfig()->getTZoffset();
        $_SESSION['TZ_DST'] = $ost->getConfig()->observeDaylightSaving();

        //The backend used decides the format of the auth key.
        // XXX: encrypt to hide the bk??
        $user->setAuthKey($authkey);

        $user->refreshSession(); //set the hash.

        //Log login info...
        $msg=sprintf('%s (%s) logged in [%s]',
                $user->getUserName(), $user->getId(), $_SERVER['REMOTE_ADDR']);
        $ost->logDebug('User login', $msg);

        //Regenerate session ID.
        $sid=session_id(); //Current session id.
        session_regenerate_id(TRUE); //get new ID.
        if(($session=$ost->getSession()) && is_object($session) && $sid!=session_id())
            $session->destroy($sid);

        return true;
    }


    protected function getAuthKey($user) {
        return null;
    }

}

/**
 * This will be an exception in later versions of PHP
 */
class AccessDenied {
    function AccessDenied() {
        call_user_func_array(array($this, '__construct'), func_get_args());
    }
    function __construct($reason) {
        $this->reason = $reason;
    }
}

/**
 * Simple authentication backend which will lock the login form after a
 * configurable number of attempts
 */
abstract class AuthStrikeBackend extends AuthenticationBackend {

    function authenticate($username, $password=null) {
        return static::authStrike($username, $password);
    }

    function signOn() {
        return static::authStrike('Unknown');
    }

    function login($user, $bk) {
        return false;
    }

    function supportsAuthentication() {
        return false;
    }

    function getAllowedBackends($userid) {
        return array();
    }

    function getAuthKey($user) {
        return null;
    }

    abstract function  authStrike($username, $password=null);
}

/*
 * Backend to monitor staff's failed login attempts
 */
class StaffAuthStrikeBackend extends  AuthStrikeBackend {

    function authstrike($username, $password=null) {
        global $ost;

        $cfg = $ost->getConfig();

        if($_SESSION['_auth']['staff']['laststrike']) {
            if((time()-$_SESSION['_auth']['staff']['laststrike'])<$cfg->getStaffLoginTimeout()) {
                $_SESSION['_auth']['staff']['laststrike'] = time(); //reset timer.
                return new AccessDenied('Max. failed login attempts reached');
            } else { //Timeout is over.
                //Reset the counter for next round of attempts after the timeout.
                $_SESSION['_auth']['staff']['laststrike']=null;
                $_SESSION['_auth']['staff']['strikes']=0;
            }
        }

        $_SESSION['_auth']['staff']['strikes']+=1;
        if($_SESSION['_auth']['staff']['strikes']>$cfg->getStaffMaxLogins()) {
            $_SESSION['_auth']['staff']['laststrike']=time();
            $alert='Excessive login attempts by a staff member?'."\n".
                   'Username: '.$username."\n"
                   .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                   .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                   .'Attempts #'.$_SESSION['_auth']['staff']['strikes']."\n"
                   .'Timeout: '.($cfg->getStaffLoginTimeout()/60)." minutes \n\n";
            $ost->logWarning('Excessive login attempts ('.$username.')', $alert,
                    $cfg->alertONLoginError());
            return new AccessDenied('Forgot your login info? Contact Admin.');
        //Log every other failed login attempt as a warning.
        } elseif($_SESSION['_auth']['staff']['strikes']%2==0) {
            $alert='Username: '.$username."\n"
                    .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                    .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                    .'Attempts #'.$_SESSION['_auth']['staff']['strikes'];
            $ost->logWarning('Failed staff login attempt ('.$username.')', $alert, false);
        }
    }
}
StaffAuthenticationBackend::register(StaffAuthStrikeBackend);

/*
 * Backend to monitor user's failed login attempts
 */
class UserAuthStrikeBackend extends  AuthStrikeBackend {

    function authstrike($username, $password=null) {
        global $ost;

        $cfg = $ost->getConfig();

        $_SESSION['_auth']['user'] = array();
        //Check time for last max failed login attempt strike.
        if($_SESSION['_auth']['user']['laststrike']) {
            if((time()-$_SESSION['_auth']['user']['laststrike'])<$cfg->getClientLoginTimeout()) {
                $_SESSION['_auth']['user']['laststrike'] = time(); //renew the strike.
                return new AccessDenied('You\'ve reached maximum failed login attempts allowed.');
            } else { //Timeout is over.
                //Reset the counter for next round of attempts after the timeout.
                $_SESSION['_auth']['user']['laststrike'] = null;
                $_SESSION['_auth']['user']['strikes'] = 0;
            }
        }

        $_SESSION['_auth']['user']['strikes']+=1;
        if($_SESSION['_auth']['user']['strikes']>$cfg->getClientMaxLogins()) {
            $_SESSION['_auth']['user']['laststrike'] = time();
            $alert='Excessive login attempts by a user.'."\n".
                    'Login: '.$username.': '.$password."\n".
                    'IP: '.$_SERVER['REMOTE_ADDR']."\n".'Time:'.date('M j, Y, g:i a T')."\n\n".
                    'Attempts #'.$_SESSION['_auth']['user']['strikes'];
            $ost->logError('Excessive login attempts (user)', $alert, ($cfg->alertONLoginError()));
            return new AccessDenied('Access Denied');
        } elseif($_SESSION['_auth']['user']['strikes']%2==0) { //Log every other failed login attempt as a warning.
            $alert='Login: '.$username.': '.$password."\n".'IP: '.$_SERVER['REMOTE_ADDR'].
                   "\n".'TIME: '.date('M j, Y, g:i a T')."\n\n".'Attempts #'.$_SESSION['_auth']['user']['strikes'];
            $ost->logWarning('Failed login attempt (user)', $alert);
        }

    }
}
UserAuthenticationBackend::register(UserAuthStrikeBackend);


class osTicketAuthentication extends StaffAuthenticationBackend {
    static $name = "Local Authentication";
    static $id = "local";

    function authenticate($username, $password) {
        if (($user = new StaffSession($username)) && $user->getId() &&
                $user->check_passwd($password)) {

            //update last login && password reset stuff.
            $sql='UPDATE '.STAFF_TABLE.' SET lastlogin=NOW() ';
            if($user->isPasswdResetDue() && !$user->isAdmin())
                $sql.=',change_passwd=1';
            $sql.=' WHERE staff_id='.db_input($user->getId());
            db_query($sql);

            return $user;
        }
    }

    protected function getAuthKey($staff) {

        if(!($staff instanceof Staff))
            return null;

        return $staff->getUsername(); //FIXME:
    }

}
StaffAuthenticationBackend::register(osTicketAuthentication);

class AuthTokenAuthentication extends UserAuthenticationBackend {
    static $name = "Auth Token Authentication";
    static $id = "authtoken";



    function signOn() {

        $user = null;
        if ($_GET['auth'])
            $user = self::__authtoken($_GET['auth']);
        // Support old ticket based tokens.
        elseif ($_GET['t'] && $_GET['e'] && $_GET['a']) {
            if (($ticket = Ticket::lookupByExtId($_GET['t'], $_GET['e']))
                    // Using old ticket auth code algo - hardcoded here because it
                    // will be removed in ticket class in the upcoming rewrite
                    && !strcasecmp($_GET['a'], md5($ticket->getId() .  $_GET['e'] . SECRET_SALT))
                    && ($client = $ticket->getClient()))
                $user = new ClientSession($client);
        }

        return $user;
    }

    protected function getAuthKey($user) {

        if (!$this->supportsAuthentication()
                || !$user
                || !($user instanceof EndUser))
            return null;

        //Generate authkey based the type of ticket user
        // It's required to validate users going forward.
        $authkey = sprintf('%s%dt%dh%s',  //XXX: Placeholder
                    $user->isOwner() ? 'o':'c',
                    $user->getId(),
                    $user->getTicketID(),
                    md5($user->getUsername().$this->id));

        return $authkey;
    }

    static private function __authtoken($token) {

        switch ($token[0]) {
            case 'c': //Collaborator c+[token]
                if (($c = Collaborator::lookupByAuthToken($token)))
                    return new ClientSession($c); //Decorator
                break;
            case 'o': //Ticket owner  o+[token]
                break;
        }
    }

    function authenticate($username, $password) {
        return false;
    }

}
UserAuthenticationBackend::register(AuthTokenAuthentication);

?>
