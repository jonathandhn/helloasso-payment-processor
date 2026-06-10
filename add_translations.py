import re

NEW_FRENCH_TRANSLATIONS = {
    "%1 (#%2, %3)": "%1 (#%2, %3)",
    "Access Token": "Jeton d'accès",
    "Amount": "Montant",
    "Checkout Intent ID": "ID de l'intention de paiement",
    "Checkout intent ID": "ID de l'intention de paiement",
    'Comma-separated contribution status names to include, for example "Completed,Pending".': 'Noms de statuts de contribution séparés par des virgules à inclure, par exemple "Completed,Pending".',
    'Comma-separated contribution status names to include, for example "Pending,Failed".': 'Noms de statuts de contribution séparés par des virgules à inclure, par exemple "Pending,Failed".',
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
    "production": "production"
}

with open('scripts/sync_l10n.py', 'r') as f:
    content = f.read()

# Generate the block to insert
insert_lines = []
for k, v in NEW_FRENCH_TRANSLATIONS.items():
    k_escaped = k.replace('"', '\\"')
    v_escaped = v.replace('"', '\\"')
    insert_lines.append(f'    "{k_escaped}": "{v_escaped}",')

insert_block = '\n'.join(insert_lines)

# Find where FRENCH_TRANSLATIONS ends
pattern = r'(FRENCH_TRANSLATIONS = \{[\s\S]*?)(\n\})'
new_content = re.sub(pattern, r'\1\n' + insert_block + r'\2', content)

with open('scripts/sync_l10n.py', 'w') as f:
    f.write(new_content)

print("Updated sync_l10n.py")
