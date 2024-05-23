<?php

chdir('../../../../');
require_once 'includes/application_top.php';
require_once 'includes/modules/payment/fondy/FondyCls.php';

$ip_address = tep_get_ip_address();

try {
    $response = $_REQUEST;
    $fondySettings = array(
        'merchant_id' => MODULE_PAYMENT_FONDY_MERCHANT,
        'secret_key' => MODULE_PAYMENT_FONDY_SECRET_KEY
    );
    if (FondyCls::isPaymentValid($fondySettings, $response)) {
        $order_id = explode('#',$response['order_id'])[0];
        $order_query = tep_db_query('
                SELECT `orders_status`, `currency`, `currency_value`
                FROM ' . TABLE_ORDERS . '
                WHERE
                    `orders_id` = ' . intval($order_id)
        );

        if (tep_db_num_rows($order_query) <= 0) {
            throw new Exception('Order not found!');

        }

        $order = tep_db_fetch_array($order_query);
        $order_currency = $order['currency'];
        if ($order_currency and $order_currency == 'RUR')
            $order_currency = 'RUB';
        if ($order_currency != $response['currency']) {
            throw new Exception('Wrong currency!');
        }

        $total_query = tep_db_query('
                    SELECT `value`
                    FROM ' . TABLE_ORDERS_TOTAL . '
                    WHERE
                        `orders_id` = ' . intval($order_id) . '
                        AND `class` = "ot_total"
                        LIMIT 1'
        );

        $total = tep_db_fetch_array($total_query);


        if (intval(number_format($total['value'], 2, '', '')) > ($response['amount'])) {
            throw new Exception('Bad amount!');
        }

        $sql_data_array = array();
        $sql_data_array['orders_id'] = intval($order_id);
        $sql_data_array['date_added'] = 'now()';
        $sql_data_array['customer_notified'] = '0';
        $sql_data_array['comments'] = '';
        foreach ($_REQUEST as $k => $v){
            if(empty($v))
                continue;
            $sql_data_array['comments'] .= $k . ' - ' . $v . "\n";
        }
        $sql_data_array['orders_status_id'] = MODULE_PAYMENT_FONDY_ORDER_STATUS_ID;
        tep_db_query('
				UPDATE ' . TABLE_ORDERS . '
				SET
					`orders_status` = ' . intval(MODULE_PAYMENT_FONDY_ORDER_STATUS_ID) . ',
					`last_modified` = NOW()
				WHERE
					`orders_id` = ' . intval($order_id)
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        die('OK');

    }

} catch (Exception $e) {
    echo get_class($e) . ': ' . $e->getMessage();
}


