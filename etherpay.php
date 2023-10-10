<?php
/*
Plugin Name: EtherPay
Plugin URI: https://evmtracker.com
Description: Any Ethereum (EVM) Payment Plugin for Woocommerce support any Erc20 token on all EvmTracker supported blockchains
Version: 1.0.0
Author: EVM
Author URI: https://evmtracker.com/about
*/

use SWeb3\Accounts;
use SWeb3\Account;
use SWeb3\Utils;
use SWeb3\SWeb3;

add_action('plugins_loaded', 'etherpay_init');

define('EVM_WOOCOMMERCE_VERSION', '1.0.0');
define('EVM_CHECKOUT_PATH', 'https://api.evmtracker.com/api/v1');
define('EVM_API', 'https://api.evmtracker.com/api/v1');

function etherpay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

    require_once(__DIR__ . '/lib/evmtracker/init.php');
    require_once(__DIR__ . '/vendor/autoload.php');

    class WC_Gateway_Evmtracker extends WC_Payment_Gateway
    {
        public function __construct()
        {
            global $woocommerce;

            $this->id = 'etherpay';
            $this->has_fields = false;
            $this->method_title = 'EtherPay';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->api_secret = $this->get_option('api_secret');
            $this->api_auth_token = (empty($this->get_option('api_auth_token')) ? $this->get_option('api_secret') : $this->get_option('api_auth_token'));
            $this->checkout_url = $this->get_option('checkout_url');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_evmtracker', array($this, 'thankyou'));
            add_action('woocommerce_api_wc_gateway_evmtracker', array($this, 'payment_callback'));
        }

        public function admin_options()
        {
            ?>
            <h3><?php _e('EtherPay', 'woothemes'); ?></h3>
            <p><?php _e('Any Ethereum (EVM) Payment Plugin for Woocommerce.', 'woothemes'); ?></p>
            <p><?php _e('1. PayPal Donate: xbuildapp@gmail.com', 'woothemes'); ?></p>
            <p><?php _e('2. Address Donate: 0x8a1070d77C49ae03187B651Eae5D003761C84fC2.', 'woothemes'); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>

            <h3><?php _e('Powerby EvmTracker.com', 'woothemes'); ?></h3>
            <?php
            $stream_opts = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false,]];
            $configs = file_get_contents("https://gist.githubusercontent.com/soaivu/1b89a2bd23f09fbb968fb70cd102cd7d/raw/9f96c289b52dbcaf183000b8851269327a112f1f/config_ethepay.json", false, stream_context_create($stream_opts));

            if (!empty($configs)) {
                ?>
                <table class="form-table" style="width: 50%;">
                    <?php
                    $configs = json_decode($configs);
                    foreach ($configs as $config) {
                        ?>
                        <tr>
                            <?php if (!empty($config->title_html)) : ?>
                                <td colspan="2"><?php echo $config->title_html; ?></td>
                            <?php else : ?>
                                <td><p><a href="<?php echo $config->url; ?>" target="_blank"><?php echo $config->title; ?></a></p></td>
                                <td><a href="<?php echo $config->url; ?>" target="_blank"><img src="<?php echo $config->banner; ?>"/></a></td>
                            <?php endif; ?>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
                <?php
            }
        }

        public function init_form_fields()
        {
            $this->init_evmtracker();

            $blockchainListOption = array();
            $blockchainListOption['0'] = '- Choose -';
            $evmtracker_order = \Evmtracker\Merchant\Order::blockchainList();
            $blockchainList = !empty($evmtracker_order->data['data']) ? $evmtracker_order->data['data'] : array();

            if (!empty($blockchainList)) {
                foreach ($blockchainList as $list) {
                    $blockchainListOption[$list['code']] = $list['title'];

                    if (!empty($this->get_option('currentcy_code')) && ($this->get_option('currentcy_code') == $list['code'])) {
                        $this->update_option('chain_rpc', $list['rpc']);
                    }
                }
            }

            $this->form_fields = array(
                'enabled'                      => array(
                    'title'       => __('Enable EtherPay', 'woocommerce'),
                    'label'       => __('Enable Bitcoin payments via EtherPay', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                ),
                'title'                        => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('The payment method title which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default'     => __('Any Ethereum (EVM) Payment Plugin for Woocommerce support any Erc20 token on all EvmTracker supported blockchains.', 'woocommerce'),
                ),
                'description'                  => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('The payment method description which a customer sees at the checkout of your store.', 'woocommerce'),
                    'default'     => __('Powered by EtherPay'),
                ),
                'api_auth_token'               => array(
                    'title'       => __('API Auth Token', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Your personal API Key. Generate one <a href="https://evmtracker.com/register?sponsorKey=aSkZj5PE9N0I" target="_blank">Get key here >></a>.  ', 'woocommerce'),
                    'default'     => (empty($this->get_option('api_secret')) ? '' : $this->get_option('api_secret')),
                ),
                'checkout_url'                 => array(
                    'title'       => __('Checkout URL', 'woocommerce'),
                    'description' => __('URL for the checkout', 'woocommerce'),
                    'type'        => 'text',
                    'default'     => EVM_CHECKOUT_PATH,
                ),
                'chain_code'                   => array(
                    'title'   => __('Chain List', 'woocommerce'),
                    'type'    => 'select',
                    'options' => $blockchainListOption
                ),
                'chain_rpc'                    => array(
                    'title'   => __('Chain RPC', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'chain_id'                     => array(
                    'title'   => __('Chain ID', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('10434', 'woocommerce'),
                ),
                'currentcy'                    => array(
                    'title' => __('Currentcy', 'woocommerce'),
                    'type'  => 'title',
                ),
                'currentcy_title'              => array(
                    'title'   => __('Currentcy Title', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'currentcy_code'               => array(
                    'title'   => __('Currentcy Code', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'currentcy_logo'               => array(
                    'title'   => __('Currentcy Logo', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'currentcy_rate'               => array(
                    'title'   => __('Currentcy Rate', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'currentcy_number_of_decimals' => array(
                    'title'   => __('Number of decimals', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('0', 'woocommerce'),
                ),
                'currentcy_gas'                => array(
                    'title'   => __('Currentcy Gas', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'native_token'                 => array(
                    'title'   => __('Native Token', 'woocommerce'),
                    'type'    => 'select',
                    'options' => array(
                        ''       => '- Choose -',
                        'Native' => 'Native Token',
                        'ERC20'  => 'ERC20',
                    ),
                    'default' => __('Native', 'woocommerce'),
                ),
                'currentcy_contract_address'   => array(
                    'title'   => __('Currentcy Contract Address', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'shopadmin'                    => array(
                    'title' => __('Shop Owner Admin', 'woocommerce'),
                    'type'  => 'title',
                ),
                'admin_recieving_fund'         => array(
                    'title'   => __('Shop Owner Admin Recieving Fund ', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
                'admin_private_key'            => array(
                    'title'   => __('Shop Owner Admin private key', 'woocommerce'),
                    'type'    => 'text',
                    'default' => __('', 'woocommerce'),
                ),
            );
        }

        public function thankyou()
        {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $this->init_evmtracker();

            $etherpay_order_id = get_post_meta($order->get_id(), 'etherpay_order_id', true);

            if (empty($etherpay_order_id)) {
                // Khởi tạo địa chỉ web3
                $account_object = Accounts::create();
                $params = array(
                    'order_id'      => $order->get_id(),
                    'web3_order_id' => [$account_object->address],
                    'price'         => (strtoupper(get_woocommerce_currency()) === 'BTC') ? number_format($order->get_total() * 100000000, 8, '.', '') : number_format($order->get_total(), 8, '.', ''),
                    'fiat'          => get_woocommerce_currency(),
                    'callback_url'  => trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_evmtracker',
                    'success_url'   => $order->get_checkout_order_received_url(),
                    'description'   => 'WooCommerce - #' . $order->get_id(),
                    'name'          => $order->get_formatted_billing_full_name(),
                    'email'         => $order->get_billing_email(),
                    'chain_code'    => $this->get_option('chain_code'),
                );
                update_post_meta($order_id, 'evmtracker_paras', $params);
                update_post_meta($order_id, 'evmtracker_web3_privateKey', $account_object->privateKey);
                update_post_meta($order_id, 'evmtracker_web3_publicKey', $account_object->publicKey);
                $evmtracker_order = \Evmtracker\Merchant\Order::create($params);

                if (isset($evmtracker_order) && ((int)$evmtracker_order->code == 200)) {
                    //$etherpay_order_id = $evmtracker_order->id;
                    update_post_meta($order_id, 'web3_order_id', $account_object->address);
                }

                return array(
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_order_received_url(),
                );
            } else {
                return array(
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_order_received_url(),
                );
            }
        }

        public function payment_callback()
        {
            global $wpdb;
            $api_auth_token = $this->get_option('api_auth_token');
            $request = $_REQUEST;

            $json = file_get_contents('php://input');

            $post_params = (array)json_decode($json, true);

            if (empty(array_filter($post_params))) {
                $return = array(
                    'status' => 201,
                    'mess'   => 'Post data is NULL'
                );
                echo json_encode($return);
                exit();
            }

            $currentcy_code = $this->get_option('currentcy_code');
            if (empty($post_params['chainCode']) || $post_params['chainCode'] != $currentcy_code) {
                $return = array(
                    'status' => 201,
                    'mess'   => 'chainCode is invalid.'
                );
                echo json_encode($return);
                exit();
            }

            // Save trans
            $table_name = $wpdb->prefix . 'evmtracker_txn';
            $wpdb->insert(
                $table_name,
                array(
                    'transaction_txn' => $json,
                    'time'            => current_time('mysql', 1)
                )
            );
            //$token = $post_params['token'];
            //unset($post_params['token']);
            //unset($post_params['signature']);

            // TODO
            // Check sign
            if (strcmp(hash_hmac('sha256', $post_params['signature'], $api_auth_token), $post_params['transactionHash']) != 0) {
                //throw new Exception('Request is not signed with the same API Key, ignoring.');
            }

            // Lấy thông tin order từ TO
            $meta_key = 'web3_order_id';
            $data = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s", $meta_key, $post_params['to']), ARRAY_N);

            $order_id = NULL;
            if (!empty($data[0][0])) {
                $order_id = $data[0][0];
            } else {
                $return = array(
                    'status' => 201,
                    'mess'   => 'Order does not exists.'
                );
                echo json_encode($return);
                exit();
            }

            $order = wc_get_order($order_id);

            try {
                if (!$order || !$order->get_id()) {
                    $return = array(
                        'status' => 201,
                        'mess'   => 'Address ' . $post_params['to'] . ' does not exists'
                    );
                    echo json_encode($return);
                    exit();
                }

                $address_web3_order_id = get_post_meta($order->get_id(), 'web3_order_id', true);
                if (empty($address_web3_order_id)) {
                    $return = array(
                        'status' => 201,
                        'mess'   => 'Order has etherpay ID associated'
                    );
                    echo json_encode($return);
                    exit();
                }

                $this->init_evmtracker();

                // Trans Coin
                $currentcy_rate = $this->get_option('currentcy_rate');
                $amount_value = $post_params['value'] * (float)$currentcy_rate;

                // update amounted
                $order_amounted = get_post_meta($order->get_id(), 'order_amounted', true);
                $order_amounted = !empty($order_amounted) ? $order_amounted : 0;
                $order_amount = (float)$order_amounted + (float)$amount_value;

                $web3_total = 0;
                if (!empty($currentcy_rate) && (float)$currentcy_rate > 0) {
                    $web3_total = $order->get_total() / (float)$currentcy_rate;
                }

                // create trans
                \Evmtracker\EtherPay::config(
                    array(
                        'chain_rpc' => $this->get_option('chain_rpc'),
                        'chain_id'  => $this->get_option('chain_id'),
                    )
                );

                // ERC20: send Gas fee shop wallet to order wallet
                if (!empty($this->get_option('native_token')) && ($this->get_option('native_token') == 'ERC20')) {
                    // Check balanceOf shop wallet
                    $wallet_address = [
                        'address'          => $this->get_option('admin_recieving_fund'),
                        'private_key'      => $this->get_option('admin_private_key'),
                        'contract_address' => $this->get_option('currentcy_contract_address'),
                    ];

                    $balanceOf = \Evmtracker\EtherPay::balanceOf($wallet_address);
                    if (!empty($balanceOf) && !empty($this->get_option('currentcy_gas'))) {
                        // Send Gas
                        $sendParamsGas = [
                            'order_id'      => $order_id,
                            'from'          => $this->get_option('admin_recieving_fund'),
                            'to'            => $address_web3_order_id,
                            'private_key'   => $this->get_option('admin_private_key'),
                            'value'         => $this->get_option('currentcy_gas'),
                            'native_token'  => $this->get_option('native_token'),
                            'currentcy_gas' => $this->get_option('currentcy_gas'),
                            'chain_rpc'     => $this->get_option('chain_rpc'),
                            'chain_id'      => $this->get_option('chain_id')
                        ];

                        //$responseGas = \Evmtracker\EtherPay::send($sendParamsGas);
                        as_enqueue_async_action('send_gas', ['data' => $sendParamsGas], 'EtherPay', false, 10);

                        // Crw from order wallet to shop wallet
                        $sendParams = [
                            'order_id'                     => $order_id,
                            'from'                         => $address_web3_order_id,
                            'to'                           => $this->get_option('admin_recieving_fund'),
                            'private_key'                  => get_post_meta($order->get_id(), 'evmtracker_web3_privateKey', true),
                            'value'                        => $post_params['value'],
                            'native_token'                 => $this->get_option('native_token'),
                            'currentcy_gas'                => $this->get_option('currentcy_gas'),
                            'contract_address'             => $this->get_option('currentcy_contract_address'),
                            'order_amount'                 => $order_amount,
                            'chain_rpc'                    => $this->get_option('chain_rpc'),
                            'chain_id'                     => $this->get_option('chain_id'),
                            'currentcy_number_of_decimals' => $this->get_option('currentcy_number_of_decimals'),
                        ];

                        //as_enqueue_async_action( 'send_token', $sendParams, 'EtherPay', false, 10 );
                        as_schedule_single_action(time() + 5 * 60, 'send_token', ['data' => $sendParams], 'EtherPay', false, 10);
                    }
                } else {
                    // Trans order wallet to shop wallet
                    $sendParams = [
                        'from'                         => $address_web3_order_id,
                        'to'                           => $this->get_option('admin_recieving_fund'),
                        'private_key'                  => get_post_meta($order->get_id(), 'evmtracker_web3_privateKey', true),
                        'value'                        => $post_params['value'],
                        'native_token'                 => $this->get_option('native_token'),
                        'currentcy_gas'                => $this->get_option('currentcy_gas'),
                        'currentcy_number_of_decimals' => $this->get_option('currentcy_number_of_decimals'),
                    ];

                    $response = \Evmtracker\EtherPay::send($sendParams);

                    update_status_order($response, $order, $order_amount);
                }

                $return = array(
                    'status' => 200,
                    'mess'   => 'success'
                );
                echo json_encode($return);
                exit();

            } catch (Exception $e) {
                die(get_class($e) . ': ' . $e->getMessage());
            }
        }

        private function init_evmtracker()
        {
            \Evmtracker\Evmtracker::config(
                array(
                    'auth_token'  => (empty($this->api_auth_token) ? (!empty($this->api_secret) ? $this->api_secret : '') : $this->api_auth_token),
                    'environment' => 'sandbox',
                    'user_agent'  => ('EtherPay - WooCommerce v' . WOOCOMMERCE_VERSION . ' Plugin v' . EVM_WOOCOMMERCE_VERSION)
                )
            );
        }
    }

    function add_evmtracker_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Evmtracker';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_evmtracker_gateway');

    add_filter('wc_get_template_part', 'override_woocommerce_template_part', 10, 3);
    // Override Template's.
    add_filter('woocommerce_locate_template', 'override_woocommerce_template', 10, 3);

    /**
     * Template Part's
     *
     * @param  string $template Default template file path.
     * @param  string $slug Template file slug.
     * @param  string $name Template file name.
     * @return string           Return the template part from plugin.
     */
    function override_woocommerce_template_part($template, $slug, $name)
    {
        // UNCOMMENT FOR @DEBUGGING
        // echo '<pre>';
        // echo 'template: ' . $template . '<br/>';
        // echo 'slug: ' . $slug . '<br/>';
        // echo 'name: ' . $name . '<br/>';
        // echo '</pre>';
        // Template directory.
        // E.g. /wp-content/plugins/my-plugin/woocommerce/
        $template_directory = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/templates/';
        if ($name) {
            $path = $template_directory . "{$slug}-{$name}.php";
        } else {
            $path = $template_directory . "{$slug}.php";
        }
        return file_exists($path) ? $path : $template;
    }

    /**
     * Template File
     *
     * @param  string $template Default template file  path.
     * @param  string $template_name Template file name.
     * @param  string $template_path Template file directory file path.
     * @return string                Return the template file from plugin.
     */
    function override_woocommerce_template($template, $template_name, $template_path)
    {
        // UNCOMMENT FOR @DEBUGGING
        // echo '<pre>';
        // echo 'template: ' . $template . '<br/>';
        // echo 'template_name: ' . $template_name . '<br/>';
        // echo 'template_path: ' . $template_path . '<br/>';
        // echo '</pre>';
        // Template directory.
        // E.g. /wp-content/plugins/my-plugin/woocommerce/
        $template_directory = untrailingslashit(plugin_dir_path(__FILE__)) . '/woocommerce/templates/';
        $path = $template_directory . $template_name;
        return file_exists($path) ? $path : $template;
    }

    global $evmtracker_txn_version;
    $evmtracker_txn_version = '1.0';

    function create_table_evmtracker_txn()
    {
        global $wpdb;
        global $evmtracker_txn_version;

        $table_name = $wpdb->prefix . 'evmtracker_txn';

        // Check ifnotexits
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            transaction_txn text NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            add_option('evmtracker_txn_version', $evmtracker_txn_version);
        }
    }

    if (empty(get_site_option('evmtracker_txn_version')) || (get_site_option('evmtracker_txn_version') != $evmtracker_txn_version)) {
        create_table_evmtracker_txn();
    }

    add_action('send_gas', 'send_gas_to_wallet', 10, 2);
    function send_gas_to_wallet($sendParamsGas)
    {

        \Evmtracker\EtherPay::config(
            array(
                'chain_rpc' => $sendParamsGas['chain_rpc'],
                'chain_id'  => $sendParamsGas['chain_id']
            )
        );

        $responseGas = \Evmtracker\EtherPay::send($sendParamsGas);
    }

    add_action('send_token', 'send_token_to_wallet', 10, 2);
    function send_token_to_wallet($sendParams)
    {
        \Evmtracker\EtherPay::config(
            array(
                'chain_rpc' => $sendParams['chain_rpc'],
                'chain_id'  => $sendParams['chain_id']
            )
        );

        $response = \Evmtracker\EtherPay::sendToken($sendParams);

        update_status_order($response, $sendParams['order_id'], $sendParams['order_amount']);
    }

    function update_status_order($response, $order_id, $order_amount)
    {
        $order = wc_get_order($order_id);
        if (!empty($response)) {
            if (empty($response->error)) {
                update_post_meta($order->get_id(), 'order_amounted', $order_amount);

                $residual_amount = $order->get_total() - $order_amount;
                switch ($residual_amount) {
                    case ($residual_amount <= 0):
                        //case 'paid':
                        $statusWas = "wc-" . $order->get_status();
                        $order->add_order_note(__('Payment is settled and has been credited to your EtherPay account. Purchased goods/services can be securely delivered to the customer.', 'etherpay'));
                        $order->payment_complete();

                        if ($order->get_status() === 'processing' && ($statusWas === 'wc-expired' || $statusWas === 'wc-canceled')) {
                            WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                        }
                        if (($order->get_status() === 'processing' || $order->get_status() == 'completed') && ($statusWas === 'wc-expired' || $statusWas === 'wc-canceled')) {
                            WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
                        }
                        break;
                    case ($residual_amount > 0):
                        //case 'processing':
                        $order->add_order_note(__('Partial payment', 'etherpay'));
                        $order->update_status('processing');
                        break;
                    /*
                case 'underpaid':
                    $missing_amt = number_format($cgOrder->missing_amt/100000000, 8, '.', '');
                    $order->add_order_note(__('Customer has paid via standard on-Chain, but has underpaid by ' . $missing_amt . '. Waiting on user to send the remainder before marking as PAID.', 'evmtracker'));
                    break;
                case 'expired':
                  $order->add_order_note(__('Payment expired', 'evmtracker'));
                  $order->update_status('cancelled');
                  break;
                case 'refunded':
                    $refund_id = $cgOrder->refund['id'];
                    $order->add_order_note(__('Customer has canceled the payment. Refund ID - ' . $refund_id . ' .', 'evmtracker'));
                    $order->update_status('cancelled');
                    break;
                    */
                }

                $return = array(
                    'status' => 200,
                    'mess'   => 'success'
                );
            } else {
                $return = array(
                    'status' => 201,
                    'mess'   => !empty($response->error->message) ? $response->error->message : 'error'
                );
            }
        } else {
            $return = array(
                'status' => 201,
                'mess'   => 'error'
            );
        }

        echo json_encode($return);
        exit();
    }

    function wpbootstrap_enqueue_scripts()
    {
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js', array('jquery'));
    }

    add_action('wp_enqueue_scripts', 'wpbootstrap_enqueue_scripts', 999);
    add_action('woocommerce_thankyou_etherpay', 'custom_thankyou_message');

    function custom_thankyou_message($order_id)
    {
        $order = wc_get_order($order_id);
        $evmtracker = new WC_Gateway_Evmtracker();
        $currentcy_title = $evmtracker->get_option('currentcy_title');
        $chain_id = $evmtracker->get_option('chain_id');
        $chain_code = $evmtracker->get_option('chain_code');
        $currentcy_rate = $evmtracker->get_option('currentcy_rate');
        $currentcy_code = $evmtracker->get_option('currentcy_code');
        $native_token = $evmtracker->get_option('native_token');

        $contract = 'Currency Contract: ' . $native_token;
        if (empty($native_token) || ($native_token == 'ERC20')) {
            $contract = '';
        }
        $web3_total = 0;
        if (!empty($currentcy_rate) && (float)$currentcy_rate > 0) {
            $web3_total = $order->get_total() / (float)$currentcy_rate;
        }
        $amount = $currentcy_code . ' ' . number_format($web3_total, 8, '.', '');

        $mess = "Please Send {$amount} {$currentcy_code} ({$currentcy_title}) On Chain {$chain_code} {$contract}";
        ?>

        <div style="">
            <div class="form-group">
                <p class="text-center"><b>Address</b></p>
                <p class="wallet-qr text-center" style="width: 220px; margin: 0 auto">
                    <a href="#" target="_blank">
                        <?php
                        $web3_order_id = sanitize_text_field(get_post_meta($order->get_id(), 'web3_order_id', true));
                        echo QRcode::svg($web3_order_id);
                        ?>
                    </a>
                </p>
                <br/>
                <p class="text-center"><?php echo $mess; ?></p>
            </div>

            <div class="d-none d-md-block d-lg-block">
                <div class="input-group">
                    <div class="input-group-prepend col-sm-3">
                        NetWork:
                    </div>
                    <input type="text" class="form-control" readonly value="<?php echo $currentcy_title . ' (' . $currentcy_code . ')' ?>">
                </div>
                <br>
                <div class="input-group">
                    <div class="input-group-prepend col-sm-3">
                        Wallet Address:
                    </div>
                    <input type="text" class="form-control" readonly value="<?php echo $web3_order_id ?>">
                </div>
            </div>
            <div class="d-block d-sm-none">
                <div class="form-group">
                    <label>NetWork:</label>
                    <input type="text" class="form-control" readonly value="<?php echo $currentcy_title . ' (' . $currentcy_code . ')' ?>">
                </div>
                <div class="form-group">
                    <label>Wallet Address:</label>
                    <textarea class="form-control" readonly><?php echo $web3_order_id ?></textarea>
                </div>
            </div>
            <br/>
            <p class="text-center"><img src="<?php echo $evmtracker->get_option('currentcy_logo') ?>" style="height: 40px; width: auto"></p>
        </div>
        <?php
    }
}
