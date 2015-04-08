<?php
/**
 * NOTICE OF LICENSE
 *
 * Copyright 2015 Guidance Solutions
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
 * @copyright  Copyright (c) 2015 Guidance Solutions (http://www.guidance.com)
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 */

require_once dirname(__FILE__) . '/abstract.php';

/**
 * Class Guidance_Shell_CacheGarbageCollection
 *
 * Runs garbage collection on the cache backends
 */
class Guidance_Shell_CacheGarbageCollection extends Mage_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2G');
        error_reporting(E_ALL | E_STRICT);
        Mage::app()->getCache()->getBackend()->clean('old');
        Enterprise_PageCache_Model_Cache::getCacheInstance()->getFrontend()->getBackend()->clean('old');
    }
}

$shell = new Guidance_Shell_CacheGarbageCollection();
$shell->run();