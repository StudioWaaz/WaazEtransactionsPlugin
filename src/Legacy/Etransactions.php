<?php

/**
 * This file is a part of OpenSource Mercanet payment library adjusted for purposes of this project.
 * We are not the authors of the core logic of this class.
 */

namespace Waaz\EtransactionsPlugin\Legacy;

use Payum\Core\Reply\HttpPostRedirect;

/**
 * @author Mikołaj Król <mikolaj.krol@bitbag.pl> READ THE FILE HEADER.
 */
class Etransactions
{
    public const TEST = "https://preprod-tpeweb.e-transactions.fr/cgi/MYchoix_pagepaiement.cgi";
    public const PRODUCTION = "https://tpeweb.e-transactions.fr/cgi/MYchoix_pagepaiement.cgi";

    public const INTERFACE_VERSION = "IR_WS_2.17";
    public const INSTALMENT = "INSTALMENT";

    // BYPASS3DS
    public const BYPASS3DS_ALL = "ALL";
    public const BYPASS3DS_MERCHANTWALLET = "MERCHANTWALLET";

    private array $brandsmap = ['ACCEPTGIRO' => 'CREDIT_TRANSFER', 'AMEX' => 'CARD', 'BCMC' => 'CARD', 'BUYSTER' => 'CARD', 'BANK CARD' => 'CARD', 'CB' => 'CARD', 'IDEAL' => 'CREDIT_TRANSFER', 'INCASSO' => 'DIRECT_DEBIT', 'MAESTRO' => 'CARD', 'MASTERCARD' => 'CARD', 'MASTERPASS' => 'CARD', 'MINITIX' => 'OTHER', 'NETBANKING' => 'CREDIT_TRANSFER', 'PAYPAL' => 'CARD', 'PAYLIB' => 'CARD', 'REFUND' => 'OTHER', 'SDD' => 'DIRECT_DEBIT', 'SOFORT' => 'CREDIT_TRANSFER', 'VISA' => 'CARD', 'VPAY' => 'CARD', 'VISA ELECTRON' => 'CARD', 'CBCONLINE' => 'CREDIT_TRANSFER', 'KBCONLINE' => 'CREDIT_TRANSFER'];

    private $pspURL = self::TEST;

    private $responseData;

    private array $parameters = [];

    private array $pspFields = ['amount', 'cardExpiryDate', 'cardNumber', 'cardCSCValue', 'currencyCode', 'merchantId', 'interfaceVersion', 'sealAlgorithm', 'transactionReference', 'keyVersion', 'paymentMeanBrand', 'customerLanguage', 'billingAddress.city', 'billingAddress.company', 'billingAddress.country', 'billingAddress', 'billingAddress.postBox', 'billingAddress.state', 'billingAddress.street', 'billingAddress.streetNumber', 'billingAddress.zipCode', 'billingContact.email', 'billingContact.firstname', 'billingContact.gender', 'billingContact.lastname', 'billingContact.mobile', 'billingContact.phone', 'customerAddress', 'customerAddress.city', 'customerAddress.company', 'customerAddress.country', 'customerAddress.postBox', 'customerAddress.state', 'customerAddress.street', 'customerAddress.streetNumber', 'customerAddress.zipCode', 'customerEmail', 'customerContact', 'customerContact.email', 'customerContact.firstname', 'customerContact.gender', 'customerContact.lastname', 'customerContact.mobile', 'customerContact.phone', 'customerContact.title', 'expirationDate', 'automaticResponseUrl', 'templateName', 'paymentMeanBrandList', 'instalmentData.number', 'instalmentData.datesList', 'instalmentData.transactionReferencesList', 'instalmentData.amountsList', 'paymentPattern', 'captureDay', 'captureMode', 'merchantTransactionDateTime', 'fraudData.bypass3DS', 'seal', 'orderChannel', 'orderId', 'returnContext', 'transactionOrigin', 'merchantWalletId', 'paymentMeanId'];

    private array $requiredFields = [
        PayBoxRequestParams::PBX_SITE,
        PayBoxRequestParams::PBX_RANG,
        PayBoxRequestParams::PBX_IDENTIFIANT,
        PayBoxRequestParams::PBX_TOTAL,
        PayBoxRequestParams::PBX_DEVISE,
        PayBoxRequestParams::PBX_CMD,
        PayBoxRequestParams::PBX_PORTEUR,
        PayBoxRequestParams::PBX_RETOUR,
        PayBoxRequestParams::PBX_HASH,
        PayBoxRequestParams::PBX_TIME,
        PayBoxRequestParams::PBX_REPONDRE_A,
    ];


    public $allowedlanguages = ['nl', 'fr', 'de', 'it', 'es', 'cy', 'en'];

    private static array $currencies = ['EUR' => '978', 'USD' => '840', 'CHF' => '756', 'GBP' => '826', 'CAD' => '124', 'JPY' => '392', 'MXP' => '484', 'TRY' => '949', 'AUD' => '036', 'NZD' => '554', 'NOK' => '578', 'BRC' => '986', 'ARP' => '032', 'KHR' => '116', 'TWD' => '901', 'SEK' => '752', 'DKK' => '208', 'KRW' => '410', 'SGD' => '702', 'XPF' => '953', 'XOF' => '952'];

    public static function convertCurrencyToCurrencyCode($currency)
    {
        if (!in_array($currency, array_keys(self::$currencies)))
            throw new \InvalidArgumentException("Unknown currencyCode $currency.");
        return self::$currencies[$currency];
    }

    public static function convertCurrencyCodeToCurrency($code)
    {
        if (!in_array($code, array_values(self::$currencies)))
            throw new \InvalidArgumentException("Unknown Code $code.");
        return array_search($code, self::$currencies);
    }

    public static function getCurrencies()
    {
        return self::$currencies;
    }

    /**
     * @param ShaComposer $hmac
     */
    public function __construct(private $hmac)
    {
    }

    /** @return string */
    public function getUrl()
    {
        return $this->pspURL;
    }

    public function setUrl($pspUrl)
    {
        $this->validateUri($pspUrl);
        $this->pspURL = $pspUrl;
    }

    public function setNormalReturnUrl($url)
    {
        $this->validateUri($url);
        $this->parameters[PayBoxRequestParams::PBX_RETOUR] = 'Mt:M;Ref:R;Auto:A;error_code:E';
    }

    public function setAutomaticResponseUrl($url)
    {
        $this->validateUri($url);
        $this->parameters[PayBoxRequestParams::PBX_REPONDRE_A] = $url;
    }

    public function setTransactionReference($transactionReference)
    {
        if (preg_match('/[^a-zA-Z0-9_-]/', $transactionReference)) {
            throw new \InvalidArgumentException("TransactionReference cannot contain special characters");
        }
        $this->parameters[PayBoxRequestParams::PBX_CMD] = $transactionReference;
    }

    /**
     * Set amount in cents, eg EUR 12.34 is written as 1234
     */
    public function setAmount($amount)
    {
        if (!is_int($amount)) {
            throw new \InvalidArgumentException("Integer expected. Amount is always in cents");
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Amount must be a positive number");
        }
        $this->parameters[PayBoxRequestParams::PBX_TOTAL] = $amount;

    }

    public function setIdentifiant($identifiant)
    {
        $this->parameters[PayBoxRequestParams::PBX_IDENTIFIANT] = $identifiant;
    }

    public function setRang($rang)
    {
        $this->parameters[PayBoxRequestParams::PBX_RANG] = $rang;
    }

    public function setSite($site)
    {
        $this->parameters[PayBoxRequestParams::PBX_SITE] = $site;
    }

    // public function setKeyVersion($keyVersion)
    // {
    //     $this->parameters['keyVersion'] = $keyVersion;
    // }

    public function setCurrency($currency)
    {
        if (!array_key_exists(strtoupper($currency), self::getCurrencies())) {
            throw new \InvalidArgumentException("Unknown currency");
        }
        $this->parameters[PayBoxRequestParams::PBX_DEVISE] = self::convertCurrencyToCurrencyCode($currency);
    }

    // public function setLanguage($language)
    // {
    //     if (!in_array($language, $this->allowedlanguages)) {
    //         throw new \InvalidArgumentException("Invalid language locale");
    //     }
    //     $this->parameters['customerLanguage'] = $language;
    // }

    // public function setCustomerEmail($email)
    // {
    //     $this->parameters['customerEmail'] = $email;
    // }

    // public function setPaymentBrand($brand)
    // {
    //     $this->parameters['paymentMeanBrandList'] = '';
    //     if (!array_key_exists(strtoupper($brand), $this->brandsmap)) {
    //         throw new \InvalidArgumentException("Unknown Brand [$brand].");
    //     }
    //     $this->parameters['paymentMeanBrandList'] = strtoupper($brand);
    // }

    public function setBillingContactEmail($email)
    {
        if (strlen($email) > 50) {
            throw new \InvalidArgumentException("Email is too long");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email is invalid");
        }
        $this->parameters[PayBoxRequestParams::PBX_PORTEUR] = $email;
    }

    // public function setBillingAddressStreet($street)
    // {
    //     if (strlen($street) > 35) {
    //         throw new \InvalidArgumentException("street is too long");
    //     }
    //     $this->parameters['billingAddress.street'] = \Normalizer::normalize($street);
    // }

    // public function setBillingAddressStreetNumber($nr)
    // {
    //     if (strlen($nr) > 10) {
    //         throw new \InvalidArgumentException("streetNumber is too long");
    //     }
    //     $this->parameters['billingAddress.streetNumber'] = \Normalizer::normalize($nr);
    // }

    // public function setBillingAddressZipCode($zipCode)
    // {
    //     if (strlen($zipCode) > 10) {
    //         throw new \InvalidArgumentException("zipCode is too long");
    //     }
    //     $this->parameters['billingAddress.zipCode'] = \Normalizer::normalize($zipCode);
    // }

    // public function setBillingAddressCity($city)
    // {
    //     if (strlen($city) > 25) {
    //         throw new \InvalidArgumentException("city is too long");
    //     }
    //     $this->parameters['billingAddress.city'] = \Normalizer::normalize($city);
    // }

    // public function setBillingContactPhone($phone)
    // {
    //     if (strlen($phone) > 30) {
    //         throw new \InvalidArgumentException("phone is too long");
    //     }
    //     $this->parameters['billingContact.phone'] = $phone;
    // }

    // public function setBillingContactFirstname($firstname)
    // {
    //     $this->parameters['billingContact.firstname'] = str_replace(array("'", '"'), '', \Normalizer::normalize($firstname)); // replace quotes
    // }

    // public function setBillingContactLastname($lastname)
    // {
    //     $this->parameters['billingContact.lastname'] = str_replace(array("'", '"'), '', \Normalizer::normalize($lastname)); // replace quotes
    // }

    // public function setCaptureDay($number)
    // {
    //     if (strlen($number) > 2) {
    //         throw new \InvalidArgumentException("captureDay is too long");
    //     }
    //     $this->parameters['captureDay'] = $number;
    // }

    // public function setCaptureMode($value)
    // {
    //     if (strlen($value) > 20) {
    //         throw new \InvalidArgumentException("captureMode is too long");
    //     }
    //     $this->parameters['captureMode'] = $value;
    // }

    public function setMerchantTransactionDateTime($value)
    {
        if (strlen($value) > 25) {
            throw new \InvalidArgumentException("merchantTransactionDateTime is too long");
        }
        $this->parameters[PayBoxRequestParams::PBX_TIME] = $value;
    }

    // public function setInterfaceVersion($value)
    // {
    //     $this->parameters['interfaceVersion'] = $value;
    // }

    public function setHash($value)
    {
        $this->parameters[PayBoxRequestParams::PBX_HASH] = $value;
    }

    // public function setOrderChannel($value)
    // {
    //     if (strlen($value) > 20) {
    //         throw new \InvalidArgumentException("orderChannel is too long");
    //     }
    //     $this->parameters['orderChannel'] = $value;
    // }

    // public function setOrderId($value)
    // {
    //     if (strlen($value) > 32) {
    //         throw new \InvalidArgumentException("orderId is too long");
    //     }
    //     $this->parameters['orderId'] = $value;
    // }

    // public function setReturnContext($value)
    // {
    //     if (strlen($value) > 255) {
    //         throw new \InvalidArgumentException("returnContext is too long");
    //     }
    //     $this->parameters['returnContext'] = $value;
    // }

    // public function setTransactionOrigin($value)
    // {
    //     if (strlen($value) > 20) {
    //         throw new \InvalidArgumentException("transactionOrigin is too long");
    //     }
    //     $this->parameters['transactionOrigin'] = $value;
    // }

    // // Methodes liees a la carte
    // public function setCardNumber($number)
    // {
    //     if (strlen($number) > 19) {
    //         throw new \InvalidArgumentException("cardNumber is too long");
    //     }
    //     if (strlen($number) < 12) {
    //         throw new \InvalidArgumentException("cardNumber is too short");
    //     }
    //     $this->parameters['cardNumber'] = $number;
    // }

    // public function setCardExpiryDate($date)
    // {
    //     if (strlen($date) != 6) {
    //         throw new \InvalidArgumentException("cardExpiryDate value is invalid");
    //     }
    //     $this->parameters['cardExpiryDate'] = $date;
    // }

    // public function setCardCSCValue($value)
    // {
    //     if (strlen($value) > 4) {
    //         throw new \InvalidArgumentException("cardCSCValue value is invalid");
    //     }
    //     $this->parameters['cardCSCValue'] = $value;
    // }

    // // Methodes liees a la lutte contre la fraude

    // public function setFraudDataBypass3DS($value)
    // {
    //     if (strlen($value) > 128) {
    //         throw new \InvalidArgumentException("fraudData.bypass3DS is too long");
    //     }
    //     $this->parameters['fraudData.bypass3DS'] = $value;
    // }

    // // Methodes liees au paiement one-click

    // public function setMerchantWalletId($wallet)
    // {
    //     if (strlen($wallet) > 21) {
    //         throw new \InvalidArgumentException("merchantWalletId is too long");
    //     }
    //     $this->parameters['merchantWalletId'] = $wallet;
    // }

    // public function setPaymentMeanId($value)
    // {
    //     if (strlen($value) > 6) {
    //         throw new \InvalidArgumentException("paymentMeanId is too long");
    //     }
    //     $this->parameters['paymentMeanId'] = $value;
    // }

    // // Methodes liees au paiement en n-fois

    // public function setInstalmentDataNumber($number)
    // {
    //     if (strlen($number) > 2) {
    //         throw new \InvalidArgumentException("instalmentData.number is too long");
    //     }
    //     if (($number < 2) || ($number > 50)) {
    //         throw new \InvalidArgumentException("instalmentData.number invalid value : value must be set between 2 and 50");
    //     }
    //     $this->parameters['instalmentData.number'] = $number;
    // }

    // public function setInstalmentDatesList($datesList)
    // {
    //     $this->parameters['instalmentData.datesList'] = $datesList;
    // }

    // public function setInstalmentDataTransactionReferencesList($transactionReferencesList)
    // {
    //     $this->parameters['instalmentData.transactionReferencesList'] = $transactionReferencesList;
    // }

    // public function setInstalmentDataAmountsList($amountsList)
    // {
    //     $this->parameters['instalmentData.amountsList'] = $amountsList;
    // }

    // public function setPaymentPattern($paymentPattern)
    // {
    //     $this->parameters['paymentPattern'] = $paymentPattern;
    // }

    // public function __call($method, $args)
    // {
    //     if (substr($method, 0, 3) == 'set') {
    //         $field = lcfirst(substr($method, 3));
    //         if (in_array($field, $this->pspFields)) {
    //             $this->parameters[$field] = $args[0];
    //             return;
    //         }
    //     }

    //     if (substr($method, 0, 3) == 'get') {
    //         $field = lcfirst(substr($method, 3));
    //         if (array_key_exists($field, $this->parameters)) {
    //             return $this->parameters[$field];
    //         }
    //     }

    //     throw new \BadMethodCallException("Unknown method $method");
    // }

    public function toArray()
    {
        ksort($this->parameters);
        return $this->parameters;
    }

    public function validate()
    {
        foreach ($this->requiredFields as $field) {
            if (empty($this->parameters[$field])) {
                throw new \RuntimeException($field . " can not be empty");
            }
        }
    }

    protected function validateUri($uri)
    {
        if (!filter_var($uri, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Uri is not valid");
        }
        if (strlen($uri) > 200) {
            throw new \InvalidArgumentException("Uri is too long");
        }
    }

    // Traitement des reponses de Mercanet
    // -----------------------------------

    /** @var string */
    public const SHASIGN_FIELD = "SEAL";

    /** @var string */
    public const DATA_FIELD = "DATA";

    private ?string $shaSign = null;

    private $dataString;

    private $responseRequest;

    private $parameterArray;

    /**
     * Filter http request parameters
     * @return array
     */
    private function filterRequestParameters(array $httpRequest)
    {
        //filter request for Sips parameters
        if (!array_key_exists(self::DATA_FIELD, $httpRequest) || $httpRequest[self::DATA_FIELD] == '') {
            throw new \InvalidArgumentException('Data parameter not present in parameters.');
        }
        $parameters = [];
        $this->responseData = $httpRequest[self::DATA_FIELD];
        $dataString = $httpRequest[self::DATA_FIELD];
        $this->dataString = $dataString;
        $dataParams = explode('|', $dataString);
        foreach ($dataParams as $dataParamString) {
            $dataKeyValue = explode('=', $dataParamString, 2);
            $parameters[$dataKeyValue[0]] = $dataKeyValue[1];
        }

        return $parameters;
    }

    public function getSeal()
    {
        return $this->shaSign;
    }

    private function extractShaSign(array $parameters)
    {
        if (!array_key_exists(self::SHASIGN_FIELD, $parameters) || $parameters[self::SHASIGN_FIELD] == '') {
            throw new \InvalidArgumentException('SHASIGN parameter not present in parameters.');
        }

        return $parameters[self::SHASIGN_FIELD];
    }

    /**
     * Checks if the response is valid
     * @return bool
     */
    public function isValid($post_data, $ip)
    {
        $ip = str_replace('::ffff:', '', $ip); //ipv4 format
        if (($post_data === null || $post_data['error_code'] == '00000') && in_array($ip, ['195.101.99.73', '195.101.99.76', '194.2.160.69', '194.2.160.76', '195.25.7.158', '195.25.7.149', '194.2.122.158', '194.2.122.190', '195.101.99.76', '195.25.67.22', '195.25.7.166', '195.101.99.67', '194.2.160.81', '194.2.160.89', '195.25.67.9', '195.25.67.1', '195.25.7.145', '194.2.160.90', '195.25.67.10']))
        {
          return true;
        }

        return false;
    }

    function getXmlValueByTag($inXmlset, $needle)
    {
        $tagValue = null;
        $resource = xml_parser_create();//Create an XML parser
        xml_parse_into_struct($resource, $inXmlset, $outArray);// Parse XML data into an array structure
        xml_parser_free($resource);//Free an XML parser
        for ($i = 0; $i < (is_countable($outArray) ? count($outArray) : 0); $i++) {
            if ($outArray[$i]['tag'] == strtoupper($needle)) {
                $tagValue = $outArray[$i]['value'];
            }
        }
        return $tagValue;
    }

    /**
     * Retrieves a response parameter
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function getParam($key)
    {
        return $this->parameterArray[$key];
    }

    public function getResponseRequest()
    {
        return $this->responseRequest;
    }

     /**
     * @param $hmac string hmac key
     * @param $fields array fields
     * @return string
     */
    protected function computeHmac($hmac, $fields)
    {
        // Si la clé est en ASCII, On la transforme en binaire
        $binKey = pack("H*", $hmac);
        $msg = self::stringify($fields);

        return strtoupper(hash_hmac($fields[PayBoxRequestParams::PBX_HASH], $msg, $binKey));
    }

    /**
     * Makes an array of parameters become a querystring like string.
     *
     *
     * @return string
     */
    static public function stringify(array $array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = sprintf('%s=%s', $key, $value);
        }
        return implode('&', $result);
    }

    public function executeRequest()
    {
        $fields = [];

        foreach ($this->requiredFields as $key) {
            $fields[$key] = $this->parameters[$key];
        }

        $fields[PayBoxRequestParams::PBX_HMAC] = strtoupper($this->computeHmac($this->hmac, $fields));

        throw new HttpPostRedirect($this->getUrl(), $fields);
    }


}
