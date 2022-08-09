<?php

namespace Waaz\EtransactionsPlugin\Legacy;


//TODO : A completer
interface PayBoxReturnParams {

    /**
     * Montant de la transaction (précisé dans PBX_TOTAL).
     */
    public const TRANSACTION_AMOUNT = "M";

    /**
     * Référence commande (précisée dans PBX_CMD) : espace URL encodé.
     */
    public const REFERENCE = "R";

    /**
     * Numéro d’appel.
     */
    public const PHONE = "T";

    /**
     * Numéro d’Autorisation (numéro remis par le centre d’autorisation) : URL encodé
     */
    public const AUTHORIZATION_NUMBER = "A";

    /**
     * Numéro d’abonnement (numéro remis par la plateforme).
     */
    public const SUBSCRIPTION_NUMBER = "B";

    /**
     * Type de Carte retenu (cf. PBX_TYPECARTE).
     */
    public const CARD_TYPE = "C";

    /**
     * Date de fin de validité de la carte du porteur. Format : AAMM.
     */
    public const CARD_END_VALIDITY = "D";

    /**
     * Code réponse de la transaction (cf. Tableau 2 : Codes réponse PBX_RETOUR).
     */
    public const TRANSACTION_RESPONSE_CODE = "E";

    /**
     * Etat de l’authentification du porteur vis-à-vis du programme 3-D Secure :
     * Y : Porteur authentifié
     * A : Authentification du porteur forcée par la banque de l’acheteur
     * U : L’authentification du porteur n’a pas pu s’effectuer
     * N : Porteur non authentifié
     */
    public const THREE_D_SECURE_AUTHENTICATION_STATE = "F";

    /**
     * Garantie du paiement par le programme 3-D Secure. Format : O ou N
     */
    public const THREE_D_SECURE_WARRANTY = "G";

    /**
     * Empreinte de la carte.
     */
    public const EMPREINTE_CARD = "H";

    /**
     * Code pays de l’adresse IP de l’internaute. Format : ISO 3166 (alphabétique).
     */
    public const USER_IP = "I";

    /**
     * 2 derniers chiffres du numéro de carte du porteur.
     */
    public const CARD_LAST_DIGITS = "J";

    /**
     * Signature sur les variables de l’URL. Format : url-encodé.
     */
    public const URL_SIGNATURE = "K";

}
