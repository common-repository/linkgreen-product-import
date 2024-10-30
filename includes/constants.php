<?php
/**
 * Constants for strongly typedness
 */

class Linkgreen_Constants {

    const SETTINGS_NONCE = 'linkgreen_product_import_settings_nonce';
    const RUN_IMPORT_HOOK_NAME = 'lgpi_product_import';
    const RUN_IMPORT_NONCE = 'linkgreen_product_import_run_nonce';
    const RUN_IMPORT_NONCE_FIELD = '_field_for_product_import';
    
    const RUN_LOG_CLEAN_HOOK_NAME = 'lgpi_product_import_clean_logs';
    const RUN_CACHE_CLEAN_HOOK_NAME = 'lgpi_product_import_clean_cache';
    const RUN_PRODUCT_CLEAN_HOOK_NAME = 'lgpi_product_import_clean_products';

    const CRON_SCHEDULE_LOG_CLEAN = DAY_IN_SECONDS * 7;
    const CRON_SCHEDULE_CACHE_CLEAN = HOUR_IN_SECONDS * 48;
}