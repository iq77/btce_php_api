<?php

class BTCE
{
    const
        BTC_USD = 'btc_usd',
        LTC_USD = 'ltc_usd',
        LTC_BTC = 'ltc_btc',
        LTC_EUR = 'ltc_eur',
        BTC_EUR = 'btc_eur',
        EUR_USD = 'eur_usd',
        METHOD_INFO = 'getInfo',
        METHOD_TRADE = 'Trade',
        METHOD_LIST = 'OrderList',
        DIRECTION_BUY = 'buy',
        DIRECTION_SELL = 'sell',
        PUBLIC_API = 'https://btc-e.com/api/2/',
        PRIVATE_API = 'https://btc-e.com/tapi',
        API_KEY = 'ENTER-YOUR-BTC-E-PUBLIC-API-KEY-HERE',
        API_SECRET = 'ENTER-YOUR-BTC-E-PRIVATE-API-KEY-HERE';

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
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10]]);
        return json_decode(file_get_contents($url, false, $context), true);
    }

/*
 * PRIVATE API
 */

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

    public static function updateInfo() {
        $result = self::apiQuery(self::METHOD_INFO);
        if ($result) {
            $return = $result->{'return'};
            if ($return) {
                $funds = $return->{'funds'};
                if ($funds) {
                    $usd = $funds->{'usd'};
                    $btc = $funds->{'btc'};
                    $ltc = $funds->{'ltc'};
                    DB::update('wallets',"usd={$usd},btc={$btc},ltc={$ltc}");
                }
            }
        }
    }

    private function apiQuery($method, $req = []) {
        $req['method'] = $method;
        $req['nonce'] = self::nonce();
         // Generate the keyed hash value to post
        $sign = hash_hmac("sha512", http_build_query($req, '', '&'), self::API_SECRET);
         // Add to the headers
        $key = self::API_KEY;
        $headers = ["Sign: {$sign}", "Key: {$key}"];
        // Create a CURL Handler for use
        $res = self::do_post_request(self::PRIVATE_API, $req, $headers);
        $out = json_decode($res);
        if(!$out || !isset($out->{'success'}) || $out->{'success'}!=1)
            $out = false;
        return $out;
    }

    private static function do_post_request($url, $postfields, $headers) {
        $content = '';
        if (in_array('curl', get_loaded_extensions())) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_TIMEOUT        , REMOTE_TIMEOUT);
            curl_setopt($ch, CURLOPT_URL            , $url);
            curl_setopt($ch, CURLOPT_HEADER         , 0);
            curl_setopt($ch, CURLOPT_FAILONERROR    , true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION , true);
            curl_setopt($ch, CURLOPT_HTTPHEADER     , $headers);
            curl_setopt($ch, CURLOPT_USERAGENT      , 'Mozilla/4.0 (compatible; '.SITE_NAME.'; '.php_uname('s').'; PHP/'.phpversion().')');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_POST           , count($postfields));
            curl_setopt($ch, CURLOPT_POSTFIELDS     , http_build_query($postfields, '', '&'));
            $content = curl_exec($ch);
            if (curl_errno($ch)>0)
                $content = false;
            curl_close($ch);
        }
        return $content;
    }

    private static function nonce() {
    /*
     * ENTER YOUR OWN DB OR OTHER CODE TO GET UNIQUE NONCE HERE
     */
        $label = 'nonce';
        $nonce = DB::selectValue($label, $label);
        DB::update($label, "{$label} = {$label}+1");
        return $nonce;
    }

}
