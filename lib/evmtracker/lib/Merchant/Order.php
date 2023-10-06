<?php

namespace Evmtracker\Merchant;

use Evmtracker\Evmtracker;
use Evmtracker\Merchant;
use Evmtracker\OrderIsNotValid;
use Evmtracker\OrderNotFound;
use Evmtracker\RecordNotFound;

class Order extends Merchant
{
    private $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function toHash()
    {
        return $this->order;
    }

    public function __get($name)
    {
        return $this->order[$name];
    }

    public static function find($orderId, $options = array(), $authentication = array())
    {
        try {
            return self::findOrFail($orderId, $options, $authentication);
        } catch (OrderNotFound $e) {
            return false;
        }
    }

    public static function findOrFail($orderId, $options = array(), $authentication = array())
    {
        //  For test
        $order['status'] = 'paid';
        return new self($order);

        // For Live
        $order = Evmtracker::request('/checkouts/status/' . $orderId, 'GET', array(), $authentication);

        return new self($order);
    }

    public static function create($params, $options = array(), $authentication = array())
    {
        $evm_data = array(
            'address'             => $params['web3_order_id'],
            'transaction_watcher' => 'TO'
        );
        $params = array_merge($params, $evm_data);

        try {
            return self::createOrFail($params, $options, $authentication);
        } catch (OrderIsNotValid $e) {
            return false;
        }
    }

    public static function createOrFail($params, $options = array(), $authentication = array())
    {
        //  For test
        //$order['id'] = '0xA2959D3F95eAe5dC7D70144Ce1b73b403b7EB6E0';
        //return new self($order);

        // For Live
        $order = Evmtracker::request('/public/address/create', 'POST', $params, $authentication);

        return new self($order);
    }

    public static function createOrFailTest($params, $options = array(), $authentication = array())
    {
        $order['id'] = '0xA2959D3F95eAe5dC7D70144Ce1b73b403b7EB6E0';

        return new self($order);
    }

    public static function blockchainList($options = array(), $authentication = array())
    {
        $authentication = array_merge($authentication, ['auth_token' => true]);
        try {
            $list = Evmtracker::request('/evmBlockchain/list', 'GET', $options, $authentication);
            return new self($list);

        } catch (OrderIsNotValid $e) {
            return false;
        }
    }
}
