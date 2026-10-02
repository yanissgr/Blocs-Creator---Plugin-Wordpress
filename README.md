# Blocs Creator

Créez vos blocs Gutenberg en déclarant leurs champs, puis dessinez-les dans un
fichier PHP de votre thème.

Auteur : **Yanis Singer** — Licence GPL-2.0-or-later — WordPress 6.5+, PHP 8.0+
(l'IA demande WordPress 7.0+)

> **Deux lectures avant de commencer.** `docs/guide-theme.md` pour construire
> un site avec le plugin (le thème, le format de `definitions.json`, les
> apparitions, les modules, les migrations). `CLAUDE.md` pour modifier le
> plugin lui-même (conventions, pièges connus, comment tester).

---

## Nouveautés de la 5.0

Ce qu'on réécrivait à chaque site rejoint le plugin. Rien ne change sur un
site existant tant qu'on ne coche rien : les nouveautés sont des réglages, des
modules désactivés, ou des fonctions qu'on appelle.

- **Des apparitions plus travaillées.** Sept variantes de parties de plus
  (`mots`, `masque`, `flou`, `trait`, `pop`, `carte`, `ligne`) ; les titres
  `data-bc-part="mots"` montent mot à mot ; les parties encore sous l'écran
  **attendent leur tour** au lieu de jouer hors de vue sur un téléphone ; le
  bas de page entre toujours.
- **Les blocs du contenu.** Une case fait entrer chaque paragraphe, titre,
  image, liste… posé dans une page, avec la scène de son type. La classe
  `bc-apparition-<scène>` en donne une à un bloc seul (modèle, pied de page).
- **L'IA dans l'éditeur** (WordPress 7.0+, Réglages › Connecteurs) :
  « Rédiger avec l'IA » sur les articles, « Remplir avec l'IA » sur les blocs
  créés ici, et l'extension « AI » de WordPress qui lit enfin le texte des
  sections.
- **Des modules** à cocher : maintenance, bandeau cookies (Google Analytics
  attend l'accord), brouillons des formulaires, pas de page d'auteur, pas de
  recherche, bouton « Modifier la page », nom des blocs sur une page
  d'exemple. Un thème peut les imposer.
- **Les définitions voyagent avec le thème** : `blocs-creator/definitions.json`
  s'installe à l'activation du thème, se compare et se recopie depuis l'écran
  Outils ; des **migrations** poussent les changements sur un site en ligne ;
  des commandes **WP-CLI** font tout cela en ligne de commande.
- **Les e-mails** des formulaires du thème, mis en page, avec leur version
  texte : `blocs_creator_email_*()`.
- **Dans l'éditeur**, les aperçus des blocs ne réagissent plus au clic (un lien
  ne fait plus quitter l'éditeur), et les valeurs par défaut des champs
  enrichis comprennent `**gras**` et `_italique_`.

Ce qui reste au thème, délibérément : les micro-interactions (compteurs,
cartes inclinables, boutons aimantés, parallaxe, accordéons, en-tête qui se
cache…). Elles se dessinent pour chaque projet.

---

## Passer en 4.2 : les noms s'allongent

Tout ce que le plugin déclare porte désormais le préfixe entier. Deux lettres,
c'est une collision qui attend son heure, et les règles du dépôt WordPress
demandent quatre caractères au minimum.

| Avant | Après |
|---|---|
| `bc_champ()`, `bc_attributs()`, `bc_boucle()`… | `blocs_creator_champ()`, `blocs_creator_attributs()`, `blocs_creator_boucle()`… |
| `BC_Definition`, `BC_Gabarits`… | `Blocs_Creator_Definition`, `Blocs_Creator_Gabarits`… |
| `includes/class-bc-*.php` | `includes/class-blocs-creator-*.php` |

**Les gabarits déjà écrits sont à reprendre** : remplacez `bc_` par
`blocs_creator_` dans `wp-content/themes/<thème>/blocs/`. La boîte « Fiche du
bloc » de l'écran d'un bloc donne les nouvelles lignes, prêtes à copier.

Ce qui **ne bouge pas** — parce que c'est écrit en base ou dans le contenu des
pages : les noms de blocs, le type de publication `bc_bloc`, les options, les
crochets (déjà en `blocs_creator_*`), les arguments d'URL et les classes CSS
`bc-*`.

---

## Le principe

Le plugin tient une frontière, et rien d'autre :

| Ce que le bloc **contient** | Ce à quoi il **ressemble** |
|---|---|
| Se déclare dans le back-office : un nom, des champs, un type par champ. | S'écrit dans un fichier PHP du thème. |
| Devient les attributs Gutenberg du bloc et son formulaire dans l'éditeur. | Reçoit les valeurs saisies, et produit le balisage que vous voulez. |
| Vit en base, s'exporte en JSON. | Vit dans le thème, se versionne avec lui. |

Aucun constructeur visuel ne génère de HTML à votre place. C'est délibéré : le
balisage d'un site est la partie qu'on retouche le plus, et la moins
automatisable.

---

## Démarrer

1. **Blocs Creator → Ajouter un bloc.** Donnez-lui un nom.
2. **Ajoutez des champs.** Un libellé, un type. La clé se déduit du libellé.
3. **Publiez.** Le fichier de rendu est créé dans
   `wp-content/themes/<thème>/blocs/<identifiant>.php`, avec un point de départ
   pour chaque champ.
4. **Ouvrez ce fichier** et écrivez votre balisage. Le plugin n'y retouchera
   jamais.

> **Si ce n'est pas vous qui dessinez.** L'écran du bloc porte une boîte
> « Fiche du bloc » : identifiant, fichier à écrire, et chaque champ avec la
> ligne qui va le chercher. Elle se copie d'un bouton et se lit telle quelle —
> c'est le pont entre les deux moitiés du plugin quand elles ne sont pas faites
> par la même personne.

---

## Écrire un gabarit

Le gabarit reçoit quatre variables :

```php
/**
 * @var array    $attributes Les valeurs brutes, telles qu'enregistrées.
 * @var string   $content    Les blocs imbriqués, déjà rendus.
 * @var WP_Block $block      L'instance du bloc.
 * @var array    $champs     Les valeurs prêtes à l'emploi, par clé.
 */
```

Et une quinzaine de fonctions :

| Fonction | Ce qu'elle rend |
|---|---|
| `blocs_creator_champ( $cle, $defaut = null )` | La valeur du champ, prête à l'emploi. |
| `blocs_creator_brut( $cle, $defaut = null )` | La valeur telle qu'enregistrée (pour une image, son identifiant). |
| `blocs_creator_a_champ( $cle )` | Le champ est-il rempli ? |
| `blocs_creator_attributs( $classes, $extra )` | Les attributs de la balise racine. **Indispensable.** |
| `blocs_creator_contenu()` | Les blocs imbriqués, rendus. |
| `blocs_creator_image( $cle, $attrs, $taille )` | La balise `<img>`, ou une surface d'attente. |
| `blocs_creator_url( $cle, $taille )` | L'URL d'une image ou d'un fichier. |
| `blocs_creator_lien_rempli( $cle )` | Le lien a-t-il une destination ? |
| `blocs_creator_lien_attrs( $cle )` | `href`, `target` et `rel`, déjà échappés. |
| `blocs_creator_lien_titre( $cle, $defaut )` | Le libellé du lien. |
| `blocs_creator_lien_url( $cle )` | L'URL seule. |
| `blocs_creator_boucle( $cle )` | Les lignes d'un répéteur — toujours un tableau. |
| `blocs_creator_compte( $cle )` | Le nombre de lignes d'un répéteur. |
| `blocs_creator_couleur( $cle, $defaut )` | Une couleur utilisable en CSS. |
| `blocs_creator_niveau( $cle, $minimum = 2 )` | Un niveau de titre borné. |
| `blocs_creator_rappel( $message )` | Un rappel visible des seuls rédacteurs. |
| `blocs_creator_bloc()` | La définition du bloc en cours. |

Ce qui sort échappé sort échappé — `blocs_creator_image()`, `blocs_creator_lien_attrs()` et
`blocs_creator_attributs()` rendent du HTML prêt à poser. Les valeurs de texte sortent
brutes : le gabarit choisit son échappement, parce que lui seul sait s'il écrit
dans un attribut, dans une balise ou dans une URL.

### Exemple

```php
<?php
defined( 'ABSPATH' ) || exit;
?>
<section <?php echo blocs_creator_attributs( 'temoignages' ); ?>>

    <?php if ( blocs_creator_a_champ( 'titre' ) ) : ?>
        <?php printf(
            '<h%1$d class="temoignages__titre">%2$s</h%1$d>',
            blocs_creator_niveau( 'niveau' ),
            esc_html( blocs_creator_champ( 'titre' ) )
        ); ?>
    <?php endif; ?>

    <ul class="temoignages__liste">
        <?php foreach ( blocs_creator_boucle( 'lignes' ) as $ligne ) : ?>
            <li>
                <blockquote><?php echo wp_kses_post( $ligne['citation'] ); ?></blockquote>
                <cite><?php echo esc_html( $ligne['auteur'] ); ?></cite>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ( blocs_creator_lien_rempli( 'cta' ) ) : ?>
        <a class="temoignages__lien" <?php echo blocs_creator_lien_attrs( 'cta' ); ?>>
            <?php echo esc_html( blocs_creator_lien_titre( 'cta' ) ); ?>
        </a>
    <?php endif; ?>

</section>
```

### Une feuille de style par bloc

Posez un `<identifiant>.css` à côté du gabarit : il est chargé automatiquement,
et seulement sur les pages qui portent le bloc.

---

## Les types de champs

| Famille | Types |
|---|---|
| **Texte** | texte, texte long, texte enrichi, nombre |
| **Choix** | oui/non, liste déroulante, groupe de boutons, cases à cocher, niveau de titre, couleur, icône |
| **Médias** | image, galerie, fichier |
| **Liens** | lien, publication, publications, terme |
| **Structure** | groupe, répéteur, blocs imbriqués, note |

Ce que `blocs_creator_champ()` rend, par type :

| Type | Rend |
|---|---|
| texte, texte long, texte enrichi | `string` |
| nombre | `float` |
| oui/non | `bool` |
| liste, boutons, couleur, icône | `string` |
| cases à cocher | `string[]` |
| niveau de titre | `int` (1-6) |
| image, fichier | `array` (`id`, `url`, `alt`, `largeur`, `hauteur`, `legende`…) ou `null` |
| galerie | `array[]` |
| lien | `array` (`url`, `titre`, `nouvelOnglet`, `attrs`, `rempli`) |
| publication | `WP_Post` ou `null` |
| publications | `WP_Post[]` |
| terme | `WP_Term` ou `WP_Term[]` |
| groupe | `array` |
| répéteur | `array[]` — une entrée par ligne |
| blocs imbriqués | rien : passe par `$content` / `blocs_creator_contenu()` |

**La structure ne s'imbrique qu'un cran.** Un répéteur contient des champs
simples, jamais un autre répéteur. C'est arbitraire, et c'est ce qui garde
l'interface lisible et les gabarits écrivables.

---

## Les blocs codés

Blocs Creator découvre et enregistre tout dossier portant un `block.json`,
dans l'ordre :

1. `blocs-creator/packs/*/blocs/*/` — les packs livrés avec le plugin ;
2. `<thème enfant>/blocs/*/` ;
3. `<thème parent>/blocs/*/` ;
4. `wp-content/blocs-creator/blocs/*/`.

Ces blocs apparaissent dans la même liste que les blocs générés, marqués
« Codés ». Le plugin ne fait que les enregistrer : leur rendu et leur éditeur
restent leur affaire.

### Les packs

Un pack est un dossier de `packs/` portant un `pack.php`. Ce fichier est inclus
avant `init`, peut charger ce qu'il veut, et retourne ses métadonnées :

```php
return array(
    'nom'         => 'Mon pack',
    'description' => "Ce qu'il apporte.",
    'auteur'      => 'Vous',
    'version'     => '1.0.0',
);
```

Supprimer le dossier suffit à retirer le pack. Aucune trace en base.

---

## Où le gabarit est cherché

Du plus spécifique au plus général :

1. `<thème enfant>/blocs/<espace>-<identifiant>.php`
2. `<thème enfant>/blocs/<identifiant>.php`
3. `<thème parent>/blocs/…`
4. `wp-content/blocs-creator/gabarits/<identifiant>.php`
5. le rendu de secours du plugin, qui affiche les champs bruts.

Le point 4 survit au changement de thème. Le point 5 fait qu'un bloc tout juste
créé montre quelque chose plutôt qu'un vide.

---

## Reprendre la main sur un bloc codé

Un bloc écrit à la main est un dossier : un `block.json`, un `rendu.php`, du
JavaScript. On ne peut ni lui ajouter un champ ni lui changer son icône sans
ouvrir un éditeur de code.

**Tous les blocs → Reprendre la main** traduit ce dossier en définition et
recopie son `rendu.php` dans le thème comme gabarit. Trois promesses tiennent
l'opération :

1. **Le nom ne bouge pas.** `mon-pack/banniere` reste `mon-pack/banniere` : les
   pages qui le portent ne voient pas la différence.
2. **Le dessin ne bouge pas.** Le `rendu.php` est recopié tel quel — il lit
   `$attributes`, que le plugin lui passe sous le même nom — et les feuilles de
   style que le `block.json` déclarait restent attachées.
3. **Rien ne se perd.** Un attribut qu'aucun type de champ ne sait porter sans
   en changer la forme — un point focal, une structure à soi — est conservé tel
   quel. Les variantes, les styles de bloc et l'exemple d'inséreur aussi.

L'écran de confirmation montre la traduction avant de la faire : quel attribut
devient quel type de champ, ce qui est conservé, ce qui change.

**Rendre au code** défait tout : la définition est supprimée, le dossier reprend
la main. Le dossier n'a jamais été touché ; le gabarit recopié reste dans le
thème, où il ne gêne pas.

Le bandeau de l'écran « Tous les blocs » propose de **tout reprendre d'un
coup** : c'est le geste qu'on fait une fois, au début, après quoi il n'y a plus
de blocs codés sur le site — seulement des blocs qui se modifient.

> Ce que la reprise remplace, c'est l'éditeur sur mesure du bloc : un sélecteur
> maison, une barre d'outils dessinée pour lui. Le bloc se règle ensuite par le
> formulaire commun, et son aperçu passe par le rendu serveur.

### Une reprise exacte : la clé `blocsCreator`

Deviner le type d'un attribut à son nom marche souvent, et pas toujours : un
`pointFocal` finirait en champ de texte, un `sens` aussi. Un bloc codé peut donc
dire lui-même ce que ses attributs doivent devenir, dans son `block.json` :

```json
"blocsCreator": {
    "animation": "cascade",
    "champs": [
        { "cle": "titre", "libelle": "Titre", "type": "texte-riche",
          "emplacement": "bloc", "options": { "placeholder": "Titre de la page" } },
        { "cle": "imageId", "libelle": "Image", "type": "image",
          "emplacement": "panneau", "options": { "taille": "full" } },
        { "cle": "pointFocal", "libelle": "Point de cadrage", "type": "point-focal",
          "emplacement": "panneau", "options": { "image": "imageId" } }
    ]
}
```

Les clés sont celles d'un champ de définition : `cle` (le nom de l'attribut,
casse comprise), `libelle`, `type`, `emplacement`, `largeur`, `aide`, `options`.
`animation` donne au bloc son apparition de départ.

Avec cette clé, la reprise est **totale** : chaque attribut devient un champ
qu'on peut modifier, et il ne reste rien de « conservé tel quel ». Les douze
blocs d'un pack devraient la porter : c'est ce qui rend la reprise exacte.

---

## Les blocs disponibles

**Réglages → Blocs disponibles** liste tous les blocs enregistrés, groupés par
provenance — WordPress, vos blocs, chaque extension — avec une case à cocher.

Deux règles tiennent l'écran :

- **On range en négatif.** C'est la liste de ce qu'on retire qui est
  enregistrée. Un bloc qui arrive demain avec une nouvelle extension est donc
  disponible d'emblée.
- **On ne se coupe pas un bras.** Vos propres blocs et les blocs qui n'existent
  qu'à l'intérieur d'un autre ne se décochent pas.

Décocher un bloc ne touche à aucune page : les blocs déjà posés continuent de
s'afficher et de se modifier. C'est l'inséreur qui ne les propose plus.

---

## Les apparitions

Deux décisions tiennent cette partie, et elles se voient à l'usage.

**L'apparition appartient au bloc, pas à la page.** Elle se choisit une fois :
sur l'écran du bloc pour ceux que vous créez, dans *Réglages → Apparitions* pour
ceux de WordPress et des autres extensions. Toutes ses occurrences entrent donc
de la même façon, sur toutes les pages. Un réglage posé page par page finit
toujours par diverger — trois bannières, trois entrées différentes, et un site
qui a l'air improvisé. Un aperçu joue la scène au moment du choix.

**Une apparition est une scène, pas un geste.** Un bloc n'est pas une boîte :
c'est un titre, un chapô, une image, des cartes. Chacune des dix scènes dit ce
que fait le bloc ET ce que font ses parties, avec un décalage entre elles.

| Scène | Ce qui se passe |
|---|---|
| Montée | Le bloc entier monte et se fond. Le plus sobre. |
| Cascade | Le bloc reste en place ; son contenu monte l'un après l'autre. |
| Croisement | Les parties arrivent alternativement de la gauche et de la droite. |
| Déploiement | Le bloc se dévoile du bas, son contenu monte derrière. |
| Signature | Un balayage découvre le bloc, comme un coup de pinceau. |
| Pastilles | Chaque partie apparaît petite et se pose, en cascade rapide. |
| Souffle | Le bloc arrive flou et trop grand, puis se pose net. |
| Bascule | Le bloc pivote vers vous depuis sa base, en perspective. |
| Ressort | Le bloc dépasse sa place et y revient. |
| **Composée** | Chaque partie entre à sa façon, celle que le dessin lui donne. |

Les **parties** sont désignées par le script : les enfants directs du bloc, et
si l'un n'est qu'un conteneur — un `div`, une `ul` — ses propres enfants à sa
place. Une grille de cartes donne donc ses cartes, et une colonne de texte donne
son titre, son paragraphe et son bouton. Un élément qui porte déjà une
transformation ou un filtre est laissé tranquille : les lui reprendre le ferait
sauter de sa place.

Leur **rang** — le décalage entre elles — repart à chaque conteneur, et à chaque
rangée d'une grille. Deux moitiés qui doivent se croiser partent donc ensemble,
et la quatrième carte d'une grille à trois colonnes n'hérite pas du retard d'une
quatrième.

### Composer une scène depuis le gabarit

Les neuf premières scènes appliquent **un même geste à toutes les parties**.
C'est ce qu'il faut la plupart du temps, et c'est insuffisant dès qu'un bloc est
fait de moitiés qui n'entrent pas de la même façon — un texte qui monte pendant
qu'une image s'installe, un trait qui se peint.

La scène **Composée** rend la main au gabarit : chaque partie porte sa variante
dans la valeur de `data-bc-part`, et le script s'en tient à cette liste au lieu
de deviner.

```php
<section <?php echo blocs_creator_attributs( 'ma-banniere' ); ?>>
	<div class="ma-banniere__texte">
		<h2 data-bc-part="haut"><?php echo esc_html( blocs_creator_champ( 'titre' ) ); ?></h2>
		<p data-bc-part="haut"><?php echo esc_html( blocs_creator_champ( 'chapo' ) ); ?></p>
	</div>
	<div class="ma-banniere__media">
		<?php echo blocs_creator_image( 'image', array( 'data-bc-part' => 'zoom' ) ); ?>
	</div>
</section>
```

| Variante | L'état de départ |
|---|---|
| `haut` / `bas` | décalé verticalement |
| `gauche` / `droite` | décalé horizontalement |
| `zoom` | légèrement réduit — pour une image qui s'installe |
| `pastille` | réduit et décalé d'un rien — pour une grille de cartes |
| `fondu` | l'opacité seule, sans déplacement |
| `pinceau` | une découpe inclinée qui balaie la largeur |
| `mots` | le titre reste en place, chaque mot monte de sous sa ligne (vaut dans **toutes** les scènes) |
| `masque` | un rideau se lève, l'image derrière se pose en dézoomant |
| `flou` | sort de la brume en montant d'un rien |
| `trait` | une ligne qui se tire de gauche à droite |
| `pop` | petit, penché, puis posé d'un rebond — une icône, un guillemet |
| `carte` | monte de loin, légèrement tournée, et se redresse (une sur deux dans l'autre sens) |
| `ligne` | une rangée de liste qui glisse depuis la gauche |

Un `data-bc-part` posé par le gabarit est toujours respecté, quelle que soit la
scène ; un `--bc-anim-rang` posé en style en ligne l'est aussi. Une scène qui ne
déplace pas le bloc et ne trouve aucune partie animable bascule sur « Montée »
plutôt que de ne rien jouer.

### Ce que le moteur fait tout seul (5.0)

- **Les titres mot à mot.** Un élément `data-bc-part="mots"` est découpé en
  mots (`bc-mot` / `bc-mot__in`) ; les balises intérieures restent. Une fois
  posés, les mots rendent leurs débords (`bc-mots-poses`) : un italique n'est
  pas rogné.
- **Les parties qui attendent leur tour.** Un bloc est révélé dès que son haut
  entre à l'écran ; sur un téléphone, ses cartes du bas joueraient leur entrée
  deux écrans plus bas. Chaque partie encore sous l'écran au chargement
  attend (`bc-attend`) d'arriver elle-même à la ligne d'entrée.
- **Le filet.** Toutes les deux secondes de page regardée, ce qui est à
  l'écran et attend encore entre : le dernier bloc d'une page n'atteint jamais
  la ligne d'entrée sur un grand écran.
- **L'évènement `blocs-creator:vu`** part de chaque bloc révélé (il remonte) :
  un script du thème qui doit démarrer à l'entrée d'un bloc l'écoute.

### Les blocs du contenu, et la classe `bc-apparition-*`

*Réglages → Apparitions → Les blocs du contenu* : une case fait entrer chaque
bloc natif posé dans le contenu d'une page, avec la scène de son type —
titre et paragraphe montent, une liste en cascade, une image se déploie, une
citation signe… (filtre `blocs_creator_apparitions_contenu`). Un groupe ou des
colonnes n'entrent pas eux-mêmes : leurs enfants entrent, l'un après l'autre.
Un bloc qui entre emmène ses enfants. L'en-tête et le pied de page ne sont pas
concernés.

Pour un bloc seul — dans un modèle du thème, un pied de page, ou par
« Classes CSS supplémentaires » — la classe `bc-apparition-cascade` (ou
`-montee`, `-souffle`…) lui donne cette scène.

Le rythme de tout le site se règle sur `:root` : `--bc-anim-duree`,
`--bc-anim-courbe`, `--bc-anim-courbe-expo`, `--bc-anim-courbe-douce`,
`--bc-anim-courbe-ressort`, `--bc-anim-cadence-mots`, `--bc-anim-ecart`.

Trois garde-fous :

1. **La classe est posée au rendu**, pas dans le balisage enregistré. Un bloc
   statique garde donc exactement le HTML qu'il avait : changer une apparition
   ne rend jamais un contenu « inattendu », et retirer le plugin ne laisse
   aucune classe orpheline.
2. **Rien ne se cache sans JavaScript.** Le CSS ne masque que sous une classe
   posée par un script d'en-tête, muet quand l'appareil demande moins
   d'animations. Un filet démasque la page si le script principal ne se charge
   pas.
3. **L'éditeur ne joue rien.** On règle l'apparition à la création du bloc, on
   ne la subit pas à chaque frappe.

Un thème peut ajouter une scène : le filtre `blocs_creator_scenarios_animation`
la déclare, des règles CSS sur `[data-bc-anim="…"]` la dessinent.

---

## Réglages

| Réglage | Par défaut |
|---|---|
| Espace de noms des nouveaux blocs | déduit du nom du site |
| Catégorie dans l'inséreur | « Mes blocs », choisie parmi les sections existantes |
| Dossier des gabarits | `blocs`, relatif au thème actif |
| Créer le gabarit à la publication | oui |
| Supprimer les données à la désinstallation | non |
| Blocs retirés de l'inséreur | aucun |
| Apparition d'un bloc | aucune, jusqu'à ce que vous en choisissiez une |
| Blocs natifs du contenu animés | non |
| IA : rédiger un article, remplir une section, extension « AI » | oui, dès qu'une IA est branchée |
| IA : délai de réponse | 90 secondes |
| Modules | aucun, sauf ceux que le thème impose |

> **Une seule catégorie.** Le plugin n'ajoute sa section à l'inséreur que si
> personne ne l'a déjà déclarée — ni sous ce slug, ni sous ce titre. Deux
> sections du même nom ne sont pas deux rangements : c'est le même, coupé en
> deux.

### L'enregistrement, et son diagnostic

Le formulaire ne passe pas par `options.php`. L'API des réglages de WordPress
fait dépendre une écriture de quatre choses qu'on ne voit pas — groupe autorisé,
capacité filtrée, jeton, transitoire de trente secondes pour le message — et
quand l'une lâche, elle renvoie l'écran à l'identique, sans un mot. Le bouton
passe alors pour mort.

Le plugin écrit donc lui-même, **relit, compare**, et annonce le résultat. Si la
relecture ne rend pas ce qu'on vient d'écrire, la ligne est écrite directement
en base : un filtre `pre_update_option` posé par une autre extension ou un cache
d'objets qui ne se vide pas ne sont pas des raisons d'abandonner en silence.

L'onglet **Blocs disponibles** n'envoie pas un champ par bloc — cent trente
dépasseraient `max_input_vars` sur bien des hébergements, et le formulaire
arriverait tronqué, c'est-à-dire avec des blocs qu'on croirait cochés. Il envoie
la liste des blocs écartés en un seul champ, composé à l'envoi, et un envoi
tronqué ne touche à rien.

Enfin, un **diagnostic** repliable, atteignable depuis la barre
d'enregistrement : limites de PHP, présence de la ligne en base, cache d'objets,
extensions actives, et le journal des huit dernières tentatives. S'il reste vide
après un clic, la requête n'est jamais arrivée jusqu'au plugin. Première cause à
écarter : un champ que le navigateur refuse, qui bloque l'envoi avant qu'il ne
parte — l'écran ouvre alors l'onglet du champ, et le navigateur dit pourquoi.
Ensuite seulement, on cherche du côté de l'hébergement.

> **Une valeur pré-remplie doit passer les contraintes de son champ.** En 4.1 et 4.2.0,
> la durée d'apparition avait `min="200" step="50"` et valait 720 par défaut : le
> navigateur la tenait pour invalide et bloquait tout le formulaire, depuis un
> onglet masqué, sans un mot. Corrigé en 4.2.1.

---

## L'IA

*Réglages → IA.* Le plugin ne parle à aucune IA lui-même et ne garde aucune
clé : il passe par le client d'IA de WordPress (7.0 et plus), branché dans
*Réglages → Connecteurs* — Google, Anthropic, OpenAI. Sans connecteur, rien
n'apparaît.

| Service | Où | Ce qu'il fait |
|---|---|---|
| Rédiger avec l'IA | Colonne de droite des articles (et des types cochés) | Des notes en vrac et une longueur → titre, chapô, texte. Relu dans une fenêtre, puis inséré. |
| Remplir avec l'IA | Colonne de droite d'un bloc créé ici | Des notes → le texte des champs du bloc, d'après sa définition. Relu champ par champ, puis appliqué (Ctrl/Cmd + Z annule). |
| Extension « AI » | Méta descriptions, extraits, résumés de l'extension de WordPress | Elle lit la page affichée, et non le contenu enregistré où une section n'est qu'un commentaire. |

Deux règles tiennent les consignes : l'IA **n'invente rien** qui ne soit dans
les notes (ni nom, ni date, ni chiffre), et **tout se relit** avant d'entrer
dans la page. « Remplir » ne touche qu'aux champs de texte placés dans le
canevas : ni images, ni adresses de liens, ni réglages de la colonne de droite.

Quand l'IA est surchargée (503) ou le quota atteint (429), on réessaie avec le
modèle suivant de la liste. Les demandes ont 90 secondes (réglable) : Gemini
ne renvoie rien tant qu'il réfléchit. Les erreurs reviennent en français, avec
quoi faire.

---

## Les modules

*Réglages → Modules.* Ce qu'un site demande presque toujours, prêt à servir ;
un module décoché ne charge pas une ligne.

| Module | Ce qu'il fait |
|---|---|
| `maintenance` | Page d'attente en 503 + `Retry-After` pour les visiteurs, site normal pour qui le modifie, interrupteur dans la barre d'outils, *Réglages → Maintenance*. |
| `cookies` | Google Analytics (Site Kit…) attend l'accord : balises en `text/plain` jusqu'à « Accepter ». Bandeau discret, « Refuser » aussi simple, choix gardé six mois, `#gerer-les-cookies` le rouvre. *Réglages → Cookies*. |
| `brouillons` | Les réponses d'un `<form data-bc-brouillon>` gardées sur l'appareil (après accord, avec le bandeau), retrouvées au retour. |
| `sans-auteurs` | Pages d'auteur redirigées, retirées du plan du site, comptes masqués à l'API pour les visiteurs. |
| `sans-recherche` | `?s=` → 404 sans requête, bloc Rechercher retiré. |
| `bouton-modifier` | Bouton flottant « Modifier la page » pour qui peut modifier ce qu'il regarde. |
| `noms-blocs` | Sur une page d'exemple, le nom de chaque bloc au-dessus de lui. |

Un thème impose ceux dont il a besoin :
`add_theme_support( 'blocs-creator-modules', array( 'maintenance', 'cookies' ) );`.
Leurs styles prennent les couleurs de `theme.json` (`base`, `contrast`,
`accent`) et se redéfinissent par des variables CSS. Le détail est dans
`docs/guide-theme.md`.

---

## Les définitions dans le thème, les migrations, WP-CLI

**`blocs-creator/definitions.json`**, dans le thème, au format de l'export :
les blocs qui manquent s'installent à l'activation du thème ; *Outils → Le
fichier du thème* compare le fichier et la base bloc par bloc et recopie dans
un sens ou dans l'autre.

**Les migrations** : ce qui doit changer en base quand le thème évolue (une
nouvelle version d'un bloc, une page à refaire), joué une fois à la première
visite qui suit la mise en ligne. Le thème les déclare par le filtre
`blocs_creator_migrations` ; une migration qui échoue (un fichier pas encore
arrivé par FTP) est retentée un quart d'heure plus tard ; ce qui a été fait
s'affiche une fois aux administrateurs. Aides :
`blocs_creator_migration_fichiers()`, `blocs_creator_importer_definitions()`,
`blocs_creator_migration_page()`.

**WP-CLI** :

```
wp blocs-creator definitions etat | importer [--ecraser] [--seulement=a,b] | exporter
wp blocs-creator migrations etat | lancer
wp blocs-creator modules liste | activer <id>… | desactiver <id>…
wp blocs-creator ia etat
```

---

## Import et export

**Blocs Creator → Outils.** L'export produit un JSON qui ne contient que les
définitions — les gabarits sont des fichiers de thème, qu'on copie comme le
reste du thème. Le JSON importé repasse entièrement par la normalisation : un
fichier trafiqué ne peut déclarer que des champs du catalogue.

---

## Crochets

| Crochet | Ce qu'il permet |
|---|---|
| `blocs_creator_champs` | Les valeurs, juste avant qu'elles n'arrivent au gabarit. |
| `blocs_creator_args_bloc` | Les arguments passés à `register_block_type()`. |
| `blocs_creator_candidats_gabarit` | Les chemins où le gabarit est cherché. |
| `blocs_creator_code_depart` | Le code du fichier de rendu généré. |
| `blocs_creator_catalogue_champs` | Ajouter un type de champ. |
| `blocs_creator_emplacements_blocs` | Les dossiers où les blocs codés sont découverts. |
| `blocs_creator_donnees_editeur` | Les données passées à l'éditeur. |
| `blocs_creator_charger_pack` | Désactiver un pack sans supprimer son dossier. |
| `blocs_creator_definition_enregistree` | Après l'enregistrement d'une définition. |
| `blocs_creator_blocs_proteges` | Les blocs qu'on refuse de retirer de l'inséreur. |
| `blocs_creator_scenarios_animation` | Ajouter une scène d'apparition. |
| `blocs_creator_animation_concerne` | L'accès d'un bloc au réglage d'apparition. |
| `blocs_creator_apparitions_contenu` | La scène de chaque bloc natif du contenu. |
| `blocs_creator_animations_actives` | Forcer le chargement des apparitions (thème classique). |
| `blocs_creator_ia_consignes` | Les consignes communes données à l'IA. |
| `blocs_creator_ia_consignes_article` | Les consignes de « Rédiger avec l'IA ». |
| `blocs_creator_ia_consignes_section` | Les consignes de « Remplir avec l'IA ». |
| `blocs_creator_ia_modeles` | Les modèles essayés, dans l'ordre. |
| `blocs_creator_ia_champ_remplissable` | Ce que l'IA peut remplir dans un bloc. |
| `blocs_creator_catalogue_modules` | Ajouter un module. |
| `blocs_creator_module_actif` | Forcer un module, ou l'éteindre. |
| `blocs_creator_fichier_definitions` | Le chemin du `definitions.json` du thème. |
| `blocs_creator_migrations` | Les migrations du thème, par source puis par numéro. |
| `blocs_creator_maintenance_peut_passer` | Qui voit le site pendant la maintenance. |
| `blocs_creator_maintenance_feuilles` | Les feuilles de la page de maintenance. |
| `blocs_creator_cookies_balise_retenue` | Les balises qui attendent l'accord du visiteur. |
| `blocs_creator_email_couleurs` | Les couleurs des e-mails. |

---

## Organisation du code

```
blocs-creator.php          En-tête, constantes, point d'entrée
includes/
  class-blocs-creator-plugin.php      Bootstrap : chargement, packs, activation
  class-blocs-creator-champs.php      Le catalogue des types de champs
  class-blocs-creator-definition.php  Une définition de bloc : lecture, nettoyage, écriture
  class-blocs-creator-registre.php    Découverte et enregistrement de tous les blocs
  class-blocs-creator-rendu.php       Appel du gabarit, pile de contexte
  class-blocs-creator-gabarits.php    Résolution des chemins, génération du fichier
  class-blocs-creator-usage.php       Où un bloc est-il utilisé
  class-blocs-creator-adoption.php    Reprendre en main un bloc codé, et le rendre
  class-blocs-creator-disponibilite.php  Ce que l'inséreur a le droit de proposer
  class-blocs-creator-animations.php  Les apparitions : réglage, rendu, assets
  class-blocs-creator-reglages.php    Les réglages
  class-blocs-creator-diagnostic.php  Journal des enregistrements, relevé de la machine
  class-blocs-creator-rest.php        Deux routes pour l'éditeur
  class-blocs-creator-ia.php          L'IA : réglages, appels, routes, panneaux
  class-blocs-creator-modules.php     Le registre des modules
  class-blocs-creator-theme.php       Le definitions.json du thème
  class-blocs-creator-migrations.php  Les migrations déclarées par le thème
  class-blocs-creator-cli.php         Les commandes WP-CLI
  fonctions.php            L'API des gabarits (blocs_creator_*)
  emails.php               La boîte à outils des e-mails
admin/
  class-blocs-creator-admin.php             Menu, écrans, actions
  class-blocs-creator-liste-table.php       L'écran « Tous les blocs »
  class-blocs-creator-ecran-definition.php  L'écran d'édition d'un bloc
  class-blocs-creator-outils.php            Import et export
  js/constructeur.js             Le constructeur de champs
  js/reglages.js                 Onglets et tri de l'écran des réglages
  js/apercu-animation.js         L'aperçu d'une apparition, au moment du choix
  js/copier.js                   Les boutons « Copier » (fiche, diagnostic)
  vues/                          Les gabarits des écrans
assets/
  js/editeur.js            L'éditeur générique des blocs générés
  js/animations.js         Révéler un bloc et ses parties quand il entre à l'écran
  js/ia-article.js         « Rédiger avec l'IA »
  js/ia-section.js         « Remplir avec l'IA »
  js/apercus-inertes.js    Les aperçus de l'éditeur ne réagissent pas au clic
  css/editeur.css          Ce que l'éditeur ajoute autour d'un bloc
  css/blocs.css            Le strict minimum côté site
  css/animations.css       Les dix scènes, les variantes de parties, l'aperçu
  css/ia.css               Les panneaux d'IA
modules/<id>/              Un module : module.php, et ses fichiers
docs/guide-theme.md        Construire un site avec le plugin
gabarits/secours.php       Le rendu d'un bloc sans gabarit
packs/                     Les blocs codés livrés avec le plugin
CLAUDE.md                  Les consignes pour modifier le plugin
```

Aucun outil de build : le JavaScript est écrit en natif
(`wp.element.createElement` plutôt que du JSX), pour que le plugin s'installe
partout sans `npm install`.

---

## Ajouter un type de champ

Trois endroits, et pas un de plus :

1. `Blocs_Creator_Champs::catalogue()` — ou le filtre `blocs_creator_catalogue_champs` —
   déclare le type : son attribut Gutenberg, sa valeur par défaut, ses
   réglages.
2. `Blocs_Creator_Champs::assainir_valeur()` et `Blocs_Creator_Champs::preparer()` disent comment il
   se nettoie et ce qu'il rend au gabarit.
3. `assets/js/editeur.js` (fonction `controle`) et `admin/js/constructeur.js`
   (fonction `reglagesDuType`) fournissent ses contrôles.

Un quatrième, facultatif : si le type est du texte que l'IA doit savoir
remplir, `Blocs_Creator_Ia::REMPLISSABLES`, avec son schéma dans
`schema_champ()` et sa fusion dans `fondre()`.
