<?php

//print $argv[1] ;die;
// Inclusion of Net_LDAP2 package. config and functions file:
require_once 'Net/LDAP2.php';
require_once 'config.php';
require_once 'class_function.php';

// Initialization of config and functions:
$config = new GlobalConfig();
//$functions = new Functions($config, $mysqli);
$functions = new Functions($config);

// MySQL connection to osTicket database
$mysqli = new mysqli($config->mysql_host, $config->mysql_user, $config->mysql_pw, $config->mysql_db);
// The ldap configuration array:
$ldap_config = array (
    'binddn'    => $config->ldap_binddn,
    'bindpw'    => $config->ldap_bindpw,
    'basedn'    => $config->ldap_basedn,
    'host'      => ($config->ldap_host),
    'port'		=> $config->ldap_port,
    'starttls'	=> $config->ldap_tls
);

// Connecting using the configuration:
$ldap = Net_LDAP2::connect($ldap_config);
$filter = $config->ldap_filter;
$searchbase = $ldap_config->basedn;
$options = array(
	'scope' => 'sub',
    'attributes' => $config->ldap_attributes
);

$email = $argv[1];

$ldap_search = $ldap->search($searchbase, '(&(mail='. $email .'))', $options);
$ldap_result = $ldap_search->sorted_as_struct();

$email = $mysqli->real_escape_string($email);
$department = $mysqli->real_escape_string($ldap_result[0]['department'][0]);
$qry_update_ostuser_dept='update ost_user u
inner join ost_user_email ue on ue.user_id=u.id
set u.org_id = (select id from ost_organization where name="'. $department .'") 
where ue.address="'.$email.'" ';
//echo $qry_update_ostuser_dept;
$res_update_ostuser_dept = $mysqli->query($qry_update_ostuser_dept);
die();
