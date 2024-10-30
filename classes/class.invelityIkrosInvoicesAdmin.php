<?php

class InvelityIkrosInvoicesAdmin
{
    private $launcher;
    private $activationMesasge = null;
    private $options;

    /**
     * Adds menu items and page
     * Gets options from database
     */
    public function __construct(InvelityIkrosInvoices $launcher)
    {
        $this->launcher = $launcher;
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_plugin_page']);
            add_action('admin_init', [$this, 'page_init']);
            add_action('admin_init', [$this, 'options_init']);
            add_action('admin_enqueue_scripts', [$this, 'loadMainAdminAssets']);
            add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        }
        $this->options = get_option('ikros_options');
        if (isset($this->options['add_mail']) && $this->options['add_mail'] == 'checked') {
            add_action('woocommerce_email_before_order_table', [$this,'addEmailInvoice'], 10, 4);
        }



    }

    public function addEmailInvoice($order, $sent_to_admin, $plain_text, $email)
    {
        if ($order->has_status('completed')) {
            ?>
            <p>
                <?php
                $invoiceUrl = get_post_meta($order->get_id(), 'invoiceUrl', true);
                $invoiceNumber = get_post_meta($order->get_id(), 'invoiceNumber', true);
                if ($invoiceUrl != "")
                    printf('<a href="%s" target="_Blank">' . __('Faktúra k vašej objednávke č. %s') . '</a>', $invoiceUrl, $invoiceNumber);
                ?>
            </p>
            <?php
        }
    }

    public function addMetaBoxes()
    {
        global $post;
        if ($post->post_type == 'shop_order' && get_post_meta($post->ID, 'invoiceUrl', true)) {
            add_meta_box(
                $this->launcher->getPluginSlug() . '-invoice-download-meta-box',
                __('Download invoice PDF', $this->launcher->getPluginSlug()),
                [$this, 'invelity_ikros_invoices_invoice_download_meta_box_callback'],
                'shop_order',
                'side'
            );
        }
    }

    public function invelity_ikros_invoices_invoice_download_meta_box_callback()
    {
        global $post;
        $invoiceUrl = get_post_meta($post->ID, 'invoiceUrl', true);
        ?>
        <a href="<?= $invoiceUrl ?>" target="_blank">
            <span class="dashicons dashicons-media-text"></span>
        </a>
        <?php

    }


    public function loadMainAdminAssets()
    {
        wp_register_style('invelity-ikros-invoices-admin-css', $this->launcher->getPluginUrl() . 'assets/css/invelity-ikros-invoices-admin.css', [], '1.0.0');
        wp_enqueue_style('invelity-ikros-invoices-admin-css');
        wp_register_script('invelity-ikros-invoices-admin-js', $this->launcher->getPluginUrl() . 'assets/js/invelity-ikros-invoices-admin.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('invelity-ikros-invoices-admin-js');
    }

    public function options_init()
    {
        if (!isset($this->options['invoice_numbering_type']) || !$this->options['invoice_numbering_type']) {
            $this->options['invoice_numbering_type'] = 'ikros';
            update_option('ikros_options', $this->options);
        }
        if (!isset($this->options['invoice_item_description']) || !$this->options['invoice_item_description']) {
            $this->options['invoice_item_description'] = 'variation_info';
            update_option('ikros_options', $this->options);
        }
        if (!isset($this->options['variable_symbol_type']) || !$this->options['variable_symbol_type']) {
            $this->options['variable_symbol_type'] = 'order';
            update_option('variable_symbol_type', $this->options);
        }
    }


    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_submenu_page(
            'invelity-plugins',
            __('Ikros invoices', $this->launcher->getPluginSlug()),
            __('Ikros invoices', $this->launcher->getPluginSlug()),
            'manage_options',
            'invelity-ikros-invoices',
            [$this, 'create_admin_page']
        );
    }

    private function getRemoteAd()
    {
        $invelityIkrosInvoicesad = get_transient('invelity-ikros-invoices-ad');
        if (!$invelityIkrosInvoicesad) {
            $response = '';
            try {
                $query = esc_url_raw(add_query_arg([], 'https://licenses.invelity.com/plugins/invelity-ikros-invoices/invelityad.json'));
                $response = wp_remote_get($query, ['timeout' => 2, 'sslverify' => false]);
                $response = wp_remote_retrieve_body($response);
                if (!$response && file_exists(plugin_dir_path(__FILE__) . '../json/invelityad.json')) {
                    $response = file_get_contents(plugin_dir_path(__FILE__) . '../json/invelityad.json');
                }
            } catch (Exception $e) {

            }
            if (!$response) {
                $response = '{}';
            }
            set_transient('invelity-plugins-ad', $response, 86400);/*Day*/
//            set_transient('invelity-ikros-invoices-ad', $response, 300);/*5 min*/
            $invelityIkrosInvoicesad = $response;
        }
        return json_decode($invelityIkrosInvoicesad, true);
    }

    /**
     * Creates contend of the option page
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option('ikros_options');
        ?>
        <div class="wrap invelity-plugins-namespace">
            <h2><?= $this->launcher->getPluginName() ?></h2>

            <form method="post" action="<?= admin_url() ?>options.php">
                <div>
                    <?php
                    settings_fields('ikros_options_group');
                    do_settings_sections('ikros-setting-admin');
                    submit_button();
                    ?>
                </div>
                <div>
                    <?php
                    $adData = $this->getRemoteAd();
                    if ($adData) {
                        ?>
                        <a href="<?= $adData['adDestination'] ?>" target="_blank">
                            <img src="<?= $adData['adImage'] ?>">
                        </a>
                        <?php

                    }
                    ?>
                </div>
            </form>
        </div>
        <?php

    }


    /**
     * Register individual setting options and option sections
     */
    public function page_init()
    {
        register_setting(
            'ikros_options_group', // Option group
            'ikros_options', // Option name
            [$this, 'sanitize'] // Sanitize
        );

        add_settings_section(
            'setting_section_1', // ID
            __('Basic settings', 'ikros-invoices'), // Title
            [$this, 'print_section_info'], // Callback
            'ikros-setting-admin' // Page
        );

        add_settings_section(
            'setting_section_2', // ID
            __('Sender settings', 'ikros-invoices'), // Title
            null,
            'ikros-setting-admin' // Page
        );
        add_settings_section(
            'setting_section_3', // ID
            __('Invoice settings', 'ikros-invoices'), // Title
            null,
            'ikros-setting-admin' // Page
        );
        add_settings_section(
            'setting_section_4', // ID
            __('Customer company settings', 'ikros-invoices'), // Title
            [$this, 'print_customer_section_info'],
            'ikros-setting-admin' // Page
        );


        add_settings_field(
            'ikros_api_key',
            __('Ikros API Key', 'ikros-invoices'),
            [$this, 'ikros_api_key_callback'],
            'ikros-setting-admin',
            'setting_section_1'
        );
        add_settings_field(
            'sender_name',
            __('Sender name', 'ikros-invoices'),
            [$this, 'sender_name_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_registration_id',
            __('Sender registration id', 'ikros-invoices'),
            [$this, 'sender_registration_id_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_registration_court',
            __('Sender registration court', 'ikros-invoices'),
            [$this, 'sender_registration_court_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_vat_id',
            __('Sender VAT id', 'ikros-invoices'),
            [$this, 'sender_vat_id_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_tax_id',
            __('Sender tax id', 'ikros-invoices'),
            [$this, 'sender_tax_id_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_street',
            __('Sender street', 'ikros-invoices'),
            [$this, 'sender_street_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_postcode',
            __('Sender postcode', 'ikros-invoices'),
            [$this, 'sender_postcode_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_town',
            __('Sender town', 'ikros-invoices'),
            [$this, 'sender_town_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_country',
            __('Sender Country', 'ikros-invoices'),
            [$this, 'sender_country_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_bank_acc',
            __('Sender bank account', 'ikros-invoices'),
            [$this, 'sender_bank_acc_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_bank_iban',
            __('Sender bank IBAN', 'ikros-invoices'),
            [$this, 'sender_bank_iban_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_bank_swift',
            __('Sender bank SWIFT', 'ikros-invoices'),
            [$this, 'sender_bank_swift_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_contact_name',
            __('Sender contact name', 'ikros-invoices'),
            [$this, 'sender_contact_name_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_phone',
            __('Sender phone', 'ikros-invoices'),
            [$this, 'sender_phone_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_email',
            __('Sender email', 'ikros-invoices'),
            [$this, 'sender_email_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
        add_settings_field(
            'sender_web',
            __('Sender web', 'ikros-invoices'),
            [$this, 'sender_web_callback'],
            'ikros-setting-admin',
            'setting_section_2'
        );
//        add_settings_field(
//            'tax',
//            __('Tax', 'ikros-invoices'),
//            array($this, 'tax_callback'),
//            'ikros-setting-admin',
//            'setting_section_3'
//        );
        add_settings_field(
            'plus_due_date',
            __('Due date', 'ikros-invoices'),
            [$this, 'plus_due_date_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );

        add_settings_field(
            'auto_generation',
            __('Automatic generation', 'ikros-invoices'),
            [$this, 'auto_generation_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );


        add_settings_field(
            'add_mail',
            __('Zobraziť odkaz na faktúru v emaili', 'ikros-invoices'),
            [$this, 'add_mail_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );

        add_settings_field(
            'due_action',
            __('Due action', 'ikros-invoices'),
            [$this, 'due_action_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );


        add_settings_field(
            'opening_text',
            __('Opening text', 'ikros-invoices'),
            [$this, 'opening_text_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );
        add_settings_field(
            'closing_text',
            __('Closing text', 'ikros-invoices'),
            [$this, 'closing_text_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );

        add_settings_field(
            'variable_symbol_type',
            __('Variable symbol type', 'ikros-invoices'),
            [$this, 'variable_symbol_type_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );

        add_settings_field(
            'invoice_numbering_type',
            __('Invoice numbering ype', 'ikros-invoices'),
            [$this, 'invoice_numbering_type_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );
        add_settings_field(
            'invoice_number_format',
            __('Invoice numbering format', 'ikros-invoices'),
            [$this, 'invoice_number_format_callback'],
            'ikros-setting-admin',
            'setting_section_3',
            [
                'class' => 'invoice_number_format-wrapper ' . ($this->options['invoice_numbering_type'] != 'plugin' ? 'hidden' : ''),
            ]
        );
        add_settings_field(
            'next_invoice_number',
            __('Next invoice number', 'ikros-invoices'),
            [$this, 'next_invoice_number_callback'],
            'ikros-setting-admin',
            'setting_section_3',
            [
                'class' => 'next_invoice_number-wrapper ' . ($this->options['invoice_numbering_type'] != 'plugin' ? 'hidden' : ''),
            ]
        );
        add_settings_field(
            'ikros_invoice_numbering_list',
            __('Ikros invoice numbering list', 'ikros-invoices'),
            [$this, 'ikros_invoice_numbering_list_callback'],
            'ikros-setting-admin',
            'setting_section_3',
            [
                'class' => 'ikros_invoice_numbering_list-wrapper ' . ($this->options['invoice_numbering_type'] != 'ikros' ? 'hidden' : ''),
            ]
        );
        add_settings_field(
            'invoice_item_description',
            __('Item description in the invoice', 'ikros-invoices'),
            [$this, 'invoice_item_description_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );
        add_settings_field(
            'invoice_sku',
            __('Zobrazit SKU', 'ikros-invoices'),
            [$this, 'invoice_sku_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );
        add_settings_field(
            'invoice_measureType',
            __('Počet kusov (ks) na faktúre', 'ikros-invoices'),
            [$this, 'invoice_measureType_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );
        add_settings_field(
            'invoice_tax',
            __('DPH', 'ikros-invoices'),
            [$this, 'invoice_tax_callback'],
            'ikros-setting-admin',
            'setting_section_3'
        );

        add_settings_field(
            'ico_company_field',
            __('ICO field name ', 'ikros-invoices'),
            [$this, 'ico_company_field_callback'],
            'ikros-setting-admin',
            'setting_section_4'
        );

        add_settings_field(
            'dic_company_field',
            __('DIC field name ', 'ikros-invoices'),
            [$this, 'dic_company_field_callback'],
            'ikros-setting-admin',
            'setting_section_4'
        );
        add_settings_field(
            'icdph_company_field',
            __('IC DPH field name ', 'ikros-invoices'),
            [$this, 'icdph_company_field_callback'],
            'ikros-setting-admin',
            'setting_section_4'
        );

    }


    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input)
    {
        $new_input = [];

        if (isset($input['ikros_api_key'])) {
            $new_input['ikros_api_key'] = sanitize_text_field($input['ikros_api_key']);
        }
        if (isset($input['sender_name'])) {
            $new_input['sender_name'] = sanitize_text_field($input['sender_name']);
        }
        if (isset($input['sender_registration_id'])) {
            $new_input['sender_registration_id'] = sanitize_text_field($input['sender_registration_id']);
        }
        if (isset($input['sender_registration_court'])) {
            $new_input['sender_registration_court'] = sanitize_text_field($input['sender_registration_court']);
        }
        if (isset($input['sender_vat_id'])) {
            $new_input['sender_vat_id'] = sanitize_text_field($input['sender_vat_id']);
        }
        if (isset($input['sender_tax_id'])) {
            $new_input['sender_tax_id'] = sanitize_text_field($input['sender_tax_id']);
        }
        if (isset($input['sender_street'])) {
            $new_input['sender_street'] = sanitize_text_field($input['sender_street']);
        }
        if (isset($input['sender_postcode'])) {
            $new_input['sender_postcode'] = sanitize_text_field($input['sender_postcode']);
        }
        if (isset($input['sender_town'])) {
            $new_input['sender_town'] = sanitize_text_field($input['sender_town']);
        }
        if (isset($input['sender_country'])) {
            $new_input['sender_country'] = sanitize_text_field($input['sender_country']);
        }
        if (isset($input['sender_bank_acc'])) {
            $new_input['sender_bank_acc'] = sanitize_text_field($input['sender_bank_acc']);
        }
        if (isset($input['sender_bank_iban'])) {
            $new_input['sender_bank_iban'] = sanitize_text_field($input['sender_bank_iban']);
        }
        if (isset($input['sender_bank_swift'])) {
            $new_input['sender_bank_swift'] = sanitize_text_field($input['sender_bank_swift']);
        }
        if (isset($input['sender_contact_name'])) {
            $new_input['sender_contact_name'] = sanitize_text_field($input['sender_contact_name']);
        }
        if (isset($input['sender_phone'])) {
            $new_input['sender_phone'] = sanitize_text_field($input['sender_phone']);
        }
        if (isset($input['sender_email'])) {
            $new_input['sender_email'] = sanitize_text_field($input['sender_email']);
        }
        if (isset($input['sender_web'])) {
            $new_input['sender_web'] = sanitize_text_field($input['sender_web']);
        }
//        if (isset($input['tax'])) {
//            $new_input['tax'] = sanitize_text_field($input['tax']);
//        }
        if (isset($input['plus_due_date'])) {
            $new_input['plus_due_date'] = sanitize_text_field($input['plus_due_date']);
        }

        if (isset($input['due_action'])) {
            $new_input['due_action'] = sanitize_text_field($input['due_action']);
        }
        if (isset($input['auto_generation'])) {
            $new_input['auto_generation'] = sanitize_text_field($input['auto_generation']);
        }

        if (isset($input['add_mail'])) {
            $new_input['add_mail'] = sanitize_text_field($input['add_mail']);
        }

        if (isset($input['opening_text'])) {
            $new_input['opening_text'] = sanitize_text_field($input['opening_text']);
        }
        if (isset($input['closing_text'])) {
            $new_input['closing_text'] = sanitize_text_field($input['closing_text']);
        }
        if (isset($input['invoice_numbering_type'])) {
            $new_input['invoice_numbering_type'] = sanitize_text_field($input['invoice_numbering_type']);
        }
        if (isset($input['invoice_number_format'])) {
            $new_input['invoice_number_format'] = sanitize_text_field($input['invoice_number_format']);
        }
        if (isset($input['next_invoice_number'])) {
            $new_input['next_invoice_number'] = sanitize_text_field($input['next_invoice_number']);
        }
        if (isset($input['ikros_invoice_numbering_list'])) {
            $new_input['ikros_invoice_numbering_list'] = sanitize_text_field($input['ikros_invoice_numbering_list']);
        }
        if (isset($input['invoice_number_format_pre'])) {
            $new_input['invoice_number_format_pre'] = sanitize_text_field($input['invoice_number_format_pre']);
        }
        if (isset($input['invoice_item_description'])) {
            $new_input['invoice_item_description'] = sanitize_text_field($input['invoice_item_description']);
        }
        if (isset($input['variable_symbol_type'])) {
            $new_input['variable_symbol_type'] = sanitize_text_field($input['variable_symbol_type']);
        }
        if (isset($input['invoice_measureType'])) {
            $new_input['invoice_measureType'] = sanitize_text_field($input['invoice_measureType']);
        }
        if (isset($input['invoice_sku'])) {
            $new_input['invoice_sku'] = sanitize_text_field($input['invoice_sku']);
        }
        if (isset($input['invoice_tax'])) {
            if (is_numeric($input['invoice_tax'])) {
                $new_input['invoice_tax'] = sanitize_text_field($input['invoice_tax']);
            }
        }

        if (isset($input['ikros_invoice_numbering_list']) && $input['ikros_invoice_numbering_list']) {
            $new_input['ikros_invoice_numbering_list'] = [];
            foreach ($input['ikros_invoice_numbering_list'] as $countryCode => $val) {
                $new_input['ikros_invoice_numbering_list'][$countryCode] = sanitize_text_field($val);
            }
        }

        if (isset($input['ico_company_field'])) {
            $new_input['ico_company_field'] = sanitize_text_field($input['ico_company_field']);
        }
        if (isset($input['dic_company_field'])) {
            $new_input['dic_company_field'] = sanitize_text_field($input['dic_company_field']);
        }

        if (isset($input['icdph_company_field'])) {
            $new_input['icdph_company_field'] = sanitize_text_field($input['icdph_company_field']);
        }

        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print __('Enter your settings below:', 'ikros-invoices');
    }

    public function print_customer_section_info(){
        print __('To display additional data of the customer\'s company on the invoice', 'ikros-invoices');
        print '<br>';
        print __('Enter names of fields as set in database or contact your programmer', 'ikros-invoices');
    }

    public function ikros_api_key_callback()
    {
        printf(
            '<input type="text" id="ikros_api_key" name="ikros_options[ikros_api_key]" value="%s" />',
            isset($this->options['ikros_api_key']) ? esc_attr($this->options['ikros_api_key']) : ''
        );
        ?>
        <p class="info">
            <?= __('Your ikros API key from <a href="https://app.ikros.sk" title="" target="_blank">https://app.ikros.sk</a>', 'ikros-invoices') ?>
        </p>
        <?php

    }

    public function sender_name_callback()
    {
        printf(
            '<input type="text" id="sender_name" name="ikros_options[sender_name]" value="%s" />',
            isset($this->options['sender_name']) ? esc_attr($this->options['sender_name']) : ''
        );
    }

    public function sender_registration_id_callback()
    {

        printf(
            '<input type="text" id="sender_registration_id" name="ikros_options[sender_registration_id]" value="%s" />',
            isset($this->options['sender_registration_id']) ? esc_attr($this->options['sender_registration_id']) : ''
        );
    }

    public function sender_registration_court_callback()
    {
        printf(
            '<input type="text" id="sender_registration_court" name="ikros_options[sender_registration_court]" value="%s" />',
            isset($this->options['sender_registration_court']) ? esc_attr($this->options['sender_registration_court']) : ''
        );
    }

    public function sender_vat_id_callback()
    {
        printf(
            '<input type="text" id="sender_vat_id" name="ikros_options[sender_vat_id]" value="%s" />',
            isset($this->options['sender_vat_id']) ? esc_attr($this->options['sender_vat_id']) : ''
        );
    }

    public function sender_tax_id_callback()
    {
        printf(
            '<input type="text" id="sender_tax_id" name="ikros_options[sender_tax_id]" value="%s" />',
            isset($this->options['sender_tax_id']) ? esc_attr($this->options['sender_tax_id']) : ''
        );
    }

    public function sender_street_callback()
    {
        printf(
            '<input type="text" id="sender_street" name="ikros_options[sender_street]" value="%s" />',
            isset($this->options['sender_street']) ? esc_attr($this->options['sender_street']) : ''
        );
    }

    public function sender_postcode_callback()
    {
        printf(
            '<input type="text" id="sender_postcode" name="ikros_options[sender_postcode]" value="%s" />',
            isset($this->options['sender_postcode']) ? esc_attr($this->options['sender_postcode']) : ''
        );
    }

    public function sender_town_callback()
    {
        printf(
            '<input type="text" id="sender_town" name="ikros_options[sender_town]" value="%s" />',
            isset($this->options['sender_town']) ? esc_attr($this->options['sender_town']) : ''
        );
    }

    public function sender_country_callback()
    {
        printf(
            '<input type="text" id="sender_country" name="ikros_options[sender_country]" value="%s" />',
            isset($this->options['sender_country']) ? esc_attr($this->options['sender_country']) : ''
        );
    }

    public function sender_bank_acc_callback()
    {
        printf(
            '<input type="text" id="sender_bank_acc" name="ikros_options[sender_bank_acc]" value="%s" />',
            isset($this->options['sender_bank_acc']) ? esc_attr($this->options['sender_bank_acc']) : ''
        );
    }

    public function sender_bank_iban_callback()
    {
        printf(
            '<input type="text" id="sender_bank_iban" name="ikros_options[sender_bank_iban]" value="%s" />',
            isset($this->options['sender_bank_iban']) ? esc_attr($this->options['sender_bank_iban']) : ''
        );
    }

    public function sender_bank_swift_callback()
    {
        printf(
            '<input type="text" id="sender_bank_swift" name="ikros_options[sender_bank_swift]" value="%s" />',
            isset($this->options['sender_bank_swift']) ? esc_attr($this->options['sender_bank_swift']) : ''
        );
    }

    public function sender_contact_name_callback()
    {
        printf(
            '<input type="text" id="sender_contact_name" name="ikros_options[sender_contact_name]" value="%s" />',
            isset($this->options['sender_contact_name']) ? esc_attr($this->options['sender_contact_name']) : ''
        );
    }

    public function sender_phone_callback()
    {
        printf(
            '<input type="text" id="sender_phone" name="ikros_options[sender_phone]" value="%s" />',
            isset($this->options['sender_phone']) ? esc_attr($this->options['sender_phone']) : ''
        );
    }

    public function sender_email_callback()
    {
        printf(
            '<input type="text" id="sender_email" name="ikros_options[sender_email]" value="%s" />',
            isset($this->options['sender_email']) ? esc_attr($this->options['sender_email']) : ''
        );
    }

    public function sender_web_callback()
    {
        printf(
            '<input type="text" id="sender_web" name="ikros_options[sender_web]" value="%s" />',
            isset($this->options['sender_web']) ? esc_attr($this->options['sender_web']) : ''
        );
    }

    public function invoice_measureType_callback()
    {
        if (!isset($this->options['invoice_measureType'])) $this->options['invoice_measureType'] = 'ks';
        printf(
            '<input type="text" id="invoice_measureType" name="ikros_options[invoice_measureType]" value="%s" />',
            isset($this->options['invoice_measureType']) ? esc_attr($this->options['invoice_measureType']) : ''
        );
        ?>
        <p class="info">
            <?= __('Zobraziť počet ks, v Nemčine napr stk. Ak predávate napríklad len v Nemecku alebo Rakúsku, tak použijete stk.', 'ikros-invoices') ?>
        </p>
        <?php

    }
    public function invoice_sku_callback()
    {

        ?>

        <input type="checkbox" name="ikros_options[invoice_sku]"
               value="checked" <?php
        if (isset($this->options['invoice_sku'])) echo ($this->options['invoice_sku'] == 'checked' ? 'checked' : ''); ?>>
        <label><?= __('', 'ikros-invoices') ?></label>
        <br>


        <?php


    }


    public function invoice_tax_callback()
    {
        printf(
            '<input type="text" id="invoice_tax" name="ikros_options[invoice_tax]" value="%s" />',
            isset($this->options['invoice_tax']) ? esc_attr($this->options['invoice_tax']) : ''
        );
        ?>
        <p class="info">
            <?= __('Leave empty to use default woocommerce taxation settings.', 'ikros-invoices') ?>
        </p>
        <?php

    }

//    public function tax_callback()
//    {
//        printf(
//            '<input type="number" id="tax" name="ikros_options[tax]" value="%d" />',
//            isset($this->options['tax']) ? esc_attr($this->options['tax']) : ''
//        );
//    }

    public function plus_due_date_callback()
    {
        printf(
            '<input type="number" id="plus_due_date" name="ikros_options[plus_due_date]" value="%d" min="0" step="1" />',
            isset($this->options['plus_due_date']) ? esc_attr($this->options['plus_due_date']) : 1
        );
        ?>
        <p class="info">
            <?= __('Number of days to be added as due date', 'ikros-invoices') ?>
        </p>
        <?php

    }


    public function ico_company_field_callback()
    {
        printf(
            '<input type="text" id="ico_company_field" name="ikros_options[ico_company_field]" value="%s" />',
            isset($this->options['ico_company_field']) ? esc_attr($this->options['ico_company_field']) : 'billing_company_ico'
        );
        ?>
        <p class="info">
            <?= __('Customer ICO field in database', 'ikros-invoices') ?>
        </p>
        <?php
    }

    public function dic_company_field_callback()
    {
        printf(
            '<input type="text" id="dic_company_field" name="ikros_options[dic_company_field]" value="%s" />',
            isset($this->options['dic_company_field']) ? esc_attr($this->options['dic_company_field']) :  'billing_company_dic'
        );
        ?>
        <p class="info">
            <?= __('Customer DIC field in database', 'ikros-invoices') ?>
        </p>
        <?php
    }



    public function icdph_company_field_callback()
    {
        printf(
            '<input type="text" id="icdph_company_field" name="ikros_options[icdph_company_field]" value="%s" />',
            isset($this->options['icdph_company_field']) ? esc_attr($this->options['icdph_company_field']) : 'billing_company_ic_dph'
        );
        ?>
        <p class="info">
            <?= __('Customer IC DPH field in database', 'ikros-invoices') ?>
        </p>
        <?php
    }


    public function due_action_callback()
    {

        echo '<select name="ikros_options[due_action]">';

        $order_statuses = $this->get_order_statuses();

        foreach ($order_statuses as $key => $order_status) {


            if (isset($this->options['due_action'])) {

                printf(
                    '<option id="due_action" value="%s" %s >%s</option>',
                    isset($this->options['due_action']) && $this->options['due_action'] == $key ? esc_attr($this->options['due_action']) : $key,
                    selected(($key), $this->options['due_action'], false),
                    isset($this->options['due_action']) && $this->options['due_action'] == $key ? $order_status : $order_status
                );

            } else {

                echo '<option id="due_action" value="' . $order_status . '"  >' . $order_status . '</option>';


            }

        }

        echo '</select>';

        ?>
        <p class="info">
            <?= __('When should be invoice automatically generated', 'ikros-invoices') ?>
        </p>
        <?php

    }

    public function auto_generation_callback()
    {
        ?>
        <input type="checkbox" name="ikros_options[auto_generation]"
               value="checked" <?= isset( $this->options['auto_generation']) && $this->options['auto_generation'] == 'checked' ? 'checked' : '' ?>>
        <label><?= __('Generate invoice after status change', 'ikros-invoices') ?></label>
        <br>
        <?php
    }

    public function add_mail_callback()
    {
        ?>
        <input type="checkbox" name="ikros_options[add_mail]"
               value="checked" <?= isset($this->options['add_mail']) && $this->options['add_mail'] == 'checked' ? 'checked' : '' ?>>
        <label><?= __('Zapnúť odosielanie faktúry v emaili pri stave Vybavená', 'ikros-invoices') ?></label>
        <br/>
        <?php
    }


    public function opening_text_callback()
    {
        printf(
            '<textarea rows="2" cols="30" id="opening_text" name="ikros_options[opening_text]">%s</textarea>',
            isset($this->options['opening_text']) ? esc_attr($this->options['opening_text']) : ''
        );
    }

    public function closing_text_callback()
    {
        printf(
            '<textarea rows="2" cols="30" type="textarea" id="closing_text" name="ikros_options[closing_text]" >%s</textarea>',
            isset($this->options['closing_text']) ? esc_attr($this->options['closing_text']) : ''
        );
    }

//    public function additional_fees_name_callback()
//    {
//        printf(
//            '<input type="text" id="additional_fees_name" name="ikros_options[additional_fees_name]" value="%s" />',
//            isset($this->options['additional_fees_name']) ? esc_attr($this->options['additional_fees_name']) : ''
//        );
//    }

//    public function additional_fees_description_callback()
//    {
//        printf(
//            '<input type="text" id="additional_fees_description" name="ikros_options[additional_fees_description]" value="%s" />',
//            isset($this->options['additional_fees_description']) ? esc_attr($this->options['additional_fees_description']) : ''
//        );
//    }

//    public function additional_fees_vat_rate_callback()
//    {
//        printf(
//            '<input type="number" id="additional_fees_vat_rate" name="ikros_options[additional_fees_vat_rate]" value="%s" />',
//            isset($this->options['additional_fees_vat_rate']) ? esc_attr($this->options['additional_fees_vat_rate']) : ''
//        );
//    }

    public function variable_symbol_type_callback()
    {
        ?>

        <input type="radio" name="ikros_options[variable_symbol_type]"
               value="invoice" <?= $this->options['variable_symbol_type'] == 'invoice' ? 'checked' : '' ?>>
        <label><?= __('Invoice number', 'ikros-invoices') ?></label>
        <br>

        <input type="radio" name="ikros_options[variable_symbol_type]"
               value="order" <?= $this->options['variable_symbol_type'] == 'order' ? 'checked' : '' ?>>
        <label><?= __('Order number', 'ikros-invoices') ?></label>
        <br>

        <?php
    }

    public function invoice_numbering_type_callback()
    {
        ?>

        <input type="radio" name="ikros_options[invoice_numbering_type]"
               value="ikros" <?= $this->options['invoice_numbering_type'] == 'ikros' ? 'checked' : '' ?>>
        <label><?= __('Ikros numbering (preferred)', 'ikros-invoices') ?></label>
        <br>

        <input type="radio" name="ikros_options[invoice_numbering_type]"
               value="plugin" <?= $this->options['invoice_numbering_type'] == 'plugin' ? 'checked' : '' ?>>
        <label><?= __('Custom plugin numbering', 'ikros-invoices') ?></label>
        <br>

        <?php

    }

    public function invoice_number_format_callback()
    {
        ?>

        <?php
        printf(
            '<input type="text" id="invoice_number_format_pre" name="ikros_options[invoice_number_format_pre]" value="%s" />',
            isset($this->options['invoice_number_format_pre']) ? esc_attr($this->options['invoice_number_format_pre']) : ''
        );

        echo '<select id="invoice_number_format" name="ikros_options[invoice_number_format]">';
        echo '<option value="RRRRXXXX" ' . ($this->options["invoice_number_format"] == "RRRRXXXX" ? "selected" : "") . '>RRRRXXXX</option>';
        echo '<option value="RRRRMMXXXX" ' . ($this->options["invoice_number_format"] == "RRRRMMXXXX" ? "selected" : "") . '>RRRRMMXXXX</option>';
        echo '<option value="RRMMDDXXXX" ' . ($this->options["invoice_number_format"] == "RRMMDDXXXX" ? "selected" : "") . '>RRMMDDXXXX</option>';
        echo '<option value="XXXXRRRRMM" ' . ($this->options["invoice_number_format"] == "XXXXRRRRMM" ? "selected" : "") . '>XXXXRRRRMM</option>';
        echo '</select>';
        ?>
        <p class="info">
            <?= __('Format of invoices numbering, first field is prefix (leave blank for no prefix), second field is format of the invoice number', 'ikros-invoices') ?>
        </p>

        <?php

    }

    public function invoice_item_description_callback()
    {
        ?>
        <input type="radio" name="ikros_options[invoice_item_description]"
               value="variation_info" <?= $this->options["invoice_item_description"] === 'variation_info' ? 'checked="checked"' : '' ?>>
        <label><?= __('Variation parameters', 'ikros-invoices') ?></label>
        <br>

        <input type="radio" name="ikros_options[invoice_item_description]"
               value="description" <?= $this->options["invoice_item_description"] === 'description' ? 'checked="checked"' : '' ?>>
        <label><?= __('Product description', 'ikros-invoices') ?></label>
        <br>


        <input type="radio" name="ikros_options[invoice_item_description]"
               value="empty" <?= $this->options["invoice_item_description"] === 'empty' ? 'checked="checked"' : '' ?>>
        <label><?= __('Empty', 'ikros-invoices') ?></label>
        <p class="info">
            <?= __('Item description displayed in the invoice', 'ikros-invoices') ?>
        </p>
        <?php

    }

    public function next_invoice_number_callback()
    {
        printf(
            '<input type="number" id="next_invoice_number" name="ikros_options[next_invoice_number]" value="%s" />',
            isset($this->options['next_invoice_number']) ? esc_attr($this->options['next_invoice_number']) : ''
        );
        ?>
        <p class="info">
            <?= __('Number of next invoice to be generated (use only number, not format. If next invoice should be in format RRRRXXXX 2020025 type in just "25")',
                'ikros-invoices') ?>
        </p>
        <p class="info">
            <span class="warning"><?= __('If there is existing invoice with specified number in Ikros allready, it will be overwritten!', 'ikros-invoices') ?></span>
        </p>
        <?php

    }

    public function ikros_invoice_numbering_list_callback()
    {
        $countries = WC()->countries->get_allowed_countries();
        if ($countries) {
            foreach ($countries as $countryCode => $countryName) {
                ?>
                <div>
                    <label for="ikros_invoice_numbering_list_<?= $countryCode ?>"><?= $countryName ?> : </label>
                    <?php
                    printf(
                        '<input type="text" id="ikros_invoice_numbering_list_' . $countryCode . '" name="ikros_options[ikros_invoice_numbering_list][' . $countryCode . ']" value="%s" />',
                        isset($this->options['ikros_invoice_numbering_list'][$countryCode]) ? esc_attr($this->options['ikros_invoice_numbering_list'][$countryCode]) : 'OF'
                    );
                    ?>
                </div>
                <?php

            }
        }

        ?>
        <p class="info">
            <?= __('Assign number list to each country, leave default value to use main invoicing list', 'ikros-invoices') ?>
        </p>
        <?php

    }

    public function get_order_statuses()
    {
        if (function_exists('wc_order_status_manager_get_order_status_posts')) // plugin WooCommerce Order Status Manager
        {
            $wc_order_statuses = array_reduce(
                wc_order_status_manager_get_order_status_posts(),
                function ($result, $item) {
                    $result[$item->post_name] = $item->post_title;
                    return $result;
                },
                []
            );

            return $wc_order_statuses;
        }

        if (function_exists('wc_get_order_statuses')) {
            $wc_get_order_statuses = wc_get_order_statuses();

            return $this->alter_wc_statuses($wc_get_order_statuses);
        }

        $order_status_terms = get_terms('shop_order_status', 'hide_empty=0');

        $shop_order_statuses = [];
        if (!is_wp_error($order_status_terms)) {
            foreach ($order_status_terms as $term) {
                $shop_order_statuses[$term->slug] = $term->name;
            }
        }

        return $shop_order_statuses;
    }

    function alter_wc_statuses($array)
    {
        $new_array = [];
        foreach ($array as $key => $value) {
            $new_array[substr($key, 3)] = $value;
        }

        return $new_array;
    }

}


?>