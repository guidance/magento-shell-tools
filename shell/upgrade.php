<?php
/**
 * NOTICE OF LICENSE
 *
 * Copyright 2014 Guidance Solutions
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author     Guidance Magento Team <magento@guidance.com>
 * @category   Guidance
 * @package    Magento Shell Tools
 * @copyright  Copyright (c) 2014 Guidance Solutions (http://www.guidance.com)
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 */

require_once dirname(__FILE__) . '/abstract.php';

/**
 * Class Guidance_Shell_Upgrade
 *
 * Runs the Magento upgrade scripts via command line.  Useful when
 * combined with maintenance mode during deployments.
 */
class Guidance_Shell_Upgrade extends Mage_Shell_Abstract
{
    /**
     * Initialize application and parse input parameters
     *
     */
    public function __construct()
    {
        if ($this->_includeMage) {
            require_once $this->_getRootPath() . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';
            Mage::app($this->_appCode, $this->_appType, array('global_ban_use_cache' => true));
        }
        $this->_factory = new Mage_Core_Model_Factory();

        $this->_applyPhpVariables();
        $this->_parseArgs();
        $this->_construct();
        $this->_validate();
        $this->_showHelp();
    }

    /**
     * Run script
     *
     */
    public function run()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2G');

        // should start the upgrade.
        $start = microtime(true);

        try {
            Mage_Core_Model_Resource_Setup::applyAllUpdates();
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

            // Now enable caching and save
            Mage::getConfig()->getOptions()->setData('global_ban_use_cache', FALSE);
            Mage::app()->baseInit(array()); // Re-init cache
            Mage::getConfig()->loadModules()->loadDb()->saveCache();
        } catch (Exception $e) {
            echo "\n";
            $e->getMessage();
            Mage::logException($e);
            echo "\n\n";
        }

        if (!$this->getArg('quiet')) {
            echo "\n";
            echo "Time For Upgrade: " . (microtime(true) - $start);
            echo "\n\n";
        }
    }
}

$shell = new Guidance_Shell_Upgrade();
$shell->run();