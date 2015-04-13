<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition End User License Agreement
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magento.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Shell
 * @copyright Copyright (c) 2006-2014 X.commerce, Inc. (http://www.magento.com)
 * @license http://www.magento.com/license/enterprise-edition
 */

require_once 'abstract.php';

/**
 * Magento Log Shell Script
 *
 * @category    Mage
 * @package     Mage_Shell
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Shell_Attribute_Options extends Mage_Shell_Abstract
{
    const ARG_ATTRIBUTE_CODE = 'attribute';
    const ARG_DELETE = 'delete';
    const ARG_FILE = 'file';
    const STORE_ID = 0;

    /**
     * Attribute to be updated
     *
     * @var Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected $_attribute;

    /**
     * Options found in the database
     *
     * @var array
     */
    protected $_options;

    /**
     * Setup class to be used for attribute option creation
     *
     * @var Mage_Eav_Model_Entity_Setup
     */
    protected $_setup;

    /**
     * Attribute options to import
     *
     * @var array
     */
    protected $_values_to_import;

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    protected function _initOptions()
    {
        $this->_options = array();
        foreach ($this->_getAttribute()->getSource()->getAllOptions() as $option) {
            $this->_options[$option['label']] = $option['value'];
        }
    }

    /**
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute()
    {
        if (is_null($this->_attribute)) {
            $this->_attribute = Mage::getModel('catalog/product')->getResource()->getAttribute($this->_getAttributeArgument());
            if (!($this->_attribute->getSource() instanceof Mage_Eav_Model_Entity_Attribute_Source_Table)) {
                throw new Exception($this->_getAttributeArgument() . ' is not an attribute which has options');
            }
            $this->_attribute->setData('store_id', self::STORE_ID);
        }
        return $this->_attribute;
    }

    /**
     * @return Mage_Eav_Model_Entity_Setup
     */
    protected function _getSetup()
    {
        if (is_null($this->_setup)) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $this->_attribute */
            $this->_setup = new Mage_Eav_Model_Entity_Setup('core_setup');
        }
        return $this->_setup;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function _getValuesToImport()
    {
        if (is_null($this->_values_to_import)) {
            $file = $this->_getFileArgument();
            $this->_values_to_import = file($file, FILE_IGNORE_NEW_LINES);
        }
        return $this->_values_to_import;
    }

    /**
     * @return mixed
     */
    protected function _getFileArgument()
    {
        $file = $this->getArg(self::ARG_FILE);
        if (empty($file)) {
            throw new Exception('You must specify the file with options to import using: --' . self::ARG_FILE);
        }
        if (!is_readable($file)) {
            throw new Exception('Cannot open file ' . $file . ' for import');
        }
        return $file;
    }

    /**
     * @return mixed
     */
    protected function _getAttributeArgument()
    {
        $attribute = $this->getArg(self::ARG_ATTRIBUTE_CODE);
        if (empty($attribute)) {
            throw new Exception('You must specify the attribute to be updated using: --' . self::ARG_ATTRIBUTE_CODE);
        }
        return $attribute;
    }

    /**
     * @return mixed
     */
    protected function _getDeleteArgument()
    {
        return $this->getArg(self::ARG_DELETE);
    }

    /**
     * @return bool
     */
    protected function _isDeleteMode()
    {
        $delete = $this->_getDeleteArgument();
        return $delete == 'yes';
    }

    /**
     * @throws Exception
     */
    public function run()
    {
        if (!$this->getArg(self::ARG_ATTRIBUTE_CODE) && !$this->getArg(self::ARG_FILE)) {
            echo $this->usageHelp();
            return;
        }
        $this->_initOptions();
        $values = $this->_getValuesToImport();
        $attribute = $this->_getAttribute();
        foreach ($values as $value) {
            if (!isset($this->_options[$value])) {
                $option = array(
                    'attribute_id' => $attribute->getId(),
                    'value' => array(
                        array($value)
                    )
                );
                $this->_getSetup()->addAttributeOption($option);
                echo $value . ' created' . PHP_EOL;
            }
        }
        if ($this->_isDeleteMode()) {
            $values_to_delete = array_diff(array_keys($this->_options), $values);
            foreach ($values_to_delete as $value) {
                if (empty($value)) {
                    continue;
                }
                $option = array(
                    'delete' => array(
                        $this->_options[$value] => true,
                    ),
                    'value' => array(
                        $this->_options[$value] => true,
                    )
                );
                $this->_getSetup()->addAttributeOption($option);
                echo $value . ' deleted' . PHP_EOL;
            }
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f import_attribute_options.php -- [options]

  --attribute <attribute_code>     Attribute whose options are to be imported (Required)
  --file      <path_to_file>       File with options to be imported           (Required)
  --delete    yes                  Options which are found in db but not in file will be deleted (Optional)
  help              This help

USAGE;
    }
}

$shell = new Mage_Shell_Attribute_Options();
$shell->run();
