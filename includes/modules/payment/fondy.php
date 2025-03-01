<?php

class Fondy
{
    var $code, $title, $description, $enabled;


    // class constructor
    function fondy()
    {
        global $order;

        $this->code = 'fondy';
        $this->title = MODULE_PAYMENT_FONDY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_FONDY_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_FONDY_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_FONDY_STATUS == 'True') ? true : false);

        $fondy = substr(__FILE__, 0, -4) . '/FondyCls.php';
        if (!is_file($fondy)) {
            throw new Exception('Main class not found! Check files.');
        }
        require_once $fondy;

        $this->form_action_url = 'https://payment.albpay.io /api/checkout/redirect/';
    }


    function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_PAYPAL_STANDARD_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPAL_STANDARD_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation()
    {
        return false;
    }

    function pre_confirmation_check()
    {
        global $cartID, $cart;

        if (empty($cart->cartID)) {
            $cartID = $cart->cartID = $cart->generate_cart_id();
        }

        if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
        }
    }

    function selection()
    {
        global $cart_Fondy_ID;

        if (tep_session_is_registered('cart_Fondy_ID')) {
            $order_id = substr($cart_Fondy_ID, strpos($cart_Fondy_ID, '-') + 1);

            $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

            if (tep_db_num_rows($check_query) < 1) {
                tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

                tep_session_unregister('cart_Fondy_ID');
            }
        }

        return array(
            'id' => $this->code,
            'module' => $this->title
        );
    }

    function confirmation()
    {
        global $cartID, $cart_Fondy_ID, $customer_id, $languages_id, $order, $order_total_modules;

        if (tep_session_is_registered('cartID')) {
            $insert_order = false;

            if (tep_session_is_registered('cart_Fondy_ID')) {
                $order_id = substr($cart_Fondy_ID, strpos($cart_Fondy_ID, '-') + 1);

                $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
                $curr = tep_db_fetch_array($curr_check);

                if (($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_Fondy_ID, 0, strlen($cartID)))) {
                    $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

                    if (tep_db_num_rows($check_query) < 1) {
                        tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
                    }

                    $insert_order = true;
                }
            } else {
                $insert_order = true;
            }

            if ($insert_order == true) {
                $order_totals = array();
                if (is_array($order_total_modules->modules)) {
                    reset($order_total_modules->modules);
                    while (list(, $value) = each($order_total_modules->modules)) {
                        $class = substr($value, 0, strrpos($value, '.'));
                        if ($GLOBALS[$class]->enabled) {
                            for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) {
                                if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                                    $order_totals[] = array('code' => $GLOBALS[$class]->code,
                                        'title' => $GLOBALS[$class]->output[$i]['title'],
                                        'text' => $GLOBALS[$class]->output[$i]['text'],
                                        'value' => $GLOBALS[$class]->output[$i]['value'],
                                        'sort_order' => $GLOBALS[$class]->sort_order);
                                }
                            }
                        }
                    }
                }

                $sql_data_array = array('customers_id' => $customer_id,
                    'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                    'customers_company' => $order->customer['company'],
                    'customers_street_address' => $order->customer['street_address'],
                    'customers_suburb' => $order->customer['suburb'],
                    'customers_city' => $order->customer['city'],
                    'customers_postcode' => $order->customer['postcode'],
                    'customers_state' => $order->customer['state'],
                    'customers_country' => $order->customer['country']['title'],
                    'customers_telephone' => $order->customer['telephone'],
                    'customers_email_address' => $order->customer['email_address'],
                    'customers_address_format_id' => $order->customer['format_id'],
                    'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                    'delivery_company' => $order->delivery['company'],
                    'delivery_street_address' => $order->delivery['street_address'],
                    'delivery_suburb' => $order->delivery['suburb'],
                    'delivery_city' => $order->delivery['city'],
                    'delivery_postcode' => $order->delivery['postcode'],
                    'delivery_state' => $order->delivery['state'],
                    'delivery_country' => $order->delivery['country']['title'],
                    'delivery_address_format_id' => $order->delivery['format_id'],
                    'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'billing_company' => $order->billing['company'],
                    'billing_street_address' => $order->billing['street_address'],
                    'billing_suburb' => $order->billing['suburb'],
                    'billing_city' => $order->billing['city'],
                    'billing_postcode' => $order->billing['postcode'],
                    'billing_state' => $order->billing['state'],
                    'billing_country' => $order->billing['country']['title'],
                    'billing_address_format_id' => $order->billing['format_id'],
                    'payment_method' => $order->info['payment_method'],
                    'cc_type' => $order->info['cc_type'],
                    'cc_owner' => $order->info['cc_owner'],
                    'cc_number' => $order->info['cc_number'],
                    'cc_expires' => $order->info['cc_expires'],
                    'date_purchased' => 'now()',
                    'orders_status' => $order->info['order_status'],
                    'currency' => $order->info['currency'],
                    'currency_value' => $order->info['currency_value']);

                tep_db_perform(TABLE_ORDERS, $sql_data_array);

                $insert_id = tep_db_insert_id();

                for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
                    $sql_data_array = array('orders_id' => $insert_id,
                        'title' => $order_totals[$i]['title'],
                        'text' => $order_totals[$i]['text'],
                        'value' => $order_totals[$i]['value'] * $order->info['currency_value'],
                        'class' => $order_totals[$i]['code'],
                        'sort_order' => $order_totals[$i]['sort_order']);

                    tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
                }

                for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                    $sql_data_array = array('orders_id' => $insert_id,
                        'products_id' => tep_get_prid($order->products[$i]['id']),
                        'products_model' => $order->products[$i]['model'],
                        'products_name' => $order->products[$i]['name'],
                        'products_price' => $order->products[$i]['price'],
                        'final_price' => $order->products[$i]['final_price'],
                        'products_tax' => $order->products[$i]['tax'],
                        'products_quantity' => $order->products[$i]['qty']);

                    tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

                    $order_products_id = tep_db_insert_id();

                    $attributes_exist = '0';
                    if (isset($order->products[$i]['attributes'])) {
                        $attributes_exist = '1';
                        for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                            if (DOWNLOAD_ENABLED == 'true') {
                                $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                           from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                           left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                           on pa.products_attributes_id=pad.products_attributes_id
                                           where pa.products_id = '" . $order->products[$i]['id'] . "'
                                           and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                           and pa.options_id = popt.products_options_id
                                           and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                           and pa.options_values_id = poval.products_options_values_id
                                           and popt.language_id = '" . $languages_id . "'
                                           and poval.language_id = '" . $languages_id . "'";
                                $attributes = tep_db_query($attributes_query);
                            } else {
                                $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                            }
                            $attributes_values = tep_db_fetch_array($attributes);

                            $sql_data_array = array('orders_id' => $insert_id,
                                'orders_products_id' => $order_products_id,
                                'products_options' => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'],
                                'options_values_price' => $attributes_values['options_values_price'],
                                'price_prefix' => $attributes_values['price_prefix']);

                            tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                            if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                                $sql_data_array = array('orders_id' => $insert_id,
                                    'orders_products_id' => $order_products_id,
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                    'download_count' => $attributes_values['products_attributes_maxcount']);

                                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                            }
                        }
                    }
                }

                $cart_Fondy_ID = $cartID . '-' . $insert_id;

                tep_session_register('cart_Fondy_ID');
            }
        }

        return false;
    }


    function process_button()
    {
        global $order, $currencies, $currency, $customer_id, $cart_Fondy_ID;

        $orderid = substr($cart_Fondy_ID, strpos($cart_Fondy_ID, '-') + 1);
        $order_desc = MODULE_PAYMENT_FONDY_ORDER_DESC . $orderid;
        $process_button_string = '';
        $fondy_currency = $currency;
        if ($fondy_currency == 'RUR')
            $fondy_currency = 'RUB';
        try {
            $request = array(
                'merchant_id' => MODULE_PAYMENT_FONDY_MERCHANT,
                'order_id' => $orderid . '#' . time(),
                'order_desc' => $order_desc,
                'amount' => number_format($order->info['total'] * $order->info['currency_value'], 2, '.', '') * 100,
                'currency' => $fondy_currency,

                'lang' => substr($_SESSION['language'], 0, 2),
                'merchant_data' => json_encode(array(
                    'p_firstname' => $order->customer['firstname'],
                    'p_lastname' => $order->customer['lastname']
                )),

                'sender_email' => $order->customer['email_address'],
                'response_url' => tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
                'server_callback_url' => tep_href_link('ext/modules/payment/fondy/callback.php', '', 'SSL', true, true, true)
            );
            $request['signature'] = FondyCls::getSignature($request, MODULE_PAYMENT_FONDY_SECRET_KEY);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if ($request) {
            foreach ($request as $field => $value) {
                $process_button_string .= tep_draw_hidden_field($field, $value);
            }
        } else {
            $process_button_string .= tep_draw_hidden_field('cancelurl', tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));;
        }
        return $process_button_string;
    }


    function before_process()
    {
        global $customer_id, $order, $order_totals, $sendto, $billto, $languages_id, $payment, $currencies, $cart, $cart_Fondy_ID;
        global $$payment;

        $GLOBALS['order']->customer['email_address'] .= "\nDont\nSend\nEmail\nPlease\n:)";

        $order_id = substr($cart_Fondy_ID, strpos($cart_Fondy_ID, '-') + 1);

        $check_query = tep_db_query("SELECT orders_status FROM " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");

        $sql_data_array = array(
            'orders_id' => $order_id,
            'date_added' => 'now()',
            'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
            'comments' => $order->info['comments']);

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        $products_ordered = '';
        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            if (STOCK_LIMITED == 'true') {
                if (DOWNLOAD_ENABLED == 'true') {
                    $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                            ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                            ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                    $products_attributes = $order->products[$i]['attributes'];
                    if (is_array($products_attributes)) {
                        $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                    }
                    $stock_query = tep_db_query($stock_query_raw);
                } else {
                    $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
                if (tep_db_num_rows($stock_query) > 0) {
                    $stock_values = tep_db_fetch_array($stock_query);
                    // do not decrement quantities if products_attributes_filename exists
                    if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                        $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                    } else {
                        $stock_left = $stock_values['products_quantity'];
                    }
                    tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                }
            }
            tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
            $attributes_exist = '0';
            $products_ordered_attributes = '';
            if (isset($order->products[$i]['attributes'])) {

                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                on pa.products_attributes_id=pad.products_attributes_id
                                where pa.products_id = '" . $order->products[$i]['id'] . "'
                                and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                and pa.options_id = popt.products_options_id
                                and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                and pa.options_values_id = poval.products_options_values_id
                                and popt.language_id = '" . $languages_id . "'
                                and poval.language_id = '" . $languages_id . "'";
                        $attributes = tep_db_query($attributes_query);
                    } else {
                        $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                    }
                    $attributes_values = tep_db_fetch_array($attributes);

                    $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                }
            }
            $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
        }
        $this->after_process();

        $cart->reset(true);
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
        tep_session_unregister('cart_Fondy_ID');
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    }

    function after_process()
    {
        return false;
    }

    function output_error()
    {
        return false;
    }


    function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_FONDY_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }


    function install()
    {
        $this->remove();
        global $language, $module_type;
        include_once(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/' . $module_type . '/fondy.php');

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('" . MODULE_PAYMENT_FONDY_STATUS_TITLE . "', 'MODULE_PAYMENT_FONDY_STATUS', 'True', '" . MODULE_PAYMENT_FONDY_STATUS_DESCRIPTION . "', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('" . MODULE_PAYMENT_FONDY_MERCHANT_TITLE . "', 'MODULE_PAYMENT_FONDY_MERCHANT', '', '" . MODULE_PAYMENT_FONDY_MERCHANT_DESCRIPTION . "', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('" . MODULE_PAYMENT_FONDY_SECRET_KEY_TITLE . "', 'MODULE_PAYMENT_FONDY_SECRET_KEY', '', '" . MODULE_PAYMENT_FONDY_SECRET_KEY_DESCRIPTION . "', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('" . MODULE_PAYMENT_FONDY_CURRENCY_TITLE . "', 'MODULE_PAYMENT_FONDY_CURRENCY', 'Selected Currency', '" . MODULE_PAYMENT_FONDY_CURRENCY_DESCRIPTION . "', '6', '6', 'tep_cfg_select_option(array(\'Selected Currency\',\'Only USD\',\'Only RUB\',\'Only UAH\',\'Only EUR\',\'Only GBP\',\'Only JPY\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('" . MODULE_PAYMENT_FONDY_ZONE_TITLE . "', 'MODULE_PAYMENT_FONDY_ZONE', '0', '" . MODULE_PAYMENT_FONDY_ZONE_DESCRIPTION . "', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('" . MODULE_PAYMENT_FONDY_SORT_ORDER_TITLE . "', 'MODULE_PAYMENT_FONDY_SORT_ORDER', '0', '" . MODULE_PAYMENT_FONDY_SORT_ORDER_DESCRIPTION . "', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_FONDY_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

        $d = dir(DIR_FS_CATALOG_LANGUAGES);
        while (false !== ($entry = $d->read())) {
            if ($entry != "." && $entry != ".." && is_dir($d->path . $entry) && is_file($d->path . $entry . '/modules/' . $module_type . '/fondy.php')) {
                $langFile = implode('', file($d->path . $entry . '/modules/' . $module_type . '/fondy.php'));
                preg_match("/MODULE_PAYMENT_FONDY_ORDERS_STATUS_20.*['\"]\s*,\s*['\"]([^\"']+)/", $langFile, $constant);

                $language_query = tep_db_query("SELECT languages_id from " . TABLE_LANGUAGES . " WHERE directory = '" . $entry . "'");
                $languageData = tep_db_fetch_array($language_query);

                if ($languageData) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values (20, '" . $languageData['languages_id'] . "', '" . trim($constant[1]) . "')");
                }
            }
        }
        $d->close();

        tep_db_query("ALTER TABLE " . TABLE_ORDERS . " ADD `SSID` VARCHAR( 40 ) NOT NULL");
    }

    function remove()
    {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE `configuration_key` IN ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("DELETE FROM " . TABLE_ORDERS_STATUS . " WHERE `orders_status_id` = '20'");

        $res = tep_db_query("SHOW FIELDS FROM " . TABLE_ORDERS);
        while ($field = tep_db_fetch_array($res)) {
            if ($field['Field'] == 'SSID') {
                tep_db_query("ALTER TABLE " . TABLE_ORDERS . " DROP `SSID` ");
                break;
            }
        }
    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_FONDY_STATUS',
            'MODULE_PAYMENT_FONDY_MERCHANT',
            'MODULE_PAYMENT_FONDY_SECRET_KEY',
            'MODULE_PAYMENT_FONDY_CURRENCY',
            'MODULE_PAYMENT_FONDY_ZONE',
            'MODULE_PAYMENT_FONDY_SORT_ORDER',
            'MODULE_PAYMENT_FONDY_ORDER_STATUS_ID'
        );
    }

    function tep_remove_order($order_id, $restock = false)
    {
        if ($restock) {
            $order_query = tep_db_query("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = '" . (int)$order_id . "'");
            while ($order = tep_db_fetch_array($order_query)) {
                tep_db_query("UPDATE " . TABLE_PRODUCTS . " 
                    SET products_quantity = 
                    products_quantity + " . $order['products_quantity'] . ", 
                    products_ordered = products_ordered - " . $order['products_quantity'] . " 
                    WHERE products_id = '" . (int)$order['products_id'] . "'"
                );
            }
        }

        tep_db_query("DELETE FROM " . TABLE_ORDERS . " WHERE orders_id = '" . (int)$order_id . "'");
        tep_db_query("DELETE FROM " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = '" . (int)$order_id . "'");
        tep_db_query("DELETE FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " WHERE orders_id = '" . (int)$order_id . "'");
        tep_db_query("DELETE FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = '" . (int)$order_id . "'");
        tep_db_query("DELETE FROM " . TABLE_ORDERS_TOTAL . " WHERE orders_id = '" . (int)$order_id . "'");
    }

}


