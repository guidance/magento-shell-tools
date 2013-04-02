<?php
/**
 * Author: Gordon Knoppe
 * Date: Mar 02, 2011
 *
 * @category    Guidance
 * @package     Mage_Shell
 * @copyright   Copyright (c) 2011 Gordon Knoppe (http://www.guidance.com)
 * @license     http://www.opensource.org/licenses/osl-3.0.php
 */

require_once 'abstract.php';

/**
 * Guidance snapshot shell script
 *
 * @category    Guidance
 * @package     Mage_Shell
 * @author      Gordon Knoppe
 */
class Guidance_Shell_Snapshot extends Mage_Shell_Abstract
{

    /**
     * Perform snapshot
     */
    function _snapshot()
    {
        # Check to make sure Magento is installed
        if (!Mage::isInstalled()) {
            echo "Application is not installed yet, please complete install wizard first.";
            exit;
        }
        
        # Initialize configuration values
        $connection = Mage::getConfig()->getNode('global/resources/default_setup/connection');
        $rootpath = $this->_getRootPath();
        $snapshot = $rootpath.'snapshot';

        # Create the snapshot directory if not exists
        $io = new Varien_Io_File();
        $io->mkdir($snapshot);

        # Prep suffix for snapshot filenames
        $file_suffix = '';
        if ($this->getArg('date')) {
            $file_suffix = '-'.date("Y-m-d");
        }

        # Create the media archive
        exec("tar -chz -C \"$rootpath\" -f \"{$snapshot}/media{$file_suffix}.tgz\" media");

        $password = escapeshellarg($connection->password);

        # Dump the database
        exec("mysqldump -h {$connection->host} -u {$connection->username} --password={$password} {$connection->dbname} | gzip > \"{$snapshot}/{$connection->dbname}{$file_suffix}.sql.gz\"");
    }

    /**
     * Run script
     */
    public function run()
    {
        if ($this->getArg('snapshot')) {
            $this->_snapshot();
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        global $argv;
        $self = basename($argv[0]);
        return <<<USAGE

Snapshot

Saves a tarball of the media directory and a gzipped database dump
taken with mysqldump

Usage:  php -f $self -- [command] [options]

  Commands:
      help              This help
      snapshot          Take snapshot

  Options:
      --date            Add a date to the media tarball and database dump filenames.


USAGE;
    }
}

if (basename($argv[0]) == basename(__FILE__)) {
    $shell = new Guidance_Shell_Snapshot();
    $shell->run();
}
