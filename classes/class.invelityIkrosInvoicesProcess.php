<?php

require_once('class.invelityIkrosInvoice.php');

class InvelityIkrosInvoicesProcess
{
    private $launcher;
    private $options;
    private $sucesfull = array();
    private $unsucesfull = array();

    /**
     * Loads plugin textdomain and sets the options attribute from database
     */
    public function __construct(InvelityIkrosInvoices $launcher)
    {
        $this->launcher = $launcher;
        load_plugin_textdomain($this->launcher->getPluginSlug(), false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->options = get_option('ikros_options');
        $this->initializeOrderTracking();


        if (isset(get_option('ikros_options')['auto_generation']) && get_option('ikros_options')['auto_generation'] == 'checked') {

            if (isset(get_option('ikros_options')['due_action'])) {

                //add_action('admin_init', array($this, 'generateInvoiceOnStatusChange'));
                $this->generateInvoiceOnStatusChange();


            }
        }

    }

    public function generateInvoiceOnStatusChange()
    {

        add_action('woocommerce_order_status_' . strtolower(get_option('ikros_options')['due_action']), array(&$this, 'autoGenerateInvoice'), 5);

    }

    public function autoGenerateInvoice($order_id)
    {

        $ikrosInvoice = new InvelityIkrosInvoice($order_id, $this->launcher);

        if (!$ikrosInvoice->getSuccess()) {
            $this->unsucesfull[] = $ikrosInvoice->getResultArray();

        }
        if (!$ikrosInvoice->sendInvoice()) {
            $this->unsucesfull[] = $ikrosInvoice->getResultArray();

        }
        if (!$ikrosInvoice->verifyResult()) {
            $this->unsucesfull[] = $ikrosInvoice->getResultArray();

        }
        if (!$ikrosInvoice->saveLocalInvoiceData()) {
            $this->unsucesfull[] = $ikrosInvoice->getResultArray();

        }
        $this->sucesfull[] = $ikrosInvoice->getResultArray();


    }

    /**
     * Sets up actions for hooks
     */
    function initializeOrderTracking()
    {
//        add_action('woocommerce_checkout_order_processed', array($this, 'custom_process_order'));
        add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
        add_action('load-edit.php', array(&$this, 'custom_bulk_action'));
        add_action('admin_notices', array(&$this, 'custom_bulk_admin_notices'));


    }

    /**
     * @deprecated
     * Method is used if invoice is created at time of order creation
     * Not used in this version, we are using manual invoice generating
     *
     * @param $order_id
     *
     */
    function custom_process_order($order_id)
    {
        $order = new WC_Order($order_id);
        $orderJson = $this->prepareOrderJson($order);
        $this->sendData($orderJson);
    }

    /**
     * Adds option to export invoices to orders page bulk select
     */
    function custom_bulk_admin_footer()
    {
        global $post_type;

        if ($post_type == 'shop_order') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery('<option>').val('invoice_export').text('<?php _e('Export orders to Ikros', $this->launcher->getPluginSlug()) ?>').appendTo("select[name='action']");
                    jQuery('<option>').val('invoice_export').text('<?php _e('Export orders to Ikros', $this->launcher->getPluginSlug()) ?>').appendTo("select[name='action2']");
                });
            </script>
            <?php

        }
    }

    /**
     * Sets up action to be taken after export option is selected
     * If export is selected, provides export and refreshes page
     * After refresh, notices are shown
     */
    function custom_bulk_action()
    {
        global $typenow;
        $post_type = $typenow;

        if ($post_type == 'shop_order') {
            $wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
            $action = $wp_list_table->current_action();
            $allowed_actions = array("invoice_export");
            if (!in_array($action, $allowed_actions)) {
                return;
            }
            check_admin_referer('bulk-posts');
            if (isset($_REQUEST['post'])) {
                $post_ids = array_map('intval', $_REQUEST['post']);
            }
            if (empty($post_ids)) {
                return;
            }
            $sendback = remove_query_arg(array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer());
            if (!$sendback) {
                $sendback = admin_url("edit.php?post_type=$post_type");
            }
            $pagenum = $wp_list_table->get_pagenum();
            $sendback = add_query_arg('paged', $pagenum, $sendback);

            switch ($action) {
                case 'invoice_export':
                    foreach ($post_ids as $post_id) {
                        $ikrosInvoice = new InvelityIkrosInvoice($post_id, $this->launcher);
                        if (!$ikrosInvoice->getSuccess()) {
                            $this->unsucesfull[] = $ikrosInvoice->getResultArray();
                            continue;
                        }
                        if (!$ikrosInvoice->sendInvoice()) {
                            $this->unsucesfull[] = $ikrosInvoice->getResultArray();
                            continue;
                        }
                        if (!$ikrosInvoice->verifyResult()) {
                            $this->unsucesfull[] = $ikrosInvoice->getResultArray();
                            continue;
                        }
                        if (!$ikrosInvoice->saveLocalInvoiceData()) {
                            $this->unsucesfull[] = $ikrosInvoice->getResultArray();
                            continue;
                        }
                        $this->sucesfull[] = $ikrosInvoice->getResultArray();
                    }
                    break;
            }
            $sucessfull = urlencode(serialize($this->sucesfull));
            $unsucessfull = urlencode(serialize($this->unsucesfull));
            $sendback = add_query_arg(array('ikros-sucessfull' => $sucessfull, 'ikros-unsucessfull' => $unsucessfull), $sendback);
            wp_redirect($sendback);
            exit();
        }

    }

    /**
     * Dsisplays the notice
     */
    function custom_bulk_admin_notices()
    {
        global $post_type, $pagenow;

        if ($pagenow == 'edit.php' && $post_type == 'shop_order' && (isset($_REQUEST['ikros-sucessfull']) || isset($_REQUEST['ikros-unsucessfull']))) {
            $sucessfull = unserialize(str_replace('\\', '', urldecode($_REQUEST['ikros-sucessfull'])));
            $unsucessfull = unserialize(str_replace('\\', '', urldecode($_REQUEST['ikros-unsucessfull'])));
            if (count($sucessfull) != 0) {
                echo "<div class=\"updated\">";
                foreach ($sucessfull as $message) {
                    $messageContent = sprintf(__('Order no. %s Sucessfully generated', $this->launcher->getPluginSlug()), $message['orderId']);
                    echo "<p>{$messageContent}</p>";
                }
                echo "</div>";
            }
            if (count($unsucessfull) != 0) {
                echo "<div class=\"error\">";
                foreach ($unsucessfull as $message) {
                    $messageContent = sprintf(__('Order no. %s Was not generated. Error : %s', $this->launcher->getPluginSlug()), $message['orderId'], $message['message']);
                    echo "<p>{$messageContent}</p>";
                }
                echo "</div>";
            }
        }
    }

    private function prepareOrderJson($order)
    {
        $ikrosOrder = array_merge(
            $this->prepareClientData($order),
            $this->prepareSenderData($order),
            $this->preparePriceData($order),
            $this->prepareAdditionalData($order)
        );
        $ikrosOrder['items'] = $this->prepareItemData($order);


        $ikrosOrderJson = json_encode($ikrosOrder, JSON_UNESCAPED_UNICODE);
        return $ikrosOrderJson;
    }


}
