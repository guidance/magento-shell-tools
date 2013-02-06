<?php
require_once './abstract.php';

/**
 * Magento MySQL backup script
 *
 * @category    Guidance
 * @package     Guidance_Shell_Magentodump
 * @author      Guidance Open Source
 */
class Guidance_Shell_Magentodump extends Mage_Shell_Abstract
{
    protected $config = array(
        'cleandata'         => false,
        'connectiontype'    => 'core_read',
        'databaseconfig'    => null,
        'exclude-config'    => false,
        'exclude-eav-entity-store'    => false,
        'excludeconfigdata' => false,
        'mysqldumpcommand'  => 'mysqldump',
        'tableprefix'       => '',
    );

    protected $customTables = array();

    /** @var string */
    protected $_filename;

    /** @var Varien_Db_Adapter_Pdo_Mysql */
    protected $_db;

    /** @var array */
    protected $_customTables = array();

    public function _construct()
    {
        // Check to make sure Magento is installed
        if (!Mage::isInstalled()) {
            echo "Application is not installed yet, please complete install wizard first.";
            exit;
        }

        // Initialize database connection
        $this->_db = Mage::getSingleton('core/resource')->getConnection($this->config['connectiontype']);

        // Process custom tables
        if ($this->getArg('custom')) {
            $cliCustomTables = array_map('trim', explode(',', $this->getArg('custom')));
            $this->customTables = $cliCustomTables;
        }
        if ($this->getArg('customfile') && is_readable($this->getArg('customfile'))) {
            $fileCustomTables = array_map('trim', file($this->getArg('customfile')));
            $this->customTables = array_merge($this->customTables, $fileCustomTables);
        }

        // Configuration
        $this->config['databaseconfig'] = Mage::getConfig()->getResourceConnectionConfig($this->config['connectiontype']);
        $this->config['tableprefix']    = (string)Mage::getConfig()->getTablePrefix();

        if ($this->getArg('clean')) {
            $this->config['cleandata'] = true;
        }
        if ($this->getArg('exclude-config')) {
            $this->config['exclude-config'] = true;
        }
        if ($this->getArg('exclude-eav-entity-store')) {
            $this->config['exclude-eav-entity-store'] = true;
        }

    }

    public function run()
    {
        // Usage help
        if ($this->getArg('dump')) {
            $this->dump();
        } elseif ($this->getArg('datatables')) {
            $this->getTablesWithData();
        } elseif ($this->getArg('nodatatables')) {
            $this->getTablesWithoutData();
        } else {
            echo $this->usageHelp();
            exit;
        }
    }

    public function dump()
    {
        // Get connection info
        $magentoConfig = $this->config['databaseconfig'];

        // Base mysqldump command
        $mysqldump = "{$this->config['mysqldumpcommand']} -h {$magentoConfig->host} -u {$magentoConfig->username} -p{$magentoConfig->password} {$magentoConfig->dbname}";

        // If not cleaning just execute mysqldump with default settings
        if (!$this->config['cleandata']) {
            passthru("$mysqldump");
            return;
        }

        $noDataTablesWhere = $this->getNoDataTablesWhere();

        $dataSql = "
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME NOT IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ";

        if ($this->config['exclude-config']) {
            $tableprefix = (string)Mage::getConfig()->getTablePrefix();
            $dataSql = "$dataSql AND TABLE_NAME != '{$tableprefix}core_config_data'";
        }

        if ($this->config['exclude-eav-entity-store']) {
            $tableprefix = (string)Mage::getConfig()->getTablePrefix();
            $dataSql = "$dataSql AND TABLE_NAME != '{$tableprefix}eav_entity_store'";
        }

        $dataTables = $this->getDb()->fetchCol($dataSql);

        $noDataTables = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ");

        // Dump tables with data
        passthru("$mysqldump " . implode(' ', $dataTables));

        // Dump tables without data
        passthru("$mysqldump --no-data " . implode(' ', $noDataTables));
    }

    protected function getDb()
    {
        return $this->_db;
    }

    protected function getNoDataTablesWhere()
    {
        return "('" . implode("', '", $this->getNoDataTables()) . "')";
    }

    protected function getNoDataTables()
    {
        if (is_null($this->_noDataTables)) {
            $coreTables = $this->getCoreTables();
            $this->_noDataTables = array_merge($coreTables, $this->customTables);
        }
        return $this->_noDataTables;
    }

    protected function getTablesWithData()
    {
        $magentoConfig     = $this->config['databaseconfig'];
        $noDataTablesWhere = $this->getNoDataTablesWhere();
        $noDataTables      = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME NOT IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ");
        echo implode("\n", $noDataTables);
        return;
    }

    protected function getTablesWithoutData()
    {
        $magentoConfig     = $this->config['databaseconfig'];
        $noDataTablesWhere = $this->getNoDataTablesWhere();
        $noDataTables      = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ");
        echo implode("\n", $noDataTables);
        return;
    }

    protected function getCoreTables()
    {
        $coretables = array(
            'adminnotification_inbox',
            'api_session',
            'catalogsearch_fulltext',
            'catalogsearch_query',
            'catalogsearch_recommendations',
            'catalogsearch_result',
            'catalog_category_anc_categs_index_idx',
            'catalog_category_anc_categs_index_tmp',
            'catalog_category_anc_products_index_idx',
            'catalog_category_anc_products_index_tmp',
            'catalog_compare_item',
            'checkout_agreement',
            'checkout_agreement_store',
            'core_cache',
            'core_cache_tag',
            'core_session',
            'coupon_aggregated',
            'coupon_aggregated_order',
            'cron_schedule',
            'customer_address_entity',
            'customer_address_entity_datetime',
            'customer_address_entity_decimal',
            'customer_address_entity_int',
            'customer_address_entity_text',
            'customer_address_entity_varchar',
            'customer_entity',
            'customer_entity_datetime',
            'customer_entity_decimal',
            'customer_entity_int',
            'customer_entity_text',
            'customer_entity_varchar',
            'dataflow_batch',
            'dataflow_batch_export',
            'dataflow_batch_import',
            'dataflow_import_data',
            'dataflow_profile_history',
            'dataflow_session',
            'downloadable_link_purchased',
            'downloadable_link_purchased_item',
            'enterprise_customer_sales_flat_order',
            'enterprise_customer_sales_flat_order_address',
            'enterprise_customer_sales_flat_quote',
            'enterprise_customer_sales_flat_quote_address',
            'enterprise_customerbalance',
            'enterprise_customerbalance_history',
            'enterprise_customersegment_customer',
            'enterprise_giftcardaccount',
            'enterprise_giftcardaccount_history',
            'enterprise_giftcardaccount_pool',
            'enterprise_giftregistry_data',
            'enterprise_giftregistry_entity',
            'enterprise_giftregistry_item',
            'enterprise_giftregistry_item_option',
            'enterprise_giftregistry_label',
            'enterprise_giftregistry_person',
            'enterprise_giftregistry_type',
            'enterprise_giftregistry_type_info',
            'enterprise_invitation',
            'enterprise_invitation_status_history',
            'enterprise_invitation_track',
            'enterprise_logging_event',
            'enterprise_logging_event_changes',
            'enterprise_reminder_rule_log',
            'enterprise_reward',
            'enterprise_reward_history',
            'enterprise_sales_creditmemo_grid_archive',
            'enterprise_sales_invoice_grid_archive',
            'enterprise_sales_order_grid_archive',
            'enterprise_sales_shipment_grid_archive',
            'gift_message',
            'googlebase_attributes',
            'googlebase_items',
            'googlecheckout_api_debug',
            'googlecheckout_notification',
            'googleoptimizer_code',
            'importexport_importdata',
            'index_event',
            'index_process',
            'index_process_event',
            'log_customer',
            'log_quote',
            'log_summary',
            'log_summary_type',
            'log_url',
            'log_url_info',
            'log_visitor',
            'log_visitor_info',
            'log_visitor_online',
            'newsletter_problem',
            'newsletter_queue',
            'newsletter_queue_link',
            'newsletter_queue_store_link',
            'newsletter_subscriber',
            'paygate_authorizenet_debug',
            'paypaluk_api_debug',
            'paypal_api_debug',
            'paypal_cert',
            'paypal_settlement_report',
            'paypal_settlement_report_row',
            'poll_vote',
            'product_alert_price',
            'product_alert_stock',
            'rating',
            'rating_entity',
            'rating_option',
            'rating_option_vote',
            'rating_option_vote_aggregated',
            'rating_store',
            'rating_title',
            'remember_me',
            'report_compared_product_index',
            'report_event',
            'report_viewed_product_index',
            'review',
            'review_detail',
            'review_entity',
            'review_entity_summary',
            'review_status',
            'review_store',
            'salesrule_coupon_usage',
            'salesrule_customer',
            'sales_bestsellers_aggregated_daily',
            'sales_bestsellers_aggregated_monthly',
            'sales_bestsellers_aggregated_yearly',
            'sales_billing_agreement',
            'sales_billing_agreement_order',
            'sales_flat_creditmemo',
            'sales_flat_creditmemo_comment',
            'sales_flat_creditmemo_grid',
            'sales_flat_creditmemo_item',
            'sales_flat_invoice',
            'sales_flat_invoice_comment',
            'sales_flat_invoice_grid',
            'sales_flat_invoice_item',
            'sales_flat_order',
            'sales_flat_order_address',
            'sales_flat_order_grid',
            'sales_flat_order_item',
            'sales_flat_order_payment',
            'sales_flat_order_status_history',
            'sales_flat_quote',
            'sales_flat_quote_address',
            'sales_flat_quote_address_item',
            'sales_flat_quote_item',
            'sales_flat_quote_item_option',
            'sales_flat_quote_payment',
            'sales_flat_quote_shipping_rate',
            'sales_flat_shipment',
            'sales_flat_shipment_comment',
            'sales_flat_shipment_grid',
            'sales_flat_shipment_item',
            'sales_flat_shipment_track',
            'sales_invoiced_aggregated',
            'sales_invoiced_aggregated_order',
            'sales_order_aggregated_created',
            'sales_order_status',
            'sales_order_status_label',
            'sales_order_status_state',
            'sales_order_tax',
            'sales_payment_transaction',
            'sales_recurring_profile',
            'sales_recurring_profile_order',
            'sales_refunded_aggregated',
            'sales_refunded_aggregated_order',
            'sales_shipping_aggregated',
            'sales_shipping_aggregated_order',
            'sitemap',
            'tag',
            'tag_properties',
            'tag_relation',
            'tag_summary',
            'tax_order_aggregated_created',
            'wishlist',
            'wishlist_item',
            'wishlist_item_option',
            'xmlconnect_history',
            'xmlconnect_queue',
        );
        if ($this->config['tableprefix']) {
            foreach ($coretables as $i => $table) {
                $coretables[$i] = "{$this->config['tableprefix']}$table";
            }
        }
        return $coretables;
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f magentodump.php -- [command] [options]
        php -f magentodump.php -- dump --clean --exclude-config --custom my_table1,my_table2

  Commands:

      dump
            Dump database data to stdout

      datatables
            Outputs tables in the database which will be exported with data

      nodatatables
            Outputs tables in the database which will be exported without data

  Options:
      --clean
            Exclude data from the dump (dump table structure only).  A list
            of core tables comes included with the script.

      --custom <table1,table2>
            Custom tables to export as structure only without data

      --customfile <filename>
            File with custom tables to export as structure only. One table
            name per line

      --exclude-config
            Do not dump the core_config_data table (configuration data)

      --exclude-eav-entity-store
            Do not dump the eav_entity_store table (increment ids)

USAGE;
    }
}

$shell = new Guidance_Shell_Magentodump();
$shell->run();
