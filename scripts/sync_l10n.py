#!/usr/bin/env python3
import os
import re
import ast
import struct
import unicodedata
from datetime import datetime, timezone

PROJECT_VERSION = "helloasso-payment-processor 2.0.0"
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
    "Maximum processing batch size (Cron)": "Taille maximale des lots de traitement (Cron)",
    "Maximum number of HelloAsso contributions processed per payment processor during a normal cron execution.": "Nombre maximum de contributions HelloAsso traitées par processeur pendant une exécution normale du cron.",
    "Strict legacy signature verification": "Vérification stricte de la signature historique",
    "Reject HelloAsso webhooks whose legacy invoiceID/sig signature is missing or invalid.": "Refuse les webhooks HelloAsso dont la signature legacy invoiceID/sig est absente ou invalide.",
    "Strict partner signature verification": "Vérification stricte de la signature partenaire",
    "Reject HelloAsso partner webhooks whose x-ha-signature header is missing or invalid when a webhook signature key is stored for this processor.": "Refuse les webhooks partenaire HelloAsso dont le header x-ha-signature est absent ou invalide quand une clé de signature webhook est enregistrée pour ce processeur.",
    "HelloAsso authorization screen: enable shared connection": "Mire HelloAsso : activer la connexion partagée",
    "Enable the shared HelloAsso OAuth authorization screen. When this setting is disabled, the authorization-screen interface is no longer offered on HelloAsso processor pages.": "Active la mire OAuth HelloAsso partagée. Quand ce réglage est désactivé, l'interface mire n'est plus proposée sur les pages processeur HelloAsso.",
    "HelloAsso authorization screen: shared client ID": "Mire HelloAsso : client ID partagé",
    "Client ID provided by HelloAsso for the shared authorization screen. Do not commit this value to a public repository.": "Client ID fourni par HelloAsso pour la mire partagée. Ne pas versionner cette valeur dans un dépôt public.",
    "Client Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le Client ID n'est pas renseigné sur cette passerelle de paiement.",
    "Client Secret Id is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le Client Secret n'est pas renseigné sur cette passerelle de paiement.",
    "HelloAsso authorization screen: production client ID": "Mire HelloAsso : client ID production",
    "Client ID dedicated to the HelloAsso production authorization screen.": "Client ID dédié à la mire HelloAsso production.",
    "HelloAsso authorization screen: sandbox client ID": "Mire HelloAsso : client ID sandbox",
    "Client ID dedicated to the HelloAsso sandbox authorization screen.": "Client ID dédié à la mire HelloAsso sandbox.",
    "HelloAsso authorization screen: shared client secret": "Mire HelloAsso : client secret partagé",
    "Client secret provided by HelloAsso for the shared authorization screen. Keep this value locally on the CiviCRM instance.": "Client secret fourni par HelloAsso pour la mire partagée. À conserver localement sur l'instance CiviCRM.",
    "HelloAsso authorization screen: production client secret": "Mire HelloAsso : client secret production",
    "Client secret dedicated to the HelloAsso production authorization screen.": "Client secret dédié à la mire HelloAsso production.",
    "HelloAsso authorization screen: sandbox client secret": "Mire HelloAsso : client secret sandbox",
    "Client secret dedicated to the HelloAsso sandbox authorization screen.": "Client secret dédié à la mire HelloAsso sandbox.",
    "HelloAsso authorization screen: authorization URL": "Mire HelloAsso : URL d'autorisation",
    "HelloAsso authorization screen URL. In production, the default value is https://auth.helloasso.com/authorize.": "URL de l'écran d'autorisation HelloAsso. En production, la valeur par défaut est https://auth.helloasso.com/authorize.",
    "HelloAsso authorization screen: token URL": "Mire HelloAsso : URL du token",
    "HelloAsso endpoint used to exchange the authorization code and refresh OAuth tokens.": "Endpoint HelloAsso utilisé pour échanger le code d'autorisation et rafraîchir les jetons OAuth.",
    "Classic API key": "Clé API classique",
    "HelloAsso sandbox authorization screen": "Mire HelloAsso sandbox",
    "HelloAsso production authorization screen": "Mire HelloAsso production",
    "Connect production to HelloAsso": "Connecter la production à HelloAsso",
    "Connect sandbox to HelloAsso": "Connecter le sandbox à HelloAsso",
    "Connect to HelloAsso": "Connecter à HelloAsso",
    "HelloAsso production connection": "Connexion HelloAsso production",
    "HelloAsso sandbox connection": "Connexion HelloAsso sandbox",
    "Live payment processor ID": "ID du processeur live",
    "Sandbox payment processor ID": "ID du processeur sandbox",
    "Live connection mode": "Mode de connexion live",
    "Sandbox connection mode": "Mode de connexion sandbox",
    "Automatically enable the webhook": "Activer automatiquement le webhook",
    "Enable automatic registration of the live HelloAsso webhook for this CiviCRM instance by default. Uncheck only if another instance retains control of the webhook URL.": "Active par défaut l'enregistrement automatique du webhook HelloAsso live pour cette instance CiviCRM. Décochez seulement si une autre instance garde la maîtrise de l'URL webhook.",
    "Enable automatic registration of the HelloAsso webhook for this CiviCRM instance by default. Uncheck only if multiple CiviCRM instances share the same HelloAsso organization and you want to manage the webhook manually.": "Active par défaut l'enregistrement automatique du webhook HelloAsso pour cette instance CiviCRM. Décochez seulement si plusieurs instances CiviCRM partagent la même organisation HelloAsso et que vous voulez gérer le webhook manuellement.",
    "This block connects the production HelloAsso authorization screen on this live processor: OAuth link, linked organization, webhook and signature key. The live payment rail can switch to the authorization screen only when this processor no longer uses classic API keys.": "Ce bloc permet de connecter la mire HelloAsso production sur ce processeur live : liaison OAuth, organisation liée, webhook et clé de signature. Le rail de paiement live ne peut basculer sur la mire que si ce processeur n'utilise plus de clés API classiques.",
    "Live API credentials are still present on this processor. The production authorization screen can be linked and tested, but the live payment mode remains locked to the classic API key until these credentials are removed.": "Des identifiants API live sont encore présents sur ce processeur. La mire production peut être reliée et testée, mais le mode de paiement live reste bloqué sur la clé API classique tant que ces identifiants ne sont pas retirés.",
    "No live API key is stored on this processor. You can therefore enable production authorization-screen mode on this processor once the OAuth link has been validated.": "Aucune clé API live n'est enregistrée sur ce processeur. Vous pouvez donc activer le mode mire production sur ce processeur si la liaison OAuth a été validée.",
    "Production organization linked: %1": "Organisation production liée : %1",
    "Linked on: %1": "Liée le : %1",
    "Access token valid until: %1": "Jeton d'accès valable jusqu'au : %1",
    "Authorization link valid until: %1": "Liaison d'autorisation valable jusqu'au : %1",
    "Webhook management: %1": "Gestion du webhook : %1",
    "Registered webhook URL: %1": "URL webhook enregistrée : %1",
    "Stored webhook signature key: %1": "Clé de signature webhook enregistrée : %1",
    "No HelloAsso production organization is linked to this processor yet.": "Aucune organisation HelloAsso production n'est encore liée à ce processeur.",
    "HelloAsso Organization Name is not set in the Administer CiviCRM &raquo; System Settings &raquo; Payment Processors.": "Le nom de l'organisation HelloAsso n'est pas renseigné sur cette passerelle de paiement.",
    "Enter the shared client ID and client secret on the authorization-screen settings page first, then return to start the connection.": "Renseignez d'abord le client ID et le client secret partagés sur la page de réglages de la mire, puis revenez lancer la connexion.",
    "Open production authorization-screen settings": "Ouvrir les réglages de la mire production",
    "Sandbox API credentials are already present on this processor. API key mode remains the safest choice until you explicitly switch.": "Des identifiants API sandbox sont déjà présents sur ce processeur. Le mode par clé API reste le choix le plus prudent tant que vous ne basculez pas explicitement.",
    "No sandbox API key is stored on this processor. The HelloAsso sandbox authorization screen is therefore offered by default.": "Aucune clé API sandbox n'est enregistrée sur ce processeur. La mire HelloAsso sandbox est donc proposée par défaut.",
    "Sandbox organization linked: %1": "Organisation sandbox liée : %1",
    "No HelloAsso sandbox organization is linked to this processor yet.": "Aucune organisation HelloAsso sandbox n'est encore liée à ce processeur.",
    "Open authorization-screen settings": "Ouvrir les réglages de la mire",
    "This button remains disabled until the shared HelloAsso authorization-screen client ID and client secret are configured.": "Ce bouton reste désactivé tant que le client ID et le client secret partagés de la mire HelloAsso ne sont pas configurés.",
    "HelloAsso helps associations collect online payments and provides its services free of charge. It covers all transaction fees so that you can receive the full amount paid by your supporters, without fees. Voluntary contributions left by them are its only source of revenue.": "HelloAsso aide les associations à collecter des paiements en ligne et propose ses services gratuitement. Elle prend à sa charge tous les frais de transaction pour que vous puissiez bénéficier de la totalité des sommes versées par vos publics, sans frais. Les contributions volontaires laissées par ces derniers sont leur unique source de revenus.",
    "manual / another instance in control": "manuel / autre instance maîtresse",
    "managed by this CiviCRM instance": "géré par cette instance CiviCRM",
    "No matching HelloAsso payment processor was found on this instance.": "Aucun processeur HelloAsso correspondant n'a été trouvé sur cette instance.",
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
    "For an initial configuration: enable the authorization screen here, save the page, then open the sandbox or production rail to enter the client ID and client secret before starting the connection.": "Pour une première configuration : activez la mire ici, enregistrez la page, puis ouvrez le rail sandbox ou production pour saisir le client ID et le client secret avant de lancer la connexion.",
    "The official button above opens the HelloAsso connection. If you need to adjust client credentials or check the link status, use the settings link first.": "Le bouton officiel ci-dessus ouvre la connexion HelloAsso. Si vous avez besoin d'ajuster les identifiants client ou de vérifier l'état de liaison, utilisez d'abord le lien d'ouverture des réglages.",
    "Refresh this HelloAsso settings page": "Actualiser cette page de réglages HelloAsso",
    "HelloAsso authorization screen": "Mire HelloAsso",
    "Shared client ID": "Client ID partagé",
    "Sandbox shared client ID": "Client ID partagé sandbox",
    "Shared client secret": "Client secret partagé",
    "Sandbox shared client secret": "Client secret partagé sandbox",
    "Payment processor ID: %1": "ID du processeur de paiement : %1",
    "Leave blank to keep the current secret.": "Laisser vide pour conserver le secret actuel.",
    "Token URL": "URL du token",
    "This page is used to enter the shared client, display the link status and start the HelloAsso connection. The general authorization-screen activation switch is configured in the HelloAsso settings.": "Cette page sert à renseigner le client partagé, afficher l'état de liaison et lancer la connexion HelloAsso. Le switch général d'activation de la mire se règle depuis les paramètres HelloAsso.",
    "Open HelloAsso settings": "Ouvrir les paramètres HelloAsso",
    "Authorization URL": "URL d'autorisation",
    "Save authorization-screen settings": "Enregistrer les réglages de la mire",
    "Callback URL to declare at HelloAsso:": "URL de callback à déclarer chez HelloAsso :",
    "The authorization screen is disabled globally. Enable it in HelloAsso settings, then return to this page to connect an organization.": "La mire est désactivée globalement. Activez-la dans les paramètres HelloAsso puis revenez sur cette page pour connecter une organisation.",
    "Disconnect the linked organization": "Déconnecter l'organisation liée",
    "No HelloAsso organization is linked yet.": "Aucune organisation HelloAsso n'est encore liée.",
    "Online contribution": "Contribution en ligne",
    "Online contribution: %1": "Contribution en ligne : %1",
    "Unable to reload contribution %1.": "Impossible de recharger la contribution %1.",
    "Invalid HelloAsso webhook payload in the queue.": "Payload webhook HelloAsso invalide dans la file.",
    "Invalid HelloAsso processor configuration for %1 (%2):": "Configuration du processeur HelloAsso %1 (%2) invalide :",
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
    "HelloAsso: unable to obtain the payment processor OAuth token.": "HelloAsso : impossible de recuperer le jeton OAuth du processeur de paiement.",
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
    "HelloAsso accepted the refund request but did not return a refund operation ID.": "HelloAsso a accepté la demande de remboursement mais n'a pas retourné d'identifiant d'opération de remboursement.",
    "HelloAsso has accepted the refund request. The local CiviCRM refund has been recorded immediately; the final HelloAsso refund state will be confirmed later by webhook or scheduled synchronization.": "HelloAsso a accepté la demande de remboursement. Le remboursement local CiviCRM a été enregistré immédiatement ; l'état final du remboursement HelloAsso sera confirmé ensuite par webhook ou synchronisation planifiée.",
    "HelloAsso refund requested": "Remboursement HelloAsso demandé",
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
    "A HelloAsso authorization link could not be renewed. Reconnect it before accepting new payments through the authorization screen: %1": "Une liaison d'autorisation HelloAsso n'a pas pu être renouvelée. Reconnectez-la avant d'accepter de nouveaux paiements via la mire : %1",
    "HelloAsso: Reconnection Required": "HelloAsso : reconnexion nécessaire",
    "refresh refused": "rafraîchissement refusé",
    "instead of": "au lieu de",
    "Domain mismatch detected: this CiviCRM instance runs on a different host than the one authorized for the HelloAsso link: %1. OAuth callbacks will not function correctly on this staging/dev instance.": "Un décalage de domaine a été détecté : cette instance CiviCRM tourne sur un hôte différent de celui utilisé pour la liaison HelloAsso de : %1. Les redirections et retours OAuth ne fonctionneront pas correctement sur cette instance de test/staging.",
    "HelloAsso: Domain Mismatch (Staging/Dev Instance)": "HelloAsso : Décalage de domaine (Instance de Test/Staging)",
    "The HelloAsso webhook registered for %1 points to a different domain. Inbound payments will not be processed on this CiviCRM instance: %2.": "Le webhook enregistré pour %1 pointe vers un domaine différent de cette instance. Les notifications de paiement HelloAsso ne seront pas reçues ni traitées sur ce CiviCRM : %2.",
    "HelloAsso: Webhook Domain Mismatch": "HelloAsso : Décalage de domaine du Webhook",
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
    regex = re.compile(r'\bE?::ts\(\s*(?:\'((?:[^\'\\]|\\.)*)\'|"((?:[^"\\]|\\.)*)")\s*(?:,|\))')
    
    for dirpath, _, filenames in os.walk(root_dir):
        if any(ignored in dirpath for ignored in [".git", "tests", "l10n", "vendor"]):
            continue
        for filename in filenames:
            if filename.endswith(".php"):
                path = os.path.join(dirpath, filename)
                with open(path, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
                for m in regex.finditer(content):
                    val = m.group(1) or m.group(2)
                    # Decode single or double quotes escapes
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
        if msgid in old_fr and old_fr[msgid].get('msgstr'):
            new_fr[msgid] = old_fr[msgid]
        elif msgid in FRENCH_TRANSLATIONS:
            new_fr[msgid] = {
                'msgstr': FRENCH_TRANSLATIONS[msgid],
                'comments': "#. Maintained French translation"
            }
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
