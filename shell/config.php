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

require_once 'abstract.php';

/**
 * Class Guidance_Shell_Config
 *
 * Used to export and compare the Magento configuration table core_config_data
 */
class Guidance_Shell_Config extends Mage_Shell_Abstract
{

    /** @var Mage_Core_Model_Config **/
    protected $_baseConfig;
    
    /**
     * Run script
     *
     */
    public function run()
    {
        // Usage help
        if ($this->getArg('printcustom')) {
            $this->printCustomConfigs();
        } elseif ($this->getArg('deletedefault')) {
            $this->deleteDefaultConfigs();
        } else {
            echo $this->usageHelp();
            exit;
        }
    }

    public function printCustomConfigs()
    {
        $collection = $this->getConfigCollection();

        foreach ($collection as $config) {
            if (!$this->configIsEqualToDefault($config)) {
                fputcsv(STDOUT, $this->getFields($config));
            }
        }
    }

    public function deleteDefaultConfigs()
    {
        $deleted = 0;
        $collection = $this->getConfigCollection();

        foreach ($collection as $config) {
            if ($this->configIsEqualToDefault($config)) {
                $config->delete();
                $deleted++;
            }
        }

        printf("%s default database records deleted\n", $deleted);
    }

    public function configIsEqualToDefault(Mage_Core_Model_Config_Data $config)
    {
        if ($config->getScope() != 'default' || $config->getScopeId() > 0) {
            return false;
        } else {
            $baseValue = (string) $this->getBaseConfig()->getNode('default/' . $config->getPath());
            return $baseValue == $config->getValue();
        }
    }

    /**
     * @return Mage_Core_Model_Config
     */
    public function getBaseConfig()
    {
        if (is_null($this->_baseConfig)) {
            $this->_baseConfig = Mage::getModel('core/config');
            $this->_baseConfig->loadBase()
                    ->loadModules();
        }
        return $this->_baseConfig;
    }

    public function getConfigCollection()
    {
        /** @var Mage_Core_Model_Config_Data $config */
        $config = Mage::getModel('core/config_data');

        /** @var Mage_Core_Model_Resource_Config_Data_Collection $collection */
        $collection = $config->getCollection();
        $collection->addOrder('scope', 'ASC')
                ->addOrder('scope_id', 'ASC')
                ->addOrder('path', 'ASC');

        return $collection;
    }

    public function getFields(Mage_Core_Model_Config_Data $config)
    {
        return array(
            'scope' => $config->getScope(),
            'scope_id' => $config->getScopeId(),
            'path' => $config->getPath(),
            'value' => $config->getValue()
        );
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f config.php -- [command] [options]

  Commands:

      printcustom
            Print customized configuration values to STDOUT in CSV format

      deletedefault
            Delete configuration values from core_config_data which are
            equivalent to their module defaults

USAGE;
    }
}

$shell = new Guidance_Shell_Config();
$shell->run();
