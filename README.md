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

  * The easiest way to import the CS data is to copy the database over and use OSTicket's builtin upgrader, so this should be done before we proceed with other changes. If we need to import data after making other changes to prod, there is an extra step to ensure that the imported data matches the current table layout.
    1. Exporting the Existing CS Data:
        1. Log on to the plchosticket server
        2. Using HeidiSQL connect to the MySQL DB on local host using the osticket user (password is in Keepass)
        3. Right click on the osticket database and select Export to SQL:
            a. Select drop and create for both database and tables
            b. Select to insert data

    2. Importing CS Data on a clean install
        1. Back up the existing OSTicket DB on webutility (use same instructions as exporting existing CS data)
        2. Drop the existing OSTicket DB on webutility 
        3. Load the exported SQL file on webutility (in HeidiSQL, File > Load SQL File, it may ask if you want to load the file directly rather than displaying, it's ok to choose Yes)
        4. Open the OSTicket staff control panel ({productionbaseurl}/osticket/scp) for the prod site and log on as an admin
        5. Step through the upgrade wizard

    3. Importing CS Data after other changes have been made
        1. Change the created database name and use statement at the top of the SQL file you're loading to osticket_upgrade
        2. Back up the existing osticket_upgrade DB on webutility (use same instructions as exporting existing CS data)
        3. Drop the existing osticket_upgrade DB on webutility
        4. Load the exported SQL file on webutility (in HeidiSQL, File > Load SQL File, it may ask if you want to load the file directly rather than displaying, it's ok to choose Yes)
        5. Go to the upgrader site staff control panel ({productionbaseurl}/osticket_upgrader/scp) and run through the upgrade wizard.
        6. Export the osticket_upgrade DB choosing Insert ignore for the data and do not select to drop or create the DB or tables
        7. Update the exported file to use osticket instead of osticket_upgrade
        8. Load the SQL file to the osticket DB


*Note: I had to modify one of the SQL upgrade scripts in order to get the upgrade from 1.9.x to 1.11 to work. Both the main production site and upgrader site have this fix, but if you need to reload the site files from the vendor and haven't deployed this code yet, you will need to copy over the upgrader scripts in /include/upgrader/streams/core/*

4. Deploy this modified code base to the prod web servers

5. Import PPGS Data

  * The PPGS data relies on some of the changes we've made to the code. To import use the PPGSMigrator application found on TFS in $/PLCH/Utilities/Plch.Migrations.OsTicket. The release build will import the data into the prod DB. Tickets imported from PPGS will have a ticket number prepended with an 'I'. This is to prevent collisions with existing CS data. The import from PPGS is slow, you should budget a week to load it all, if all of the data from PPGS needs to be imported.

6. Create a scheduled task to run the AD sync script ldap_user_info/update_user_info.php



