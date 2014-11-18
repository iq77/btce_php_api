<?php

class BTCE
{
    const
        METHOD_INFO = 'getInfo',
        METHOD_TRADE = 'Trade',
        METHOD_LIST_ACTIVE = 'ActiveOrders',
        METHOD_CANCEL = 'CancelOrder',
        DIRECTION_BUY = 'buy',
        DIRECTION_SELL = 'sell',
        PUBLIC_API = 'https://btc-e.com/api/2/',
        PRIVATE_API = 'https://btc-e.com/tapi',
        API_KEY = 'ENTER-YOUR-BTC-E-PUBLIC-API-KEY-HERE',
        API_SECRET = 'ENTER-YOUR-BTC-E-PRIVATE-API-KEY-HERE',
        USER_AGENT_NAME = 'BTC-E PHP API';

/*
 * PUBLIC API
 */
    public static function getPairFee($pair) {
        $api_url = self::PUBLIC_API;
        return self::getJSON("{$api_url}{$pair}/fee");
    }

    public static function getPairTicker($pair) {
        $api_url = self::PUBLIC_API;
        return self::getJSON("{$api_url}{$pair}/ticker");
    }

    public static function getPairTrades($pair) {
        $api_url = self::PUBLIC_API;
        return self::getJSON("{$api_url}{$pair}/trades");
    }

    public static function getPairDepth($pair) {
        $api_url = self::PUBLIC_API;
        return self::getJSON("{$api_url}{$pair}/depth");
    }

    private static function getJSON($url) {
        return json_decode(self::get_remote($url), true);
    }

/*
 * PRIVATE API
 */

    public static function cancelBuyOrders() {
        $count = 0;
        $active_orders = self::apiQuery(self::METHOD_LIST_ACTIVE);
        if ($active_orders) {
            $success = $active_orders->{'success'};
            if ($success == YES) {
                $orderlist = get_object_vars($active_orders->{'return'});
                foreach($orderlist as $varname=>$value) {
                    $obj = $value;
                    if ($obj->{'type'}=='buy') {
                        self::apiQuery(self::METHOD_CANCEL, ['order_id'=>$varname]);
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    public static function placeOrder($amount, $pair, $direction, $price) {
        $result = self::apiQuery(self::METHOD_TRADE , ['pair'=>$pair, 'type'=>$direction, 'rate'=>$price, 'amount'=>$amount]);
        $out = false;
        if ($result) {
            $success = $result->{'success'};
            if ($success == YES)
                $out = true;
        }
        return $out;
    }

    public static function getInfo() {
        return self::apiQuery(self::METHOD_INFO);
    }


/*
 * subroutines
 */

    private function apiQuery($method, $req = []) {
        $req['method'] = $method;
        $req['nonce'] = self::nonce();
        $sign = hash_hmac("sha512", http_build_query($req, '', '&'), self::API_SECRET);
        $key = self::API_KEY;
        $headers = ["Sign: {$sign}", "Key: {$key}"];
        $result = self::get_remote(self::PRIVATE_API, $req, $headers);
        $out = json_decode($result);
        if(!$out || !isset($out->{'success'}) || $out->{'success'}!=1)
            $out = false;
        return $out;
    }

    private static function get_remote($url, $postfields=null, $headers=null) {
        $content = '';
        if (in_array('curl', get_loaded_extensions())) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_TIMEOUT        , REMOTE_TIMEOUT);
            curl_setopt($ch, CURLOPT_URL            , $url);
            curl_setopt($ch, CURLOPT_HEADER         , false);
            curl_setopt($ch, CURLOPT_FAILONERROR    , true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION , true);
            if (!is_null($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER , $headers);
            }
            curl_setopt($ch, CURLOPT_USERAGENT      , 'Mozilla/4.0 (compatible; '.self::USER_AGENT_NAME.'; '.php_uname('s').'; PHP/'.phpversion().')');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
            if (is_array($postfields)) {
                curl_setopt($ch, CURLOPT_POST       , count($postfields));
                curl_setopt($ch, CURLOPT_POSTFIELDS , http_build_query($postfields, '', '&'));
            }
            $content = curl_exec($ch);
            if (curl_errno($ch)>0)
                $content = false;
            curl_close($ch);
        }
        return $content;
    }


    private static function nonce() {
    /*
     * add your own code to create a unique noonce integer value here
     * the value has to be unique for each api call sent through apiQuery()
     */
        $label = 'nonce';
        $nonce = DB::selectValue($label, $label);
        DB::update($label, "{$label} = {$label}+1");
        return $nonce;
    }

}
