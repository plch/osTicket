<?php

define('SQLITE3_OPEN_READONLY', false);

class GlobalConfig {
	// Version and Info
	public $version = "v0.4";
	
	// DEBUG and LOGG
    public $debug = "true";
	public $logpath = "c:\Temp\\";
	public $logfilename = "ost_update_user_info.log";  
	public $loglastexec = "ost_last_exec_user_info.log";

	//osTicket MySQL Database
    public $mysql_host = "localhost";
    public $mysql_db = "osticket";
    public $mysql_user = "osticket";
    public $mysql_pw = "Prune530!@";
    
    // Net LDAP2 Connection
    public $ldap_host = 'plchpdc.plch.net';
    public $ldap_port = '389'; //hide from config_ui to guarantee functionality
    public $ldap_binddn = 'CN=svc_ldapreader,OU=Service Accounts,DC=PLCH,DC=NET';
    public $ldap_bindpw = 'Vist@1968';
    public $ldap_basedn = 'OU=Users,OU=Staff,DC=PLCH,DC=NET';
    public $ldap_tls = 'false';
    public $ldap_attributes = array('samaccountname','cn','telephonenumber','department','personaltitle','sn','mail');
    public $ldap_filter = '(&(sAMAccountType=805306368)(!(userAccountControl=514))(mail=*))'; //hide from config_ui to guarantee functionality
   
    // LDAP filter explained in detail:
    // Normal User Accounts:								sAMAccountType=805306368
    // NOT Disabled Accounts:								!(userAccountControl=514)
    // NOT Disabled Accounts with password never expire: 	!(userAccountControl=66050)
    // Accounts with mail address:							mail=*

    
    // osTicket Agents and User Contact information field variables
   	public $agents = 'true';
   	public $ost_contact_info_fields = array('phone' => 'telephonenumber');
   	public $ost_contact_info_special_fields = array('');

   	
   	// ITDB Database
    public $itdb_connection = "SQLite3"; //hide from config_ui to guarantee functionality
    public $itdb_open_mode = SQLITE3_OPEN_READONLY; //hide from config_ui to guarantee functionality
    public $itdb_database = "";
    
    // Computername Links
    public $href_1 = "";
    public $href_2 = "";
    public $href_text_1 = "";
    public $href_text_2 = "";
}
?>
