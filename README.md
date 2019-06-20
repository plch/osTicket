osTicket
========

A fork of osTicket in use by the Public Library of Cincinnati and Hamilton County for an internal help desk

Development Setup:
1. Install Composer (PHP package manager): https://getcomposer.org/
2. Use Composer to install phing (Build runner) globally: `C:\osticket\> composer global require phing/phing`
3. Set environment variables for beta and prod directories: OsTicketBetaDir and OsTicketProdDir
    (Remember that you will need to restart open programs to access these new environment variables)

To deploy:
Beta: `C:\osticket\> phing beta`
Prod: `C:\osticket\> phing prod`

Initial Production Deploy Steps:
~~1. Install OsTicket v1.11~~

2. Import CS Data:
It is best to import CS Data before making any major changes to prod, this will make your life much easier. This is because the easiest way to import the CS data is to copy the database over and use OSTicket's builtin upgrader. If we need to import data after making data changes to prod, the easiest way to do the import will be to copy the database to a third temporary site, do the upgrade to v1.11 there, then export the data from that temp database to import to the prod site

    1. Export the existing database to SQL:
        a. Select drop and create for both database and tables
        b. Select to insert all data
    2. Back up the existing OSTicket DB on prod
    3. Drop the existing OSTicket DB on prod
    4. Load the exported SQL file to the prod server
    5. Open the OSTicket staff control panel for the prod site and log on as an admin
    6. Step through the upgrade wizard

** Note: I had to modify one of the upgrade scripts in order to get the upgrade from 1.9.x to 1.11 to work. If you set up a temporary v1.11 site to do the upgrade**

3. Deploy this modified code base to the prod web servers

4. Import PPGS Data

The PPGS data relies on some of the changes we've made to the code. To import use the PPGSMigrator application. Found on TFS in $/PLCH/Utilities/Plch.Migrations.OsTicket. The release build will import the data into the prod DB. Tickets imported from PPGS will have a ticket number prepended with an 'I'. This is to prevent collisions with existing CS data.




