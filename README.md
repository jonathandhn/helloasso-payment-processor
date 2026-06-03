# HelloAsso Payment Processor for CiviCRM

Cette extension permet d'encaisser avec HelloAsso des contributions créées
dans CiviCRM : dons, adhésions, inscriptions et parcours Afform / Form Builder.

CiviCRM reste la source de vérité du parcours métier. L'extension crée le
checkout HelloAsso, rapproche le paiement de la contribution, traite les
webhooks et revérifie les paiements lorsque le retour navigateur ou une
notification ne suffit pas.

L'extension est publiée sous licence [AGPL-3.0](LICENSE.txt).

## Fonctionnalités

- Paiement redirigé via HelloAsso Checkout, en production et en sandbox.
- Configuration historique par clé API HelloAsso.
- Connexion optionnelle par mire d'autorisation HelloAsso, avec enregistrement
  du webhook et vérification de sa signature.
- Intégration Afform / Form Builder par `Checkout Option` CiviCRM.
- File d'attente des webhooks via `PaymentprocessorWebhook`.
- Revérifications courtes à `T+5` et `T+15` minutes après un checkout.
- Suivi long indépendant pour détecter les évolutions ultérieures, notamment
  les remboursements.
- Fonctionnement validé sur des instances CiviCRM Drupal, WordPress et
  Standalone.

## Prérequis

- CiviCRM `6.14` ou version ultérieure.
- Extension CiviCRM `mjwshared`.
- Un compte HelloAsso et, pour les essais, un compte
  [HelloAsso sandbox](https://www.helloasso-sandbox.com/).
- Pour la configuration par clé API : un `client_id`, un `client_secret` et le
  slug de l'organisation HelloAsso.
- Pour la mire : les identifiants partenaires dédiés fournis par HelloAsso
  pour chaque environnement utilisé.

La version de PHP à utiliser est celle supportée par votre version de CiviCRM.

## Installation

1. Installer l'extension dans le répertoire d'extensions CiviCRM.
2. Activer `mjwshared`, puis activer `helloasso-payment-processor`.
3. Appliquer les mises à niveau et vider les caches :

```bash
cv updb
cv flush
```

4. Ouvrir **Administrer > CiviContribute > Passerelles de paiement** et créer
   une passerelle de type **HelloAsso**.

Les nouveaux processeurs HelloAsso utilisent `Credit Card` comme moyen de
paiement par défaut. Une instance qui dispose déjà d'un moyen de paiement en
ligne dédié à HelloAsso peut le sélectionner ; les configurations existantes ne
sont pas remplacées automatiquement.

## Connexion Par Clé API

La clé API classique est le mode conservateur pour une passerelle déjà en
production. Renseigner sur le processeur HelloAsso :

- `Client Id`
- `Client Secret`
- `Organization Name`, correspondant au slug de l'organisation

Les URLs d'API par défaut sont :

- Production : `https://api.helloasso.com`
- Sandbox : `https://api.helloasso-sandbox.com`

Ne pas ajouter `/v5` dans le champ URL de la passerelle. L'extension ajoute
elle-même les routes API nécessaires, par exemple les routes de checkout et de
paiement sous `/v5`.

## Connexion Par Mire HelloAsso

La mire est optionnelle et désactivée par défaut. Elle permet à une
organisation de se connecter depuis CiviCRM et apporte la gestion automatique
du webhook partenaire et de sa clé de signature.

HelloAsso aide les associations à collecter des paiements en ligne et propose
ses services gratuitement. Elle prend à sa charge tous les frais de
transaction pour que vous puissiez bénéficier de la totalité des sommes
versées par vos publics, sans frais. Les contributions volontaires laissées
par ces derniers sont leur unique source de revenus.

Pour utiliser la mire :

1. Ouvrir **Administrer > Paramètres système > HelloAsso settings**.
2. Activer **Mire HelloAsso : activer la connexion partagée**.
3. Ouvrir le rail sandbox ou production proposé sur cette page.
4. Saisir le `client_id` et le `client_secret` partenaires correspondant à cet
   environnement.
5. Copier l'URL de callback affichée par CiviCRM dans la configuration
   HelloAsso, puis lancer la connexion.
6. Vérifier l'organisation liée, l'URL webhook enregistrée et la présence de
   la clé de signature webhook.

Les identifiants mire sandbox et production sont distincts. Ils ne doivent ni
être intervertis, ni être versionnés dans un dépôt public.

La connexion d'une organisation par la mire ne bascule pas silencieusement un
processeur live configuré par clé API. Le mode de connexion du processeur doit
être sélectionné explicitement.

Une même organisation sandbox peut légitimement diffuser ses webhooks vers
plusieurs instances CiviCRM, par exemple via un relais de test. En revanche,
la connexion OAuth par mire doit être considérée comme exclusive pour un même
couple client HelloAsso / organisation / environnement : reconnecter une autre
instance peut invalider les refresh tokens déjà stockés ailleurs. Dans ce cas,
une seule instance doit être propriétaire de la mire ; les autres peuvent
recevoir les webhooks diffusés mais ne doivent pas gérer leur propre liaison
OAuth concurrente avec les mêmes identifiants.

### Restauration D'Une Sauvegarde Avec Mire

Après restauration d'une sauvegarde, les tokens et informations webhook
restaurés peuvent ne plus refléter l'état actuel côté HelloAsso.

- Si la sauvegarde est récente et qu'aucune autre instance n'a reconnecté la
  même mire, le refresh token restauré devrait permettre de récupérer un nouvel
  access token au prochain appel API.
- Si le refresh token restauré est expiré ou a été invalidé par une autre
  reconnexion, l'extension marque la liaison comme `reconnect_required` ou
  `refresh_failed` et une reconnexion administrateur est nécessaire.
- Si la sauvegarde est restaurée sur un autre domaine, les alertes CiviCRM
  signalent les écarts entre le domaine courant, l'URL de callback OAuth et
  l'URL webhook enregistrée.
- Les webhooks signés restent acceptés seulement si la clé de signature locale
  correspond à celle enregistrée côté HelloAsso. Les webhooks non signés ou non
  vérifiables ne valident pas directement un paiement ; ils ne déclenchent une
  confirmation API que pour un objet HelloAsso déjà connu localement.

Après un restore, vérifier les alertes CiviCRM HelloAsso, l'état
`refresh_status`, l'URL webhook et le domaine de callback. Ne reconnecter la
mire que sur l'instance qui doit être propriétaire de cette organisation et de
cet environnement.

## Webhooks Et Fiabilité

En mode mire avec gestion automatique du webhook, l'extension enregistre
l'URL et conserve la clé permettant de vérifier le header
`x-ha-signature`.

En mode clé API, déclarer l'URL de notification du processeur concerné chez
HelloAsso. La route CiviCRM utilise le format :

```text
/civicrm/payment/ipn/ID_DU_PROCESSEUR
```

Utiliser l'URL absolue générée pour l'instance et l'ID réel du processeur
production ou sandbox. Ne pas déduire l'ID sandbox à partir de l'ID production :
les identifiants dépendent de chaque base CiviCRM.

Pour obtenir l'URL absolue correcte, y compris sur WordPress :

```bash
cv ev 'echo CRM_HelloassoPaymentProcessor_Webhook::getWebhookPath(1), PHP_EOL;'
```

Remplacer `1` par l'ID du processeur à déclarer chez HelloAsso.

Par défaut :

- les webhooks sont mis en file d'attente ;
- la signature partenaire est exigée lorsqu'une clé de signature est
  enregistrée ;
- la signature historique `invoiceID` / `sig` n'est pas exigée ;
- deux revérifications courtes sont programmées après le checkout ;
- le suivi long reste indépendant afin de détecter un remboursement postérieur.

### Signatures Des Webhooks

L'extension connaît deux mécanismes de signature, car les sites peuvent
recevoir à la fois des notifications historiques configurées manuellement et
des notifications créées par la mire.

| Réglage | Défaut | Mécanisme | Effet |
| --- | ---: | --- | --- |
| `helloasso_v2_require_partner_webhook_signature` | Activé | Mire / `x-ha-signature` | En mode mire, exige que le header `x-ha-signature` corresponde à la clé de signature stockée lors de l'enregistrement du webhook. Peut être désactivé pour un relais webhook ou une architecture multi-instances qui ne transmet pas cette signature telle quelle. |
| `helloasso_v2_require_webhook_signature` | Désactivé | Legacy / `metadata.invoiceID` + `metadata.sig` | Exige l'ancien HMAC local basé sur l'`invoiceID`. À activer seulement si les webhooks historiques envoyés à cette instance portent bien cette signature. |

Une signature présente et vérifiable doit toujours être correcte. Une
signature absente peut être tolérée selon les réglages ci-dessus, mais le
payload n'est alors pas considéré comme une preuve suffisante de paiement.

Pour les webhooks non signés ou non vérifiables, l'extension ne valide jamais
directement l'état reçu. Elle appelle l'API HelloAsso uniquement si le webhook
référence un objet déjà attendu localement, c'est-à-dire un
`helloasso_payment_id` ou un `checkout_intent_id` stocké dans les métadonnées
de la contribution. Un webhook qui ne correspond qu'à un `invoiceID` local est
ignoré comme preuve directe ; les jobs de suivi se chargent du rattrapage.

Ce comportement protège les installations en transition V1/V2 et les agences
qui utilisent un relais commun : un webhook destiné à un autre client ne doit
pas pouvoir valider une contribution locale ni provoquer des appels API
HelloAsso sur des identifiants inconnus.

## Tâches Planifiées

Vérifier que les jobs suivants sont actifs dans CiviCRM :

| Job | Rôle |
| --- | --- |
| `Job.process_paymentprocessor_webhooks` | Traite les notifications placées en file d'attente. |
| `Job.process_helloasso` | Exécute le rattrapage court `T+5` / `T+15`. |
| `Job.process_helloasso_long_followup` | Contrôle les changements tardifs, dont les remboursements. |
| `Job.refresh_helloasso_partner_links` | Renouvelle les liaisons mire avant leur expiration. |

## Commandes Utiles

Traiter la file de webhooks :

```bash
cv api3 Job.process_paymentprocessor_webhooks
```

Exécuter les contrôles courts arrivés à échéance :

```bash
cv api3 Job.process_helloasso only_scheduled=1 due_before=now limit=15
```

Forcer la synchronisation d'une contribution, y compris lorsque le rail court
automatique est désactivé :

```bash
cv api3 Job.process_helloasso contribution_id=12345 payment_processor_id=1 only_scheduled=0 limit=1
```

Exécuter le suivi long arrivé à échéance :

```bash
cv api3 Job.process_helloasso_long_followup due_before=now limit=15
```

## Réglages V2

Les réglages principaux sont disponibles sur la page **HelloAsso settings**.
Certains réglages techniques restent déclarés pour permettre une surcharge par
configuration ou API, mais ne sont pas exposés dans l'interface afin d'éviter
de désactiver accidentellement des protections de base.

| Setting | Défaut | Exposé dans l'UI | Rôle |
| --- | ---: | --- | --- |
| `helloasso_v2_standard_frontend_bridge` | Activé | Non | Active le pont frontend `CRM.payment` / `mjwshared` utilisé par les formulaires classiques et Webform. |
| `helloasso_v2_safe_abort_urls` | Activé | Non | Remplace les URL d'annulation ou d'erreur fragiles par une URL sûre lorsque le contexte est AJAX ou CiviCRM interne. |
| `helloasso_v2_queue_webhooks` | Activé | Oui, page globale | Place les webhooks dans la file `PaymentprocessorWebhook` au lieu de les traiter immédiatement. |
| `helloasso_v2_followup_enabled` | Activé | Oui, page globale | Programme les contrôles courts `T+5` / `T+15` après création d'un checkout. |
| `helloasso_v2_afform_checkout` | Activé | Oui, page globale | Expose la Checkout Option HelloAsso pour Afform / Form Builder. |
| `helloasso_enable_refunds` | Désactivé | Oui, page globale | Autorise les remboursements complets HelloAsso depuis l'écran de remboursement CiviCRM. Nécessite le mode mire HelloAsso. |
| `helloasso_v2_cron_limit` | `15` | Oui, page globale | Limite le nombre de contributions traitées par processeur lors des jobs de maintenance. |
| `helloasso_v2_require_webhook_signature` | Désactivé | Oui, page globale | Rejette les webhooks legacy dont la signature `invoiceID` / `sig` est absente ou invalide. |
| `helloasso_v2_require_partner_webhook_signature` | Activé | Oui, page globale | Rejette les webhooks mire dont `x-ha-signature` est absent ou invalide lorsqu'une clé de signature est stockée. Peut être désactivé pour les architectures multi-instances ou avec relais webhook. |
| `helloasso_partner_auth_enabled` | Désactivé | Oui, page globale | Affiche et autorise les pages de connexion par mire HelloAsso. |
| `helloasso_partner_client_id_test` | Vide | Oui, page mire sandbox | Client ID partenaire pour la mire sandbox. |
| `helloasso_partner_client_secret_test` | Vide | Oui, page mire sandbox | Client secret partenaire pour la mire sandbox. |
| `helloasso_partner_client_id_live` | Vide | Oui, page mire production | Client ID partenaire pour la mire production. |
| `helloasso_partner_client_secret_live` | Vide | Oui, page mire production | Client secret partenaire pour la mire production. |
| `helloasso_partner_authorize_url` | `https://auth.helloasso.com/authorize` | Oui, pages mire | URL d'autorisation OAuth HelloAsso. |
| `helloasso_partner_token_url` | `https://api.helloasso.com/oauth2/token` | Oui, pages mire | URL d'échange et de renouvellement des tokens OAuth. |

Les données opérationnelles par processeur de la mire sont stockées dans la
table dédiée de l'extension lorsque le schéma est à jour.

## Contributions Et Intégrations Spécifiques

Le processeur doit conserver un coeur générique, mais il a également vocation
à servir de point d'appui à des intégrations plus spécialisées.

Deux formes d'intégration sont déjà utilisées sur le terrain :

- La façade `CRM_HelloassoPaymentProcessor_Service` permet à une extension
  tierce de consulter les données HelloAsso en lecture seule, sans exposer les
  secrets du processeur ni ouvrir de méthode d'écriture. Ce point d'entrée est
  particulièrement important avec la mire : une même instance CiviCRM ne doit
  pas multiplier les clients actifs concurrents pour une même organisation et
  un même environnement. La liaison HelloAsso et ses tokens restent portés par
  le processeur ; les extensions complémentaires réutilisent cette connexion
  via la façade.
- Une extension Drupal peut s'insérer pleinement dans le flux de paiement
  CiviCRM / HelloAsso, par exemple pour porter un parcours Webform métier tout
  en laissant ce processeur gérer le checkout, les webhooks et la
  réconciliation.

### Accès Service En Lecture Seule

Une extension complémentaire peut instancier directement la façade :

```php
$service = new CRM_HelloassoPaymentProcessor_Service();
```

Les méthodes classiques utilisent le processeur HelloAsso actif préféré du
mode demandé. Elles fonctionnent avec le mode clé API classique ou avec la
mire, selon la configuration du processeur :

```php
$isTest = FALSE; // FALSE = production, TRUE = sandbox.

$processors = $service->getProcessors($isTest);
$processor = $service->getPreferredProcessor($isTest);
$payments = $service->listOrganizationPayments($isTest, [
  'from' => '2026-01-01',
  'pageSize' => 100,
]);
$payment = $service->getPayment($isTest, 123456789);
$checkoutIntent = $service->getCheckoutIntent($isTest, 987654321);
```

Les méthodes `Partner*` utilisent obligatoirement un processeur actif connecté
par la mire HelloAsso. Elles choisissent le processeur par environnement :
d'abord le processeur par défaut du mode demandé s'il est lié par mire, sinon
le premier processeur actif lié par mire. Si aucun processeur mire n'est lié,
une `PaymentProcessorException` est levée.

```php
$isTest = TRUE; // Sandbox.

$organization = $service->getPartnerLinkedOrganization($isTest);
$payments = $service->listPartnerOrganizationPayments($isTest, [
  'pageSize' => 100,
]);
$payment = $service->getPartnerPayment(123456789, [], $isTest);
$checkoutIntent = $service->getPartnerCheckoutIntent(987654321, [], $isTest);
```

Pour compatibilité, `listPartnerOrganizationPayments($query)` reste accepté et
utilise la production par défaut. Les nouveaux développements doivent préférer
`listPartnerOrganizationPayments($isTest, $query)` pour éviter toute ambiguïté.

Les propositions de fonctions helper ou de points d'extension facilitant ce
type d'intégration sont bienvenues, notamment pour Webform, Services ou
d'autres parcours métier. Elles doivent rester isolées et documentées, afin
de ne pas imposer un comportement spécifique aux parcours CiviCRM standards.

## Crédits

L'extension originale a été initiée par civiuser (Sidney) et Pierre Morvan :
[dépôt historique](https://github.com/ryarnyah/helloasso-payment-processor).

La version publiée par Makoa a été développée par Antoine Breheret et Dewy
Mercerais.

La branche V2, ses intégrations multi-CMS et ses mécanismes de fiabilisation
ont été développés et validés par Jonathan Dahan
([jonathan.dhn.one](https://jonathan.dhn.one), `jonathan@dhn.one`).

Contributions historiques : Guillaume Sorel / All In Appli et
[Symbiotic](https://symbiotic.coop).

## Licence

HelloAsso Payment Processor for CiviCRM est un logiciel libre distribué sous
licence [AGPL-3.0](LICENSE.txt). Ce projet n'est pas une publication officielle
de HelloAsso.
