# HelloAsso payment processor

la verion avec image se trouve dans la [documentation](https://docs.google.com/document/d/1vIahUu0339Ie-DJn4ks_U38a3Q56KZT82TDPtDrk6E8/edit)

L’extension Helloasso-payment-processor permet la création et le paiement de contributions (dons, inscriptions, adhésions) dans CiviCRM via la passerelle de paiement HelloAsso (“HelloAsso Checkout” uniquement). 

Les paiements créent automatiquement la contribution avec le statut adéquat et le règlement.

Il s’agit de transmettre les données nécessaires à HelloAsso (InvoiceID pour associer la contribution à la transaction) et CiviCRM pour pré-remplir le formulaire de paiement HelloAsso 


This is an [extension for CiviCRM](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/), licensed under [AGPL-3.0](LICENSE.txt).

# Crédits
La v1 a été développée par civiuser (Sidney) et Pierre MORVAN : https://github.com/ryarnyah/helloasso-payment-processor.git
Moved here: https://lab.civicrm.org/ryarnyah/helloasso-payment-processor/

La version actuelle a été développée par Makoa
(Antoine Breheret, Dewy Mercerais)

Contributeurs : 
- Jonathan Dahan - https://www.sos-homophobie.org
- Guillaume Sorel - https://all-in-appli.com
- Symbiotic - https://symbiotic.coop

API HelloAsso - HelloAsso-2024-Extension-CiviCRM

# Requirements
- PHP v7.2+
- CiviCRM 5.65 or later (hopefully)


# Getting Started

- avoir un compte HelloAsso (https://www.helloasso.com/)
- et un compte sandBox HelloAsso (https://www.helloasso-sandbox.com).
Le sandbox vous permet de faire des tests.
- connaître ces 4 valeurs :
  - client id
  - client secret
  - Organization name : à trouver dans l’URL https://admin.helloasso.com/nom-de-l-organisation/integrations
  - URL du Site : 
    - https://api.helloasso.com/v5 (live)
    - https://api.helloasso-sandbox.com/v5 (test)

Vous les trouverez dans le back-office de HelloAsso 
https://admin.helloasso.com > Mon compte > Intégration et API

# Installation & Paramétrage
1. Installer l'extension 
source de l’extension : gitlab ou shop officiel CiviCRM
Elle crée un type de passerelle de paiement HelloAsso (comparable dans le principe à Paypal, Stripe ou le SEPA)

2. (Optionnel) Créer votre moyen de paiement “HelloAsso”.
Dans Administrer > CiviContribute > moyen de paiement 
Cela aidera votre comptable à retrouver les contributions provenant de HelloAsso.

3. Créer votre passerelle de paiement 
Administrer > CiviContribute > Passerelle de paiement
- Cliquer sur "Ajouter une passerelle de paiement"
- Sélectionner “Type de passerelle de paiement” = “HelloAsso”

_Si vous ne le voyez pas c’est que le type passerelle HelloAsso n’est pas actif. Dans ce cas, il faut l’activer dans la table civicrm_payment_processor_type.is_active =1. Ou alors faire un cv flush ou drush cvapi sytem.flush afin que le managed soit correctement pris en compte)_

une fois votre passerelle de paiement créer vous avez les id de production et de test (voir image dans documentaion)
**Votre passerelle de paiement de production** (ici d’[id_production] = 4) 
**Votre passerelle de test  : Son [id_test] est ID de production - 1**, donc ici se serait [id_test]= 3.( car la passerelle est créée avant celui de production et depuis les version de civicrm > 5.65 on ne voit plus l’id de test)
Vision de la version 5.65 (voir image dans documentaion)

# Notification URLCallBack

Important il faut paramétrer votre url de callBack.  
Dans votre compte HelloAsso il faut dans “Mon Compte > Intégrations et API”, renseigner “Mon URL de callback” dans la partie Notifications qui sera : 
- En DRUPAL
  - Pour votre production : https://[host]/civicrm/payment/ipn/[id_production]
  - Pour votre test (sandBox) : https://[host]/civicrm/payment/ipn/[id_test]
- EN WORDPRESS 
  - Pour votre production : https://[host]/wp-admin/admin.php?page=CiviCRM&q=civicrm/payment/ipn/[id_production]
  - Pour votre test (sandBox) : https://[host]/wp-admin/admin.php?page=CiviCRM&q=civicrm/payment/ipn/[id_test]
_avec [host] : domaine de votre site exemple “monsite.com”_

# Remarques
Lors des tests sur la SandBox
Utiliser une carte CB de test prévue : (https://docs.sips.worldline-solutions.com/fr/cartes-de-test.html.)
Ne pas utiliser les cartes mastercard (cela ne fonctionne pas et vous évite une perte de temps de test)


# Erreurs connues
## Could not get OAuth token for Payment Processor
Cela veut dire que votre clientId et/ou votre ClientSecret n’est pas valide.

## Type de passerelle de paiement n’est pas visible dans la page de création d’une nouvelle passerelle
Si vous ne le voyez pas c’est que le type passerelle HelloAsso n’est pas actif. Dans ce cas, il faut l’activer dans la table civicrm_payment_processor_type.is_active =1. Ou alors faire un cv flush ou drush cvapi sytem.flush afin que le managed soit correctement pris en compte)

# Hors Périmètre - idées de développement
- Modal Pop-up (timeout 15’’)
- Un seul paiement pour 2 contributions (adhésion + don)
- Distinguer le processeur de paiement (checkout) et l’extension de configuration qui traite les données (meta données).
- Gestion détaillée des erreurs :
  - garbage collector : mécanisme de nettoyage dans CiviCRM type cron job – table de log : Dans les 5 jours appeler API HelloAsso pour voir où en sont les contributions qui ne sont pas en completed
  - webhook : erreur / paiement non collecté / demande de remboursement…
  - specs du traitement des échecs (à faire pour une version suivante)

# License
HelloAsso Payment Processor for CiviCRM.
Makoa n’a aucun lien avec HelloAsso.
This program is free software: you can redistribute it and/or modify it. This is an [extension for CiviCRM](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/), licensed under AGPL-3.0.



