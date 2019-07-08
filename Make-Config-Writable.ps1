Invoke-Command -ComputerName plchint01 -ScriptBlock { 
    icacls E:\osticket\include\ost-config.php /inheritance:e
    icacls E:\osticket\ldap_user_info\config.php /inheritance:e
}
