magento-shell-tools
===================

Mage_Shell scripts to help manage Magento installations.

Description
-------------------

Magento contains a little-known shell abstract class to manage Magento via CLI.
While there are a few concrete classes, the core doesn't ship with much functionality.
This projects aims to augment the CLI interface and provide some useful tools.

The Tools
-------------------

 - **cache.php**: All functionality that exists in the admin cache management panel. Plus some more!
   Really useful in deployment scripts.
 - **magentodump.php**: Create a database backup using mysqldump.  Also can clean customer and order 
   data from core tables to create a database dump that can be used for synchronizing environments
   such as local/dev/stage, etc.
 - **snapshot.php**: Create a compressed tar archive of the /media directory and a database dump into 
   a directory called /snapshot.  Useful for developers bootstrapping their local environments off 
   of an existing development environment.

Usage
-------------------
You can use this shell script like the other Magento shells. Help is provided.

### cache.php usage

    Usage:  php -f cache.php -- [options]
      info                          Show Magento cache types.
      --enable <cachetype>          Enable caching for a cachetype.
      --disable <cachetype>         Disable caching for a cachetype.
      --refresh <cachetype>         Clean cache types.
      --flush <magento|storage>     Flushes slow|fast cache storage.

      cleanmedia                    Clean the JS/CSS cache.
      cleanimages                   Clean the image cache.
      destroy                       Clear all caches.
      help                          This help.

      <cachetype>     Comma separated cache codes or value "all" for all caches

### magentodump.php usage

    Usage:  php -f magentodump.php -- [command] [options]
            php -f magentodump.php -- dump --clean --exclude-config --custom my_table1,my_table2

      Commands:

          dump
                Dump database data to stdout

          datatables
                Outputs tables in the database which will be exported with data

          nodatatables
                Outputs tables in the database which will be exported without data

          customtables
                Outputs tables in the database which are not recognized as Magento core

      Options:
          --clean
                Excludes customer or system instance related data from the dump such as
                (customers, logs, etc) by exporting certain tables as structure only
                without their data.

          --custom <table1,table2>
                Comma separated list of tables to export as structure only without data
                (only applies when running with --clean)

          --customfile <filename>
                Name of a file with a list of tables to export as structure only. One table
                name per line
                (only applies when running with --clean)

          --exclude-config
                Do not dump the core_config_data table (configuration data)
                (only applies when running with --clean)

          --exclude-eav-entity-store
                Do not dump the eav_entity_store table (increment ids)
                (only applies when running with --clean)
    
Using magentodump.php (Workflow)
---------------------
### Taking a snapshot of a production database using SSH web server credentials

  1. Download magentodump.php to the shell/ directory  
     * `wget https://raw2.github.com/guidance/magento-shell-tools/master/shell/magentodump.php`
  1. Analyze the database to determine which tables need to be dumped without data (because they contain customer info)
     * Export a list of tables not recognized as core
       * `php magentodump.php -- customtables > customer-customtables.txt`
     * Open the custom tables file in a text editor
     * Remove any tables from this list which you would like to include data for, leave any tables which should be dumped without data (usually easy to determine by inspecting the database schema, ie. customer_id, order_id fields, etc)
     * What remains in this file are the tables which will be exported without data
  1. Execute magentodump.php with --clean enabled and the custom tables file created above
     * `php magentodump.php -- dump --clean --customfile customer-customtables.txt | gzip > 2014-01-28-customer-db.sql.gz`

*  For syntax help execute the script without options: php magentodump.php

### Two approaches for managing application configuration

  1. Exclude the core_config_data table (using --exclude-config) from production and manage configuration separately
  2. Include the core_config_data but script an UPDATE to that table which sets local (dev, stage, etc) configuration

Requirements
-------------------

Any Magento version that has the /shell directory.

Installation
--------------------

Installation is very simple! Clone/copy the contents of /shell to your Magento /shell directory.

License
-------------------
http://www.opensource.org/licenses/osl-3.0.php

