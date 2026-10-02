# Construire un site avec Blocs Creator

Le guide à lire au début d'un nouveau projet — par Yanis, ou par Claude quand
c'est lui qui écrit le thème. Il dit comment on monte un thème autour du
plugin, et où chaque chose doit vivre.

Pour modifier le plugin lui-même : `CLAUDE.md`. Pour le détail de l'API des
gabarits et des types de champs : `README.md`.

---

## 1. Le partage des rôles

| Le plugin | Le thème |
|---|---|
| Les **champs** de chaque bloc (définitions) | Le **dessin** de chaque bloc (`blocs/<id>.php`, `blocs/<id>.css`) |
| Le moteur d'**apparitions** (scènes, variantes, mot à mot, attente) | Quelles parties entrent, et comment (`data-bc-part` dans les gabarits) |
| L'**IA** de l'éditeur | Les consignes propres au site (Réglages › IA, ou filtres) |
| Les **modules** (maintenance, cookies, brouillons…) | Lesquels il impose, et leur allure (variables CSS, gabarit de maintenance) |
| Les **outils** : `definitions.json`, migrations, WP-CLI | Le fichier `definitions.json`, ses migrations |
| — | **Les micro-interactions** : compteurs, cartes inclinables, boutons aimantés, parallaxe, accordéons, en-tête qui se cache, frise, menu mobile |

La dernière ligne est une règle : ce qui est du dessin ou du comportement
propre au projet s'écrit dans le thème, à la main, pour ce projet-là.

---

## 2. La structure d'un thème

```
mon-theme/
  style.css, theme.json, functions.php
  templates/, parts/, patterns/
  blocs/
    hero.php          le dessin du bloc mon-theme/hero
    hero.css          sa feuille, chargée seulement là où il est posé
    …
  blocs-creator/
    definitions.json  les champs de tous les blocs du thème
    maintenance.php   (facultatif) la page de maintenance, dessinée par le thème
  inc/
    migrations.php    ce qui doit changer en base quand le thème évolue
  assets/js/, assets/css/   les interactions du thème
```

Dans `functions.php` :

```php
// Les modules dont le site a besoin : ils ne se décochent plus à l'écran.
add_theme_support( 'blocs-creator-modules', array( 'maintenance', 'cookies', 'brouillons', 'sans-auteurs', 'bouton-modifier' ) );

// Prévenir si le plugin manque : sans lui, les sections ne s'affichent plus.
add_action( 'admin_notices', function () {
	if ( ! function_exists( 'blocs_creator' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-warning"><p>Ce thème a besoin de l’extension Blocs Creator.</p></div>';
	}
} );
```

Réglage à faire une fois (Blocs Creator › Réglages › Général) : l'**espace de
noms** des blocs = le slug du thème (`mon-theme/hero`), le **dossier des
gabarits** = `blocs`.

---

## 3. Le déroulé

1. **Déclarer les blocs.** Soit dans le back-office (Blocs Creator › Ajouter
   un bloc), soit directement dans `blocs-creator/definitions.json` (format
   plus bas), puis `wp blocs-creator definitions importer`.
2. **Dessiner** chaque bloc dans `blocs/<id>.php` (le plugin crée un fichier
   de départ à la publication, avec une ligne par champ). Feuille de style dans
   `blocs/<id>.css`.
3. **Ranger les définitions dans le thème** dès qu'un bloc est au point :
   Outils › « Écrire ces définitions dans le thème », ou
   `wp blocs-creator definitions exporter`. Le fichier voyage alors avec le
   thème (Git, FTP).
4. **Sur le site en ligne**, les blocs qui manquent s'installent à
   l'activation du thème. Une nouvelle version d'un bloc existant se pousse par
   une **migration** (section 9).

Vérifier où l'on en est : `wp blocs-creator definitions etat` (ou l'écran
Outils) montre, bloc par bloc, `identique`, `different`, `absent` (dans le
thème seulement) ou `base` (en base seulement).

---

## 4. Le format de `definitions.json`

C'est le format de l'export de l'écran Outils.

```json
{
  "blocs": [
    {
      "titre": "Héros d'accueil",
      "slug": "hero",
      "espace": "mon-theme",
      "description": "Grand titre d'ouverture, image à droite.",
      "icone": "cover-image",
      "categorie": "mon-theme",
      "mots_cles": [ "accueil", "héros" ],
      "supports": { "anchor": true, "align": true, "customClassName": true, "multiple": true, "reusable": true },
      "apercu": "serveur",
      "animation": "composee",
      "animation_duree": 0,
      "champs": [
        { "cle": "surtitre", "libelle": "Surtitre", "type": "texte" },
        { "cle": "titre", "libelle": "Titre", "type": "texte-riche",
          "options": { "defaut": "Bienvenue **chez nous**", "placeholder": "Le grand titre" } },
        { "cle": "niveau", "libelle": "Niveau du titre", "type": "niveau-titre", "emplacement": "panneau",
          "options": { "defaut_niveau": 1, "niveau_min": 1 } },
        { "cle": "image", "libelle": "Image", "type": "image", "emplacement": "panneau", "options": { "taille": "large" } },
        { "cle": "bouton", "libelle": "Bouton", "type": "lien", "options": { "defaut_libelle": "Nous contacter" } },
        { "cle": "cartes", "libelle": "Cartes", "type": "repeteur",
          "options": { "libelle_ligne": "Carte", "min_lignes": 1, "max_lignes": 6, "lignes_depart": 3 },
          "sous_champs": [
            { "cle": "titre", "libelle": "Titre", "type": "texte" },
            { "cle": "texte", "libelle": "Texte", "type": "texte-long" },
            { "cle": "picto", "libelle": "Pictogramme", "type": "liste", "options": { "choix": "coeur : ♥\netoile : ★" } }
          ] }
      ]
    }
  ]
}
```

**Le bloc** : `espace` + `slug` font son nom (`mon-theme/hero`, à ne plus
jamais changer une fois des pages faites) ; `categorie` est la section de
l'inséreur — celle des réglages (`blocs-creator` par défaut, titre réglable),
ou une que le thème déclare avec le filtre `block_categories_all` ; `apercu`
vaut `serveur` (le canevas montre le vrai rendu) ou `formulaire`.

**Un champ** : `cle` (minuscules, soulignés, unique dans le bloc), `libelle`,
`type`, `aide` (texte sous le champ), `emplacement` (`bloc` = dans le canevas,
`panneau` = colonne de droite), `largeur` (33, 50 ou 100), `options` (selon le
type, ci-dessous), `sous_champs` (groupe et répéteur, un seul niveau).

| Type | `options` |
|---|---|
| `texte` | `defaut`, `placeholder`, `maxlength` |
| `texte-long` | `defaut`, `placeholder`, `lignes` |
| `texte-riche` | `defaut` (balisage léger : `**gras**`, `_italique_`), `placeholder`, `balise` |
| `nombre` | `defaut`, `min`, `max`, `pas`, `curseur` |
| `bascule` | `defaut_bascule` |
| `liste`, `boutons` | `choix` (une option par ligne, `valeur : Libellé`), `defaut` |
| `cases` | `choix` |
| `niveau-titre` | `defaut_niveau`, `niveau_min` |
| `couleur` | `defaut`, `palette_seule` |
| `icone` | `defaut` (un nom de Dashicon) |
| `point-focal` | `image` (la clé du champ image) |
| `image` | `taille` |
| `galerie` | `taille`, `max_medias` |
| `fichier` | `types_fichier` |
| `lien` | `defaut_libelle` |
| `contenu`, `contenus` | `types_contenu`, `max_contenus` |
| `type-publication` | `defaut` |
| `taxonomie` | `taxonomie`, `terme_unique` |
| `groupe` | — (`sous_champs`) |
| `repeteur` | `libelle_ligne`, `min_lignes`, `max_lignes`, `lignes_depart` (+ `sous_champs`) |
| `blocs-imbriques` | `blocs_autorises`, `gabarit_interne`, `verrou_gabarit`, `orientation` |
| `message` | `message` (une note pour la rédaction, sans valeur) |

Tout ce qui entre repasse par la normalisation : une option inconnue est
jetée sans bruit. Après un import, relire `wp blocs-creator definitions etat`.

**Conseils de conception** — un bloc lisible pour la rédaction :

- dans le canevas (`bloc`), ce qu'on **écrit** ; dans la colonne de droite
  (`panneau`), ce qu'on **règle** (image, niveau de titre, variantes). C'est
  aussi la règle de l'IA : elle ne remplit que les champs de texte du canevas ;
- un champ `message` en tête de colonne de droite, « Mode d'emploi », pour
  dire comment le bloc se modifie ;
- des valeurs par défaut réalistes : un bloc tout juste posé doit déjà
  ressembler à ce qu'il sera.

---

## 5. Écrire un gabarit

```php
<?php
defined( 'ABSPATH' ) || exit;
?>
<section <?php echo blocs_creator_attributs( 'hero' ); ?>>
	<?php if ( blocs_creator_a_champ( 'surtitre' ) ) : ?>
		<p class="hero__surtitre" data-bc-part="gauche"><?php echo esc_html( blocs_creator_champ( 'surtitre' ) ); ?></p>
	<?php endif; ?>

	<?php printf(
		'<h%1$d class="hero__titre" data-bc-part="mots">%2$s</h%1$d>',
		blocs_creator_niveau( 'niveau', 1 ),
		wp_kses_post( blocs_creator_champ( 'titre' ) )
	); ?>

	<div class="hero__media" data-bc-part="masque">
		<?php echo blocs_creator_image( 'image', array( 'class' => 'hero__image' ), 'large' ); ?>
	</div>

	<ul class="hero__cartes">
		<?php foreach ( blocs_creator_boucle( 'cartes' ) as $carte ) : ?>
			<li class="hero__carte" data-bc-part="carte"><?php echo esc_html( $carte['titre'] ); ?></li>
		<?php endforeach; ?>
	</ul>

	<?php if ( blocs_creator_lien_rempli( 'bouton' ) ) : ?>
		<a class="hero__bouton" data-bc-part="haut" <?php echo blocs_creator_lien_attrs( 'bouton' ); ?>>
			<?php echo esc_html( blocs_creator_lien_titre( 'bouton' ) ); ?>
		</a>
	<?php endif; ?>
</section>
```

Règles :

- `blocs_creator_attributs()` sur la balise racine, **toujours** : c'est elle
  qui porte la classe, l'ancre, l'alignement — et l'apparition.
- Ce qui sort de `blocs_creator_image()`, `blocs_creator_lien_attrs()`,
  `blocs_creator_attributs()` est déjà échappé. Le texte sort brut :
  `esc_html()`, `esc_attr()`, `wp_kses_post()` selon l'endroit.
- `blocs_creator_balisage_leger( $texte )` traduit `**gras**` et `_italique_`
  pour un texte qui vient d'un réglage (les défauts des champs enrichis le sont
  déjà).
- Un seul `<h1>` par page : le niveau du titre d'un héros se règle (champ
  `niveau-titre`), les autres sections commencent en `h2`.

---

## 6. Les apparitions

Une **apparition appartient au bloc** : elle se choisit dans sa définition
(`"animation"`), pas page par page. Pour un bloc dessiné pour ça, prendre
**Composée** et distribuer les rôles dans le gabarit avec `data-bc-part` :

| Variante | Ce qu'elle fait | Pour |
|---|---|---|
| `haut` / `bas` | monte / descend | texte, bouton |
| `gauche` / `droite` | glisse de côté | moitiés qui se croisent, surtitre |
| `zoom` | grandit d'un rien | une image qui s'installe |
| `pastille` | petite, puis posée d'un rebond | cartes en grille |
| `fondu` | l'opacité seule | ce qui ne doit pas attirer l'œil |
| `pinceau` | une découpe inclinée balaie la largeur | un trait peint, un aplat |
| `mots` | **chaque mot monte de sous sa ligne** (vaut dans toutes les scènes) | les titres |
| `masque` | un rideau se lève, l'image dézoome derrière | photos, visuels |
| `flou` | sort de la brume | un chapô, une citation |
| `trait` | se tire de gauche à droite | filets, soulignements |
| `pop` | petit, penché, posé d'un rebond | icône, guillemet, étiquette |
| `carte` | monte de loin, tournée, se redresse | cartes (une sur deux tourne dans l'autre sens) |
| `ligne` | glisse depuis la gauche | rangées de liste |

Le moteur fait le reste : décalage entre parties (le rang repart à chaque
conteneur et à chaque rangée de grille ; un `style="--bc-anim-rang:3"` posé à
la main est respecté), titres découpés en mots (les `<strong>`, `<em>`, `<br>`
restent), parties sous l'écran qui **attendent leur tour** au lieu de jouer
hors de vue sur mobile, filet qui révèle tout ce qui est à l'écran. Rien n'est
caché sans JavaScript ni pour qui a demandé moins d'animations.

Trois portes de plus :

- **Les blocs du contenu** — Réglages › Apparitions › « Faire entrer aussi les
  blocs natifs » : paragraphes, titres, images, listes… entrent chacun avec la
  scène de son type (filtre `blocs_creator_apparitions_contenu`).
- **La classe `bc-apparition-<scène>`** sur n'importe quel bloc (modèle du
  thème, pied de page, « Classes CSS supplémentaires ») : `bc-apparition-cascade`.
- **L'évènement `blocs-creator:vu`**, envoyé par chaque bloc révélé (il
  remonte) : c'est là qu'une interaction du thème démarre (un compteur…).

Le rythme se règle dans le thème, d'un coup, en redéfinissant sur `:root` :
`--bc-anim-duree`, `--bc-anim-courbe`, `--bc-anim-courbe-expo`,
`--bc-anim-courbe-douce`, `--bc-anim-courbe-ressort`, `--bc-anim-cadence-mots`,
`--bc-anim-ecart`. Une variante propre au thème s'écrit en CSS sur
`.bc-anim-prete .bc-anim[data-bc-anim="composee"] [data-bc-part="ma-variante"]`
— avec son état `bc-attend` (voir la fin de `assets/css/animations.css`).

---

## 7. L'IA

Réglages › IA. Le plugin passe par le client d'IA de WordPress (7.0+) : on
branche Google, Anthropic ou OpenAI dans Réglages › Connecteurs, aucune clé
dans le plugin.

- **« Qui écrit, pour qui »** : la première chose à remplir sur un nouveau
  site. Deux ou trois phrases : ce qu'est le site, à qui il parle, le ton,
  tutoiement ou vouvoiement.
- **Rédiger avec l'IA** (articles, ou d'autres types cochés) : notes en vrac →
  titre, chapô, texte en blocs, relus avant insertion.
- **Remplir avec l'IA** (blocs créés ici) : notes → texte des champs du bloc.
  Seuls les champs de texte (`texte`, `texte-long`, `texte-riche`, `nombre`,
  libellé d'un `lien`, et ceux d'un `groupe` ou d'un `repeteur`) **placés dans
  le canevas** sont proposés. Le filtre `blocs_creator_ia_champ_remplissable`
  ajuste.
- Consignes par service : filtres `blocs_creator_ia_consignes`,
  `blocs_creator_ia_consignes_article`, `blocs_creator_ia_consignes_section`.

L'IA n'invente rien qui ne soit dans les notes (consigne écrite) ; tout se
relit avant d'entrer dans la page.

---

## 8. Les modules

Réglages › Modules, ou imposés par le thème (`add_theme_support(
'blocs-creator-modules', … )`). Désactivé, un module ne charge rien.

| Module | Pour le thème |
|---|---|
| `maintenance` | Réglages › Maintenance, interrupteur dans la barre d'outils. Le thème peut dessiner la page : `blocs-creator/maintenance.php` (reçoit `$reglages`, `$retour`, `$apercu`), ou ajouter sa feuille : filtre `blocs_creator_maintenance_feuilles`. |
| `cookies` | Réglages › Cookies. Un lien vers `#gerer-les-cookies` rouvre le bandeau (le mettre sur la page Cookies et en pied de page). `window.blocsCreatorCookies.choix()`, évènement `blocs-creator:cookies`. Couleurs : `--bc-cookies-fond`, `--bc-cookies-texte`, `--bc-cookies-accent` sur `.bc-cookies`. |
| `brouillons` | `<form data-bc-brouillon="contact">` est suivi tout seul ; `blocs_creator_brouillon_messages()` écrit les petits mots en tête du formulaire. Formulaire envoyé en `fetch` : appeler `form.bcBrouillon.effacer()` après succès. |
| `sans-auteurs` | Penser aussi à couper « Archives d'auteur » dans Yoast. |
| `sans-recherche` | Retirer le bloc Rechercher du modèle 404. |
| `bouton-modifier` | Couleurs : `--bc-modifier-fond`, `--bc-modifier-texte`, `--bc-modifier-accent`. |
| `noms-blocs` | Une page « Exemple — tous les blocs », case « Afficher le nom des blocs » dans l'éditeur. |

Les styles des modules prennent les couleurs de `theme.json` quand elles
s'appellent `base`/`contrast` (ou `background`/`foreground`) et
`accent`/`primary`. Sinon, les redéfinir par les variables ci-dessus.

Une fonction d'un module n'existe que si le module est actif : dans un
gabarit, `if ( function_exists( 'blocs_creator_brouillon_messages' ) )`.

---

## 9. Les e-mails

Toujours disponibles (`includes/emails.php`), sans effet tant qu'on ne les
appelle pas. Pour un formulaire du thème :

```php
$html = blocs_creator_email_html( array(
	'titre' => 'Nouveau message',
	'intro' => wp_date( 'j F Y, G \h i' ),
	'corps' => blocs_creator_email_tableau(
		blocs_creator_email_ligne( 'Nom', esc_html( $nom ) )
		. blocs_creator_email_ligne( 'E-mail', blocs_creator_email_lien( 'mailto:' . $email, $email ) )
		. blocs_creator_email_ligne( 'Message', nl2br( esc_html( $message ) ) )
	),
) );
blocs_creator_email_envoyer( $destinataire, 'Nouveau message', $html, array( 'Reply-To: ' . $email ) );

// Et au visiteur — jamais une copie de ce qu'il a écrit (anti-relais de spam) :
blocs_creator_email_confirmation( array(
	'email' => $email, 'nom' => $nom, 'sujet' => 'Message bien reçu', 'titre' => 'Merci !',
	'message' => $message_de_la_redaction, 'reponse' => $destinataire,
) );
```

Couleurs : filtre `blocs_creator_email_couleurs`.

---

## 10. Les migrations

Le site est en ligne, les fichiers y arrivent par FTP : ce qui doit changer
en base passe par une migration numérotée, jouée une fois à la première visite.

```php
add_filter( 'blocs_creator_migrations', function ( $migrations ) {
	$migrations['mon-theme'] = array(
		1 => 'mon_theme_migration_faq',
	);
	return $migrations;
} );

function mon_theme_migration_faq() {
	// Tous les fichiers de cette version doivent être en ligne : sinon, on attend.
	$prets = blocs_creator_migration_fichiers( array(
		'blocs/faq.php'                  => 'data-bc-part="ligne"',
		'blocs-creator/definitions.json' => '"faq"',
	) );

	if ( is_wp_error( $prets ) ) {
		return $prets; // retentée dans un quart d'heure
	}

	$bilan = blocs_creator_importer_definitions( true, array( 'faq' ) );

	if ( $bilan['erreurs'] ) {
		return new WP_Error( 'import', implode( ' ; ', $bilan['erreurs'] ) );
	}

	return array( 'Bloc FAQ : une question ouverte à la fois.' ); // montré une fois aux administrateurs
}
```

Pour réécrire une page : `blocs_creator_migration_page( $page, $contenu )`
(l'état d'avant reste dans les révisions). Ne jamais renuméroter une migration
déjà en ligne ; une migration devenue inutile rend `array()`.

---

## 11. WP-CLI

```
wp blocs-creator definitions etat | importer [--ecraser] [--seulement=a,b] | exporter [--seulement=a,b]
wp blocs-creator migrations etat | lancer
wp blocs-creator modules liste | activer <id>… | desactiver <id>…
wp blocs-creator ia etat
```

---

## 12. Check-list d'un nouveau projet

- [ ] Thème bloc, `theme.json` avec ses couleurs (`base`, `contrast`, `accent`
      au minimum : les modules s'en servent).
- [ ] Réglages › Général : espace de noms = slug du thème, dossier `blocs`.
- [ ] Blocs déclarés, dessinés, rangés dans `blocs-creator/definitions.json`.
- [ ] Apparition de chaque bloc choisie (souvent Composée) et `data-bc-part`
      posés ; « blocs du contenu » coché si le site le veut.
- [ ] Modules imposés dans `functions.php` ; page Cookies avec un lien
      `#gerer-les-cookies` ; textes de maintenance relus.
- [ ] Réglages › IA : « Qui écrit, pour qui » rempli ; une IA branchée dans
      Réglages › Connecteurs (avec le client).
- [ ] Une page « Exemple — tous les blocs » avec le nom des blocs.
- [ ] `inc/migrations.php` prêt avant la première mise en ligne.
- [ ] Les interactions du projet (compteurs, en-tête, FAQ…) dans
      `assets/js/` du thème, branchées sur `blocs-creator:vu` si besoin.
