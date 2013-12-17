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
        } elseif ($this->getArg('customtables')) {
            $this->getCustomTables();
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
        $dataTables = array_map('escapeshellarg', $dataTables);
        
        $noDataTables = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME IN {$noDataTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ");
        $noDataTables = array_map('escapeshellarg', $noDataTables);

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
        return $this->createWhereFromArray($this->getNoDataTables());
    }

    protected function getCoreTablesWhere()
    {
        return $this->createWhereFromArray($this->getCoreTableNames());
    }

    protected function createWhereFromArray($array)
    {
        if (!is_array($array)) {
            throw new Exception('Expecting $array to be an array');
        }
        return "('" . implode("', '", $array) . "')";
    }

    protected function getNoDataTables()
    {
        if (is_null($this->_noDataTables)) {
            $coreTables = $this->getCoreTables();
            $this->_noDataTables = array_merge($coreTables, $this->customTables);
        }
        return $this->_noDataTables;
    }

    protected function getCustomTables()
    {
        $magentoConfig   = $this->config['databaseconfig'];
        $coreTablesWhere = $this->getCoreTablesWhere();
        $customTables    = $this->getDb()->fetchCol("
            SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_NAME NOT IN {$coreTablesWhere} AND TABLE_SCHEMA = '{$magentoConfig->dbname}'
        ");
        echo implode("\n", $customTables);
        return;
    }

    protected function getCoreTableNames()
    {
        $coretables = explode("\n", self::MAGENTO_CORE_TABLES);
        if ($this->config['tableprefix']) {
            foreach ($coretables as $i => $table) {
                $coretables[$i] = "{$this->config['tableprefix']}$table";
            }
        }
        return $coretables;
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

USAGE;
    }

    const MAGENTO_CORE_TABLES = <<<EOF
admin_assert
admin_role
admin_rule
admin_user
adminnotification_inbox
api2_acl_attribute
api2_acl_role
api2_acl_rule
api2_acl_user
api_assert
api_role
api_rule
api_session
api_user
captcha_log
catalog_category_anc_categs_index_idx
catalog_category_anc_categs_index_tmp
catalog_category_anc_products_index_idx
catalog_category_anc_products_index_tmp
catalog_category_entity
catalog_category_entity_datetime
catalog_category_entity_decimal
catalog_category_entity_int
catalog_category_entity_text
catalog_category_entity_varchar
catalog_category_flat_store_1
catalog_category_flat_store_2
catalog_category_flat_store_3
catalog_category_flat_store_4
catalog_category_product
catalog_category_product_index
catalog_category_product_index_enbl_idx
catalog_category_product_index_enbl_tmp
catalog_category_product_index_idx
catalog_category_product_index_tmp
catalog_compare_item
catalog_eav_attribute
catalog_product_bundle_option
catalog_product_bundle_option_value
catalog_product_bundle_price_index
catalog_product_bundle_selection
catalog_product_bundle_selection_price
catalog_product_bundle_stock_index
catalog_product_enabled_index
catalog_product_entity
catalog_product_entity_datetime
catalog_product_entity_decimal
catalog_product_entity_gallery
catalog_product_entity_group_price
catalog_product_entity_int
catalog_product_entity_media_gallery
catalog_product_entity_media_gallery_value
catalog_product_entity_text
catalog_product_entity_tier_price
catalog_product_entity_varchar
catalog_product_flat_1
catalog_product_flat_2
catalog_product_flat_3
catalog_product_flat_4
catalog_product_index_eav
catalog_product_index_eav_decimal
catalog_product_index_eav_decimal_idx
catalog_product_index_eav_decimal_tmp
catalog_product_index_eav_idx
catalog_product_index_eav_tmp
catalog_product_index_group_price
catalog_product_index_price
catalog_product_index_price_bundle_idx
catalog_product_index_price_bundle_opt_idx
catalog_product_index_price_bundle_opt_tmp
catalog_product_index_price_bundle_sel_idx
catalog_product_index_price_bundle_sel_tmp
catalog_product_index_price_bundle_tmp
catalog_product_index_price_cfg_opt_agr_idx
catalog_product_index_price_cfg_opt_agr_tmp
catalog_product_index_price_cfg_opt_idx
catalog_product_index_price_cfg_opt_tmp
catalog_product_index_price_downlod_idx
catalog_product_index_price_downlod_tmp
catalog_product_index_price_final_idx
catalog_product_index_price_final_tmp
catalog_product_index_price_idx
catalog_product_index_price_opt_agr_idx
catalog_product_index_price_opt_agr_tmp
catalog_product_index_price_opt_idx
catalog_product_index_price_opt_tmp
catalog_product_index_price_tmp
catalog_product_index_tier_price
catalog_product_index_website
catalog_product_link
catalog_product_link_attribute
catalog_product_link_attribute_decimal
catalog_product_link_attribute_int
catalog_product_link_attribute_varchar
catalog_product_link_type
catalog_product_option
catalog_product_option_price
catalog_product_option_title
catalog_product_option_type_price
catalog_product_option_type_title
catalog_product_option_type_value
catalog_product_relation
catalog_product_super_attribute
catalog_product_super_attribute_label
catalog_product_super_attribute_pricing
catalog_product_super_link
catalog_product_website
cataloginventory_stock
cataloginventory_stock_item
cataloginventory_stock_status
cataloginventory_stock_status_idx
cataloginventory_stock_status_tmp
catalogrule
catalogrule_affected_product
catalogrule_customer_group
catalogrule_group_website
catalogrule_product
catalogrule_product_price
catalogrule_website
catalogsearch_fulltext
catalogsearch_query
catalogsearch_recommendations
catalogsearch_result
checkout_agreement
checkout_agreement_store
cms_block
cms_block_store
cms_page
cms_page_store
core_cache
core_cache_option
core_cache_tag
core_config_data
core_email_template
core_flag
core_layout_link
core_layout_update
core_resource
core_session
core_store
core_store_group
core_translate
core_url_rewrite
core_variable
core_variable_value
core_website
coupon_aggregated
coupon_aggregated_order
coupon_aggregated_updated
cron_schedule
customer_address_entity
customer_address_entity_datetime
customer_address_entity_decimal
customer_address_entity_int
customer_address_entity_text
customer_address_entity_varchar
customer_eav_attribute
customer_eav_attribute_website
customer_entity
customer_entity_datetime
customer_entity_decimal
customer_entity_int
customer_entity_text
customer_entity_varchar
customer_form_attribute
customer_group
dataflow_batch
dataflow_batch_export
dataflow_batch_import
dataflow_import_data
dataflow_profile
dataflow_profile_history
dataflow_session
design_change
directory_country
directory_country_format
directory_country_region
directory_country_region_name
directory_currency_rate
downloadable_link
downloadable_link_price
downloadable_link_purchased
downloadable_link_purchased_item
downloadable_link_title
downloadable_sample
downloadable_sample_title
eav_attribute
eav_attribute_group
eav_attribute_label
eav_attribute_option
eav_attribute_option_value
eav_attribute_set
eav_entity
eav_entity_attribute
eav_entity_datetime
eav_entity_decimal
eav_entity_int
eav_entity_store
eav_entity_text
eav_entity_type
eav_entity_varchar
eav_form_element
eav_form_fieldset
eav_form_fieldset_label
eav_form_type
eav_form_type_entity
enterprise_admin_passwords
enterprise_banner
enterprise_banner_catalogrule
enterprise_banner_content
enterprise_banner_customersegment
enterprise_banner_salesrule
enterprise_catalogevent_event
enterprise_catalogevent_event_image
enterprise_catalogpermissions
enterprise_catalogpermissions_index
enterprise_catalogpermissions_index_product
enterprise_cms_hierarchy_lock
enterprise_cms_hierarchy_metadata
enterprise_cms_hierarchy_node
enterprise_cms_increment
enterprise_cms_page_revision
enterprise_cms_page_version
enterprise_customer_sales_flat_order
enterprise_customer_sales_flat_order_address
enterprise_customer_sales_flat_quote
enterprise_customer_sales_flat_quote_address
enterprise_customerbalance
enterprise_customerbalance_history
enterprise_customersegment_customer
enterprise_customersegment_event
enterprise_customersegment_segment
enterprise_customersegment_website
enterprise_giftcard_amount
enterprise_giftcardaccount
enterprise_giftcardaccount_history
enterprise_giftcardaccount_pool
enterprise_giftregistry_data
enterprise_giftregistry_entity
enterprise_giftregistry_item
enterprise_giftregistry_item_option
enterprise_giftregistry_label
enterprise_giftregistry_person
enterprise_giftregistry_type
enterprise_giftregistry_type_info
enterprise_giftwrapping
enterprise_giftwrapping_store_attributes
enterprise_giftwrapping_website
enterprise_invitation
enterprise_invitation_status_history
enterprise_invitation_track
enterprise_logging_event
enterprise_logging_event_changes
enterprise_reminder_rule
enterprise_reminder_rule_coupon
enterprise_reminder_rule_log
enterprise_reminder_rule_website
enterprise_reminder_template
enterprise_reward
enterprise_reward_history
enterprise_reward_rate
enterprise_reward_salesrule
enterprise_rma
enterprise_rma_grid
enterprise_rma_item_eav_attribute
enterprise_rma_item_eav_attribute_website
enterprise_rma_item_entity
enterprise_rma_item_entity_datetime
enterprise_rma_item_entity_decimal
enterprise_rma_item_entity_int
enterprise_rma_item_entity_text
enterprise_rma_item_entity_varchar
enterprise_rma_item_form_attribute
enterprise_rma_shipping_label
enterprise_rma_status_history
enterprise_sales_creditmemo_grid_archive
enterprise_sales_invoice_grid_archive
enterprise_sales_order_grid_archive
enterprise_sales_shipment_grid_archive
enterprise_scheduled_operations
enterprise_staging
enterprise_staging_action
enterprise_staging_item
enterprise_staging_log
enterprise_staging_product_unlinked
enterprise_targetrule
enterprise_targetrule_customersegment
enterprise_targetrule_index
enterprise_targetrule_index_crosssell
enterprise_targetrule_index_related
enterprise_targetrule_index_upsell
enterprise_targetrule_product
gift_message
googlecheckout_notification
importexport_importdata
index_event
index_process
index_process_event
log_customer
log_quote
log_summary
log_summary_type
log_url
log_url_info
log_visitor
log_visitor_info
log_visitor_online
newsletter_problem
newsletter_queue
newsletter_queue_link
newsletter_queue_store_link
newsletter_subscriber
newsletter_template
oauth_consumer
oauth_nonce
oauth_token
paypal_cert
paypal_payment_transaction
paypal_settlement_report
paypal_settlement_report_row
persistent_session
poll
poll_answer
poll_store
poll_vote
product_alert_price
product_alert_stock
rating
rating_entity
rating_option
rating_option_vote
rating_option_vote_aggregated
rating_store
rating_title
report_compared_product_index
report_event
report_event_types
report_viewed_product_aggregated_daily
report_viewed_product_aggregated_monthly
report_viewed_product_aggregated_yearly
report_viewed_product_index
review
review_detail
review_entity
review_entity_summary
review_status
review_store
sales_bestsellers_aggregated_daily
sales_bestsellers_aggregated_monthly
sales_bestsellers_aggregated_yearly
sales_billing_agreement
sales_billing_agreement_order
sales_flat_creditmemo
sales_flat_creditmemo_comment
sales_flat_creditmemo_grid
sales_flat_creditmemo_item
sales_flat_invoice
sales_flat_invoice_comment
sales_flat_invoice_grid
sales_flat_invoice_item
sales_flat_order
sales_flat_order_address
sales_flat_order_grid
sales_flat_order_item
sales_flat_order_payment
sales_flat_order_status_history
sales_flat_quote
sales_flat_quote_address
sales_flat_quote_address_item
sales_flat_quote_item
sales_flat_quote_item_option
sales_flat_quote_payment
sales_flat_quote_shipping_rate
sales_flat_shipment
sales_flat_shipment_comment
sales_flat_shipment_grid
sales_flat_shipment_item
sales_flat_shipment_track
sales_invoiced_aggregated
sales_invoiced_aggregated_order
sales_order_aggregated_created
sales_order_aggregated_updated
sales_order_status
sales_order_status_label
sales_order_status_state
sales_order_tax
sales_order_tax_item
sales_payment_transaction
sales_recurring_profile
sales_recurring_profile_order
sales_refunded_aggregated
sales_refunded_aggregated_order
sales_shipping_aggregated
sales_shipping_aggregated_order
salesrule
salesrule_coupon
salesrule_coupon_usage
salesrule_customer
salesrule_customer_group
salesrule_label
salesrule_product_attribute
salesrule_website
sendfriend_log
shipping_tablerate
sitemap
tag
tag_properties
tag_relation
tag_summary
tax_calculation
tax_calculation_rate
tax_calculation_rate_title
tax_calculation_rule
tax_class
tax_order_aggregated_created
tax_order_aggregated_updated
weee_discount
weee_tax
widget
widget_instance
widget_instance_page
widget_instance_page_layout
wishlist
wishlist_item
wishlist_item_option
xmlconnect_application
xmlconnect_config_data
xmlconnect_history
xmlconnect_notification_template
xmlconnect_queue
EOF;

}

$shell = new Guidance_Shell_Magentodump();
$shell->run();
