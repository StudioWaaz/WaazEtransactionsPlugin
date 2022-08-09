<?php
namespace Waaz\EtransactionsPlugin\Legacy;

use Http\Message\MessageFactory;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\HttpClientInterface;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Reply\HttpRedirect;
use RuntimeException;

class Etransactions
{
    /**
     * Primary server.
     */
    public const MAIN_SERVER = "tpeweb.e-transactions.fr/cgi/MYchoix_pagepaiement.cgi";

    /**
     * Backup server.
     */
    public const BACKUP_SERVER = "tpeweb1.e-transactions.fr/cgi/MYchoix_pagepaiement.cgi";

    /**
     * Sandbox server.
     */
    public const SANDBOX_SERVER = "preprod-tpeweb.e-transactions.fr/cgi/MYchoix_pagepaiement.cgi";

    /**
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(protected array $options, protected HttpClientInterface $client, protected MessageFactory $messageFactory)
    {
    }


    public function doPayment(array $fields)
    {
        $fields[PayBoxRequestParams::PBX_SITE] = $this->options['site'];
        $fields[PayBoxRequestParams::PBX_RANG] = $this->options['rang'];
        $fields[PayBoxRequestParams::PBX_IDENTIFIANT] = $this->options['identifiant'];
        $fields[PayBoxRequestParams::PBX_HASH] = $this->options['hash'];
        $fields[PayBoxRequestParams::PBX_RETOUR] = $this->options['retour'];
        $fields[PayBoxRequestParams::PBX_TYPEPAIEMENT] = $this->options['type_paiement'];
        $fields[PayBoxRequestParams::PBX_TYPECARTE] = $this->options['type_carte'];
        $fields[PayBoxRequestParams::PBX_HMAC] = strtoupper($this->computeHmac($this->options['hmac'], $fields));

        $authorizeTokenUrl = $this->getAuthorizeTokenUrl();
        throw new HttpPostRedirect($authorizeTokenUrl, $fields);
    }

    /**
     * @return array
     */
    protected function doRequest($method, array $fields)
    {
        $headers = [];

        $request = $this->messageFactory->createRequest($method, $this->getApiEndpoint(), $headers, http_build_query($fields));

        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        return $response;
    }

    /**
     * Get api end point.
     * @return string server url
     * @throws RuntimeException if no server available
     */
    protected function getApiEndpoint()
    {
        $servers = [];
        if ($this->options['sandbox']) {
            $servers[] = self::SANDBOX_SERVER;
        } else {
            $servers = [self::MAIN_SERVER, self::BACKUP_SERVER];
        }

        foreach ($servers as $server) {
            $doc = new \DOMDocument();
            $doc->loadHTMLFile('https://'. $server . "/load.html");

            $element = $doc->getElementById('server_status');
            if ($element && 'OK' == $element->textContent) {
                return $server;
            }
        }

        throw new RuntimeException('No server available.');
    }

    /**
     * @return string
     */
    public function getAuthorizeTokenUrl()
    {
        return sprintf(
            'https://%s/cgi/MYchoix_pagepaiement.cgi',
            $this->getApiEndpoint()
        );
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

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

}
