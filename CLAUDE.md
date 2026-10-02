# Blocs Creator — consignes pour travailler sur le plugin

Ce fichier est pour qui **modifie le plugin** (Yanis, ou Claude). Pour
**construire un site** avec le plugin, lire `docs/guide-theme.md`. Pour la
présentation complète, `README.md`.

## Ce qu'est le plugin, et ce qu'il n'est pas

Une frontière, tenue depuis la 1.0 : **ce qu'un bloc contient** se déclare
(champs, en base, exportables en JSON) ; **ce à quoi il ressemble** s'écrit
dans un fichier PHP du thème (`blocs/<identifiant>.php`). Le plugin ne génère
jamais de balisage à la place du thème.

Depuis la 5.0, le plugin porte aussi ce qu'on réécrivait à chaque site :

- le **moteur d'apparitions** (scènes, variantes de parties, titres mot à mot,
  parties qui attendent leur tour, blocs natifs du contenu) ;
- l'**IA de l'éditeur** (rédiger un article, remplir une section, extension
  « AI » de WordPress) ;
- les **outils de thème** (`definitions.json`, migrations, WP-CLI) ;
- des **modules optionnels** (maintenance, bandeau cookies, brouillons de
  formulaires, pas de page d'auteur, pas de recherche, bouton Modifier, nom des
  blocs) et une boîte à outils d'e-mails.

**Ce qui n'entre pas dans le plugin** : tout ce qui est dessin ou comportement
propre à un projet. Compteurs, cartes inclinables, boutons aimantés,
parallaxe, accordéons de FAQ, en-tête qui se cache, frise qui se trace, menu
mobile : Yanis les développe dans le thème, en fonction du projet. Avant de
remonter quelque chose d'un site dans le plugin, se demander : « est-ce un
moteur ou une fonction de site, ou est-ce du dessin ? ». Dans le doute,
demander.

## Conventions

- **Tout est en français** : noms de fonctions, de variables, de classes CSS,
  commentaires, textes de l'interface. Garder ce ton : des commentaires qui
  disent *pourquoi*, en phrases, souvent avec l'incident qui a appris la règle.
- Préfixes : fonctions `blocs_creator_*`, classes `Blocs_Creator_*`, options
  `blocs_creator_*`, crochets `blocs_creator_*`, CSS et attributs `bc-*` /
  `data-bc-*`, domaine de traduction `blocs-creator`. Variables globales des
  vues : `$blocs_creator_*` (règle du dépôt WordPress).
- **Aucun outil de build.** JavaScript natif, `wp.element.createElement` plutôt
  que JSX, `var` et fonctions classiques dans le code du site (il doit tourner
  partout). Vérifier avec `node --check fichier.js`.
- Normes WordPress (tabulations, conditions Yoda, espaces dans les
  parenthèses, échappement à la sortie). Vérifier avec `php -l`.
- Les fichiers d'assets prennent leur version de leur date
  (`Blocs_Creator_Plugin::version_fichier()`, `Blocs_Creator_Modules::version()`).
- Une valeur pré-remplie d'un champ de formulaire **doit passer ses propres
  contraintes** (`min`, `step`, `pattern`) : sinon le navigateur bloque tout le
  formulaire en silence (bug des 4.1 et 4.2.0).

## Ce qui ne bouge jamais

C'est écrit en base ou dans le contenu des pages : le changer casse des sites.

- les noms de blocs (`espace/slug`), le type de publication `bc_bloc` ;
- les noms d'options (`blocs_creator_reglages`, `blocs_creator_animations`,
  `blocs_creator_ia`, `blocs_creator_modules`, `blocs_creator_maintenance`,
  `blocs_creator_cookies`, `blocs_creator_migrations`…) ;
- les classes et attributs posés sur le site (`bc-anim`, `data-bc-anim`,
  `data-bc-part`, `is-vu`, `bc-anim-prete`, `bc-attend`, `bc-mot`…) ;
- les crochets publics et l'API des gabarits (`includes/fonctions.php`) ;
- le cookie `bc_cookies`, le préfixe `bc-brouillon:` du stockage local.

Une fonction publique qu'on veut renommer garde un alias.

## Carte du code

```
blocs-creator.php                    En-tête, constantes, point d'entrée
includes/
  class-blocs-creator-plugin.php     Bootstrap : liste des fichiers, ordre de démarrage
  class-blocs-creator-champs.php     Catalogue des types de champs
  class-blocs-creator-definition.php Une définition : lecture, normalisation, écriture
  class-blocs-creator-registre.php   Enregistrement des blocs, données de l'éditeur
  class-blocs-creator-rendu.php      Appel du gabarit
  class-blocs-creator-animations.php Apparitions : réglages, pile de rendu, amorce
  class-blocs-creator-ia.php         IA : réglages, appels, routes REST, panneaux
  class-blocs-creator-modules.php    Registre des modules optionnels
  class-blocs-creator-theme.php      definitions.json du thème : état, import, export
  class-blocs-creator-migrations.php Migrations déclarées par le thème
  class-blocs-creator-cli.php        Commandes `wp blocs-creator …`
  fonctions.php                      API des gabarits (blocs_creator_*)
  emails.php                         Boîte à outils des e-mails
admin/                               Écrans : liste, bloc, réglages (5 onglets), outils
assets/js/editeur.js                 L'éditeur commun des blocs générés
assets/js/animations.js              Révéler blocs et parties
assets/js/ia-article.js, ia-section.js   Panneaux d'IA
assets/js/apercus-inertes.js         Aperçus de l'éditeur sans clic
assets/css/animations.css            Scènes et variantes
modules/<id>/module.php              Un module (chargé seulement s'il est actif)
docs/guide-theme.md                  Construire un thème avec le plugin
```

## Pièges connus

- **Pile de rendu** (`Blocs_Creator_Animations::empiler()` / `rendre()`) :
  `render_block_data` empile, `render_block` dépile. `rendre()` dépile **en
  premier**, avant tout `return` : sinon la pile se décale pour toute la page.
- **Une nouvelle variante de partie** (`data-bc-part="…"`) avec un état de
  départ à elle doit aussi l'écrire dans le bloc `bc-attend` de
  `animations.css`, sinon elle part de l'état commun quand elle attend.
- **Bas de page** : le dernier bloc n'atteint jamais la ligne d'entrée sur un
  grand écran ; c'est la ronde de `animations.js` (toutes les 2 s) qui le
  révèle. Ne pas la retirer.
- **Canevas de l'éditeur** : c'est une iframe dont WordPress remplace le
  `<body>` au chargement. Un script chargé par `enqueue_block_assets` ne
  compte jamais sur `document.body` au démarrage (incident du 29/09/2026).
- **`get_bloginfo()` rend du HTML** : décoder (`wp_specialchars_decode`) avant
  de l'envoyer à une IA ou dans un e-mail.
- **IA** : Gemini ne renvoie rien tant qu'il réfléchit → délai de 90 s
  (`http_request_args` + `wp_ai_client_default_request_timeout`). Le 503
  (surcharge) et le 429 (quota) basculent sur le modèle suivant ; un délai
  dépassé ne se retente pas. Éviter `gemini-3.8-flash` (503 fréquents).
- **Formulaires de site** : une extension qui cache wp-admin
  (change-wp-admin-login…) casse `admin-post.php` pour les visiteurs. Un
  formulaire public s'envoie à la page elle-même, jamais à admin-post.
- **Envoi FTP non atomique** : un fichier peut être lu à moitié écrit. Une
  migration vérifie ses fichiers avec un témoin
  (`blocs_creator_migration_fichiers()`) avant de toucher à la base.
- L'écran des réglages ne passe pas par `options.php` (voir l'en-tête de
  `admin/vues/reglages.php`) : un nouvel onglet ajoute ses champs au même
  formulaire et son enregistrement dans `Blocs_Creator_Admin::action_reglages()`.
  Une case décochée n'envoie rien : son absence vaut « non ».

## Tester sans site

Pas de PHP global sur la machine : celui de Local (Flywheel) sert, **sans
jamais toucher au site Club Canin ni à sa base** (sa `wp-config.php` locale
porte les identifiants de production).

```bash
PHP="$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php"
find . -name "*.php" -not -path "./.git/*" -exec "$PHP" -l {} \; | grep -v "No syntax errors"
for f in assets/js/*.js admin/js/*.js modules/*/*.js; do node --check "$f"; done
```

Pour un vrai essai, un WordPress jetable dans un dossier temporaire :

1. MySQL de Local : `mysqld --no-defaults --initialize-insecure --datadir=<tmp>/data`,
   puis lancé avec `--port=33099 --socket=<chemin court>` (le chemin du socket
   doit faire moins de 104 caractères) ;
2. copier le cœur de WordPress (sans `wp-config.php` ni `wp-content`), une
   `wp-config.php` neuve sur `127.0.0.1:33099`, le plugin en lien symbolique ;
3. WP-CLI : `"$PHP" /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar --path=<tmp>/wp …`
   (`core install`, `plugin activate blocs-creator`, `blocs-creator …`) ;
4. `"$PHP" -S 127.0.0.1:8099 -t <tmp>/wp` et le navigateur intégré. Il est
   souvent caché : les transitions n'y avancent pas et `IntersectionObserver`
   ne s'y déclenche pas — vérifier l'état par script, et forcer
   `document.visibilityState` pour faire tourner la ronde.

Tout arrêter et supprimer à la fin.

## Publier une version

1. `blocs-creator.php` : `Version:` et `BLOCS_CREATOR_VERSION`.
2. `readme.txt` : `Stable tag:` et une entrée de `== Changelog ==` (en anglais,
   comme le reste du fichier).
3. `README.md` : la section « Nouveautés » si quelque chose change pour qui
   s'en sert.
4. Une montée qui demande un rattrapage en base va dans
   `Blocs_Creator_Plugin::mettre_a_jour()`.
5. **Yanis fait lui-même les commits et le push** : ne pas commiter.
