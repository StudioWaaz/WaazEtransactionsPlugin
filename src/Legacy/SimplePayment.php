<?php

/**
 * This file was created by the developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on kontakt@bitbag.pl.
 */

namespace Waaz\EtransactionsPlugin\Legacy;

use Payum\Core\Reply\HttpResponse;

/**
 * @author Mikołaj Król <mikolaj.krol@bitbag.pl>
 * @author Patryk Drapik <patryk.drapik@bitbag.pl>
 */
final class SimplePayment
{
    /**
     * @param $identifiant
     * @param $rang
     * @param $amount
     * @param $targetUrl
     * @param $currency
     * @param $transactionReference
     * @param $customerEmail
     * @param $automaticResponseUrl
     * @param string $rang
     * @param string $identifiant
     * @param string $site
     * @param bool $sandbox
     * @param string $amount
     * @param string $currency
     * @param string $transactionReference
     * @param string $customerEmail
     * @param string $automaticResponseUrl
     */
    public function __construct(
        private Etransactions $etransactions,
        private $identifiant,
        private $rang,
        private $site,
        private $sandbox,
        private $amount,
        $targetUrl,
        private $currency,
        private $transactionReference,
        private $customerEmail,
        private $automaticResponseUrl
    )
    {
        $this->targetUrl = $targetUrl;
    }

    public function execute()
    {
        $this->resolveEnvironment();

        $this->etransactions->setSite($this->site);
        $this->etransactions->setRang($this->rang);
        $this->etransactions->setIdentifiant($this->identifiant);
        $this->etransactions->setAmount($this->amount);
        $this->etransactions->setCurrency($this->currency);
        $this->etransactions->setTransactionReference($this->transactionReference);
        $this->etransactions->setBillingContactEmail($this->customerEmail);
        //$this->etransactions->setInterfaceVersion(Etransactions::INTERFACE_VERSION);
        //$this->etransactions->setKeyVersion('1');
        $this->etransactions->setNormalReturnUrl($this->targetUrl);
        $this->etransactions->setHash("SHA512");
        $this->etransactions->setMerchantTransactionDateTime(date('c'));
        $this->etransactions->setAutomaticResponseUrl($this->automaticResponseUrl);

        $this->etransactions->validate();

        $response = $this->etransactions->executeRequest();

        throw new HttpResponse($response);
    }

    /**
     * @return void
     */
    private function resolveEnvironment()
    {
        if ($this->sandbox) {
            $this->etransactions->setUrl(Etransactions::TEST);
        } else {
            $this->etransactions->setUrl(Etransactions::PRODUCTION);
        }

        return;
    }
}
