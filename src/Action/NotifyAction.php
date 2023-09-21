<?php

/**
 * This file was created by the developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on kontakt@bitbag.pl.
 */

namespace Waaz\EtransactionsPlugin\Action;

use Waaz\EtransactionsPlugin\Bridge\EtransactionsBridgeInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Payum\Core\Request\Notify;
use Sylius\Component\Payment\PaymentTransitions;
use Webmozart\Assert\Assert;
use SM\Factory\FactoryInterface;

/**
 * @author Patryk Drapik <patryk.drapik@bitbag.pl>
 */
final class NotifyAction implements ActionInterface, ApiAwareInterface
{
    use GatewayAwareTrait;

    private $api = [];

    /**
     * @var EtransactionsBridgeInterface
     */
    private $etransactionsBridge;

    /**
     * @var FactoryInterface
     */
    private $stateMachineFactory;

    /**
     * @param EtransactionsBridgeInterface $etransactionsBridge
     * @param FactoryInterface $stateMachineFactory
     */
    public function __construct(
        EtransactionsBridgeInterface $etransactionsBridge,
        FactoryInterface $stateMachineFactory
    )
    {
        $this->etransactionsBridge = $etransactionsBridge;
        $this->stateMachineFactory = $stateMachineFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request Notify */
        RequestNotSupportedException::assertSupports($this, $request);

        if ($this->etransactionsBridge->paymentVerification($this->api['hmac'])) {

            /** @var PaymentInterface $payment */
            $payment = $request->getFirstModel();

            Assert::isInstanceOf($payment, PaymentInterface::class);

            $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH)->apply(PaymentTransitions::TRANSITION_COMPLETE);;
        }
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
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayObject
        ;
    }
}
