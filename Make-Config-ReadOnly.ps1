Invoke-Command -ComputerName plchint01 -ScriptBlock { 
    icacls E:\osticket\include\ost-config.php /inheritance:r /grant:r USERS:RX
    icacls E:\osticket\ldap_user_info\config.php /inheritance:r /grant:r USERS:RX
}
