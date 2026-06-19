#!/usr/bin/env python3
import os
import re
import ast
import struct
import unicodedata
from datetime import datetime, timezone

PROJECT_VERSION = "helloasso-payment-processor 2.1.0-alpha1"
TRANSLATOR = "Jonathan Dahan <jonathan@dhn.one>"

# English source strings mapped to the reviewed French user interface wording.
FRENCH_TRANSLATIONS = {
    "Standard frontend bridge (mjwshared)": "Pont d'intégration standard (mjwshared)",
    "Enable the standard HelloAsso frontend integration based on CRM.payment and mjwshared.": "Active l'intégration frontend standard HelloAsso basée sur CRM.payment et mjwshared.",
    "Secure handling of failure and cancellation URLs": "Gestion sécurisée des URL d'échec et d'annulation",
    "Avoid redirecting users to fragile CiviCRM or AJAX URLs when a payment is cancelled or fails.": "Évite de renvoyer les utilisateurs vers des URL CiviCRM ou AJAX fragiles lorsqu'un paiement est annulé ou échoue.",
    "Webhook processing queue": "File d'attente pour le traitement des webhooks",
    "Place HelloAsso webhooks in the PaymentprocessorWebhook queue instead of processing them immediately.": "Place les webhooks HelloAsso dans la file PaymentprocessorWebhook au lieu de les traiter immédiatement.",
    "Payment status reliability for automations (T+5 / T+15 min)": "Fiabilisation du statut pour les automatisations (T+5 / T+15 min)",
    "Enable automatic checks at T+5 and T+15 after creation of a HelloAsso checkout. Long-term monitoring of later changes, including refunds, remains independent.": "Active les contrôles automatiques à T+5 puis T+15 après création d'un checkout HelloAsso. Le suivi long des changements ultérieurs, notamment les remboursements, reste indépendant.",
    "Afform / Form Builder integration": "Intégration Afform / Form Builder",
    "Publish a HelloAsso Checkout Option for Afform / Form Builder based on the CiviCRM core checkout mechanism.": "Publie une Checkout Option HelloAsso pour Afform / Form Builder en s'appuyant sur le mécanisme de checkout du core CiviCRM.",
    "Enable HelloAsso refunds": "Activer les remboursements HelloAsso",
    "Allow CiviCRM users with refund permissions to request full HelloAsso refunds from the payment refund screen. Refunds require the HelloAsso authorization-screen mode. HelloAsso partial refunds remain unsupported by this integration.": "Autorise les utilisateurs CiviCRM disposant des droits de remboursement à demander des remboursements complets HelloAsso depuis l'écran de remboursement. Les remboursements nécessitent le mode mire HelloAsso. Les remboursements partiels HelloAsso restent non pris en charge par cette intégration.",
    "HelloAsso redirect message on standard forms": "Message de redirection HelloAsso sur les formulaires classiques",
    "Message displayed on standard contribution and event forms when the selected payment processor is HelloAsso.": "Message affiché sur les formulaires classiques de contribution et d'inscription à un événement lorsque le processeur de paiement sélectionné est HelloAsso.",
    "Number of installments": "Nombre d'échéances",
    "One-time payment": "Paiement en une fois",
    "Choose a one-time payment or a fixed schedule of 2 to 12 monthly payments.": "Choisissez un paiement en une fois ou un échéancier fixe de 2 à 12 mensualités.",
    "Maximum processing batch size (Cron)": "Taille maximale des lots de traitement (Cron)",
    "Maximum number of HelloAsso contributions processed per payment processor during a normal cron execution.": "Nombre maximum de contributions HelloAsso traitées par processeur pendant une exécution normale du cron.",
    "Strict legacy signature verification": "Vérification stricte de la signature historique",
    "Reject HelloAsso webhooks whose legacy invoiceID/sig signature is missing or invalid.": "Refuse les webhooks HelloAsso dont la signature legacy invoiceID/sig est absente ou invalide.",
    "Strict partner signature verification": "Vérification stricte de la signature partenaire",
    "Reject HelloAsso partner webhooks whose x-ha-signature header is missing or invalid when a webhook signature key is stored for this processor.": "Refuse les webhooks partenaire HelloAsso dont le header x-ha-signature est absent ou invalide quand une clé de signature webhook est enregistrée pour ce processeur.",
    "HelloAsso authorization screen: enable shared connection": "Mire HelloAsso : activer la connexion partagée",
    "Enable the shared HelloAsso OAuth authorization screen. When this setting is disabled, the authorization-screen interface is no longer offered on HelloAsso processor pages.": "Active la mire OAuth HelloAsso partagée. Quand ce réglage est désactivé, l'interface mire n'est plus proposée sur les pages processeur HelloAsso.",
    "Client Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le Client ID n'est pas renseigné sur cette passerelle de paiement.",
    "Client Secret Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le Client Secret n'est pas renseigné sur cette passerelle de paiement.",
    "HelloAsso authorization screen: production client ID": "Mire HelloAsso : client ID production",
    "Client ID dedicated to the HelloAsso production authorization screen.": "Client ID dédié à la mire HelloAsso production.",
    "HelloAsso authorization screen: sandbox client ID": "Mire HelloAsso : client ID bac à sable",
    "Client ID dedicated to the HelloAsso sandbox authorization screen.": "Client ID dédié à la mire HelloAsso bac à sable.",
    "HelloAsso authorization screen: production client secret": "Mire HelloAsso : client secret production",
    "Client secret dedicated to the HelloAsso production authorization screen.": "Client secret dédié à la mire HelloAsso production.",
    "HelloAsso authorization screen: sandbox client secret": "Mire HelloAsso : client secret bac à sable",
    "Client secret dedicated to the HelloAsso sandbox authorization screen.": "Client secret dédié à la mire HelloAsso bac à sable.",
    "HelloAsso authorization screen: authorization URL": "Mire HelloAsso : URL d'autorisation",
    "HelloAsso authorization screen URL. In production, the default value is https://auth.helloasso.com/authorize.": "URL de l'écran d'autorisation HelloAsso. En production, la valeur par défaut est https://auth.helloasso.com/authorize.",
    "HelloAsso authorization screen: token URL": "Mire HelloAsso : URL du token",
    "HelloAsso endpoint used to exchange the authorization code and refresh OAuth tokens.": "Endpoint HelloAsso utilisé pour échanger le code d'autorisation et rafraîchir les jetons OAuth.",
    "Classic API key": "Clé API classique",
    "HelloAsso sandbox authorization screen": "Mire HelloAsso bac à sable",
    "HelloAsso production authorization screen": "Mire HelloAsso production",
    "Connect production to HelloAsso": "Connecter la production à HelloAsso",
    "Connect sandbox to HelloAsso": "Connecter le bac à sable à HelloAsso",
    "Connect to HelloAsso": "Connecter à HelloAsso",
    "HelloAsso production connection": "Connexion HelloAsso production",
    "HelloAsso sandbox connection": "Connexion HelloAsso bac à sable",
    "Live payment processor ID": "ID du processeur live",
    "Sandbox payment processor ID": "ID du processeur bac à sable",
    "Live connection mode": "Mode de connexion live",
    "Sandbox connection mode": "Mode de connexion bac à sable",
    "Automatically enable the webhook": "Activer automatiquement le webhook",
    "Enable automatic registration of the live HelloAsso webhook for this CiviCRM instance by default. Uncheck only if another instance retains control of the webhook URL.": "Active par défaut l'enregistrement automatique du webhook HelloAsso live pour cette instance CiviCRM. Décochez seulement si une autre instance garde la maîtrise de l'URL webhook.",
    "Enable automatic registration of the HelloAsso webhook for this CiviCRM instance by default. Uncheck only if multiple CiviCRM instances share the same HelloAsso organization and you want to manage the webhook manually.": "Active par défaut l'enregistrement automatique du webhook HelloAsso pour cette instance CiviCRM. Décochez seulement si plusieurs instances CiviCRM partagent la même organisation HelloAsso et que vous voulez gérer le webhook manuellement.",
    "Production authorization-screen mode is locked while classic live API credentials are still stored on this payment processor.": "Le mode mire production est verrouillé tant que des identifiants API live classiques sont encore enregistrés sur ce processeur de paiement.",
    "Production authorization-screen mode is locked until the production client ID and client secret are configured.": "Le mode mire production est verrouillé tant que le client ID et le client secret de production ne sont pas configurés.",
    "This block connects the production HelloAsso authorization screen on this live processor: OAuth link, linked organization and webhook registration. The live payment rail can switch to the authorization screen only when this processor no longer uses classic API keys.": "Ce bloc permet de connecter la mire HelloAsso production sur ce processeur live : liaison OAuth, organisation liée et enregistrement du webhook. Le rail de paiement live ne peut basculer sur la mire que si ce processeur n'utilise plus de clés API classiques.",
    "Live API credentials are still present on this processor. The production authorization screen can be linked and tested, but the live payment mode remains locked to the classic API key until these credentials are removed.": "Des identifiants API live sont encore présents sur ce processeur. La mire production peut être reliée et testée, mais le mode de paiement live reste bloqué sur la clé API classique tant que ces identifiants ne sont pas retirés.",
    "The production authorization-screen option is greyed out because this live processor still contains classic API credentials. You can connect and test the authorization screen first, then remove the live API key fields from this processor and save the processor. The authorization-screen option will become selectable once those fields are empty.": "L'option mire production est grisée parce que ce processeur live contient encore des identifiants API classiques. Vous pouvez d'abord connecter et tester la mire, puis retirer les champs de clé API live de ce processeur et enregistrer le processeur. L'option mire deviendra sélectionnable une fois ces champs vides.",
    "The production authorization-screen option is also greyed out because the production client ID and client secret are not configured yet.": "L'option mire production est aussi grisée parce que le client ID et le client secret de production ne sont pas encore configurés.",
    "No live API key is stored on this processor. You can therefore enable production authorization-screen mode on this processor once the OAuth link has been validated.": "Aucune clé API live n'est enregistrée sur ce processeur. Vous pouvez donc activer le mode mire production sur ce processeur si la liaison OAuth a été validée.",
    "Production organization linked: %1": "Organisation production liée : %1",
    "Linked on: %1": "Liée le : %1",
    "Access token valid until: %1": "Jeton d'accès valable jusqu'au : %1",
    "Authorization link valid until: %1": "Liaison d'autorisation valable jusqu'au : %1",
    "Webhook management: %1": "Gestion du webhook : %1",
    "Registered webhook URL: %1": "URL webhook enregistrée : %1",
    "No HelloAsso production organization is linked to this processor yet.": "Aucune organisation HelloAsso production n'est encore liée à ce processeur.",
    "HelloAsso Organization Name is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le nom de l'organisation HelloAsso n'est pas renseigné sur cette passerelle de paiement.",
    "Enter the shared client ID and client secret on the authorization-screen settings page first, then return to start the connection.": "Renseignez d'abord le client ID et le client secret partagés sur la page de réglages de la mire, puis revenez lancer la connexion.",
    "Open production authorization-screen settings": "Ouvrir les réglages de la mire production",
    "Sandbox API credentials are already present on this processor. API key mode remains the safest choice until you explicitly switch.": "Des identifiants API bac à sable sont déjà présents sur ce processeur. Le mode par clé API reste le choix le plus prudent tant que vous ne basculez pas explicitement.",
    "No sandbox API key is stored on this processor. The HelloAsso sandbox authorization screen is therefore offered by default.": "Aucune clé API bac à sable n'est enregistrée sur ce processeur. La mire HelloAsso bac à sable est donc proposée par défaut.",
    "Sandbox organization linked: %1": "Organisation bac à sable liée : %1",
    "No HelloAsso sandbox organization is linked to this processor yet.": "Aucune organisation HelloAsso bac à sable n'est encore liée à ce processeur.",
    "Open authorization-screen settings": "Ouvrir les réglages de la mire",
    "This button remains disabled until the shared HelloAsso authorization-screen client ID and client secret are configured.": "Ce bouton reste désactivé tant que le client ID et le client secret partagés de la mire HelloAsso ne sont pas configurés.",
    "HelloAsso helps associations collect online payments and provides its services free of charge. It covers all transaction fees so that you can receive the full amount paid by your supporters, without fees. Voluntary contributions left by them are its only source of revenue.": "HelloAsso aide les associations à collecter des paiements en ligne et propose ses services gratuitement. Elle prend à sa charge tous les frais de transaction pour que vous puissiez bénéficier de la totalité des sommes versées par vos publics, sans frais. Les contributions volontaires laissées par ces derniers sont leur unique source de revenus.",
    "manual / another instance in control": "manuel / autre instance maîtresse",
    "managed by this CiviCRM instance": "géré par cette instance CiviCRM",
    "No matching HelloAsso payment processor was found on this instance.": "Aucun processeur HelloAsso correspondant n'a été trouvé sur cette instance.",
    "No active HelloAsso authorization-screen processor is linked for mode %1.": "Aucun processeur HelloAsso actif connecté par mire n'est lié pour le mode %1.",
    "The operational choice between API key and authorization screen remains on the processor record. This page enables the authorization-screen flow, explains how it works and starts the connection.": "Le choix opérationnel entre clé API et mire reste sur la fiche du processeur. Cette page sert à activer le parcours mire, expliquer son fonctionnement et lancer la connexion.",
    "Target processor:": "Processeur ciblé :",
    "Organization linked: %1": "Organisation liée : %1",
    "The HelloAsso link can no longer be renewed%1%2. Reconnect the organization before accepting new payments through the authorization screen.": "La liaison HelloAsso ne peut plus être renouvelée%1%2. Reconnectez l'organisation avant d'accepter de nouveaux paiements par la mire.",
    "The latest HelloAsso link renewal failed. Check the next maintenance job or reconnect the organization if the problem persists.": "Le dernier renouvellement de la liaison HelloAsso a échoué. Vérifiez le prochain job de maintenance ou reconnectez l'organisation si le problème persiste.",
    "No organization is linked on this rail yet.": "Aucune organisation n'est encore liée sur ce rail.",
    "Open this authorization-screen settings page": "Ouvrir les réglages de cette mire",
    "Enable the authorization-screen switch below and save the page before starting the connection.": "Activez d'abord le switch mire ci-dessous puis enregistrez la page avant de lancer la connexion.",
    "Open this authorization-screen settings page first to enter the shared client ID and client secret, then return to start the connection.": "Ouvrez d'abord les réglages de cette mire pour saisir le client ID et le client secret partagés, puis revenez lancer la connexion.",
    "HelloAsso authorization-screen flow": "Parcours mire HelloAsso",
    "The switch below enables or hides the authorization-screen interface on HelloAsso processors. Shared client credentials and connection startup remain managed from the dedicated authorization-screen pages.": "Le switch ci-dessous active ou masque l'interface mire sur les processeurs HelloAsso. Les identifiants client partagés et le lancement de la connexion restent gérés depuis les écrans mire dédiés.",
    "For an initial configuration: enable the authorization screen here, save the page, then open the sandbox or production rail to enter the client ID and client secret before starting the connection.": "Pour une première configuration : activez la mire ici, enregistrez la page, puis ouvrez le rail bac à sable ou production pour saisir le client ID et le client secret avant de lancer la connexion.",
    "Enter the HelloAsso client secret paired with the client ID above. Store the live and sandbox secrets separately.": "Saisissez le client secret HelloAsso associé au client ID ci-dessus. Conservez les secrets production et bac à sable séparément.",
    "Base HelloAsso API URL for live payments. Keep the default production URL unless HelloAsso explicitly instructs otherwise.": "URL de base de l'API HelloAsso pour les paiements production. Conservez l'URL de production par défaut sauf instruction explicite de HelloAsso.",
    "Base HelloAsso API URL for sandbox payments. Keep the sandbox default unless HelloAsso explicitly instructs otherwise.": "URL de base de l'API HelloAsso pour les paiements bac à sable. Conservez l'URL bac à sable par défaut sauf instruction explicite de HelloAsso.",
    "The official button above opens the HelloAsso connection. If you need to adjust client credentials or check the link status, use the settings link first.": "Le bouton officiel ci-dessus ouvre la connexion HelloAsso. Si vous avez besoin d'ajuster les identifiants client ou de vérifier l'état de liaison, utilisez d'abord le lien d'ouverture des réglages.",
    "Refresh this HelloAsso settings page": "Actualiser cette page de réglages HelloAsso",
    "HelloAsso authorization screen": "Mire HelloAsso",
    "Shared client ID": "Client ID partagé",
    "Sandbox shared client ID": "Client ID partagé bac à sable",
    "Shared client secret": "Client secret partagé",
    "Sandbox shared client secret": "Client secret partagé bac à sable",
    "Payment processor ID: %1": "ID du processeur de paiement : %1",
    "Leave blank to keep the current secret.": "Laisser vide pour conserver le secret actuel.",
    "Token URL": "URL du token",
    "This page is used to enter the shared client, display the link status and start the HelloAsso connection. The general authorization-screen activation switch is configured in the HelloAsso settings.": "Cette page sert à renseigner le client partagé, afficher l'état de liaison et lancer la connexion HelloAsso. Le switch général d'activation de la mire se règle depuis les paramètres HelloAsso.",
    "Open HelloAsso settings": "Ouvrir les paramètres HelloAsso",
    "HelloAsso authorization-screen setup": "Configuration de la mire HelloAsso",
    "Authorization URL": "URL d'autorisation",
    "Save authorization-screen settings": "Enregistrer les réglages de la mire",
    "This HelloAsso processor can be saved a first time without API credentials when the authorization screen is enabled.": "Ce processeur HelloAsso peut être enregistré une première fois sans identifiants API lorsque la mire est activée.",
    "Enter the processor name, save once, then return to this processor to configure the live and sandbox authorization-screen connections.": "Saisissez le nom du processeur, enregistrez une première fois, puis revenez sur ce processeur pour configurer les connexions mire live et sandbox.",
    "Community HelloAsso authorization-screen credentials are currently used for this rail. Enter a local client ID and client secret here only if you want to override the community credentials.": "Les identifiants communautaires de mire HelloAsso sont actuellement utilisés pour ce rail. Renseignez ici un client ID et un client secret locaux seulement si vous voulez remplacer les identifiants communautaires.",
    "Local HelloAsso authorization-screen credentials are currently used for this rail.": "Les identifiants locaux de mire HelloAsso sont actuellement utilisés pour ce rail.",
    "Callback URL to declare at HelloAsso:": "URL de callback à déclarer chez HelloAsso :",
    "The authorization screen is disabled globally. Enable it in HelloAsso settings, then return to this page to connect an organization.": "La mire est désactivée globalement. Activez-la dans les paramètres HelloAsso puis revenez sur cette page pour connecter une organisation.",
    "Disconnect the linked organization": "Déconnecter l'organisation liée",
    "No HelloAsso organization is linked yet.": "Aucune organisation HelloAsso n'est encore liée.",
    "Online contribution": "Contribution en ligne",
    "Online contribution: %1": "Contribution en ligne : %1",
    "Unable to reload contribution %1.": "Impossible de recharger la contribution %1.",
    "Invalid HelloAsso webhook payload in the queue.": "Payload webhook HelloAsso invalide dans la file.",
    "Invalid HelloAsso processor configuration for %1:": "Configuration du processeur HelloAsso %1 invalide :",
    "%1 (#%2, sandbox linked to live processor #%3)": "%1 (#%2, bac à sable lié au processeur actif en production #%3)",
    "sandbox": "bac à sable",
    "Unable to find the contribution to update for the HelloAsso checkout.": "Impossible de retrouver la contribution à mettre à jour pour le checkout HelloAsso.",
    "Unknown error while preparing the HelloAsso redirect.": "Erreur inconnue lors de la préparation de la redirection HelloAsso.",
    "Unable to update contribution %1.": "Impossible de mettre à jour la contribution %1.",
    "HelloAsso error": "Erreur HelloAsso",
    "The %1 must contain at least 3 characters (HelloAsso rule).": "Le %1 doit contenir au moins 3 caractères (règle HelloAsso).",
    "The %1 must not contain 3 repeated characters (HelloAsso rule).": "Le %1 ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).",
    "The %1 must not contain numbers (HelloAsso rule).": "Le %1 ne doit pas contenir de chiffres (règle HelloAsso).",
    "The %1 must contain at least one vowel (HelloAsso rule).": "Le %1 doit contenir au moins une voyelle (règle HelloAsso).",
    "The value of %1 is not allowed by HelloAsso.": "La valeur du %1 n'est pas autorisée par HelloAsso.",
    "The %1 contains unauthorized characters (HelloAsso rule).": "Le %1 contient des caractères non autorisés (règle HelloAsso).",
    "First name and last name must not be identical (HelloAsso rule).": "Le nom et le prénom ne doivent pas être identiques (règle HelloAsso).",
    "first name": "prénom",
    "last name": "nom",
    "No primary email address is available to start the HelloAsso checkout for contribution %1.": "Aucune adresse email principale n'est disponible pour lancer le checkout HelloAsso de la contribution %1.",
    "Duplicate webhook ignored.": "Webhook dupliqué ignoré.",
    "No matching contribution. Webhook ignored.": "Aucune contribution correspondante. Webhook ignoré.",
    "HelloAsso object not found (404). Short and long follow-up checks have been disabled to avoid repeated calls.": "Objet HelloAsso introuvable (404). Les révérifications courte et longue ont été désactivées pour éviter des appels répétés.",
    "Contribution %1 is locked by DonRec. The status used for the tax receipt differs from the current HelloAsso payment gateway status (%2). Metadata has been synchronized, but the contribution status has not been changed.": "La contribution %1 est verrouillée par DonRec. Le statut pris en compte pour le reçu fiscal diffère du statut actuel de la passerelle de paiement HelloAsso (%2). Les métadonnées ont été synchronisées, mais le statut de la contribution n'a pas été modifié.",
    "HelloAsso: unable to authenticate with the payment processor API keys.": "HelloAsso : impossible de s'authentifier avec les clés API du processeur de paiement.",
    "HelloAsso: analyze concatenated legacy trxn_id values (%1 cases)": "HelloAsso: analyser les trxn_id legacy concaténés (%1 cas)",
    "HelloAsso: too many cases (%1) for GUI repair. Use terminal mode.": "HelloAsso: trop de cas (%1) pour la réparation GUI. Utiliser le mode terminal.",
    "HelloAsso: repair legacy trxn_id values (batch %1, contributions %2 to %3)": "HelloAsso: réparer les trxn_id legacy (lot %1, contributions %2 à %3)",
    "HelloAsso: final report of remaining legacy trxn_id values": "HelloAsso: rapport final des trxn_id legacy restants",
    "You will be redirected to HelloAsso to complete your payment.": "Vous serez redirigé vers HelloAsso pour effectuer le paiement.",
    "No active HelloAsso connection is available for this Checkout Option.": "Aucune connexion HelloAsso active n'est disponible pour cette Checkout Option.",
    "First/last name must contain at least 3 characters (HelloAsso rule).": "Le nom/prénom doit contenir au moins 3 caractères (règle HelloAsso).",
    "First/last name must not contain 3 repeated characters (HelloAsso rule).": "Le nom/prénom ne doit pas contenir 3 caractères répétitifs (règle HelloAsso).",
    "First/last name must not contain numbers (HelloAsso rule).": "Le nom/prénom ne doit pas contenir de chiffres (règle HelloAsso).",
    "First/last name must contain at least one vowel (HelloAsso rule).": "Le nom/prénom doit contenir au moins une voyelle (règle HelloAsso).",
    "This value is not allowed by HelloAsso.": "Cette valeur n'est pas autorisée par HelloAsso.",
    "First/last name contains unauthorized special characters (HelloAsso rule).": "Le nom/prénom contient des caractères spéciaux non autorisés (règle HelloAsso).",
    "You opened the HelloAsso test processor directly. CiviCRM stores the live and test values together on this screen. To avoid overwriting production, you will be redirected to the associated live processor record.": "Vous avez ouvert directement le processeur de test HelloAsso. CiviCRM enregistre les valeurs live et test ensemble sur cet écran. Pour éviter d'écraser la production, vous allez être redirigé vers la fiche principale du processeur live associé.",
    "HelloAsso settings": "Paramètres HelloAsso",
    "HelloAsso online payments, authorization-screen connections, secure webhooks and payment reconciliation.": "Paiements en ligne HelloAsso, connexions par mire, webhooks sécurisés et rapprochement des paiements.",
    "HelloAsso payment is temporarily unavailable: HelloAsso connection must be restored by an administrator.": "Le paiement HelloAsso est temporairement indisponible : la connexion HelloAsso doit être rétablie par un administrateur.",
    "HelloAsso refund cannot be requested without a payment ID and a positive amount.": "Le remboursement HelloAsso ne peut pas être demandé sans identifiant de paiement et montant positif.",
    "HelloAsso refund cannot be requested because the original CiviCRM payment could not be found.": "Le remboursement HelloAsso ne peut pas être demandé car le paiement CiviCRM d'origine est introuvable.",
    "HelloAsso accepted the refund request but did not return a refund operation ID.": "HelloAsso a accepté la demande de remboursement mais n'a pas retourné d'identifiant d'opération de remboursement.",
    "HelloAsso has accepted the refund request. The local CiviCRM refund has been recorded immediately; the final HelloAsso refund state will be confirmed later by webhook or scheduled synchronization.": "HelloAsso a accepté la demande de remboursement. Le remboursement local CiviCRM a été enregistré immédiatement ; l'état final du remboursement HelloAsso sera confirmé ensuite par webhook ou synchronisation planifiée.",
    "HelloAsso has accepted the refund request for payment %1. Refund operation %2 has been recorded in CiviCRM; the final HelloAsso refund state will be confirmed later by webhook or scheduled synchronization.": "HelloAsso a accepté la demande de remboursement du paiement %1. L'opération de remboursement %2 a été enregistrée dans CiviCRM ; l'état final du remboursement HelloAsso sera confirmé ensuite par webhook ou synchronisation planifiée.",
    "HelloAsso refund requested": "Remboursement HelloAsso demandé",
    "HelloAsso only supports full refunds through this integration. Partial refunds must be handled manually in HelloAsso.": "HelloAsso prend uniquement en charge les remboursements complets dans cette intégration. Les remboursements partiels doivent être traités manuellement dans HelloAsso.",
    "HelloAsso payment is temporarily unavailable: the linked organization is not currently allowed by HelloAsso to receive online payments.": "Le paiement HelloAsso est temporairement indisponible : l'organisation liée n'est actuellement pas autorisée par HelloAsso à recevoir des paiements en ligne.",
    "The linked HelloAsso organization is not currently allowed to receive online payments%1%2. Check its administrative status in HelloAsso before accepting new payments through the authorization screen.": "L'organisation HelloAsso liée n'est actuellement pas autorisée à recevoir des paiements en ligne%1%2. Vérifiez son état administratif dans HelloAsso avant d'accepter de nouveaux paiements via la mire.",
    "HelloAsso partner API status: reachable.": "État de l'API partenaire HelloAsso : joignable.",
    "Partner account: %1": "Compte partenaire : %1",
    "Authorized partner domain: %1": "Domaine partenaire autorisé : %1",
    "Partner API privileges: %1": "Privilèges API partenaire : %1",
    "Partner notification URLs returned by HelloAsso: %1": "URL de notification partenaire retournées par HelloAsso : %1",
    "HelloAsso did not return any partner notification URL on this client.": "HelloAsso n'a retourné aucune URL de notification partenaire pour ce client.",
    "HelloAsso partner information could not be retrieved: %1": "Les informations partenaire HelloAsso n'ont pas pu être récupérées : %1",
    "none": "aucune",
    "payment refused": "paiement refusé",
    "One or more HelloAsso linked organizations are not currently allowed by HelloAsso to receive online payments. Check their administrative status before accepting new payments through the authorization screen: %1": "Une ou plusieurs organisations HelloAsso liées ne sont actuellement pas autorisées par HelloAsso à recevoir des paiements en ligne. Vérifiez leur état administratif avant d'accepter de nouveaux paiements via la mire : %1",
    "HelloAsso: Organization Cannot Receive Payments": "HelloAsso : organisation non autorisée à recevoir des paiements",
    "HelloAsso API error (%1)": "Erreur API HelloAsso (%1)",
    "HelloAsso partner authorization failed: %1 (%2)": "L'autorisation partenaire HelloAsso a échoué : %1 (%2)",
    "HelloAsso partner authorization requires a payment processor.": "L'autorisation partenaire HelloAsso nécessite un processeur de paiement.",
    "A HelloAsso authorization link could not be renewed. Reconnect it before accepting new payments through the authorization screen: %1": "Une liaison d'autorisation HelloAsso n'a pas pu être renouvelée. Reconnectez-la avant d'accepter de nouveaux paiements via la mire : %1",
    "HelloAsso: Reconnection Required": "HelloAsso : reconnexion nécessaire",
    "refresh refused": "rafraîchissement refusé",
    "instead of": "au lieu de",
    "Domain mismatch detected: this CiviCRM instance runs on a different host than the one authorized for the HelloAsso link: %1. OAuth callbacks will not function correctly on this staging/dev instance.": "Un décalage de domaine a été détecté : cette instance CiviCRM tourne sur un hôte différent de celui utilisé pour la liaison HelloAsso de : %1. Les redirections et retours OAuth ne fonctionneront pas correctement sur cette instance de test/staging.",
    "HelloAsso: Domain Mismatch (Staging/Dev Instance)": "HelloAsso : Décalage de domaine (Instance de Test/Staging)",
    "The HelloAsso webhook registered for %1 points to a different domain. Inbound payments will not be processed on this CiviCRM instance: %2.": "Le webhook enregistré pour %1 pointe vers un domaine différent de cette instance. Les notifications de paiement HelloAsso ne seront pas reçues ni traitées sur ce CiviCRM : %2.",
    "HelloAsso: Webhook Domain Mismatch": "HelloAsso : Décalage de domaine du Webhook",
    "%1 monthly installments": "%1 mensualités",
    "A recurring HelloAsso payment is missing its order or installment identity.": "Un paiement récurrent HelloAsso ne contient pas l'identifiant de sa commande ou de son échéance.",
    "Allow finite monthly installment checkout payloads for HelloAsso payment processors.": "Autorise les échéanciers mensuels limités pour les processeurs de paiement HelloAsso.",
    "CiviCRM could not create the contribution for HelloAsso installment %1.": "CiviCRM n'a pas pu créer la contribution correspondant à l'échéance HelloAsso %1.",
    "Contribution is not a future scheduled HelloAsso installment.": "La contribution n'est pas une échéance future planifiée HelloAsso.",
    "Contribution is not a future HelloAsso installment": "La contribution n'est pas une échéance future HelloAsso",
    "Enable HelloAsso installment payments": "Activer les paiements HelloAsso en plusieurs fois",
    "Enable HelloAsso SEPA direct debit": "Activer le prélèvement SEPA HelloAsso",
    "Future HelloAsso installments were cancelled successfully. Payments already collected were not refunded.": "Les échéances futures HelloAsso ont été annulées. Les paiements déjà encaissés n'ont pas été remboursés.",
    "HelloAsso installment cancellation is available only for processors connected through the authorization screen.": "L'annulation d'un échéancier HelloAsso est disponible uniquement pour les processeurs connectés via la mire.",
    "HelloAsso installment cancellation requires an authorization-screen connection.": "L'annulation d'un échéancier HelloAsso nécessite une connexion via la mire.",
    "HelloAsso installment payments are disabled.": "Les paiements HelloAsso en plusieurs fois sont désactivés.",
    "HelloAsso installments must be collected every month.": "Les échéances HelloAsso doivent être prélevées chaque mois.",
    "HelloAsso installments must be monthly.": "Les échéances HelloAsso doivent être mensuelles.",
    "HelloAsso requires between %1 and %2 installments for this form.": "HelloAsso exige entre %1 et %2 échéances pour ce formulaire.",
    "HelloAsso requires between 2 and 12 installments.": "HelloAsso exige entre 2 et 12 échéances.",
    "HelloAsso refused the cancellation. Reconnect the organization through the authorization screen and grant the OrganizationAdmin or FormAdmin role; the client must also include the RefundManagement privilege.": "HelloAsso a refusé l'annulation. Reconnectez l'association via la mire et accordez le rôle OrganizationAdmin ou FormAdmin ; le client doit également disposer du privilège RefundManagement.",
    "Installment schedule": "Échéancier de paiement",
    "Invalid HelloAsso installment schedule: %1": "Échéancier HelloAsso invalide : %1",
    "Long follow-up scheme": "Schéma de suivi à long terme",
    "Maximum installments": "Nombre maximal d'échéances",
    "Minimum installments": "Nombre minimal d'échéances",
    "Next scheduled long sync date": "Prochaine date de synchronisation longue planifiée",
    "Next scheduled sync date": "Prochaine date de synchronisation planifiée",
    "Only process follow-ups due on or before this date/time. Leave empty to disable this filter.": "Traite uniquement les suivis arrivant à échéance au plus tard à cette date et heure. Laissez vide pour désactiver ce filtre.",
    "Only process long follow-ups due on or before this date/time. Leave empty to use now.": "Traite uniquement les suivis longs arrivant à échéance au plus tard à cette date et heure. Laissez vide pour utiliser la date actuelle.",
    "Offer HelloAsso installment payments": "Proposer le paiement HelloAsso en plusieurs fois",
    "Offer SEPA direct debit on HelloAsso Checkout, including installment checkouts. HelloAsso only displays it for eligible organizations and may keep card payment available.": "Propose le prélèvement SEPA sur le Checkout HelloAsso, y compris pour les paiements en plusieurs fois. HelloAsso l'affiche uniquement pour les associations éligibles et peut maintenir le paiement par carte.",
    "Pay in full": "Payer en une fois",
    "Pay in full or split this payment into a fixed schedule of 2 to 12 monthly installments handled by HelloAsso.": "Payez en une fois ou répartissez ce paiement selon un échéancier fixe de 2 à 12 mensualités géré par HelloAsso.",
    "Payment schedule": "Échéancier de paiement",
    "Process scheduled HelloAsso payment follow-ups and targeted synchronisations.": "Traite les suivis planifiés des paiements HelloAsso et les synchronisations ciblées.",
    "Process scheduled long-window HelloAsso consistency checks.": "Traite les contrôles de cohérence HelloAsso planifiés à long terme.",
    "The HelloAsso authorization does not include the RefundManagement privilege required to cancel future installments.": "L'autorisation HelloAsso ne contient pas le privilège RefundManagement requis pour annuler les échéances futures.",
    "The HelloAsso installment mapping table is missing. Apply the extension database upgrades before processing installments.": "La table de correspondance des échéances HelloAsso est absente. Appliquez les mises à niveau de la base de données de l'extension avant de traiter les échéances.",
    "The HelloAsso order ID is missing from this recurring contribution.": "L'identifiant de commande HelloAsso est absent de cette contribution périodique.",
    "The HelloAsso order ID stored on this recurring contribution is invalid.": "L'identifiant de commande HelloAsso enregistré sur cette contribution périodique est invalide.",
    "The contribution mapped to this HelloAsso installment no longer exists.": "La contribution associée à cette échéance HelloAsso n'existe plus.",
    "The HelloAsso v2 schema upgrade is incomplete. %1 Run <code>cv updb</code> and then <code>cv flush</code> before relying on webhook queueing, follow-up sync, or legacy repair.": "La mise à niveau du schéma HelloAsso v2 est incomplète. %1 Exécutez <code>cv updb</code> puis <code>cv flush</code> avant d'utiliser la file de webhooks, la synchronisation de suivi ou la réparation des anciennes données.",
    "When enabled, only contributions with a scheduled HelloAsso follow-up are processed.": "Lorsque cette option est activée, seules les contributions ayant un suivi HelloAsso planifié sont traitées.",
    "The HelloAsso payment processor is currently unavailable. Please try again later.": "Le processeur de paiement HelloAsso est temporairement indisponible. Veuillez réessayer ultérieurement.",
    "HelloAsso is currently unavailable. Please try again later.": "HelloAsso est temporairement indisponible. Veuillez réessayer ultérieurement.",
    "%1 (#%2, %3)": "%1 (#%2, %3)",
    "Access Token": "Jeton d'accès",
    "Amount": "Montant",
    "Checkout Intent ID": "ID de l'intention de paiement",
    "Checkout intent ID": "ID de l'intention de paiement",
    "Comma-separated contribution status names to include, for example \"Completed,Pending\".": "Noms de statuts de contribution séparés par des virgules à inclure, par exemple \"Completed,Pending\".",
    "Comma-separated contribution status names to include, for example \"Pending,Failed\".": "Noms de statuts de contribution séparés par des virgules à inclure, par exemple \"Pending,Failed\".",
    "Connection Mode": "Mode de connexion",
    "Contribution Recur ID": "ID de contribution périodique",
    "Created At": "Créé le",
    "Enter the HelloAsso client ID used by this payment processor for the selected environment. Keep the live and test values aligned with the matching HelloAsso application.": "Saisissez le client ID HelloAsso utilisé par ce processeur de paiement pour l'environnement sélectionné. Gardez les valeurs live et test alignées avec l'application HelloAsso correspondante.",
    "Enter the HelloAsso organization slug or organization name expected by this processor when building API calls and payment links.": "Saisissez le slug ou le nom de l'organisation HelloAsso attendu par ce processeur lors de la construction des appels API et des liens de paiement.",
    "Expires At": "Expire le",
    "Hello Asso Installment": "Échéance HelloAsso",
    "Hello Asso Installments": "Échéances HelloAsso",
    "Hello Asso Processor Auth": "Autorisation processeur HelloAsso",
    "Hello Asso Processor Auths": "Autorisations processeurs HelloAsso",
    "HelloAsso Reference command ID": "ID de commande de référence HelloAsso",
    "HelloAsso authentication is currently being refreshed. Please retry the payment.": "L'authentification HelloAsso est en cours d'actualisation. Veuillez réessayer le paiement.",
    "HelloAsso authorization screen is selected for this processor, but no linked HelloAsso organization is stored yet.": "La mire HelloAsso est sélectionnée pour ce processeur, mais aucune organisation HelloAsso liée n'est encore enregistrée.",
    "HelloAsso live API calls require SSL verification to be enabled.": "Les appels à l'API live HelloAsso nécessitent que la vérification SSL soit activée.",
    "HelloAsso notification signature cannot be verified because the local signing key is missing.": "La signature de la notification HelloAsso ne peut pas être vérifiée car la clé de signature locale est absente.",
    "HelloAsso partner authorization did not return an organization slug.": "L'autorisation partenaire HelloAsso n'a pas retourné de slug d'organisation.",
    "HelloAsso partner authorization is currently being refreshed. Please retry the payment.": "L'autorisation partenaire HelloAsso est en cours de rafraîchissement. Veuillez réessayer le paiement.",
    "HelloAsso partner authorization refresh token has expired.": "Le jeton de rafraîchissement de l'autorisation partenaire HelloAsso a expiré.",
    "HelloAsso partner authorization requires SSL verification to be enabled.": "L'autorisation partenaire HelloAsso nécessite que la vérification SSL soit activée.",
    "HelloAsso partner authorization response is missing OAuth tokens.": "La réponse de l'autorisation partenaire HelloAsso ne contient pas les jetons OAuth.",
    "HelloAsso partner client ID and secret must be configured before connecting an organization.": "Le client ID et le secret partenaire HelloAsso doivent être configurés avant de connecter une organisation.",
    "HelloAsso partner settings have been saved on this page.": "Les paramètres partenaire HelloAsso ont été enregistrés sur cette page.",
    "HelloAsso partner webhook signature cannot be verified because the local signature key is missing.": "La signature du webhook partenaire HelloAsso ne peut pas être vérifiée car la clé de signature locale est absente.",
    "HelloAsso partner webhook signature is required but missing.": "La signature du webhook partenaire HelloAsso est requise mais manquante.",
    "HelloAsso payment reconciliation metadata": "Métadonnées de rapprochement des paiements HelloAsso",
    "HelloAsso per-processor OAuth and webhook state": "État OAuth et webhook par processeur HelloAsso",
    "HelloAsso plugin-public mode requires a saved payment processor ID.": "Le mode plugin-public de HelloAsso requiert un ID de processeur de paiement enregistré.",
    "HelloAsso processor authorization table is missing.": "La table des autorisations de processeurs HelloAsso est manquante.",
    "HelloAsso webhook checkout intent could not be confirmed with a payment by the API.": "L'intention de paiement du webhook HelloAsso n'a pas pu être confirmée par un paiement via l'API.",
    "HelloAsso webhook payload could not be stored in the queue.": "Le contenu du webhook HelloAsso n'a pas pu être stocké dans la file d'attente.",
    "HelloAsso webhook payment ID could not be confirmed by the API.": "L'ID de paiement du webhook HelloAsso n'a pas pu être confirmé via l'API.",
    "HelloAsso: Authorization Check Failed": "HelloAsso : Échec de la vérification de l'autorisation",
    "HelloAsso: Authorization Expired": "HelloAsso : Autorisation expirée",
    "HelloAsso: Authorization Expiring Soon": "HelloAsso : Autorisation expirant bientôt",
    "HelloAsso: Authorization Renewal Failed": "HelloAsso : Échec du renouvellement de l'autorisation",
    "HelloAsso: Payment Method Missing": "HelloAsso : Méthode de paiement manquante",
    "HelloAsso: Payment Method Requires Review": "HelloAsso : Méthode de paiement nécessitant une révision",
    "HelloAsso: Webhook Not Configured": "HelloAsso : Webhook non configuré",
    "ID": "ID",
    "Idempotent mapping of HelloAsso installments to CiviCRM contributions": "Mappage idempotent des échéances HelloAsso vers les contributions CiviCRM",
    "Installment Number": "Numéro de l'échéance",
    "Last Refresh Error": "Dernière erreur de rafraîchissement",
    "Last Refresh Error Date": "Date de la dernière erreur de rafraîchissement",
    "Last Refresh Http Status": "Dernier statut HTTP de rafraîchissement",
    "Last event type": "Dernier type d'événement",
    "Last known state": "Dernier état connu",
    "Last long sync date": "Date de la dernière synchronisation longue",
    "Linked At": "Lié le",
    "Long sync attempt count": "Nombre de tentatives de synchronisation longue",
    "Long sync origin date": "Date d'origine de la synchronisation longue",
    "Long sync technical error count": "Nombre d'erreurs techniques de la synchronisation longue",
    "Maximum number of contributions to process per payment processor.": "Nombre maximum de contributions à traiter par processeur de paiement.",
    "Missing authorization columns: %1": "Colonnes d'autorisation manquantes : %1",
    "Missing metadata columns: %1": "Colonnes de métadonnées manquantes : %1",
    "One or more HelloAsso authorization links have expired and must be reconnected: %1": "Une ou plusieurs liaisons d'autorisation HelloAsso ont expiré et doivent être reconnectées : %1",
    "One or more HelloAsso authorization links will expire soon: %1": "Une ou plusieurs liaisons d'autorisation HelloAsso expireront bientôt : %1",
    "One or more HelloAsso links expect automatic webhook registration, but no webhook is stored yet: %1. Reconnect the authorization screen to push the webhook, or disable automatic webhook registration on that processor if another CiviCRM instance must keep manual control of the HelloAsso webhook URL.": "Une ou plusieurs liaisons HelloAsso attendent l'enregistrement automatique du webhook, mais aucun webhook n'est encore enregistré : %1. Reconnectez la mire pour pousser le webhook, ou désactivez l'enregistrement automatique sur ce processeur si une autre instance CiviCRM doit conserver le contrôle manuel.",
    "One or more HelloAsso payment processors have no payment method configured: %1. Configure a payment method on the processor; new processors use Credit Card by default.": "Un ou plusieurs processeurs de paiement HelloAsso n'ont aucune méthode de paiement configurée : %1. Configurez une méthode de paiement sur le processeur ; les nouveaux processeurs utilisent Carte de crédit par défaut.",
    "One or more HelloAsso payment processors still rely on API keys that could not authenticate: %1": "Un ou plusieurs processeurs de paiement HelloAsso s'appuient encore sur des clés API qui n'ont pas pu s'authentifier : %1",
    "One or more HelloAsso payment processors use Check as their payment method: %1. Review the processor configuration and select Credit Card or your existing HelloAsso online payment method.": "Un ou plusieurs processeurs de paiement HelloAsso utilisent Chèque comme méthode de paiement : %1. Révisez la configuration du processeur et sélectionnez Carte de crédit ou votre méthode de paiement en ligne HelloAsso existante.",
    "Only process contributions received on or after this date/time.": "Ne traiter que les contributions reçues à partir de cette date/heure.",
    "Only process contributions received on or before this date/time.": "Ne traiter que les contributions reçues jusqu'à cette date/heure.",
    "Order ID": "ID de commande",
    "Organization Slug": "Slug de l'organisation",
    "Payment Date": "Date de paiement",
    "Payment details will be shown here depending on the selected Checkout Option. For payment to be taken you must either set a Checkout Option value on the Contribution or add a user input to select one.": "Les détails du paiement seront affichés ici selon la Checkout Option sélectionnée. Pour qu'un paiement soit encaissé, vous devez soit définir une valeur de Checkout Option sur la contribution, soit ajouter un champ utilisateur pour en sélectionner une.",
    "Process first step": "Traiter la première étape",
    "Process second step": "Traiter la deuxième étape",
    "Redirect Uri": "URI de redirection",
    "Refresh Expires At": "Rafraîchissement expire le",
    "Refresh Issued At": "Rafraîchissement émis le",
    "Refresh Status": "Statut du rafraîchissement",
    "Refresh Token": "Jeton de rafraîchissement",
    "Refresh linked HelloAsso authorizations once their current refresh tokens reach mid-life.": "Rafraîchir les autorisations HelloAsso liées lorsque leurs jetons de rafraîchissement actuels atteignent la mi-vie.",
    "Restrict maintenance to one linked HelloAsso payment processor.": "Restreindre la maintenance à un processeur de paiement HelloAsso lié.",
    "Sandbox organization slug or name used for test API calls.": "Slug ou nom de l'organisation bac à sable utilisé pour les appels API de test.",
    "Signing Key": "Clé de signature",
    "State": "État",
    "Sync attempt count": "Nombre de tentatives de synchronisation",
    "Sync origin date": "Date d'origine de la synchronisation",
    "Sync technical error count": "Nombre d'erreurs techniques de la synchronisation",
    "The HelloAsso API key health check could not be completed: %1": "La vérification de l'état de la clé API HelloAsso n'a pas pu aboutir : %1",
    "The HelloAsso authorization check could not be completed: %1": "La vérification de l'autorisation HelloAsso n'a pas pu aboutir : %1",
    "The HelloAsso partner settings form token is invalid. Please reload the page and try again.": "Le jeton du formulaire de paramètres partenaire HelloAsso est invalide. Veuillez recharger la page et réessayer.",
    "The last HelloAsso authorization renewal failed for: %1. The maintenance job will retry; reconnect if the failure continues.": "Le dernier renouvellement de l'autorisation HelloAsso a échoué pour : %1. La tâche de maintenance réessaiera ; reconnectez-vous si l'échec persiste.",
    "Unique HelloassoMetaData ID": "ID unique HelloassoMetaData",
    "Untrusted webhook ignored; no locally expected HelloAsso object identifier.": "Webhook non approuvé ignoré ; aucun identifiant d'objet HelloAsso localement attendu.",
    "Updated At": "Mis à jour le",
    "Upgrade Batch (%1 => %2)": "Mise à niveau par lot (%1 => %2)",
    "Webhook Ownership": "Propriété du webhook",
    "Webhook Signature Key": "Clé de signature du webhook",
    "Webhook Updated At": "Webhook mis à jour le",
    "Webhook Url": "URL du webhook",
    "production": "production",
}

def build_header(language, is_template=False):
    timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    lines = [
        f"Project-Id-Version: {PROJECT_VERSION}",
    ]
    if is_template:
        lines.append(f"POT-Creation-Date: {timestamp}")
    lines.extend([
        f"PO-Revision-Date: {timestamp}",
        f"Last-Translator: {TRANSLATOR}",
        f"Language-Team: {language or 'Translations'}",
        f"Language: {language}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
    ])
    return "\n".join(lines) + "\n"

def normalize(s):
    # Remove accents, punctuation, apostrophes, and spaces for matching
    s = s.strip()
    s = "".join(c for c in unicodedata.normalize("NFD", s) if not unicodedata.combining(c))
    return re.sub(r"[^a-zA-Z0-9]", "", s).lower()

def escape_str(s):
    res = []
    for c in s:
        if c == '"':
            res.append('\\"')
        elif c == '\\':
            res.append('\\\\')
        elif c == '\n':
            res.append('\\n')
        elif c == '\t':
            res.append('\\t')
        elif c == '\r':
            res.append('\\r')
        else:
            res.append(c)
    return "".join(res)

def format_po_str(label, s):
    if '\n' in s:
        lines = s.split('\n')
        out = f'{label} ""\n'
        for i, line in enumerate(lines):
            escaped_line = escape_str(line)
            if i < len(lines) - 1:
                out += f'"{escaped_line}\\n"\n'
            else:
                out += f'"{escaped_line}"\n'
        return out
    else:
        escaped = escape_str(s)
        return f'{label} "{escaped}"\n'

def parse_po_str(s):
    parts = []
    for line in s.strip().split('\n'):
        line = line.strip()
        if line.startswith('"') and line.endswith('"'):
            parts.append(ast.literal_eval(line))
    return "".join(parts)

def parse_po(po_path):
    translations = {}
    if not os.path.exists(po_path):
        return translations
        
    with open(po_path, "r", encoding="utf-8") as f:
        content = f.read()
        
    entry_pattern = re.compile(
        r'(?:^|\n\n)(?P<comments>(?:[ \t]*#.*\n)*)'
        r'[ \t]*msgid\s+(?P<msgid>""|"(?:[^"\\]|\\.)*"(?:\s*"(?:[^"\\]|\\.)*")*)\s*'
        r'msgstr\s+(?P<msgstr>""|"(?:[^"\\]|\\.)*"(?:\s*"(?:[^"\\]|\\.)*")*)',
        re.MULTILINE
    )
    
    for m in entry_pattern.finditer(content):
        msgid = parse_po_str(m.group('msgid'))
        msgstr = parse_po_str(m.group('msgstr'))
        comments = m.group('comments').strip()
        
        translations[msgid] = {
            'msgstr': msgstr,
            'comments': comments
        }
        
    return translations

def write_po(po_dict, output_path, header_comments=""):
    with open(output_path, "w", encoding="utf-8") as f:
        if header_comments:
            f.write(header_comments + "\n\n")
        else:
            f.write("# Translation file\n\n")
            
        if "" in po_dict:
            f.write('msgid ""\n')
            header_str = po_dict[""]["msgstr"]
            f.write('msgstr ""\n')
            for line in header_str.split("\n"):
                if line:
                    f.write(f'"{line}\\n"\n')
            f.write("\n")
            
        for msgid in sorted(po_dict.keys()):
            if msgid == "":
                continue
            entry = po_dict[msgid]
            if entry.get('comments'):
                f.write(entry['comments'] + "\n")
            f.write(format_po_str("msgid", msgid))
            f.write(format_po_str("msgstr", entry['msgstr']))
            f.write("\n")

def compile_mo(po_dict, output_path):
    # Keep msgid "" in the MO: gettext uses it for charset and language metadata.
    po_dict = {k: v["msgstr"] for k, v in po_dict.items() if v.get("msgstr")}
    sorted_keys = sorted(po_dict.keys(), key=lambda s: s.encode('utf-8'))
    
    offsets = []
    original_data = b""
    translation_data = b""
    
    for key in sorted_keys:
        val = po_dict[key]
        key_bytes = key.encode('utf-8') + b'\x00'
        val_bytes = val.encode('utf-8') + b'\x00'
        
        offsets.append((len(key_bytes) - 1, len(original_data), len(val_bytes) - 1, len(translation_data)))
        
        original_data += key_bytes
        translation_data += val_bytes
        
    num_strings = len(sorted_keys)
    orig_table_offset = 28
    trans_table_offset = orig_table_offset + num_strings * 8
    orig_strings_base = trans_table_offset + num_strings * 8
    trans_strings_base = orig_strings_base + len(original_data)
    
    header = struct.pack("<Iiiiiii",
        0x950412de,        # Magic number
        0,                 # Version
        num_strings,       # Number of entries
        orig_table_offset, # Start of key index
        trans_table_offset,# Start of value index
        0,                 # Size of hash table
        0                  # Offset of hash table
    )
    
    orig_table = b""
    trans_table = b""
    
    for len_orig, off_orig, len_trans, off_trans in offsets:
        orig_table += struct.pack("<ii", len_orig, orig_strings_base + off_orig)
        trans_table += struct.pack("<ii", len_trans, trans_strings_base + off_trans)
        
    with open(output_path, "wb") as f:
        f.write(header)
        f.write(orig_table)
        f.write(trans_table)
        f.write(original_data)
        f.write(translation_data)

def extract_strings(root_dir):
    strings = set()
    php_regex = re.compile(r'(?:\bE::ts|(?<![.\w])ts)\(\s*(?:\'((?:[^\'\\]|\\.)*)\'|"((?:[^"\\]|\\.)*)")\s*(?:,|\))')
    frontend_regex = re.compile(r'(?<![.\w])ts\(\s*(?:\'((?:[^\'\\]|\\.)*)\'|"((?:[^"\\]|\\.)*)")\s*(?:,|\))')
    
    for dirpath, _, filenames in os.walk(root_dir):
        if any(ignored in dirpath for ignored in [".git", "tests", "l10n", "vendor"]):
            continue
        for filename in filenames:
            extension = os.path.splitext(filename)[1]
            if extension == ".json":
                path = os.path.join(dirpath, filename)
                with open(path, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
                for label in re.findall(r'"label"\s*:\s*"((?:[^"\\]|\\.)*)"', content):
                    strings.add(label.replace('\\"', '"'))
                continue
            if extension not in {".php", ".js", ".html"}:
                continue
            path = os.path.join(dirpath, filename)
            with open(path, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()
            regex = php_regex if extension == ".php" else frontend_regex
            for m in regex.finditer(content):
                val = m.group(1) or m.group(2)
                if m.group(1):
                    val = val.replace("\\'", "'")
                else:
                    val = val.replace('\\"', '"')
                strings.add(val)
    return strings

def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    print(f"Project root: {root}")
    
    extracted = extract_strings(root)
    print(f"Extracted {len(extracted)} strings from source files.")
    
    l10n_dir = os.path.join(root, "l10n")
    os.makedirs(l10n_dir, exist_ok=True)
    
    # Process French
    fr_po_path = os.path.join(l10n_dir, "fr_FR.po")
    fr_mo_path = os.path.join(l10n_dir, "fr_FR.mo")
    old_fr = parse_po(fr_po_path)
    print(f"Loaded {len(old_fr)} entries from fr_FR.po")
    
    # Build normalizer map for mapping old msgids to new ones
    norm_to_old_fr = {}
    for msgid, entry in old_fr.items():
        if msgid != "":
            norm_to_old_fr[normalize(msgid)] = (msgid, entry)
            
    new_fr = {
        "": {
            "msgstr": build_header("fr_FR"),
            "comments": old_fr.get("", {}).get("comments", ""),
        }
    }
        
    for msgid in extracted:
        norm_key = normalize(msgid)
        if msgid in FRENCH_TRANSLATIONS:
            new_fr[msgid] = {
                'msgstr': FRENCH_TRANSLATIONS[msgid],
                'comments': "#. Maintained French translation"
            }
        elif msgid in old_fr and old_fr[msgid].get('msgstr'):
            new_fr[msgid] = old_fr[msgid]
        elif msgid in old_fr:
            new_fr[msgid] = old_fr[msgid]
        elif norm_key in norm_to_old_fr:
            old_msgid, entry = norm_to_old_fr[norm_key]
            # Map existing translation to the corrected msgid!
            new_fr[msgid] = {
                'msgstr': entry['msgstr'],
                'comments': f"#. Corrected from old msgid: {old_msgid}\n{entry['comments']}".strip()
            }
            print(f"Mapped translation for: '{old_msgid}' -> '{msgid}'")
        else:
            # Check if French is default
            # (If it's French already, default translation is itself)
            is_french = any(c in msgid.lower() for c in ['é', 'è', 'à', 'ù', 'ç', 'ê', 'î', 'ô']) or "l'" in msgid.lower() or "d'" in msgid.lower()
            new_fr[msgid] = {
                'msgstr': msgid if is_french else "",
                'comments': ""
            }
            
    header_fr = old_fr.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in French")
    write_po(new_fr, fr_po_path, header_fr)
    compile_mo(new_fr, fr_mo_path)
    fr_runtime_mo_path = os.path.join(l10n_dir, "fr_FR", "LC_MESSAGES", "helloasso_payment_processor.mo")
    os.makedirs(os.path.dirname(fr_runtime_mo_path), exist_ok=True)
    compile_mo(new_fr, fr_runtime_mo_path)
    print(f"Synchronized and compiled fr_FR.po/mo successfully!")

    # English is the source language; keep an empty catalog for packaging symmetry.
    en_po_path = os.path.join(l10n_dir, "en_US.po")
    en_mo_path = os.path.join(l10n_dir, "en_US.mo")
    old_en = parse_po(en_po_path)
    new_en = {
        "": {
            "msgstr": build_header("en_US"),
            "comments": old_en.get("", {}).get("comments", ""),
        }
    }
            
    header_en = old_en.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in English")
    write_po(new_en, en_po_path, header_en)
    compile_mo(new_en, en_mo_path)
    en_runtime_mo_path = os.path.join(l10n_dir, "en_US", "LC_MESSAGES", "helloasso_payment_processor.mo")
    os.makedirs(os.path.dirname(en_runtime_mo_path), exist_ok=True)
    compile_mo(new_en, en_runtime_mo_path)
    print(f"Synchronized and compiled en_US.po/mo with public-only translations successfully!")
    
    # Process Spanish (for default spanish fallback of public-facing strings)
    es_po_path = os.path.join(l10n_dir, "es_ES.po")
    es_mo_path = os.path.join(l10n_dir, "es_ES.mo")
    old_es = parse_po(es_po_path)
    
    public_translations_es = {
        "Online contribution": "Contribución en línea",
        "Online contribution: %1": "Contribución en línea: %1",
        "You will be redirected to HelloAsso to complete your payment.": "Será redirigido a HelloAsso para realizar el pago.",
        "Number of installments": "Número de plazos",
        "One-time payment": "Pago único",
        "Choose a one-time payment or a fixed schedule of 2 to 12 monthly payments.": "Elija un pago único o un calendario fijo de 2 a 12 mensualidades.",
        "HelloAsso authorization-screen setup": "Configuración de la pantalla de autorización de HelloAsso",
        "This HelloAsso processor can be saved a first time without API credentials when the authorization screen is enabled.": "Este procesador HelloAsso puede guardarse una primera vez sin credenciales API cuando la pantalla de autorización está activada.",
        "Enter the processor name, save once, then return to this processor to configure the live and sandbox authorization-screen connections.": "Introduzca el nombre del procesador, guarde una primera vez y vuelva después a este procesador para configurar las conexiones de la pantalla de autorización en producción y sandbox.",
        
        # Form validation errors
        "First/last name must contain at least 3 characters (HelloAsso rule).": "El nombre/apellido debe contener al menos 3 caracteres (regla HelloAsso).",
        "First/last name must not contain 3 repeated characters (HelloAsso rule).": "El nombre/apellido no debe contener 3 caracteres repetitivos (regla HelloAsso).",
        "First/last name must not contain numbers (HelloAsso rule).": "El nombre/apellido no debe contener números (regla HelloAsso).",
        "First/last name must contain at least one vowel (HelloAsso rule).": "El nombre/apellido debe contener al menos una vocal (regla HelloAsso).",
        "This value is not allowed by HelloAsso.": "Este valor no está permitido por HelloAsso.",
        "First/last name contains unauthorized special characters (HelloAsso rule).": "El nombre/apellido contiene caracteres especiales no permitidos (regla HelloAsso).",
        "First name and last name must not be identical (HelloAsso rule).": "El nombre y el apellido no deben ser idénticos (regla HelloAsso).",
        
        # Core API validation errors
        "The %1 must contain at least 3 characters (HelloAsso rule).": "El %1 debe contener al menos 3 caracteres (regla HelloAsso).",
        "The %1 must not contain 3 repeated characters (HelloAsso rule).": "El %1 no debe contener 3 caracteres repetitivos (regla HelloAsso).",
        "The %1 must not contain numbers (HelloAsso rule).": "El %1 no debe contener números (regla HelloAsso).",
        "The %1 must contain at least one vowel (HelloAsso rule).": "El %1 debe contener al menos una vocal (regla HelloAsso).",
        "The value of %1 is not allowed by HelloAsso.": "El valor de %1 no está permitido por HelloAsso.",
        "The %1 contains unauthorized characters (HelloAsso rule).": "El %1 contiene caracteres no permitidos (regla HelloAsso).",

        # Finite recurring installment checkout
        "%1 monthly installments": "%1 mensualidades",
        "A recurring HelloAsso payment is missing its order or installment identity.": "Falta el identificador del pedido o de la cuota en un pago recurrente de HelloAsso.",
        "CiviCRM could not create the contribution for HelloAsso installment %1.": "CiviCRM no pudo crear la contribución correspondiente a la cuota HelloAsso %1.",
        "Contribution is not a future HelloAsso installment": "La contribución no es una cuota futura de HelloAsso",
        "Future HelloAsso installments were cancelled successfully. Payments already collected were not refunded.": "Las futuras cuotas de HelloAsso se cancelaron correctamente. Los pagos ya cobrados no se reembolsaron.",
        "Enable HelloAsso installment payments": "Activar los pagos HelloAsso en cuotas",
        "Enable HelloAsso SEPA direct debit": "Activar el débito directo SEPA de HelloAsso",
        "HelloAsso installment cancellation is available only for processors connected through the authorization screen.": "La cancelación de cuotas HelloAsso solo está disponible para procesadores conectados mediante la pantalla de autorización.",
        "HelloAsso installment cancellation requires an authorization-screen connection.": "La cancelación de cuotas HelloAsso requiere una conexión mediante la pantalla de autorización.",
        "HelloAsso installment payments are disabled.": "Los pagos de HelloAsso en cuotas están desactivados.",
        "HelloAsso installments must be collected every month.": "Las cuotas HelloAsso deben cobrarse cada mes.",
        "HelloAsso installments must be monthly.": "Las cuotas HelloAsso deben ser mensuales.",
        "HelloAsso requires between %1 and %2 installments for this form.": "HelloAsso requiere entre %1 y %2 cuotas para este formulario.",
        "HelloAsso requires between 2 and 12 installments.": "HelloAsso requiere entre 2 y 12 cuotas.",
        "HelloAsso refused the cancellation. Reconnect the organization through the authorization screen and grant the OrganizationAdmin or FormAdmin role; the client must also include the RefundManagement privilege.": "HelloAsso rechazó la cancelación. Vuelva a conectar la asociación mediante la pantalla de autorización y conceda el rol OrganizationAdmin o FormAdmin; el cliente también debe incluir el privilegio RefundManagement.",
        "Installment schedule": "Calendario de pagos",
        "Invalid HelloAsso installment schedule: %1": "Calendario de cuotas HelloAsso no válido: %1",
        "Maximum installments": "Número máximo de cuotas",
        "Minimum installments": "Número mínimo de cuotas",
        "Offer HelloAsso installment payments": "Ofrecer el pago HelloAsso en cuotas",
        "Offer SEPA direct debit on HelloAsso Checkout, including installment checkouts. HelloAsso only displays it for eligible organizations and may keep card payment available.": "Ofrece el débito directo SEPA en HelloAsso Checkout, incluidos los pagos en cuotas. HelloAsso solo lo muestra para las asociaciones elegibles y puede mantener disponible el pago con tarjeta.",
        "Pay in full": "Pagar en un solo pago",
        "Pay in full or split this payment into a fixed schedule of 2 to 12 monthly installments handled by HelloAsso.": "Pague de una vez o divida este pago en un calendario fijo de 2 a 12 mensualidades gestionado por HelloAsso.",
        "Payment schedule": "Calendario de pagos",
        "The HelloAsso authorization does not include the RefundManagement privilege required to cancel future installments.": "La autorización de HelloAsso no incluye el privilegio RefundManagement necesario para cancelar futuras cuotas.",
        "The HelloAsso installment mapping table is missing. Apply the extension database upgrades before processing installments.": "Falta la tabla de correspondencia de cuotas HelloAsso. Aplique las actualizaciones de la base de datos de la extensión antes de procesar las cuotas.",
        "The HelloAsso order ID is missing from this recurring contribution.": "Falta el identificador del pedido HelloAsso en esta contribución recurrente.",
        "The HelloAsso order ID stored on this recurring contribution is invalid.": "El identificador del pedido HelloAsso guardado en esta contribución recurrente no es válido.",
        "The contribution mapped to this HelloAsso installment no longer exists.": "La contribución asociada a esta cuota HelloAsso ya no existe.",
        "The HelloAsso payment processor is currently unavailable. Please try again later.": "El procesador de pago HelloAsso no está disponible temporalmente. Por favor, inténtelo de nuevo más tarde.",
        "HelloAsso is currently unavailable. Please try again later.": "HelloAsso no está disponible temporalmente. Por favor, inténtelo de nuevo más tarde.",
    }
    
    new_es = {
        "": {
            "msgstr": build_header("es_ES"),
            "comments": old_es.get("", {}).get("comments", ""),
        }
    }
        
    for msgid in extracted:
        norm_key = normalize(msgid)
        matched_public_key = None
        for pub_key in public_translations_es:
            if normalize(pub_key) == norm_key:
                matched_public_key = pub_key
                break
                
        if matched_public_key:
            new_es[msgid] = {
                'msgstr': public_translations_es[matched_public_key],
                'comments': "#. Public-facing string in checkout or validation flow"
            }
            
    header_es = old_es.get("", {}).get("comments", "# Translation of HelloAsso Payment Processor in Spanish")
    write_po(new_es, es_po_path, header_es)
    compile_mo(new_es, es_mo_path)
    es_runtime_mo_path = os.path.join(l10n_dir, "es_ES", "LC_MESSAGES", "helloasso_payment_processor.mo")
    os.makedirs(os.path.dirname(es_runtime_mo_path), exist_ok=True)
    compile_mo(new_es, es_runtime_mo_path)
    print(f"Synchronized and compiled es_ES.po/mo with public-only translations successfully!")
    
    # Generate helloasso-payment-processor.pot template
    pot_path = os.path.join(l10n_dir, "helloasso-payment-processor.pot")
    pot = {
        "": {
            "msgstr": build_header("", is_template=True),
            "comments": "",
        }
    }
    for msgid in extracted:
        pot[msgid] = {'msgstr': "", 'comments': ""}
    write_po(pot, pot_path, "# POT template for HelloAsso Payment Processor")
    print(f"Generated helloasso-payment-processor.pot template successfully!")

if __name__ == "__main__":
    main()
