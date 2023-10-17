<?php
namespace Evmtracker;

Class Curl
{
    private $url;

    function __construct()
    {
    }

    private static function _get_header()
    {
        return array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Cache-Control: no-cache',
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
            'X-MicrosoftAjax: Delta=true',
            'Content-Type: application/json',
        );
    }

    public static function post($uri, $data = array())
    {
        $url = $uri;
        $data = self::handle_data_request($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $headers = self::_get_header();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);

        $res_infor = curl_getinfo($ch);

        if ($response === FALSE && $res_infor['http_code'] !== ASTATUS_SUCCESS) {
            curl_close($ch);
        } else {
            curl_close($ch);
            return self::handle_response($response, $data);
        }
        return false;
    }

    public static function get($uri, $data = array('return_array' => true))
    {
        $url = $uri;
        //$data = $this->handle_data_request($data);
        if (!empty($data)) {
            $url = $url . '?' . http_build_query($data);
        }
        $res_headers = [];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $headers = self::_get_header();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $res_infor = curl_getinfo($ch);

        if ($response === FALSE && $res_infor['http_code'] !== ASTATUS_SUCCESS) {
            curl_close($ch);
        } else {
            curl_close($ch);
            // if (!empty($res_headers['signature'][0]) && $this->verify_signature($res_headers['signature'][0], $response)) {
            return self::handle_response($response, $data);
            // }
        }
        return false;
    }

    private static function handle_data_request($data)
    {
        return $data;
    }

    private static function handle_response($response, $data = array('return_array' => true))
    {
        if (!empty($data['return_array'])) {
            $response = json_decode($response);
        }

        return $response;
    }
}