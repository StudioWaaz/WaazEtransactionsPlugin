<?php

/**
 * This file was created by the developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on kontakt@bitbag.pl.
 */

namespace Waaz\EtransactionsPlugin\Action;

use Waaz\EtransactionsPlugin\Legacy\SimplePayment;
use Waaz\EtransactionsPlugin\Bridge\EtransactionsBridgeInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Security\TokenInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;
use Payum\Core\Payum;
use Sylius\Component\Core\Model\OrderInterface;


/**
 * @author Mikołaj Król <mikolaj.krol@bitbag.pl>
 * @author Patryk Drapik <patryk.drapik@bitbag.pl>
 */
final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    use GatewayAwareTrait;

    private array $api = [];

    public function __construct(private Payum $payum, private EtransactionsBridgeInterface $etransactionsBridge)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setApi($api)
    {
        if (!is_array($api)) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        /** @var PaymentInterface $payment */
        $payment = $request->getFirstModel();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();


        Assert::isInstanceOf($payment, PaymentInterface::class);

        /** @var TokenInterface $token */
        $token = $request->getToken();

        $transactionReference = $model['transactionReference'] ?? null;

        if ($transactionReference !== null) {

            if ($this->etransactionsBridge->isPostMethod()) {

                $model['status'] = $this->etransactionsBridge->paymentVerification($this->api['hmac']) ?
                    PaymentInterface::STATE_COMPLETED : PaymentInterface::STATE_CANCELLED;

                $request->setModel($model);

                return;
            }

            if ($model['status'] === PaymentInterface::STATE_COMPLETED) {

                return;
            }
        }

        $notifyToken = $this->createNotifyToken($token->getGatewayName(), $token->getDetails());

        $hmac = $this->api['hmac'];

        $etransactions = $this->etransactionsBridge->createEtransactions($hmac);

        $identifiant = $this->api['identifiant'];
        $rang = $this->api['rang'];
        $site = $this->api['site'];
        $sandbox = $this->api['sandbox'];

        $automaticResponseUrl = $notifyToken->getTargetUrl();
        $currencyCode = $payment->getCurrencyCode();
        $targetUrl = $request->getToken()->getTargetUrl();
        $customerEmail = $order->getCustomer()->getEmail();
        $amount = $payment->getAmount();
        $transactionReference = "etransactionsWS" . uniqid($payment->getOrder()->getNumber());

        $model['transactionReference'] = $transactionReference;

        $simplePayment = new SimplePayment(
            $etransactions,
            $identifiant,
            $rang,
            $site,
            $sandbox,
            $amount,
            $targetUrl,
            $currencyCode,
            $transactionReference,
            $customerEmail,
            $automaticResponseUrl
        );

        $request->setModel($model);
        $simplePayment->execute();
    }

    /**
     * @param string $gatewayName
     * @param object $model
     *
     * @return TokenInterface
     */
    private function createNotifyToken($gatewayName, $model)
    {
        return $this->payum->getTokenFactory()->createNotifyToken(
            $gatewayName,
            $model
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }
}
