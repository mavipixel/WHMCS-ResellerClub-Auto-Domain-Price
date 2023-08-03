<?php
/*
    * WHMCS ResellerClub Auto Domain Price Crons
    *
    * Turkish: WHMCS için ResellerClub Auto Domain Price.
    * Version: 1.0 (1.0release.1)
    * BuildId: 20230803.001
    * Build Date: 03 Aug 2023
    * Email: info[@]ferdiozturk.com
    * Website: https://ferdiozturk.com
    * 
    *
    * @license MIT License
*/

$dir = explode('includes', dirname(__FILE__))[0];

include_once $dir.'/init.php';

use WHMCS\Database\Capsule;

$domain_price_types = [
    'Register' => 'domainrenew',
    'Transfer' => 'domaintransfer'
];

$domain_api_price_types = [
    'domainregister' => 'addnewdomain',
    'domaintransfer' => 'addtransferdomain',
    'domainrenew'    => 'renewdomain'
];

$domain_price_periods = [
    1 => 'msetupfee',
    2 => 'qsetupfee',
    3 => 'ssetupfee',
    4 => 'asetupfee',
    5 => 'bsetupfee',
    6 => 'monthly',
    7 => 'quarterly',
    8 => 'semiannually',
    9 => 'annually',
    10 => 'biennially'
];

$domain_periods = [
    'msetupfee',
    'qsetupfee',
    'ssetupfee',
    'asetupfee',
    'bsetupfee',
    'monthly',
    'quarterly',
    'semiannually',
    'annually',
    'biennially'
];

$total_updated_client = 0;
$total_updated_domain = 0;

//Komisyon Oranı = 1.20 = %20 --- 1.30 = %30

$price_multiplier = 1.30;

$postfields = [];

//ResellerClub API Bilgileri

$postfields['auth-userid'] = 'USER-ID';
$postfields['api-key'] = 'ApiKey';

try{
   
    $try_id = Capsule::table('tblcurrencies')->where('code', 'TRY')->value('id');

    $get_domains = Capsule::table('tbldomainpricing')->get();

    $tld_details = domain_pricing_SendCommand('details', 'products', $postfields, '', 'GET', true);

    $tld_pricings = domain_pricing_SendCommand('reseller-cost-price', 'products', $postfields, '', 'GET', true);

    $domain_names = [];

    foreach($get_domains as $domain){

      foreach($tld_details as $key => $data){

          if(in_array(substr($domain->extension, 1), $data['tldlist'])) $domain_names[$domain->extension] = $key;

      }
    }
   
    foreach($get_domains as $domain){

      if(strlen($domain_names[$domain->extension]) < 1) continue;

        $get_pricings = Capsule::table('tblpricing')->where('type', 'domainregister')->orWhere('type', 'domaintransfer')->orWhere('type', 'domainrenew')->where('relid', $domain->id)->where('currency', $try_id)->get();

        foreach($get_pricings as $k => $pricing){

            foreach($pricing as $ks => $price){

                if(!in_array($ks, $domain_periods)) continue;

                if((int)$tld_pricings[$domain_names[$domain->extension]][$domain_api_price_types[$pricing->type]][strval(array_search($ks, $domain_price_periods))] > 0){

                    Capsule::table('tblpricing')->where('type', $pricing->type)->where('relid', $domain->id)->where('currency', $try_id)->where('id', $pricing->id)->update([

                        $ks => intval(floatval($tld_pricings[$domain_names[$domain->extension]][$domain_api_price_types[$pricing->type]][strval(array_search($ks, $domain_price_periods))] * $price_multiplier))

                    ]);

                }elseif(in_array($ks, $domain_periods)){

                    Capsule::table('tblpricing')->where('type', $pricing->type)->where('relid', $domain->id)->where('currency', $try_id)->where('id', $pricing->id)->update([

                        $ks => intval((floatval($tld_pricings[$domain_names[$domain->extension]][$domain_api_price_types[$pricing->type]]["1"] * $price_multiplier)) * array_search($ks, $domain_price_periods))

                    ]);
                }
            }

            $total_updated_domain++;
        }

        // Müşterilerin ürünlerini zamlandırma

        $get_accounts = Capsule::table('tbldomains')->where('domain', 'like', '%'.$domain->extension.'%')->where('status', 'Active')->get();

        foreach($get_accounts as $account){

            $control = Capsule::table('tblclients')->where('id', $account->userid)->value('currency');

            $control_tld = extractTLD($account->domain);

            if($domain->extension != $control_tld) continue;

            if($control != $try_id) continue;

            if((int)$account->registrationperiod > 10){

                $new_price = Capsule::table('tblpricing')->where('type', $domain_price_types[$account->type])->where('relid', $domain->id)->where('currency', $try_id)->value('msetupfee');

                $new_price = $new_price*$account->registrationperiod;

            }else{

                $new_price = Capsule::table('tblpricing')->where('type', $domain_price_types[$account->type])->where('relid', $domain->id)->where('currency', $try_id)->value($domain_price_periods[(int)$account->registrationperiod]);
            }
            if($new_price <= 0) continue;

            Capsule::table('tbldomains')->where('id', $account->id)->where('domain', $account->domain)->update(['recurringamount' => $new_price]);

            $total_updated_client++;

        }
    }

    $message = "Günlük alan adı fiyat güncellemesi işlemi tamamlandı. {$total_updated_domain} adet alan adının fiyatı güncellendi. Toplam {$total_updated_client} alan adı hizmetinin yineleme fiyatı güncellendi.";

    logActivity($message);

    exit($message);

}catch (Exception $e){
   
    $message = "Alan adı ücretleri güncellenirken bir hata oluştu. Hata: {$e->getMessage()}";

    logActivity($message);

    exit($message);
   
}

function domain_pricing_GetIP() {

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api1.whmcs.com/ip/get");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    $contents = curl_exec($ch);

    curl_close($ch);

    if (!empty($contents)) {

        $data = json_decode($contents, true);

        if (is_array($data) && isset($data["ip"])) {

            return $data["ip"];

        }

    }

    return "";

}

function domain_pricing_SendCommand($command, $type, $postfields, $params, $method, $jsonDecodeResult = false) {

    $url = "https://httpapi.com/api/" . $type . "/" . $command . ".json";

    $curlOptions = array();

    $callDataForLog = $curlPostData = $postfields;

    $postFieldQuery = "";

    if ($method == "GET") {

        $queryParams = "";

        foreach ($curlPostData as $field => $data) {

            if (is_array($data)) {

                foreach ($data as $subData) {

                    $queryParams .= "&" . build_query_string(array($field => $subData), PHP_QUERY_RFC3986);

                }

            } else {

                $queryParams .= "&" . build_query_string(array($field => $data), PHP_QUERY_RFC3986);

            }
        }

        if ($queryParams) {

            $url .= "?" . ltrim($queryParams, "&");
           
        }

        unset($queryParams);

        $callDataForLog["url"] = $url;

    }

    $ch = curlCall($url, $postFieldQuery, $curlOptions, true);

    $data = curl_exec($ch);

    if (curl_errno($ch)) {

        $ip = domain_pricing_GetIP();

        $ip2 = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : $_SERVER["LOCAL_ADDR"];

        $result["response"]["status"] = "ERROR";

        $result["response"]["message"] = "CURL Error: " . curl_errno($ch) . " - " . curl_error($ch) . " (IP: " . $ip . " & " . $ip2 . ")";

    } else {

        if (!$jsonDecodeResult && is_numeric($data)) {

            $result = $data;

        } else {

            $result = json_decode($data, true);

        }
    }
    curl_close($ch);

    logModuleCall("logicboxes", (string) $type . "/" . $command, $callDataForLog, $data, $result, array($params["ResellerID"], $params["APIKey"]));

    return $result;

}

function extractTLD( $domain ) {

    $productTLD = '';

    $tempstr = explode(".", $domain);

    unset($tempstr[0]);

    foreach($tempstr as $value){

        $productTLD = $productTLD.".".$value;

    }

    return $productTLD;

}
