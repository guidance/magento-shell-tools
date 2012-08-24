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
    /** @var array */
    protected $_noDataTables;

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
    }

    public function run()
    {
        // Usage help
        if ($this->getArg('dump')) {
            $this->dump();
        } else {
            echo $this->usageHelp();
            exit;
        }
    }

    public function dump()
    {
        // Get connection info
        $config = Mage::getConfig()->getResourceConnectionConfig('core_read');

        // Base mysqldump command
        $mysqldump = "mysqldump -h {$config->host} -u {$config->username} -p{$config->password} {$config->dbname}";

        // If not cleaning just execute mysqldump with default settings
        if (!$this->getArg('clean')) {
            passthru("$mysqldump");
            exit;
        }

        $noDataTablesWhere = $this->getNoDataTablesWhere();

        $dataSql = "
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME NOT IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$config->dbname}'
        ";

        if ($this->getArg('exclude-config')) {
            $dataSql = "$dataSql AND TABLE_NAME != 'core_config_data'";
        }

        $dataTables = $this->getDb()->fetchCol($dataSql);

        $noDataTables = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$config->dbname}'
        ");

        // Dump tables with data
        passthru("$mysqldump " . implode(' ', $dataTables));

        // Dump tables without data
        passthru("$mysqldump --no-data " . implode(' ', $noDataTables));
    }

    protected function getDb()
    {
        if (is_null($this->_db)) {
            $this->_db = Mage::getSingleton('core/resource')->getConnection('core_read');
        }
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
            $customTables = array();
            if ($this->getArg('custom')) {
                $cliCustomTables = array_map('trim', explode(',', $this->getArg('custom')));
                $customTables = array_merge($customTables, $cliCustomTables);
            }
            if ($this->getArg('customfile') && is_readable($this->getArg('customfile'))) {
                $fileCustomTables = array_map('trim', file($this->getArg('customfile')));
                $customTables = array_merge($customTables, $fileCustomTables);
            }
            $this->_noDataTables = array_merge($coreTables, $customTables);
        }
        return $this->_noDataTables;
    }

    protected function getCoreTables()
    {
        return array(
            'adminnotification_inbox',
            'api_session',
            'catalogsearch_fulltext',
            'catalogsearch_query',
            'catalogsearch_recommendations',
            'catalog_category_anc_categs_index_idx',
            'catalog_category_anc_categs_index_tmp',
            'catalog_category_anc_products_index_idx',
            'catalog_category_anc_products_index_tmp',
            'catalog_compare_item',
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
  dump  Dump database data to stdout

  Options:
  --clean                     Exclude data from the dump (dump table structure only).  A list
                              of core tables comes included with the script. 
  --custom <table1,table2>    Custom tables to export as structure only without data
  --customfile <filename>     File with custom tables to export as structure only. One table
                              name per line
  --exclude-config            Do not dump the core_config_data table

USAGE;
    }
}

$shell = new Guidance_Shell_Magentodump();
$shell->run();
