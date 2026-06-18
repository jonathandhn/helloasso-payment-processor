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

Les réglages principaux sont disponibles sur la page **HelloAsso settings** :

| Réglage | Défaut | Rôle |
| --- | ---: | --- |
| Pont d'intégration standard (`mjwshared`) | Activé | Intègre le processeur au frontend CiviCRM. |
| Gestion sécurisée des URL d'échec et d'annulation | Activée | Sécurise les retours utilisateurs. |
| File d'attente pour le traitement des webhooks | Activée | Traite les notifications de façon asynchrone. |
| Fiabilisation du statut (`T+5` / `T+15`) | Activée | Rattrape un retour ou un webhook manquant. |
| Intégration Afform / Form Builder | Activée | Expose la Checkout Option HelloAsso. |
| Vérification stricte de la signature historique | Désactivée | Contrôle l'ancien mécanisme `invoiceID` / `sig`. |
| Vérification stricte de la signature partenaire | Activée | Contrôle `x-ha-signature` lorsqu'une clé est disponible. |
| Mire HelloAsso : activer la connexion partagée | Désactivée | Affiche et autorise le parcours de connexion partenaire. |

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
