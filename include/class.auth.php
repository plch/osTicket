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

    //Backend used to authenticate the user
    abstract function getAuthBackend();

    //Authentication key
    function setAuthKey($key) {
        $this->authkey = $key;
    }

    function getAuthKey() {
        return $this->authkey;
    }

    // logOut the user
    function logOut() {

        if ($bk = $this->getAuthBackend())
            return $bk->signOut($this);

        return false;
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
 * Class: ClientCreateRequest
 *
 * Simple container to represent a remote authentication success for a
 * client which should be imported into the local database. The class will
 * provide access to the backend that authenticated the user, the username
 * that the user entered when logging in, and any other information about
 * the user that the backend was able to lookup. Generally, this extra
 * information would be the same information retrieved from calling the
 * AuthDirectorySearch::lookup() method.
 */
class ClientCreateRequest {

    var $backend;
    var $username;
    var $info;

    function __construct($backend, $username, $info=array()) {
        $this->backend = $backend;
        $this->username = $username;
        $this->info = $info;
    }

    function getBackend() {
        return $this->backend;
    }
    function setBackend($what) {
        $this->backend = $what;
    }

    function getUsername() {
        return $this->username;
    }
    function getInfo() {
        return $this->info;
    }

    function attemptAutoRegister() {
        global $cfg;

        if (!$cfg)
            return false;

        // Attempt to automatically register
        $this_form = UserForm::getUserForm()->getForm($this->getInfo());
        $bk = $this->getBackend();
        $defaults = array(
            'timezone_id' => $cfg->getDefaultTimezoneId(),
            'dst' => $cfg->observeDaylightSaving(),
            'username' => $this->getUsername(),
        );
        if ($bk->supportsInteractiveAuthentication())
            // User can only be authenticated against this backend
            $defaults['backend'] = $bk::$id;
        if ($this_form->isValid(function($f) { return !$f->get('private'); })
                && ($U = User::fromVars($this_form->getClean()))
                && ($acct = ClientAccount::createForUser($U, $defaults))
                // Confirm and save the account
                && $acct->confirm()
                // Login, since `tickets.php` will not attempt SSO
                && ($cl = new ClientSession(new EndUser($U)))
                && ($bk->login($cl, $bk)))
            return $cl;
    }
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

    static function getSearchDirectoryBackend($id) {

        if ($id
                && ($backends = static::getSearchDirectories())
                && isset($backends[$id]))
            return $backends[$id];
    }

    /*
     * Allow the backend to do login audit depending on the result
     * This is mainly used to track failed login attempts
     */
    static function authAudit($result, $credentials=null) {

        if (!$result) return;

        foreach (static::allRegistered() as $bk)
            $bk->audit($result, $credentials);
    }

    static function process($username, $password=null, &$errors) {

        if (!$username)
            return false;

        $backends =  static::getAllowedBackends($username);
        foreach (static::allRegistered() as $bk) {
            if ($backends //Allowed backends
                    && $bk->supportsInteractiveAuthentication()
                    && !in_array($bk::$id, $backends))
                // User cannot be authenticated against this backend
                continue;

            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            try {
                $result = $bk->authenticate($username, $password);
                if ($result instanceof AuthenticatedUser
                        && ($bk->login($result, $bk)))
                    return $result;
                elseif ($result instanceof ClientCreateRequest
                        && $bk instanceof UserAuthenticationBackend)
                    return $result;
                elseif ($result instanceof AccessDenied) {
                    break;
                }
            }
            catch (AccessDenied $e) {
                $result = $e;
                break;
            }
        }

        if (!$result)
            $result = new AccessDenied('Access denied');

        if ($result && $result instanceof AccessDenied)
            $errors['err'] = $result->reason;

        $info = array('username' => $username, 'password' => $password);
        Signal::send('auth.login.failed', null, $info);
        self::authAudit($result, $info);
    }

    /*
     *  Attempt to process non-interactive sign-on e.g  HTTP-Passthrough
     *
     * $forcedAuth - indicate if authentication is required.
     *
     */
    function processSignOn(&$errors, $forcedAuth=true) {

        foreach (static::allRegistered() as $bk) {
            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            try {
                $result = $bk->signOn();
                if ($result instanceof AuthenticatedUser) {
                    //Perform further Object specific checks and the actual login
                    if (!$bk->login($result, $bk))
                        continue;

                    return $result;
                }
                elseif ($result instanceof ClientCreateRequest
                        && $bk instanceof UserAuthenticationBackend)
                    return $result;
                elseif ($result instanceof AccessDenied) {
                    break;
                }
            }
            catch (AccessDenied $e) {
                $result = $e;
                break;
            }
        }

        if (!$result && $forcedAuth)
            $result = new  AccessDenied('Unknown user');

        if ($result && $result instanceof AccessDenied)
            $errors['err'] = $result->reason;

        self::authAudit($result);
    }

    static function getSearchDirectories() {
        $backends = array();
        foreach (StaffAuthenticationBackend::allRegistered() as $bk)
            if ($bk instanceof AuthDirectorySearch)
                $backends[$bk::$id] = $bk;

        foreach (UserAuthenticationBackend::allRegistered() as $bk)
            if ($bk instanceof AuthDirectorySearch)
                $backends[$bk::$id] = $bk;

        return array_unique($backends);
    }

    static function searchUsers($query) {
        $users = array();
        foreach (static::getSearchDirectories() as $bk)
            $users = array_merge($users, $bk->search($query));

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
    function supportsInteractiveAuthentication() {
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

    protected function validate($auth) {
        return null;
    }

    protected function audit($result, $credentials) {
        return null;
    }

    abstract function authenticate($username, $password);
    abstract function login($user, $bk);
    abstract static function getUser(); //Validates  authenticated users.
    abstract function getAllowedBackends($userid);
    abstract protected function getAuthKey($user);
    abstract static function signOut($user);
}

/**
 * ExternalAuthenticationBackend
 *
 * External authentication backends are backends such as Google+ which
 * require a redirect to a remote site and a redirect back to osTicket in
 * order for a  user to be authenticated. For such backends, neither the
 * username and password fields nor single sign on alone can be used to
 * authenticate the user.
 */
interface ExternalAuthentication {

    /**
     * Requests the backend to render an external link box. When the user
     * clicks this box, the backend will be prompted to redirect the user to
     * the remote site for authentication there.
     */
    function renderExternalLink();

    /**
     * Function: triggerAuth
     *
     * Called when a user clicks the button rendered in the
     * ::renderExternalLink() function. This method should initiate the
     * remote authentication mechanism.
     */
    function triggerAuth();
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

        if (($res=db_query($sql, false)) && db_num_rows($res))
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
        $authsession = &$_SESSION['_auth']['staff'];

        $authsession = array(); //clear.
        $authsession['id'] = $staff->getId();
        $authsession['key'] =  $authkey;

        $staff->setAuthKey($authkey);
        $staff->refreshSession(true); //set the hash.

        $_SESSION['TZ_OFFSET'] = $staff->getTZoffset();
        $_SESSION['TZ_DST'] = $staff->observeDaylight();

        Signal::send('auth.login.succeeded', $staff);

        if ($bk->supportsInteractiveAuthentication())
            $staff->cancelResetTokens();

        return true;
    }

    /* Base signOut
     *
     * Backend should extend the signout and perform any additional signout
     * it requires.
     */

    static function signOut($staff) {
        global $ost;

        $_SESSION['_auth']['staff'] = array();
        unset($_SESSION[':token']['staff']);
        $ost->logDebug('Staff logout',
                sprintf("%s logged out [%s]",
                    $staff->getUserName(),
                    $_SERVER['REMOTE_ADDR'])); //Debug.

        Signal::send('auth.logout', $staff);
    }

    // Called to get authenticated user (if any)
    static function getUser() {

        if (!isset($_SESSION['_auth']['staff'])
                || !$_SESSION['_auth']['staff']['key'])
            return null;

        list($id, $auth) = explode(':', $_SESSION['_auth']['staff']['key']);

        if (!($bk=static::getBackend($id)) //get the backend
                || !($staff = $bk->validate($auth)) //Get AuthicatedUser
                || !($staff instanceof Staff)
                || $staff->getId() != $_SESSION['_auth']['staff']['id'] // check ID
        )
            return null;

        $staff->setAuthKey($_SESSION['_auth']['staff']['key']);

        return $staff;
    }

    function authenticate($username, $password) {
        return false;
    }

    // Generic authentication key for staff's backend is the username
    protected function getAuthKey($staff) {

        if(!($staff instanceof Staff))
            return null;

        return $staff->getUsername();
    }

    protected function validate($authkey) {

        if (($staff = new StaffSession($authkey)) && $staff->getId())
            return $staff;
    }
}

abstract class ExternalStaffAuthenticationBackend
        extends StaffAuthenticationBackend
        implements ExternalAuthentication {

    static $fa_icon = "signin";
    static $sign_in_image_url = false;
    static $service_name = "External";

    function renderExternalLink() { ?>
        <a class="external-sign-in" title="Sign in with <?php echo static::$service_name; ?>"
                href="login.php?do=ext&amp;bk=<?php echo urlencode(static::$id); ?>">
<?php if (static::$sign_in_image_url) { ?>
        <img class="sign-in-image" src="<?php echo static::$sign_in_image_url;
            ?>" alt="Sign in with <?php echo static::$service_name; ?>"/>
<?php } else { ?>
            <div class="external-auth-box">
            <span class="external-auth-icon">
                <i class="icon-<?php echo static::$fa_icon; ?> icon-large icon-fixed-with"></i>
            </span>
            <span class="external-auth-name">
                Sign in with <?php echo static::$service_name; ?>
            </span>
            </div>
<?php } ?>
        </a><?php
    }

    function triggerAuth() {
        $_SESSION['ext:bk:class'] = get_class($this);
    }
}
Signal::connect('api', function($dispatcher) {
    $dispatcher->append(
        url('^/auth/ext$', function() {
            if ($class = $_SESSION['ext:bk:class']) {
                $bk = StaffAuthenticationBackend::getBackend($class::$id)
                    ?: UserAuthenticationBackend::getBackend($class::$id);
                if ($bk instanceof ExternalAuthentication)
                    $bk->triggerAuth();
            }
        })
    );
});

abstract class UserAuthenticationBackend  extends AuthenticationBackend {

    static private $_registry = array();

    static function _register($class) {
        static::$_registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return array_merge(self::$_registry, parent::allRegistered());
    }

    function getAllowedBackends($userid) {
        $backends = array();
        $sql = 'SELECT A1.backend FROM '.USER_ACCOUNT_TABLE
              .' A1 INNER JOIN '.USER_EMAIL_TABLE.' A2 ON (A2.user_id = A1.user_id)'
              .' WHERE backend IS NOT NULL '
              .' AND (A1.username='.db_input($userid)
                  .' OR A2.`address`='.db_input($userid).')';

        if (!($res=db_query($sql, false)))
            return $backends;

        while (list($bk) = db_fetch_row($res))
            $backends[] = $bk;

        return array_filter($backends);
    }

    function login($user, $bk) {
        global $ost;

        if (!$user || !$bk
                || !$bk::$id //Must have ID
                || !($authkey = $bk->getAuthKey($user)))
            return false;

        $acct = $user->getAccount();

        if ($acct) {
            if (!$acct->isConfirmed())
                throw new AccessDenied('Account confirmation required');
            elseif ($acct->isLocked())
                throw new AccessDenied('Account is administratively locked');
        }

        // Tag the user and associated ticket in the SESSION
        $this->setAuthKey($user, $bk, $authkey);

        //The backend used decides the format of the auth key.
        // XXX: encrypt to hide the bk??
        $user->setAuthKey($authkey);

        $user->refreshSession(true); //set the hash.

        if (($acct = $user->getAccount()) && ($tid = $acct->get('timezone_id'))) {
            $_SESSION['TZ_OFFSET'] = Timezone::getOffsetById($tid);
            $_SESSION['TZ_DST'] = $acct->get('dst');
        }

        //Log login info...
        $msg=sprintf('%s (%s) logged in [%s]',
                $user->getUserName(), $user->getId(), $_SERVER['REMOTE_ADDR']);
        $ost->logDebug('User login', $msg);

        if ($bk->supportsInteractiveAuthentication() && ($acct=$user->getAccount()))
            $acct->cancelResetTokens();

        return true;
    }

    function setAuthKey($user, $bk, $key=false) {
        $authkey = $key ?: $bk->getAuthKey($user);

        //Tag the authkey.
        $authkey = $bk::$id.':'.$authkey;

        //Set the session goodies
        $authsession = &$_SESSION['_auth']['user'];

        $authsession = array(); //clear.
        $authsession['id'] = $user->getId();
        $authsession['key'] = $authkey;
    }

    function authenticate($username, $password) {
        return false;
    }

    static function signOut($user) {
        global $ost;

        $_SESSION['_auth']['user'] = array();
        unset($_SESSION[':token']['client']);
        $ost->logDebug('User logout',
                sprintf("%s logged out [%s]",
                    $user->getUserName(), $_SERVER['REMOTE_ADDR']));
    }

    protected function getAuthKey($user) {
        return  $user->getId();
    }

    static function getUser() {

        if (!isset($_SESSION['_auth']['user'])
                || !$_SESSION['_auth']['user']['key'])
            return null;

        list($id, $auth) = explode(':', $_SESSION['_auth']['user']['key']);

        if (!($bk=static::getBackend($id)) //get the backend
                || !($user=$bk->validate($auth)) //Get AuthicatedUser
                || !($user instanceof AuthenticatedUser) // Make sure it user
                || $user->getId() != $_SESSION['_auth']['user']['id'] // check ID
                )
            return null;

        $user->setAuthKey($_SESSION['_auth']['user']['key']);

        return $user;
    }

    protected function validate($userid) {
        if (!($user = User::lookup($userid)))
            return false;
        elseif (!$user->getAccount())
            return false;

        return new ClientSession(new EndUser($user));
    }
}

abstract class ExternalUserAuthenticationBackend
        extends UserAuthenticationBackend
        implements ExternalAuthentication {

    static $fa_icon = "signin";
    static $sign_in_image_url = false;
    static $service_name = "External";

    function renderExternalLink() { ?>
        <a class="external-sign-in" title="Sign in with <?php echo static::$service_name; ?>"
                href="login.php?do=ext&amp;bk=<?php echo urlencode(static::$id); ?>">
<?php if (static::$sign_in_image_url) { ?>
        <img class="sign-in-image" src="<?php echo static::$sign_in_image_url;
            ?>" alt="Sign in with <?php echo static::$service_name; ?>"/>
<?php } else { ?>
            <div class="external-auth-box">
            <span class="external-auth-icon">
                <i class="icon-<?php echo static::$fa_icon; ?> icon-large icon-fixed-with"></i>
            </span>
            <span class="external-auth-name">
                Sign in with <?php echo static::$service_name; ?>
            </span>
            </div>
<?php } ?>
        </a><?php
    }

    function triggerAuth() {
        $_SESSION['ext:bk:class'] = get_class($this);
    }
}

/**
 * This will be an exception in later versions of PHP
 */
class AccessDenied extends Exception {
    function __construct($reason) {
        $this->reason = $reason;
        parent::__construct($reason);
    }
}

/**
 * Simple authentication backend which will lock the login form after a
 * configurable number of attempts
 */
abstract class AuthStrikeBackend extends AuthenticationBackend {

    function authenticate($username, $password=null) {
        return static::authTimeout();
    }

    function signOn() {
        return static::authTimeout();
    }

    static function signOut($user) {
        return false;
    }


    function login($user, $bk) {
        return false;
    }

    static function getUser() {
        return null;
    }

    function supportsInteractiveAuthentication() {
        return false;
    }

    function getAllowedBackends($userid) {
        return array();
    }

    function getAuthKey($user) {
        return null;
    }

    //Provides audit facility for logins attempts
    function audit($result, $credentials) {

        //Count failed login attempts as a strike.
        if ($result instanceof AccessDenied)
            return static::authStrike($credentials);

    }

    abstract function authStrike($credentials);
    abstract function authTimeout();
}

/*
 * Backend to monitor staff's failed login attempts
 */
class StaffAuthStrikeBackend extends  AuthStrikeBackend {

    function authTimeout() {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['staff'];
        if (!$authsession['laststrike'])
            return;

        //Veto login due to excessive login attempts.
        if((time()-$authsession['laststrike'])<$cfg->getStaffLoginTimeout()) {
            $authsession['laststrike'] = time(); //reset timer.
            return new AccessDenied('Max. failed login attempts reached');
        }

        //Timeout is over.
        //Reset the counter for next round of attempts after the timeout.
        $authsession['laststrike']=null;
        $authsession['strikes']=0;
    }

    function authstrike($credentials) {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['staff'];

        $username = $credentials['username'];

        $authsession['strikes']+=1;
        if($authsession['strikes']>$cfg->getStaffMaxLogins()) {
            $authsession['laststrike']=time();
            $alert='Excessive login attempts by a staff member?'."\n".
                   'Username: '.$username."\n"
                   .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                   .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                   .'Attempts #'.$authsession['strikes']."\n"
                   .'Timeout: '.($cfg->getStaffLoginTimeout()/60)." minutes \n\n";
            $ost->logWarning('Excessive login attempts ('.$username.')', $alert,
                    $cfg->alertONLoginError());
            return new AccessDenied('Forgot your login info? Contact Admin.');
        //Log every other third failed login attempt as a warning.
        } elseif($authsession['strikes']%3==0) {
            $alert='Username: '.$username."\n"
                    .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                    .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                    .'Attempts #'.$authsession['strikes'];
            $ost->logWarning('Failed staff login attempt ('.$username.')', $alert, false);
        }
    }
}
StaffAuthenticationBackend::register('StaffAuthStrikeBackend');

/*
 * Backend to monitor user's failed login attempts
 */
class UserAuthStrikeBackend extends  AuthStrikeBackend {

    function authTimeout() {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['user'];
        if (!$authsession['laststrike'])
            return;

        //Veto login due to excessive login attempts.
        if ((time()-$authsession['laststrike']) < $cfg->getStaffLoginTimeout()) {
            $authsession['laststrike'] = time(); //reset timer.
            return new AccessDenied("You've reached maximum failed login attempts allowed.");
        }

        //Timeout is over.
        //Reset the counter for next round of attempts after the timeout.
        $authsession['laststrike']=null;
        $authsession['strikes']=0;
    }

    function authstrike($credentials) {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['user'];

        $username = $credentials['username'];
        $password = $credentials['password'];

        $authsession['strikes']+=1;
        if($authsession['strikes']>$cfg->getClientMaxLogins()) {
            $authsession['laststrike'] = time();
            $alert='Excessive login attempts by a user.'."\n".
                    'Username: '.$username."\n".
                    'IP: '.$_SERVER['REMOTE_ADDR']."\n".'Time:'.date('M j, Y, g:i a T')."\n\n".
                    'Attempts #'.$authsession['strikes'];
            $ost->logError('Excessive login attempts (user)', $alert, ($cfg->alertONLoginError()));
            return new AccessDenied('Access Denied');
        } elseif($authsession['strikes']%3==0) { //Log every third failed login attempt as a warning.
            $alert='Username: '.$username."\n".'IP: '.$_SERVER['REMOTE_ADDR'].
                   "\n".'TIME: '.date('M j, Y, g:i a T')."\n\n".'Attempts #'.$authsession['strikes'];
            $ost->logWarning('Failed login attempt (user)', $alert);
        }

    }
}
UserAuthenticationBackend::register('UserAuthStrikeBackend');


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

}
StaffAuthenticationBackend::register('osTicketAuthentication');

class PasswordResetTokenBackend extends StaffAuthenticationBackend {
    static $id = "pwreset.staff";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn($errors=array()) {
        global $ost;

        if (!isset($_POST['userid']) || !isset($_POST['token']))
            return false;
        elseif (!($_config = new Config('pwreset')))
            return false;
        elseif (($staff = new StaffSession($_POST['userid'])) &&
                !$staff->getId())
            $errors['msg'] = 'Invalid user-id given';
        elseif (!($id = $_config->get($_POST['token']))
                || $id != $staff->getId())
            $errors['msg'] = 'Invalid reset token';
        elseif (!($ts = $_config->lastModified($_POST['token']))
                && ($ost->getConfig()->getPwResetWindow() < (time() - strtotime($ts))))
            $errors['msg'] = 'Invalid reset token';
        elseif (!$staff->forcePasswdRest())
            $errors['msg'] = 'Unable to reset password';
        else
            return $staff;
    }

    function login($staff, $bk) {
        $_SESSION['_staff']['reset-token'] = $_POST['token'];
        Signal::send('auth.pwreset.login', $staff);
        return parent::login($staff, $bk);
    }
}
StaffAuthenticationBackend::register('PasswordResetTokenBackend');

/*
 * AuthToken Authentication Backend
 *
 * Provides auto-login facility for end users with valid link
 *
 * Ticket used to loggin is tracked durring the session this is
 * important in the future when auto-logins will be
 * limited to single ticket view.
 */
class AuthTokenAuthentication extends UserAuthenticationBackend {
    static $name = "Auth Token Authentication";
    static $id = "authtoken";


    function signOn() {

        $user = null;
        if ($_GET['auth']) {
            if (($u = TicketUser::lookupByToken($_GET['auth'])))
                $user = new ClientSession($u);
        }
        // Support old ticket based tokens.
        elseif ($_GET['t'] && $_GET['e'] && $_GET['a']) {
            if (($ticket = Ticket::lookupByNumber($_GET['t'], $_GET['e']))
                    // Using old ticket auth code algo - hardcoded here because it
                    // will be removed in ticket class in the upcoming rewrite
                    && !strcasecmp($_GET['a'], md5($ticket->getId() .  $_GET['e'] . SECRET_SALT))
                    && ($owner = $ticket->getOwner()))
                $user = new ClientSession($owner);
        }

        return $user;
    }

    function supportsInteractiveAuthentication() {
        return false;
    }

    protected function getAuthKey($user) {

        if (!$user)
            return null;

        //Generate authkey based the type of ticket user
        // It's required to validate users going forward.
        $authkey = sprintf('%s%dt%dh%s',  //XXX: Placeholder
                    ($user->isOwner() ? 'o':'c'),
                    $user->getId(),
                    $user->getTicketId(),
                    md5($user->getId().$this->id));

        return $authkey;
    }

    protected function validate($authkey) {

        $regex = '/^(?P<type>\w{1})(?P<id>\d+)t(?P<tid>\d+)h(?P<hash>.*)$/i';
        $matches = array();
        if (!preg_match($regex, $authkey, $matches))
            return false;

        $user = null;
        switch ($matches['type']) {
            case 'c': //Collaborator
                $criteria = array( 'userId' => $matches['id'],
                        'ticketId' => $matches['tid']);
                if (($c = Collaborator::lookup($criteria))
                        && ($c->getTicketId() == $matches['tid']))
                    $user = new ClientSession($c);
                break;
            case 'o': //Ticket owner
                if (($ticket = Ticket::lookup($matches['tid']))
                        && ($o = $ticket->getOwner())
                        && ($o->getId() == $matches['id']))
                    $user = new ClientSession($o);
                break;
        }

        //Make sure the authkey matches.
        if (!$user || strcmp($this->getAuthKey($user), $authkey))
            return null;

        $user->flagGuest();

        return $user;
    }

}
UserAuthenticationBackend::register('AuthTokenAuthentication');

//Simple ticket lookup backend used to recover ticket access link.
// We're using authentication backend so we can guard aganist brute force
// attempts (which doesn't buy much since the link is emailed)
class AccessLinkAuthentication extends UserAuthenticationBackend {
    static $name = "Ticket Access Link Authentication";
    static $id = "authlink";

    function authenticate($email, $number) {

        if (!($ticket = Ticket::lookupByNumber($number))
                || !($user=User::lookup(array('emails__address' => $email))))
            return false;

        // Ticket owner?
        if ($ticket->getUserId() == $user->getId())
            $user = $ticket->getOwner();
        // Collaborator?
        elseif (!($user = Collaborator::lookup(array(
                'userId' => $user->getId(),
                'ticketId' => $ticket->getId()))))
            return false; //Bro, we don't know you!

        return new ClientSession($user);
    }

    //We are not actually logging in the user....
    function login($user, $bk) {
        return true;
    }
    function supportsInteractiveAuthentication() {
        return false;
    }
}
UserAuthenticationBackend::register('AccessLinkAuthentication');

class osTicketClientAuthentication extends UserAuthenticationBackend {
    static $name = "Local Client Authentication";
    static $id = "client";

    function authenticate($username, $password) {
        if (!($acct = ClientAccount::lookupByUsername($username)))
            return;

        if (($client = new ClientSession(new EndUser($acct->getUser())))
                && !$client->getId())
            return false;
        elseif (!$acct->checkPassword($password))
            return false;
        else
            return $client;
    }
}
UserAuthenticationBackend::register('osTicketClientAuthentication');

class ClientPasswordResetTokenBackend extends UserAuthenticationBackend {
    static $id = "pwreset.client";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn($errors=array()) {
        global $ost;

        if (!isset($_POST['userid']) || !isset($_POST['token']))
            return false;
        elseif (!($_config = new Config('pwreset')))
            return false;
        elseif (!($acct = ClientAccount::lookupByUsername($_POST['userid']))
                || !$acct->getId()
                || !($client = new ClientSession(new EndUser($acct->getUser()))))
            $errors['msg'] = 'Invalid user-id given';
        elseif (!($id = $_config->get($_POST['token']))
                || $id != $client->getId())
            $errors['msg'] = 'Invalid reset token';
        elseif (!($ts = $_config->lastModified($_POST['token']))
                && ($ost->getConfig()->getPwResetWindow() < (time() - strtotime($ts))))
            $errors['msg'] = 'Invalid reset token';
        elseif (!$acct->forcePasswdReset())
            $errors['msg'] = 'Unable to reset password';
        else
            return $client;
    }

    function login($client, $bk) {
        $_SESSION['_client']['reset-token'] = $_POST['token'];
        Signal::send('auth.pwreset.login', $client);
        return parent::login($client, $bk);
    }
}
UserAuthenticationBackend::register('ClientPasswordResetTokenBackend');

class ClientAcctConfirmationTokenBackend extends UserAuthenticationBackend {
    static $id = "confirm.client";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn($errors=array()) {
        global $ost;

        if (!isset($_GET['token']))
            return false;
        elseif (!($_config = new Config('pwreset')))
            return false;
        elseif (!($id = $_config->get($_GET['token'])))
            return false;
        elseif (!($acct = ClientAccount::lookup(array('user_id'=>$id)))
                || !$acct->getId()
                || $id != $acct->getUserId()
                || !($client = new ClientSession(new EndUser($acct->getUser()))))
            return false;
        else
            return $client;
    }
}
UserAuthenticationBackend::register('ClientAcctConfirmationTokenBackend');
?>
