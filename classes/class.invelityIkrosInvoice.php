<?php

class InvelityIkrosInvoice
{

    private $launcher;
    private $wcOrderId = null;
    private $wcOrder = null;
    private $message = '';
    private $success = true;
    private $options = null;
    private $response = null;
    private $highestVatFound = 0;

    private $invoiceData = [];
    private $senderData = [];
    private $clientBillingData = [];
    private $clientShippingData = [];
    private $priceData = [];
    private $itemsData = [];

    public function __construct($orderId, InvelityIkrosInvoices $launcher)
    {
        $this->launcher = $launcher;
        $this->options = get_option('ikros_options');
        if (!$orderId) {
            $this->success = false;
            $this->message = __("Order id not valid. Order Id : ", $this->launcher->getPluginSlug()) . $orderId;
        }
        $this->wcOrderId = $orderId;
        $this->wcOrder = new WC_Order($orderId);
        if (!$this->wcOrder) {
            $this->success = false;
            $this->message = __('Could not instantiate Woocommerce order. Order Id : ', $this->launcher->getPluginSlug()) . $orderId;
        }

        $this->prepareInvoiceData();
        $this->prepareSenderData();
        $this->prepareClientData();
        $this->prepareClientShippingData();
        $this->preparePriceData();
        $this->prepareItemData();
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getSuccess()
    {
        return $this->success;
    }

    public function getResultArray()
    {
        return [
            'orderId' => $this->wcOrderId,
            'message' => $this->message,
        ];
    }


    private function prepareInvoiceData()
    {
        $order = $this->wcOrder;
        $shippingMethod = $order->get_shipping_method();

        if ($this->options['variable_symbol_type'] == 'order') {
            $variableSymbol = $order->get_order_number();
        } elseif ($this->options['variable_symbol_type'] == 'invoice') {
            $variableSymbol = '';
        } else {
            $variableSymbol = $order->get_order_number();
        }


        $ikrosOrder = [
            'createDate' => date('Y-m-d H:i:s'),
            'dueDate' => date('Y-m-d H:i:s', strtotime("+ " . $this->options['plus_due_date'] . " days")),
            'completionDate' => null,
            'variableSymbol' => $variableSymbol,
            'openingText' => $this->options['opening_text'],
            'closingText' => $this->options['closing_text'],
            'paymentType' => mb_substr($order->payment_method_title, 0, 50),
            'deliveryType' => mb_substr($shippingMethod, 0, 50),
            'orderNumber' => $order->get_order_number(),
            'clientNote' => $order->customer_note,
        ];


        $documentNo = '';
        $numberingSequence = '';
        if (get_post_meta($this->wcOrderId, 'invoiceNumber')) { //UPDATE
            $documentNo = get_post_meta($this->wcOrderId, 'invoiceNumber', true);
            if ($this->options['invoice_numbering_type'] == 'ikros') {
                if (method_exists($this->wcOrder, 'get_billing_country')) {
                    if (isset($this->options['ikros_invoice_numbering_list'][$this->wcOrder->get_billing_country()])) {
                        $numberingSequence = $this->options['ikros_invoice_numbering_list'][$this->wcOrder->get_billing_country()];
                    }
                } else {
                    if (isset($this->options['ikros_invoice_numbering_list'][$this->wcOrder->billing_country])) {
                        $numberingSequence = $this->options['ikros_invoice_numbering_list'][$this->wcOrder->billing_country];
                    }
                }
            }
        } else { //CREATE
            if ($this->options['invoice_numbering_type'] == 'plugin') {
                $documentNo = $this->generateDocumentNo();
            } else {
                if ($this->options['invoice_numbering_type'] == 'ikros') {
                    if (method_exists($this->wcOrder, 'get_billing_country')) {
                        if (isset($this->options['ikros_invoice_numbering_list'][$this->wcOrder->get_billing_country()])) {
                            $numberingSequence = $this->options['ikros_invoice_numbering_list'][$this->wcOrder->get_billing_country()];
                        }
                    } else {
                        if (isset($this->options['ikros_invoice_numbering_list'][$this->wcOrder->billing_country])) {
                            $numberingSequence = $this->options['ikros_invoice_numbering_list'][$this->wcOrder->billing_country];
                        }
                    }
                }
            }
        }

        $ikrosOrder += [
            'documentNumber' => $documentNo,
            'numberingSequence' => $numberingSequence,
        ];
        $this->invoiceData = $ikrosOrder;
    }

    private function generateDocumentNo()
    {
        $documentNo = $this->options['invoice_number_format_pre'];
        switch ($this->options['invoice_number_format']) {
            case 'RRRRXXXX':
                $documentNo .= date("Y");
                $documentNo .= str_pad($this->options['next_invoice_number'], 4, '0', STR_PAD_LEFT);
                break;
            case 'RRMMDDXXXX':
                $documentNo .= date('ymd');
                $documentNo .= str_pad($this->options['next_invoice_number'], 4, '0', STR_PAD_LEFT);
                break;
            case 'XXXXRRRRMM':
                $documentNo .= str_pad($this->options['next_invoice_number'], 4, '0', STR_PAD_LEFT);
                $documentNo .= date('Ym');
                break;
            case 'RRRRMMXXXX':
                $documentNo .= date('Ym');
                $documentNo .= str_pad($this->options['next_invoice_number'], 4, '0', STR_PAD_LEFT);
                break;
        }
        return $documentNo;
    }

    private function prepareSenderData()
    {
        $senderData = [
            'senderName' => $this->options['sender_name'],
            'senderRegistrationId' => $this->options['sender_registration_id'],
            'senderRegistrationCourt' => $this->options['sender_registration_court'],
            'senderVatId' => $this->options['sender_vat_id'],
            'senderTaxId' => $this->options['sender_tax_id'],
            'senderStreet' => $this->options['sender_street'],
            'senderPostCode' => $this->options['sender_postcode'],
            'senderTown' => $this->options['sender_town'],
            'senderRegion' => null,
            'senderCountry' => $this->options['sender_country'],
            'senderBankAccount' => $this->options['sender_bank_acc'],
            'senderBankIban' => $this->options['sender_bank_iban'],
            'senderBankSwift' => $this->options['sender_bank_swift'],
            'senderContactName' => $this->options['sender_contact_name'],
            'senderPhone' => $this->options['sender_phone'],
            'senderEmail' => $this->options['sender_email'],
            'senderWeb' => $this->options['sender_web'],
        ];
        $this->senderData = $senderData;
    }

    private function prepareClientData()
    {
        $order = $this->wcOrder;

        $clientCompanyInfo = $this->prepaceClientCompanyInfo();

        $clientBillingData = [
            'clientName' => $order->billing_company != '' ? $order->billing_company : $order->billing_first_name . " " . $order->billing_last_name,
            'clientContact' => $order->billing_first_name . " " . $order->billing_last_name,
            'clientContactName' => $order->billing_first_name,
            'clientContactSurname' => $order->billing_last_name,
            'clientStreet' => ($order->billing_address_1 != "" ? $order->billing_address_1 : "") . ($order->billing_address_2 != "" ? " " . $order->billing_address_2 : ""),
            'clientPostCode' => $order->billing_postcode,
            'clientTown' => $order->billing_city,
            'clientCountry' => $order->billing_country,
            'clientPhone' => $order->billing_phone,
            'clientEmail' => $order->billing_email,
            'clientRegistrationId' => isset($clientCompanyInfo['ico']) && $clientCompanyInfo['ico'] ? $clientCompanyInfo['ico'] : null,
            'clientTaxId' => isset($clientCompanyInfo['dic']) && $clientCompanyInfo['dic'] ? $clientCompanyInfo['dic'] : null,
            'clientVatId' => isset($clientCompanyInfo['icdph']) && $clientCompanyInfo['icdph'] ? $clientCompanyInfo['icdph'] : null,
            'clientInternalId' => null,
        ];

        $this->clientBillingData = $clientBillingData;



    }

    private function prepaceClientCompanyInfo()
    {
        $order = $this->wcOrder;

        $ico_field = sanitize_text_field($this->options['ico_company_field']);
        $dic_field = sanitize_text_field($this->options['dic_company_field']);
        $icdph_field = sanitize_text_field($this->options['icdph_company_field']);
        $clientCompanyInfo = [];
        if (isset($ico_field) && $ico_field !== '') {
            $ico = get_post_meta($order->get_id(), $ico_field, true);
            if ($ico) {
                $clientCompanyInfo['ico'] = $ico;
            }
        }

        if (isset($dic_field) && $dic_field !== '') {
            $dic = get_post_meta($order->get_id(), $dic_field, true);
            if ($dic) {
                $clientCompanyInfo['dic'] = $dic;
            }
        }

        if (isset($icdph_field) && $icdph_field !== '') {
            $ic_dph = get_post_meta($order->get_id(), $icdph_field, true);
            if ($ic_dph) {
                $clientCompanyInfo['icdph'] = $ic_dph;
            }
        }
        return $clientCompanyInfo;


    }

    private function prepareClientShippingData()
    {
        $order = $this->wcOrder;
        $isDifferent = false;

        if ($order->shipping_company != "" && $order->shipping_company != $order->billing_company) {
            $isDifferent = true;
        }
        if ($order->shipping_first_name != "" && $order->shipping_first_name != $order->billing_first_name) {
            $isDifferent = true;
        }
        if ($order->shipping_last_name != "" && $order->shipping_last_name != $order->billing_last_name) {
            $isDifferent = true;
        }
        if ($order->shipping_phone != "" && $order->shipping_phone != $order->billing_phone) {
            $isDifferent = true;
        }
        if ($order->shipping_address_1 != "" && $order->shipping_address_1 != $order->billing_address_1) {
            $isDifferent = true;
        }
        if ($order->shipping_address_2 != "" && $order->shipping_address_2 != $order->billing_address_2) {
            $isDifferent = true;
        }
        if ($order->shipping_postcode != "" && $order->shipping_postcode != $order->billing_postcode) {
            $isDifferent = true;
        }
        if ($order->shipping_city != "" && $order->shipping_postcode != $order->billing_postcode) {
            $isDifferent = true;
        }
        if ($order->shipping_country != "" && $order->shipping_country != $order->billing_country) {
            $isDifferent = true;
        }


        $clientShippingData = [
            'clientPostalName' => $order->shipping_company != '' ? $order->shipping_company : $order->shipping_first_name . " " . $order->shipping_last_name,
            'clientPostalContact' => $order->shipping_first_name . " $order->shipping_last_name",
            'clientPostalContactName' => $order->shipping_first_name,
            'clientPostalContactSurname' => $order->shipping_last_name,
            'clientPostalPhone' => $order->shipping_phone,
            'clientPostalStreet' => ($order->shipping_address_1 != "" ? $order->shipping_address_1 : "") . ($order->shipping_address_2 != "" ? " " . $order->shipping_address_2 : ""),
            'clientPostalPostCode' => $order->shipping_postcode,
            'clientPostalTown' => $order->shipping_city,
            'clientPostalCountry' => $order->shipping_country,
            'clientHasDifferentPostalAddress' => $isDifferent,
        ];

        $this->clientShippingData = $clientShippingData;
    }

    private function preparePriceData()
    {
        /**Prices are calculated from Items**/
        $order = $this->wcOrder;
        $discountAmount = null;
        $discountPerc = null;


        $priceData = [
            'exchangeRate' => 1,
            'senderIsVatPayer' => true,
            'totalPrice' => null, //totalPrice is calculated in Ikros system from individual products
            'totalPriceWithVat' => null, //totalPriceWithVat is calculated in Ikros system from individual products
            'discountPercent' => 0,
            'discountValue' => $order->get_total_discount(true),
            'discountValueWithVat' => $order->get_total_discount(false),
            'priceDecimalPlaces' => null,
            'deposit' => null,
            'depositText' => null,
            'depositDate' => null,
            'isVatAccordingPayment' => true,
        ];

        if (method_exists($order, 'get_currency')) {
            $currency = $order->get_currency();
        } else {
            $currency = $order->get_order_currency();
        }

        if ($currency) {
            $priceData['currency'] = $currency;
        } else {
            $priceData['currency'] = get_woocommerce_currency();
        }

        $this->priceData = $priceData;
    }

    private function prepareItemData()
    {

        $order = $this->wcOrder;
        $couponCodes = $order->get_used_coupons();
        $coupons = [];
        $items = [];

        foreach ($couponCodes as $couponCode) {
            $coupon = new WC_Coupon($couponCode);
            $coupons[] = $coupon;
        }

        foreach ($order->get_items() as $item) {
            $pf = new WC_Product_Factory();
            if ($item['variation_id'] != "" && $item['variation_id'] != '0') {
                $product = $pf->get_product($item['variation_id']);
            } else {
                $product = $pf->get_product($item['product_id']);
            }

            $productDiscountVal = null;
            $productDiscountPerc = null;
            $couponNames = null;
            foreach ($coupons as $coupon) {
                if (in_array($item['product_id'], $coupon->product_ids)) {
                    if ($coupon->discount_type == 'fixed_product') {
                        $productDiscountVal += $productDiscountVal + $coupon->amount;
                        $couponNames .= $coupon->code . " | ";
                    } elseif ($coupon->discount_type == 'percent_product') {
                        $productDiscountPerc += $productDiscountPerc + $coupon->amount;
                        $couponNames .= $coupon->code . " | ";
                    }
                }
            }

            if ($productDiscountVal && $productDiscountPerc) {
                $productDiscountVal += ($item['line_subtotal'] + $item['line_subtotal_tax']) * ($productDiscountPerc / 100);
            }

            $itemDescriptionType = $this->options['invoice_item_description'];
            $description = '';
            if ($itemDescriptionType == 'description') {
                $description = wp_trim_words(strip_tags(get_post($item['product_id'])->post_content), 10, '...');
            } else {
                if ($itemDescriptionType === 'variation_info') {
                    if ($product && $product->is_type('variation')) {
                        $description = wc_get_formatted_variation($product->get_variation_attributes(), true);
                    } else {
                        $description = wp_trim_words(strip_tags(get_post($item['product_id'])->post_content), 10, '...');
                    }
                }
            }

            $tax_rates = WC_Tax::find_rates(array(
                'tax_class' => $item->get_tax_class(),
                'country' => $order->get_billing_country()
            ));

            $tax_status = $item->get_tax_status();


            if ($tax_rates && $tax_status !== 'shipping' ) {
                $rate = reset($tax_rates)['rate'];
                $fullPrice = $item['line_subtotal'] * (($rate / 100) + 1);
                $lineSubtotalTax = $fullPrice - $item['line_subtotal'];
            } else {
                $lineSubtotalTax = 0;
            }

            $vat = (isset($this->options['invoice_tax']) && $this->options['invoice_tax'])
                ? $this->options['invoice_tax']
                : (round($lineSubtotalTax / $item['line_subtotal'], 2) * 100);
            if ($vat > $this->highestVatFound) {
                $this->highestVatFound = $vat;
            }

            $discountValue = $productDiscountVal / (($lineSubtotalTax + $item['line_subtotal']) / $item['line_subtotal']);

            if (is_nan($vat)) {
                $vat = 0;
            }
            if (is_nan($discountValue)) {
                $discountValue = 0;
            }


            // Get SKU
            if (sanitize_text_field($this->options['invoice_sku'])){
                $sku = " (".$product->get_sku().")";
            } else {
                $sku = '';
            }


            $items[] = [
                'name' => $item['name'].$sku,
                'description' => $description,
                'count' => $item['qty'],
                'measureType' => sanitize_text_field(($this->options['invoice_measureType']) ? ($this->options['invoice_measureType']) : 'ks' ),
                'totalPrice' => $item['line_subtotal'],
                'totalPriceWithVat' => $item['line_subtotal'] + $lineSubtotalTax,
                'unitPrice' => $item['line_subtotal'] / $item['qty'],
                'unitPriceWithVat' => ($lineSubtotalTax + $item['line_subtotal']) / $item['qty'],
                'vat' => $vat,
                'discountName' => $couponNames,
                'discountPercent' => $productDiscountPerc,
                'discountValue' => $discountValue ? $discountValue : null,
                'discountValueWithVat' => $productDiscountVal,
                'productCode' => $product ? $product->get_sku() : '',
                'typeId' => 1,
                'warehouseCode' => null,
                'foreignName' => null,
                'customText' => null,
                'ean' => null,
                'jkpov' => null,
                'plu' => null,
                'numberingSequenceCode' => null,
                'specialAttribute' => null
            ];
        }


        $shipping = $order->get_shipping_methods();
        foreach ($shipping as $shippingMethod) {

            $shippingTotalPrice = (isset($shippingMethod['total']) && $shippingMethod['total']) ? $shippingMethod['total'] : $shippingMethod['cost'][0];
            if (!floatval($shippingTotalPrice)) {
                $shippingVat = $this->highestVatFound;
            } else {
                $shippingVat = (isset($this->options['invoice_tax']) && $this->options['invoice_tax'])
                    ? $this->options['invoice_tax']
                    : (($shippingMethod['total_tax'] && $shippingMethod['total']) ? round($shippingMethod['total_tax'] / $shippingMethod['total'], 2) * 100 : 0);
            }


            $items[] = [
                'name' => $shippingMethod['name'],
                'description' => "",
                'count' => 1,
                'measureType' => sanitize_text_field(($this->options['invoice_measureType']) ? ($this->options['invoice_measureType']) : 'ks' ),
                'totalPrice' => $shippingTotalPrice,
                'totalPriceWithVat' => (isset($shippingMethod['total']) && $shippingMethod['total'] && isset($shippingMethod['total_tax']))
                    ? $shippingMethod['total'] + $shippingMethod['total_tax']
                    : ($shippingMethod['cost']),
                'unitPrice' => (isset($shippingMethod['total']) && $shippingMethod['total']) ? $shippingMethod['total'] : $shippingMethod['cost'][0],
                'unitPriceWithVat' => (isset($shippingMethod['total']) && $shippingMethod['total'] && isset($shippingMethod['total_tax']))
                    ? $shippingMethod['total'] + $shippingMethod['total_tax']
                    : ($shippingMethod['cost']),
                'vat' => $shippingVat,
                'hasDiscount' => false,
                'discountName' => null,
                'discountPercent' => null,
                'discountValue' => null,
                'discountValueWithVat' => null,
                'productCode' => '',
                'typeId' => 1,
                'warehouseCode' => null,
                'foreignName' => null,
                'customText' => null,
                'ean' => null,
                'jkpov' => null,
                'plu' => null,
                'numberingSequenceCode' => null,
                'specialAttribute' => null
            ];
        }


        $fees = $order->get_fees();
        foreach ($fees as $fee) {
            $items[] = [
                'name' => $fee['name'],
                'description' => null,
                'count' => 1,
                'measureType' => sanitize_text_field(($this->options['invoice_measureType']) ? ($this->options['invoice_measureType']) : 'ks' ),
                'totalPrice' => $fee['line_total'],
                'totalPriceWithVat' => $fee['line_total'] + $fee['line_tax'],
                'unitPrice' => $fee['line_total'],
                'unitPriceWithVat' => $fee['line_total'] + $fee['line_tax'],
                'vat' => (isset($this->options['invoice_tax']) && $this->options['invoice_tax'])
                    ? $this->options['invoice_tax']
                    : (round($fee['line_tax'] / $fee['line_total'], 2) * 100),
                'hasDiscount' => false,
                'discountName' => '',
                'discountPercent' => null,
                'discountValue' => null,
                'discountValueWithVat' => null,
                'productCode' => '',
                'typeId' => 1,
                'warehouseCode' => null,
                'foreignName' => null,
                'customText' => null,
                'ean' => null,
                'jkpov' => null,
                'plu' => null,
                'numberingSequenceCode' => null,
                'specialAttribute' => null
            ];

        }

        $this->itemsData['items'] = $items;
    }


    public function sendInvoice()
    {
        $invoiceData = array_merge(
            $this->invoiceData,
            $this->senderData,
            $this->clientBillingData,
            $this->clientShippingData,
            $this->priceData,
            $this->itemsData
        );

        $ikrosOrderJson = json_encode($invoiceData, JSON_UNESCAPED_UNICODE);
        if (!$ikrosOrderJson) {
            ob_start();
            var_dump($invoiceData);
            $data = ob_get_clean();
            $this->success = false;
            $this->message = __('Failed to convert array to json, please contact us at mike@invelity.com with content of this error : ', $this->launcher->getPluginSlug()) . $data;
            return false;
        }

        $request = null;
        $ch = curl_init();
        if ($ch === false) {
            throw new Exception('failed to initialize');
        }

        curl_setopt($ch, CURLOPT_URL, 'https://eshops.inteo.sk/api/v1/invoices/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "[" . $ikrosOrderJson . "]");


        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->options['ikros_api_key']
        ));

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        if (!$response) {
            $this->success = false;
            $this->message = __('Ikros response not valid. Ikros response : ', $this->launcher->getPluginSlug()) . $response;
            return false;
        }
        curl_close($ch);
        $this->response = $response;
        return true;
    }

    public function verifyResult()
    {
        $result = json_decode($this->response);
        if (isset($result->result)) {
            if ($result->result == 1) {
                $this->options['next_invoice_number'] = intval($this->options['next_invoice_number']) + 1;
                update_option("ikros_options", $this->options);
                return true;

            } elseif ($result->result == 0) {
                $this->success = false;
                $this->message = __("General error", $this->launcher->getPluginSlug());
                return false;
            } elseif ($result->result == 3) {
                $this->success = false;
                $this->message = __("License expired", $this->launcher->getPluginSlug());
                return false;
            }
        } else {
            if (isset($result->modelState) && count($result->modelState) > 0) {
                foreach ($result->modelState as $error) {
                    if (count($error) > 0) {
                        foreach ($error as $errorMessage) {
                            $this->success = false;
                            $this->message = __("Invalid request with error : ", $this->launcher->getPluginSlug()) . $errorMessage;
                            return false;
                        }
                    }
                }
            } else {
                $this->success = false;
                $this->message = __("Unable to connect with ikros system, please check your API number in plugin options", $this->launcher->getPluginSlug());
                return false;
            }
        }
        return false;
    }

    public function saveLocalInvoiceData()
    {
        $returnedData = json_decode($this->response, true);
        if (!$returnedData) {
            $this->success = false;
            $this->message = __('Ikros problem : Can not parse data returned from Ikros', $this->launcher->getPluginSlug());
        }
        $document = $returnedData['documents'][0];
        $documentUrl = $document['downloadUrl'];
        if (!$documentUrl) {
            $this->success = false;
            $this->message = __('Ikros problem : Unable to get invoice document URL', $this->launcher->getPluginSlug());
            return false;
        }
        update_post_meta($this->wcOrderId, 'invoiceUrl', $documentUrl);
        $invoiceNumber = $this->getInvoiceNumberByUrl($documentUrl);
        if (!$invoiceNumber) {
            $this->success = false;
            $this->message = __('Ikros problem : Can not get invoice number from URL', $this->launcher->getPluginSlug());
            return false;
        }
        update_post_meta($this->wcOrderId, 'invoiceNumber', $invoiceNumber);

        return true;
    }

    private function getInvoiceNumberByUrl($url)
    {
        $landingPage = file_get_contents($url);
        $pattern = '/<iframe src="([\w\/.?=&%]*)"/';
        $pdfUrl = preg_match($pattern, $landingPage, $matches);
        if (!$pdfUrl) {
            return false;
        }
        $pdfUrl = 'https://app.ikros.sk' . $matches['1'];
        $pdf = fopen($pdfUrl, 'r');
        $metatada = stream_get_meta_data($pdf);
        $invoiceNumber = '';
        if ($metatada['wrapper_data']) {
            foreach ($metatada['wrapper_data'] as $documentMetaData) {
                if (strpos($documentMetaData, 'Faktura_')) {
                    $pattern = '/filename="Faktura_([\w\/.?=&%]*).pdf"/';
                    preg_match($pattern, $documentMetaData, $matches);
                    if ($matches[1]) {
                        $invoiceNumber = $matches[1];
                    }
                }
            }
        }
        if (!$invoiceNumber) {
            return false;
        }
        return $invoiceNumber;
    }
}