<?php

namespace Evmtracker;

use stdClass;
use SWeb3\Accounts;
use SWeb3\Account;
use SWeb3\SWeb3;
use SWeb3\Utils;
use SWeb3\SWeb3_Contract;
use phpseclib\Math\BigInteger as BigNumber;

class EtherPay
{
    public static $chain_rpc = '';
    public static $chain_id = '';
    public static $gas_limit = 210000;
    public static $erc20ABI = 'bep20.abi';

    public static function config($config)
    {
        if (isset($config['chain_rpc']))
            self::$chain_rpc = $config['chain_rpc'];

        if (isset($config['chain_id']))
            self::$chain_id = $config['chain_id'];
    }

    public static function send($sendParams)
    {
        $sweb3 = new SWeb3(self::$chain_rpc);
        $sweb3->chainId = self::$chain_id;

        // Send from to
        $sweb3->setPersonalData($sendParams['from'], ltrim($sendParams['private_key'], "0x"));

        $sendParamsWeb3 = [
            'from'     => $sendParams['from'],
            'to'       => $sendParams['to'],
            //'value'    => Utils::toWei("{$sendParams['value']}", 'wei'),
            'value'    => Utils::toWeiFromDecimals("{$sendParams['value']}", (int)$sendParams['currentcy_number_of_decimals']),
            'gasLimit' => self::$gas_limit,
            'nonce'    => $sweb3->personal->getNonce()
        ];

        $response = $sweb3->send($sendParamsWeb3);

        return $response;

        /*if (!empty($response->error)) {
            return false;
        } else {
            return true;
        }
        */
    }

    public static function sendToken($params)
    {
        $sweb3 = new SWeb3(self::$chain_rpc);
        $sweb3->chainId = self::$chain_id;

        if (!empty($params['currentcy_gas'])) {
            $config = new stdClass();
            $config->personalAdress = $params['from'];
            $config->personalPrivateKey = ltrim($params['private_key'], "0x");
            $config->transferToAddress = $params['to'];

            $config->erc20Address = $params['contract_address'];
            //$stream_opts = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false,]];
            //$config->erc20ABI = file_get_contents(self::$erc20ABI, false, stream_context_create($stream_opts));
            ob_start();
            include untrailingslashit(plugin_dir_path(__FILE__)) . '/assets/resources/' . self::$erc20ABI;
            $config->erc20ABI = ob_get_clean();
            //$config->erc20ABI = \Evmtracker\Curl::get(self::$erc20ABI,  array());

            $sweb3->setPersonalData($config->personalAdress, $config->personalPrivateKey);

            $contract = new SWeb3_contract($sweb3, $config->erc20Address, $config->erc20ABI);
            //$value = Utils::toWei("{$params['value']}", 'wei');
            $value = Utils::toWeiFromDecimals("{$params['value']}", (int)$params['currentcy_number_of_decimals']);
            $extra_data = [
                'gasLimit' => self::$gas_limit,
                'nonce'    => $sweb3->personal->getNonce()
            ];

            $response = $contract->send('transfer', [$config->transferToAddress, $value], $extra_data);

            return $response;
            /*
            if (!empty($response->error)) {
                return false;
            } else {
                return true;
            }
            */
        }

        return false;
    }

    public static function balanceOf($wallet_address)
    {
        $sweb3 = new SWeb3(self::$chain_rpc);
        $sweb3->chainId = self::$chain_id;

        $config = new stdClass();
        $config->personalAdress = $wallet_address['address'];
        $config->personalPrivateKey = ltrim($wallet_address['private_key'], "0x");
        $config->erc20Address = $wallet_address['contract_address'];
        //$stream_opts = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false,]];
        //$config->erc20ABI = file_get_contents(self::$erc20ABI, false, stream_context_create($stream_opts));
        $config->erc20ABI = \Evmtracker\Curl::get(self::$erc20ABI,  array());
        $sweb3->setPersonalData($config->personalAdress, $config->personalPrivateKey);

        $contract = new SWeb3_contract($sweb3, $config->erc20Address, $config->erc20ABI);
        $response = $contract->call('balanceOf', [$config->personalAdress]);

        //print_r($res->result);die;
        if ($response->result > 0) {
            return true;
        } else {
            return false;
        }
    }
}
