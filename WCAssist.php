<?php


class WCAssist extends WC_Payment_Gateway
{
    public $id = 'assist';
    public $has_fields = false;

    public function __construct()
    {
        $this->icon = apply_filters('woocommerce_assist_icon', plugin_dir_url(__FILE__) . 'assist.png');
        $this->enabled = $this->isValidForUse();

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
        add_action('valid-assist-standard-ipn-request', [$this, 'successful_request']);
        add_action('novalid-assist-standard-ipn-request', [$this, 'fail_request']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receiptPage']);

        // Save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Payment listener/API hook
        add_action('woocommerce_api_wc_' . $this->id, [$this, 'check_ipn_response']);
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    private function isValidForUse(): bool
    {
        return get_option('woocommerce_currency') === 'BYN';
    }

    public function admin_options()
    {
        ?>
        <h3><?php _e('Assist Belarus', 'woocommerce'); ?></h3>
        <p><?php _e('Настройка приема электронных платежей через Assist Belarus', 'woocommerce'); ?></p>

        <?php if ($this->isValidForUse()) : ?>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>

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
    public function init_form_fields()
    {
        $this->form_fields = [
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
                'default' => 'Assist Belarus',
            ),
            'assist_merchant' => array(
                'title' => __('Merchant_ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('Введите Merchant_ID', 'woocommerce'),
                'default' => ''
            ),
            'assist_key1' => array(
                'title' => __('Секретное слово', 'woocommerce'),
                'type' => 'text',
                'description' => __('Введите секретное слово', 'woocommerce'),
                'default' => ''
            ),
            'assist_url' => array(
                'title' => __('Адрес', 'woocommerce'),
                'type' => 'text',
                'description' => __('Введите assist URL. Для тестовых платежей используйте <code>https://test.paysec.by/pay/order.cfm</code>', 'woocommerce'),
                'default' => 'https://test.paysec.by/pay/order.cfm'
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
        ];
    }

    /**
     * There are no payment fields for sprypay, but we want to show the description if set.
     **/
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    /**
     * Generate the dibs button link
     * @param $order_id
     * @return string
     */
    public function generateForm($order_id): string
    {
        $order = wc_get_order($order_id);

        $action_adr = $this->liveurl;

        $out_summ = number_format($order->get_total(), 2, '.', '');

        $hashcode = strtoupper(md5(strtoupper(md5($this->assist_key1) . md5($this->assist_merchant . $order_id . $out_summ . str_replace("RUR", "RUB", get_option('woocommerce_currency'))))));

        $args = [
            'Merchant_ID' => $this->assist_merchant,
            'OrderNumber' => $order_id,
            'URL_RETURN' => get_home_url() . '/?wc-api=wc_assist&assist=result',
            'CheckValue' => $hashcode,
            'OrderCurrency' => 'BYN',
            'Lastname' => $order->get_billing_last_name(),
            'Firstname' => $order->get_billing_first_name(),
            'Language' => 'RU',
            'URL_RETURN_OK' => get_home_url() . '/?wc-api=wc_assist&assist=success',
            'URL_RETURN_NO' => get_home_url() . '/?wc-api=wc_assist&assist=fail',
            'Email' => $order->get_billing_email(),
            'MobilePhone' => $order->get_billing_phone(),
            'OrderComment' => $order->get_customer_note(),
            'OrderAmount' => $out_summ,
        ];

        $args_array = [];

        foreach ($args as $key => $value) {
            $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
        }

        return '<form action="' . esc_url($action_adr) . '" method="POST" id="assist_payment_form">' . "\n" .
            implode("\n", $args_array) .
            '<input type="submit" class="button alt" id="submit_assist_payment_form" value="' . __('Оплатить', 'woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Отказаться от оплаты и вернуться в корзину', 'woocommerce') . '</a>' . "\n" .
            '</form>';
    }

    /**
     * Process the payment and return the result
     **/
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        return array(
            'result' => 'success',
            'redirect' => add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))))
        );
    }

    public function receiptPage($order)
    {
        echo '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>';
        echo $this->generateForm($order);
    }

    /**
     * Check assist IPN validity
     **/
    public function check_ipn_request_is_valid($posted)
    {
        $out_summ = $posted['orderamount'];
        $inv_id = $posted['ordernumber'];

        $checksum = strtoupper(md5(strtoupper(md5($this->assist_key1) . md5($this->assist_merchant . $inv_id . $out_summ . $posted['ordercurrency'] . $posted['orderstate']))));

        return $posted['checkvalue'] === $checksum;
    }

    /**
     * Check Response
     **/
    public function check_ipn_response()
    {
        if (isset($_GET['assist']) and $_GET['assist'] === 'result') {
            $data = stripslashes_deep($_REQUEST);

            if ($this->check_ipn_request_is_valid($data)) {
                if ($_POST['orderstate'] === "Approved") {
                    do_action('valid-assist-standard-ipn-request', $data);
                }
                if ($_POST['orderstate'] === "Declined") {
                    do_action('novalid-assist-standard-ipn-request', $data);
                }
            } else {
                wp_die('IPN Request Failure');
                exit;
            }
        } elseif (isset($_GET['assist']) && $_GET['assist'] === 'success') {
            $inv_id = $_REQUEST['ordernumber'];
            $order = wc_get_order($inv_id);
            $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
            WC()->cart->empty_cart();
            wp_redirect($this->get_return_url($order));
        } elseif (isset($_GET['assist']) and $_GET['assist'] === 'fail') {
            $inv_id = $_POST['ordernumber'];
            $order = wc_get_order($inv_id);

            $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));

            wp_redirect($order->get_cancel_order_url());
            exit;
        }
    }

    /**
     * Successful Payment!
     **/
    public function successful_request($posted)
    {
        $orderId = $posted['ordernumber'];

        $order = wc_get_order($orderId);
        // Check order not already completed
        if ($order->get_status() === 'completed') {
            exit;
        }

        // Payment completed
        $order->add_order_note(__('Платеж успешно завершен.', 'woocommerce'));
        $order->payment_complete();

        exit;
    }

    public function fail_request($posted)
    {
        $orderId = $posted['ordernumber'];

        $order = wc_get_order($orderId);

        // Payment completed
        $order->add_order_note(__('Платеж успешно завершен неудачно.', 'woocommerce'));
        $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
        exit;
    }
}
