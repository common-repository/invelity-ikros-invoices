<?php
/*
Plugin Name: Invelity Ikros Invoices
Plugin URI: https://www.invelity.com/sk/sluzby
Description: Plugin Invelity iKros invoices is designed for Wordpress (WooCommerce) online stores who have purchased invoicing software iKros. Plugin automates the connecting and sending data from e-shop to invoicing system. You can create invoices directly from your e-shop orders.
Author: Invelity
Author URI: https://www.invelity.com
Version: 1.3.3

*/
defined('ABSPATH') or die('No script kiddies please!');

require_once('classes/class.invelityIkrosInvoicesAdmin.php');
require_once('classes/class.invelityIkrosInvoicesProcess.php');
if (!class_exists('InvelityPluginsAdmin')) {
    require_once('classes/class.invelityPluginsAdmin.php');
}

class InvelityIkrosInvoices
{
    public $settings = [];
    public $licenseValidator;
    public $updateChecker;

    public function __construct()
    {
        load_plugin_textdomain('ikros-invoices', false, dirname(plugin_basename(__FILE__)) . '/lang');
        $this->settings['plugin-slug'] = 'invelity-ikros-invoices';
        $this->settings['old-plugin-slug'] = 'finest-ikros-invoices';
        $this->settings['plugin-path'] = plugin_dir_path(__FILE__);
        $this->settings['plugin-url'] = plugin_dir_url(__FILE__);
        $this->settings['plugin-name'] = 'Invelity Ikros Invoices';
        $this->settings['plugin-license-version'] = '1.x.x';
        $this->initialize();
    }

    private function initialize()
    {
        new InvelityPluginsAdmin($this);
        new InvelityIkrosInvoicesAdmin($this);
        new InvelityIkrosInvoicesProcess($this);
    }

    public function getPluginSlug()
    {
        return $this->settings['plugin-slug'];
    }

    public function getPluginPath()
    {
        return $this->settings['plugin-path'];
    }

    public function getPluginUrl()
    {
        return $this->settings['plugin-url'];
    }

    public function getPluginName()
    {
        return $this->settings['plugin-name'];
    }

    public function getPluginLicenseVersion()
    {
        return $this->settings['plugin-license-version'];
    }

    public function getOldPluginSlug()
    {
        return $this->settings['old-plugin-slug'];
    }

}

new InvelityIkrosInvoices();



