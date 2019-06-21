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

1. ~~Install the base OsTicket v1.11~~

2. ~~Install the LDAP and HTTP passthrough plug-ins as instructed.~~

3. Import CS Data:

  * The easiest way to import the CS data is to copy the database over and use OSTicket's builtin upgrader, so this should be done before we proceed with other changes. If we need to import data after making data changes to prod, the easiest way to do the import will be to copy the database to a third temporary site, do the upgrade to v1.11 there, then export the data from that temp database to import to the prod site


    1. Importing CS Data on a clean install
        1. Export the existing database to SQL:
            a. Select drop and create for both database and tables
            b. Select to insert all data
        2. Back up the existing OSTicket DB on prod
        3. Drop the existing OSTicket DB on prod
        4. Load the exported SQL file to the prod server
        5. Open the OSTicket staff control panel for the prod site and log on as an admin
        6. Step through the upgrade wizard

    2. Importing CS Data after other changes have been made
        1. Export the existing database to SQL:
            a. Select drop and create for both database and tables
            b. Select to insert all data
        2. Change the created database name at the top of the SQL file to osticket_upgrade
        2. Back up the existing osticket_upgrade DB on prod
        3. Drop the existing osticket_upgrade DB on prod
        4. Load the exported SQL file to the prod server
        5. Go to the upgrader site and run through the upgrade wizard.
        6. Export the osticket_upgrade DB choosing Insert ignore for the data and do not drop or create the DB
        7. Update the exported file to use osticket instead of osticket_upgrade
        8. Load the SQL file to the osticket DB


*Note: I had to modify one of the SQL upgrade scripts in order to get the upgrade from 1.9.x to 1.11 to work. If you set up a temporary v1.11 site to import data, copy over the files from ~/include/upgrader/streams/core to get this fix*

4. Deploy this modified code base to the prod web servers

5. Import PPGS Data

  * The PPGS data relies on some of the changes we've made to the code. To import use the PPGSMigrator application. Found on TFS in $/PLCH/Utilities/Plch.Migrations.OsTicket. The release build will import the data into the prod DB. Tickets imported from PPGS will have a ticket number prepended with an 'I'. This is to prevent collisions with existing CS data.




