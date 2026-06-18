# AGENT

Ce document decrit les regles durables du projet `helloasso-payment-processor`.
Il ne doit pas decrire l'etat temporaire d'une instance, d'un poste, d'un worktree ou d'une branche.
Il fixe uniquement:

- les regles techniques de projet
- les invariants metier
- les erreurs de raisonnement a ne pas reproduire

## 1. Regles techniques de projet

### 1.1 Sources de verite

- La source de verite est le depot lui-meme, pas les suppositions d'un agent.
- Quand plusieurs fichiers semblent se contredire, privilegier:
  - les fichiers de configuration effectivement utilises par le projet
  - le code de production reel
  - les invariants metier documentes ici

### 1.2 PHPUnit

- Regle de projet: raisonner en **PHPUnit 11**.
- Si un doute existe, la reference est la configuration PHPUnit du depot, pas un souvenir de version ni une dependance dev ancienne.
- Ne pas introduire de syntaxe ou de conventions de tests incompatibles avec PHPUnit 11.
- Sur un build Drupal, PHPUnit s'utilise ou s'installe au niveau du `composer` racine du site, pas dans l'extension seule.
- Un agent ne doit pas conclure trop vite que PHPUnit est "absent" juste parce que `vendor/bin/phpunit` manque dans le dossier de l'extension.
- Exception importante: ne pas appliquer cette hypothese aveuglement sur un build WordPress ou sur un autre packaging qui n'utilise pas le meme root `composer`.

### 1.2.b Outils du root Drupal

- Sur un build Drupal avec root `composer`, verifier d'abord les executables dans `vendor/bin` du site avant de conclure qu'un outil est absent.
- Cela vaut au minimum pour:
  - `phpunit`
  - `phpstan`
  - `civix`
  - et les autres outils de dev portes par le root Drupal
- Un agent ne doit pas s'arreter a l'absence d'un binaire dans le dossier courant de l'extension si le projet est manifestement pilote par le root Drupal.

### 1.3 PHPStan

- Regle de projet: le nouveau code doit etre ecrit pour etre **au moins de niveau PHPStan 6**.
- Le baseline sert a tolerer de la dette existante, pas a justifier du nouveau code flou.
- Pour tout nouveau code:
  - typer les parametres et retours quand c'est raisonnable
  - documenter les shapes de tableaux importants
  - eviter les effets de bord implicites
  - eviter les chemins de controle dependants d'un contexte cache

### 1.4 Compatibilite CiviCRM

- Regle de projet: la compatibilite minimale importante est `CiviCRM 6.14+`.
- Une incompatibilite partielle avec une sous-feature ne doit pas casser tout le module.
- Si une capacite n'est pas disponible sur une version donnee:
  - masquer ou desactiver uniquement cette capacite
  - laisser fonctionner le reste du flux

### 1.5 Modules Angular et Afform

- Ne jamais desactiver globalement le chargement du module Angular pour contourner une incompatibilite partielle.
- Une limitation sur le multiterme ne doit pas faire disparaitre:
  - l'option de paiement Afform
  - l'admin Afform
  - les options de configuration admin

## 2. Regles d'architecture

### 2.1 Une meme regle metier doit produire le meme resultat dans tous les flux

- QuickForm et Afform peuvent avoir des interfaces differentes.
- Mais le resultat metier doit etre identique entre:
  - QuickForm
  - hosted checkout
  - Afform

En particulier:

- meme interpretation du nombre d'echeances
- meme montant total promis
- meme decoupage du premier paiement et des termes suivants
- meme logique de comptabilisation

### 2.2 Pas de source de verite cachee

- Un flux ne doit pas dependre d'un `$_POST` implicite si un autre flux passe par session, service, objet ou parametres.
- Les donnees necessaires au calcul metier doivent etre transmises explicitement.
- Un agent doit preferer:
  - parametres explicites
  - objets de contexte
  - valeurs derivees de la contribution ou de la session

et eviter:

- les branches implicites liees au formulaire courant
- les effets de bord reposant seulement sur la requete HTTP

### 2.3 Separation stricte entre technique et metier

- Une erreur technique:
  - timeout
  - absence de reponse
  - refus de connexion
  - echec reseau
- Une erreur metier:
  - `409`
  - organisation non autorisee
  - droits OAuth insuffisants
  - reconnexion administrateur necessaire

Ces cas ne doivent pas partager:

- le meme message utilisateur
- le meme stockage d'etat
- la meme logique de reprise
- la meme classification interne

## 3. Invariants metier HelloAsso

### 3.1 Le 409 est une reponse metier

- Un `409` HelloAsso n'est pas un timeout.
- Un `409` HelloAsso n'est pas un `504`.
- Un `409` HelloAsso signifie que l'API a repondu et a oppose une regle metier.
- On ne corrige jamais un probleme d'infrastructure en remappant ou en reinterpretant un `409`.

### 3.2 Les timeouts se corrigent dans le client HTTP

- Si PHP ou le worker peut mourir avant la reponse distante, le bon correctif est au niveau du client HTTP sortant.
- Les timeouts doivent etre poses dans les couches qui appellent HelloAsso.
- Un timeout technique doit echouer proprement sans fabriquer un faux etat metier persistant.

### 3.3 Le multiterme HelloAsso est un paiement fractionne

- Le multiterme n'est pas une recurrence libre au sens metier.
- Le multiterme est un **paiement fractionne** sur un nombre fixe d'echeances.
- Le calcul doit toujours partir de la somme totale promise, puis la fractionner.

Invariant central:

- `totalAmount = initialAmount + somme(terms[].amount)`

Interdits:

- repartir d'un acompte deja calcule pour reconstruire un echeancier global
- traiter un paiement fractionne comme un abonnement libre
- laisser QuickForm et Afform diverger sur le sens du montant

### 3.4 Le premier paiement doit correspondre au montant reellement encaisse

- Le premier paiement d'un echeancier doit etre comptabilise sur le montant reellement encaisse.
- Il ne doit pas etre comptabilise sur le montant total promis si seul l'acompte initial a ete preleve.
- La contribution d'ancrage, le `ContributionRecur.amount` et le paiement cree dans Civi doivent rester coherents.
- La contribution d'ancrage doit representer le premier terme reel, pas toute la promesse restante.
- Si le premier terme de `5 EUR` est entierement encaisse, cette contribution doit pouvoir finir en `Completed`.
- Le plan `ContributionRecur` peut rester `In Progress` ou l'equivalent tant que les echeances suivantes ne sont pas toutes collectees.
- Il ne faut pas laisser la contribution d'ancrage en `Partially paid` uniquement parce que des termes futurs restent a venir.

### 3.5 L'intention utilisateur ne doit pas etre perdue

- Changer temporairement de moyen de paiement ne doit pas effacer silencieusement une intention de paiement fractionne.
- Le JS ne doit pas decoche/vider des champs natifs sans action explicite de l'utilisateur.

## 4. Regles de messages

- Les messages doivent decrire la vraie nature du probleme.
- Un message technique ne doit pas ecraser un message metier plus precis.
- Un paiement fractionne ne doit pas etre decrit avec un vocabulaire de "recurrence habituelle" si cela cree de la confusion.
- Le vocabulaire doit rester coherent entre:
  - QuickForm
  - Afform
  - hosted checkout
  - mire admin / OAuth

## 5. Ce qu'un agent ne doit pas faire

- Ne pas fixer un bug d'infrastructure en changeant une classification metier.
- Ne pas introduire de logique metier qui depend seulement du formulaire courant ou du `POST`.
- Ne pas faire diverger QuickForm et Afform sans raison metier explicite.
- Ne pas couper globalement un module pour contourner une incompatibilite locale.
- Ne pas conclure qu'un diff est bon parce qu'un seul chemin heureux marche.
- Ne pas utiliser la dette existante comme excuse pour ajouter du nouveau code peu typé ou implicite.

## 6. Ce qu'un agent doit verifier avant de conclure

- Le checkout simple continue de fonctionner.
- Le paiement fractionne part bien de la somme totale et la decoupe.
- QuickForm et Afform convergent vers le meme resultat metier.
- Le premier paiement d'un echeancier utilise le bon montant.
- Les timeouts techniques restent traites comme des erreurs techniques.
- Les erreurs metier restent traitees comme des erreurs metier.
- Les messages affiches correspondent bien a la nature reelle du probleme.
