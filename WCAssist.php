<?php


class WCAssist extends WC_Payment_Gateway
{
    public const orderStatuses = [
//        'Pending payment' => '',
//        'Failed' => '',
//        'Processing' => '',
//        'Completed' => '',
//        'On hold' => '',
//        'Canceled' => '',
//        'Refunded' => '',
//        'Authentication required' => '',
//        'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
//        'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
//        'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
//        'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
//        'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
//        'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
//        'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
    ];


    public function __construct()
    {
        $plugin_dir = plugin_dir_url(__FILE__);

        global $woocommerce;

        $this->id = 'assist';
        $this->icon = apply_filters('woocommerce_assist_icon', '' . $plugin_dir . 'assist.png');
        $this->has_fields = false;

        $this->enabled = $this->is_valid_for_use();

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->assist_merchant = $this->get_option('assist_merchant');
        $this->assist_key1 = $this->get_option('assist_key1');
        $this->liveurl = $this->get_option('assist_url');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        // Actions
        add_action('valid-assist-standard-ipn-request', array($this, 'successful_request'));
        add_action('novalid-assist-standard-ipn-request', array($this, 'fail_request'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Payment listener/API hook
        add_action('woocommerce_api_wc_' . $this->id, [$this, 'check_ipn_response']);
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    function is_valid_for_use()
    {
        return get_option('woocommerce_currency') === 'BYN';
    }

    public function admin_options()
    {
        ?>
        <h3><?php _e('Assist', 'woocommerce'); ?></h3>
        <p><?php _e('Настройка приема электронных платежей через Assist.', 'woocommerce'); ?></p>

        <?php if ($this->is_valid_for_use()) : ?>

        <table class="form-table">

            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
        </table><!--/.form-table-->

    <?php else : ?>
        <div class="inline error"><p><strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e('assist не поддерживает валюты Вашего магазина.', 'woocommerce'); ?></p></div>
    <?php
    endif;

    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Включить/Выключить', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Включен', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Название', 'woocommerce'),
                'type' => 'text',
                'description' => __('Это название, которое пользователь видит во время проверки.', 'woocommerce'),
                'default' => __('assist', 'woocommerce')
            ),
            'assist_merchant' => array(
                'title' => __('Merchant_ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Пожалуйста введите Merchant_ID', 'woocommerce'),
                'default' => ''
            ),
            'assist_key1' => array(
                'title' => __('Секретное слово', 'woocommerce'),
                'type' => 'text',
                'description' => __('Пожалуйста введите секретное слово', 'woocommerce'),
                'default' => ''
            ),
            'assist_url' => array(
                'title' => __('Адрес', 'woocommerce'),
                'type' => 'text',
                'description' => __('Пожалуйста введите assist URL', 'woocommerce'),
                'default' => ''
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce'),
                'default' => 'Оплата с помощью assist.'
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Инструкции, которые будут добавлены на страницу благодарностей.', 'woocommerce'),
                'default' => 'Оплата с помощью assist.'
            )
        );
    }

    /**
     * There are no payment fields for sprypay, but we want to show the description if set.
     **/
    function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    /**
     * Generate the dibs button link
     **/
    public function generate_form($order_id)
    {
        global $woocommerce;

        $order = new WC_Order($order_id);

        $action_adr = $this->liveurl;

        $out_summ = number_format($order->order_total, 2, '.', '');

        $hashcode = strtoupper(md5(strtoupper(md5($this->assist_key1) . md5($this->assist_merchant . $order_id . $out_summ . str_replace("RUR", "RUB", get_option('woocommerce_currency'))))));

        $args = [
            'Merchant_ID' => $this->assist_merchant,
            'OrderNumber' => $order_id,
//				'URL_RETURN' => 'http://' . $_SERVER['HTTP_HOST'] . '/?wc-api=wc_assist&assist=result',
            'CheckValue' => $hashcode,
            'OrderCurrency' => 'BYN',
            'Lastname' => $order->billing_last_name,
            'Firstname' => $order->billing_first_name,
//				'Language' => 'RU',
            'URL_RETURN_OK' => 'http://' . $_SERVER['HTTP_HOST'] . '/?wc-api=wc_assist&assist=success',
            'URL_RETURN_NO' => 'http://' . $_SERVER['HTTP_HOST'] . '/?wc-api=wc_assist&assist=fail',
            'Email' => $order->billing_email,
            'MobilePhone' => $order->billing_phone,
            'OrderComment' => $order->customer_note . 'test',
            'OrderAmount' => $out_summ,
        ];

        $paypal_args = apply_filters('woocommerce_assist_args', $args);

        $args_array = array();

        foreach ($args as $key => $value) {
            $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }

        return '<form action="' . esc_url($action_adr) . '" method="POST" id="assist_payment_form">' . "\n" .
            implode("\n", $args_array) .
            '<input type="submit" class="button alt" id="submit_assist_payment_form" value="' . __('Оплатить', 'woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Отказаться от оплаты & вернуться в корзину', 'woocommerce') . '</a>' . "\n" .
            '</form>';
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
        );
    }

    function receipt_page($order)
    {
        echo '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>';
        echo $this->generate_form($order);
    }

    /**
     * Check assist IPN validity
     **/
    function check_ipn_request_is_valid($posted)
    {
        $out_summ = $posted['orderamount'];
        $inv_id = $posted['ordernumber'];
        
        if ($posted['checkvalue'] == strtoupper(md5(strtoupper(md5($this->assist_key1) . md5($this->assist_merchant . $inv_id . $out_summ . $posted['ordercurrency'] . $posted['orderstate']))))) {
            echo 'OK' . $inv_id;
            return true;
        }

        return false;
    }

    /**
     * Check Response
     **/
    function check_ipn_response()
    {
        global $woocommerce;

        if (isset($_GET['assist']) and $_GET['assist'] == 'result') {
            file_put_contents(__DIR__ . '/debug.log', print_r($_REQUEST, true));
            @ob_clean();

            $_POST = stripslashes_deep($_REQUEST);

            if ($this->check_ipn_request_is_valid($_POST)) {

                if ($_POST['orderstate'] == "Approved") {
                    do_action('valid-assist-standard-ipn-request', $_POST);
                }
                if ($_POST['orderstate'] == "Declined") {
                    do_action('novalid-assist-standard-ipn-request', $_POST);
                }
            } else {
                wp_die('IPN Request Failure');
                exit;
            }
        } else {
            if (isset($_GET['assist']) and $_GET['assist'] == 'success') {
                $inv_id = $_POST['ordernumber'];
                $order = new WC_Order($inv_id);
                $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
                WC()->cart->empty_cart();

                wp_redirect($this->get_return_url($order));
            } else {
                if (isset($_GET['assist']) and $_GET['assist'] == 'fail') {
                    $inv_id = $_POST['ordernumber'];
                    $order = new WC_Order($inv_id);
                    $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));

                    wp_redirect($order->get_cancel_order_url());
                    exit;
                }
            }
        }

    }

    /**
     * Successful Payment!
     **/
    function successful_request($posted)
    {
        global $woocommerce;

        $out_summ = $posted['orderamount'];
        $inv_id = $posted['ordernumber'];

        $order = new WC_Order($inv_id);
        // Check order not already completed
        if ($order->status == 'completed') {
            exit;
        }

        // Payment completed
        $order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
        $order->payment_complete();
        exit;
    }

    function fail_request($posted)
    {
        global $woocommerce;

        $out_summ = $posted['orderamount'];
        $inv_id = $posted['ordernumber'];

        $order = new WC_Order($inv_id);


        // Payment completed
        $order->add_order_note(__('Платеж успешно завершен неудачно.', 'woocommerce'));
        $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
        exit;
    }
}
