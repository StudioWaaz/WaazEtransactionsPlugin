<?php
namespace Waaz\EtransactionsPlugin;

use Waaz\EtransactionsPlugin\Action\ConvertPaymentAction;
use Waaz\EtransactionsPlugin\Action\CaptureAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class EtransactionsGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name' => 'etransactions',
            'payum.factory_title' => 'Etransactions',
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.http_client' => '@waaz.etransactions.bridge.etransactions_bridge',
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = array(
                'site' => '',
                'rang' => '',
                'identifiant' => '',
                'hmac' => '',
                'hash' => 'SHA512',
                'retour' => 'Mt:M;Ref:R;Auto:A;error_code:E',
                'sandbox' => false,
                'type_paiement' => '',
                'type_carte' => ''
            );
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = array('site', 'rang', 'identifiant', 'hmac');

            // $config['payum.api'] = function (ArrayObject $config) {
            //     $config->validateNotEmpty($config['payum.required_options']);

            //     return new Api((array) $config, $config['payum.http_client'], $config['httplug.message_factory']);
            // };

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                $etransactionsConfig = [
                    'site' => $config['site'],
                    'rang' => $config['rang'],
                    'identifiant' => $config['identifiant'],
                    'hmac' => $config['hmac'],
                    'sandbox' => $config['sandbox']
                ];

                return $etransactionsConfig;
            };
        }

        // $config['payum.paths'] = array_replace([
        //     'PayumPaybox' => __DIR__.'/Resources/views',
        // ], $config['payum.paths'] ?: []);
    }
}
