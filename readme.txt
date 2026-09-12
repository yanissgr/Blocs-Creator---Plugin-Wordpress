=== Blocs Creator ===
Contributors: yanissinger
Tags: blocks, gutenberg, custom blocks, fields, acf
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 4.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Créez vos blocs Gutenberg en déclarant leurs champs, puis dessinez-les dans un simple fichier PHP de votre thème.

== Description ==

Blocs Creator sépare deux choses que la plupart des outils mélangent.

**Ce que le bloc contient** se déclare dans le back-office : un nom, des champs,
un type par champ. Aucune ligne de code.

**Ce à quoi le bloc ressemble** s'écrit dans un fichier PHP de votre thème. Le
plugin l'appelle avec les valeurs saisies, et se retire. Pas de constructeur
visuel qui génère du balisage que vous devrez déjouer ensuite : votre HTML
reste le vôtre.

= Ce que vous obtenez =

* Un écran qui liste **tous** les blocs du site — ceux déclarés ici comme ceux
  écrits à la main dans un fichier. Avec, pour chacun, son identifiant, ses
  champs, son gabarit et le nombre de pages qui s'en servent.
* Vingt-quatre types de champs, du texte simple au répéteur, en passant par les
  images, les liens, les publications, le point de cadrage et les blocs
  imbriqués.
* Un fichier de rendu créé pour vous à la publication du bloc, avec un point de
  départ pour chaque champ déclaré — et jamais réécrit ensuite.
* Un aperçu fidèle dans l'éditeur : le bloc s'affiche par son rendu serveur,
  exactement le code qui tournera sur le site. Une bascule dans la barre
  d'outils passe au formulaire pour saisir.
* Un import/export JSON, pour emporter vos blocs d'un site à l'autre.
* **Reprendre la main** sur un bloc écrit à la main : sa déclaration devient une
  définition modifiable, son fichier de rendu s'installe dans votre thème, et
  son identifiant ne bouge pas. Un bouton reprend tous les blocs codés d'un
  coup, et l'opération se défait bloc par bloc.
* **Le tri de l'inséreur** : une case à cocher par bloc — ceux de WordPress,
  ceux des autres extensions — pour que le « + » ne propose que ce qui sert.
* **Les apparitions** : neuf scènes d'entrée au défilement, choisies une fois
  pour le bloc, avec aperçu au moment du choix.

= Les types de champs =

Texte, texte long, texte enrichi, nombre, oui/non, liste déroulante, groupe de
boutons, cases à cocher, niveau de titre, couleur, icône, point de cadrage,
image, galerie, fichier, lien, publication, publications, type de publication,
terme, groupe, répéteur, blocs imbriqués, note.

= Écrire un gabarit =

Un fichier, une quinzaine de fonctions :

`
<section <?php echo bc_attributs( 'temoignages' ); ?>>

    <?php printf( '<h%1$d>%2$s</h%1$d>', bc_niveau( 'niveau' ), esc_html( bc_champ( 'titre' ) ) ); ?>

    <?php foreach ( bc_boucle( 'lignes' ) as $ligne ) : ?>
        <blockquote><?php echo wp_kses_post( $ligne['citation'] ); ?></blockquote>
        <cite><?php echo esc_html( $ligne['auteur'] ); ?></cite>
    <?php endforeach; ?>

    <?php if ( bc_lien_rempli( 'cta' ) ) : ?>
        <a <?php echo bc_lien_attrs( 'cta' ); ?>><?php echo esc_html( bc_lien_titre( 'cta' ) ); ?></a>
    <?php endif; ?>

</section>
`

La liste complète des fonctions est dans le menu **Blocs Creator → Écrire un
gabarit**, avec ce que rend chaque type de champ.

= Les blocs déjà codés =

Si vous avez déjà des blocs écrits à la main, Blocs Creator les découvre et les
enregistre : posez un dossier portant un `block.json` dans `blocs/` de votre
thème, dans `wp-content/blocs-creator/blocs/`, ou dans un pack du plugin. Ils
apparaissent dans la même liste que les autres, marqués « Codés ».

Et vous pouvez les **reprendre en main**, un par un ou tous d'un coup. L'action
traduit le `block.json` en définition — ses attributs deviennent des champs —,
recopie son `rendu.php` dans votre thème comme gabarit, et garde son
identifiant, ses feuilles de style et ses variantes. Le bloc se modifie alors
comme si vous l'aviez créé ici. « Rendre au code » défait tout : le dossier
reprend la main, et il n'a jamais été touché.

Pour que la reprise soit **exacte**, un bloc codé peut décrire lui-même ce que
ses attributs doivent devenir, dans une clé `blocsCreator` de son `block.json` :

`
"blocsCreator": {
    "animation": "cascade",
    "champs": [
        { "cle": "titre", "libelle": "Titre", "type": "texte-riche", "emplacement": "bloc" },
        { "cle": "pointFocal", "libelle": "Cadrage", "type": "point-focal",
          "emplacement": "panneau", "options": { "image": "imageId" } }
    ]
}
`

Sans cette clé, le manifeste est lu au mieux : les types sont devinés, et un
attribut qu'aucun champ ne sait porter est conservé tel quel plutôt
qu'abandonné. L'écran de confirmation montre la traduction avant de la faire.

= Les blocs disponibles =

**Réglages → Blocs disponibles** liste tous les blocs enregistrés, groupés par
provenance, avec une case à cocher. Décocher un bloc le retire de l'inséreur —
et de lui seul : les pages qui le portent déjà continuent de s'afficher et de
se modifier.

Deux familles résistent : vos propres blocs, et les blocs qui n'existent qu'à
l'intérieur d'un autre. La liste enregistrée est celle de ce que vous retirez,
jamais de ce que vous gardez : un bloc qui arrive avec une nouvelle extension
est donc disponible d'emblée.

= Les apparitions =

Une apparition appartient au bloc, pas à la page : elle se choisit une fois —
sur l'écran du bloc pour ceux que vous créez, dans les réglages pour ceux de
WordPress et des autres extensions — et toutes ses occurrences entrent de la
même façon, partout. Un aperçu la joue au moment du choix.

Une apparition est une scène, pas un geste : chacune des neuf dit ce que fait
le bloc ET ce que font ses parties — son titre, son texte, ses cartes — avec un
décalage entre elles.

La classe est posée au rendu, pas dans le contenu enregistré : un bloc statique
garde donc exactement le balisage qu'il avait, et retirer le plugin ne laisse
rien derrière. Rien ne s'anime pour qui a demandé moins d'animations dans les
réglages de son appareil, et rien n'est jamais caché si le JavaScript ne se
charge pas.

== Installation ==

1. Déposez le dossier `blocs-creator` dans `wp-content/plugins/`.
2. Activez le plugin.
3. Ouvrez **Blocs Creator** dans le menu, puis **Ajouter un bloc**.

Un dossier `packs/` peut contenir des blocs livrés avec le plugin. Sur une
installation neuve, supprimez ce dossier : le plugin n'en saura rien.

== Frequently Asked Questions ==

= Où est le fichier de rendu de mon bloc ? =

Dans `wp-content/themes/<votre-thème>/blocs/<identifiant>.php`. Le chemin exact
est écrit dans la colonne de droite de l'écran du bloc, et dans la liste. Le
dossier se change dans les réglages.

= Le plugin va-t-il écraser mon fichier ? =

Jamais. Il le crée s'il n'existe pas, et n'y retouche plus — même si vous
ajoutez ou supprimez des champs.

= Puis-je charger une feuille de style avec un bloc ? =

Oui. Posez un `<identifiant>.css` à côté du gabarit : il est chargé
automatiquement, et seulement sur les pages qui portent le bloc.

= Que se passe-t-il si je renomme un bloc déjà utilisé ? =

Les pages qui s'en servent ne le reconnaîtront plus. L'écran vous avertit du
nombre de pages concernées avant que vous ne changiez quoi que ce soit.

= Et si je désinstalle le plugin ? =

Par défaut, rien n'est supprimé : vos définitions restent en base et vos
gabarits dans le thème. Une case dans les réglages permet de tout effacer à la
désinstallation, si c'est ce que vous voulez.

== Screenshots ==

1. Tous les blocs du site, générés et codés, dans une seule liste.
2. Le constructeur de champs.
3. Un bloc généré dans l'éditeur : son rendu serveur, et ses champs.
4. L'aide à l'écriture d'un gabarit.

== Changelog ==

= 4.1.0 =
* **Le plugin insiste quand l'écriture ne prend pas.** Les réglages sont écrits,
  relus, comparés ; si la relecture ne rend pas ce qu'on vient d'écrire, la
  ligne est écrite directement en base. Un filtre `pre_update_option` posé par
  une autre extension, un cache d'objets qui ne se vide pas : autant de choses
  invisibles depuis le plugin, et qui donnent toutes le même bouton mort.
* **Un diagnostic, sur l'écran des réglages.** Un écran qui revient inchangé
  peut avoir quatre causes très différentes, et elles se ressemblent toutes vues
  du navigateur. Le relevé les distingue : version, limites de PHP, présence de
  la ligne en base, cache d'objets, extensions actives, et surtout le **journal
  des huit dernières tentatives**. S'il reste vide après un clic, la requête
  n'arrive pas jusqu'au plugin — et l'on cherche du côté de l'hébergement. Le
  tout se copie d'un bouton.
* Le diagnostic s'atteint depuis la barre d'enregistrement, qui ne quitte jamais
  l'écran, et s'ouvre de lui-même quand un enregistrement vient d'échouer. Au
  bas d'une page de cent trente blocs, personne ne le trouvait.

= 4.0.0 =

Quatre corrections, et elles ont la même racine : le plugin savait faire des
choses qu'il ne disait pas, et refusait des choses qu'on lui demandait.

* **Les réglages s'enregistrent, et le disent.** Le formulaire ne passe plus par
  `options.php` : il appelle son propre gestionnaire, qui écrit, RELIT, compare,
  et annonce le résultat. L'onglet ouvert revient avec.
* **Une définition se supprime, toujours.** L'action s'appelle « Supprimer », et
  elle supprime — même quand le bloc codé d'origine a disparu du disque, cas où
  « Rendre au code » refusait à juste titre et laissait la définition coincée
  dans la liste. La confirmation dit ce que ça coûte, et combien de publications
  s'en servent. Une copie de bloc ne se souvient plus d'être née d'une reprise.
* **Une dixième scène : « Composée ».** Les neuf autres appliquent un même geste
  à toutes les parties d'un bloc. Celle-ci laisse le DESSIN distribuer les
  rôles, partie par partie : `data-bc-part="zoom"` sur l'image, `"haut"` sur le
  texte, `"pinceau"` sur un trait. Huit variantes — haut, bas, gauche, droite,
  zoom, pastille, fondu, pinceau. C'est ce qu'il fallait pour qu'un bloc dont
  les moitiés n'entrent pas de la même façon garde son entrée, tout en restant
  réglable depuis le back-office.
* **Le rang d'une partie repart à chaque conteneur**, et à chaque rangée d'une
  grille. Deux moitiés qui doivent se croiser partent donc ensemble, et la
  quatrième carte d'une grille à trois colonnes n'attend plus le retard d'une
  quatrième. Un rang déjà posé par le dessin est respecté.
* **La fiche du bloc.** Une boîte sur l'écran d'un bloc rassemble tout ce qu'il
  faut pour le dessiner — identifiant, fichier à écrire, chaque champ avec la
  ligne qui va le chercher — et la copie d'un bouton. C'est le pont entre les
  deux moitiés du plugin : on déclare ici, on dessine là-bas, et il fallait
  pouvoir passer de l'une à l'autre sans ouvrir la base.

= 3.2.0 =
* L'écran des réglages dit ce qu'il a fait : une confirmation s'affiche après
  l'enregistrement, et l'on revient sur l'onglet qu'on avait sous les yeux.
* L'onglet « Blocs disponibles » n'envoie plus un champ par bloc mais un seul :
  sur un site fourni, le formulaire dépassait `max_input_vars` et arrivait
  tronqué — des blocs décochés revenaient, et l'enregistrement semblait sans
  effet. Un envoi tronqué ne touche désormais plus à rien.
* Le même écran se lit enfin : un bilan en tête, un filtre Tous / Disponibles /
  Écartés, des groupes qui se plient, et des compteurs qui suivent les clics.
* L'écran d'un bloc s'ouvre sur son parcours en trois étapes — nommer, déclarer
  les champs, dessiner — avec l'état réel du fichier de dessin. Chaque champ
  rappelle la ligne qui va le chercher dans le gabarit.
* Un dossier de thème verrouillé en écriture est annoncé, et le code de départ
  est montré à copier plutôt qu'un bouton qui échoue.
* Un bloc repris se supprime : l'action de sa ligne s'appelle « Supprimer », et
  un bouton « Tout rendre au code » défait une reprise en un geste.
* Un gabarit peut désigner lui-même les parties qui entrent en scène, en posant
  `data-bc-part` sur ses éléments. Une scène qui n'anime que les parties d'un
  bloc qui n'en offre aucune fait désormais entrer le bloc entier plutôt que
  de ne rien jouer.

= 3.1.0 =
* Les apparitions se règlent sur le bloc, plus sur la page : un bloc entre
  toujours de la même façon, partout. Un aperçu les joue au moment du choix.
* Neuf scènes travaillées à la place des quinze gestes : chacune anime le bloc
  ET ses parties, avec un décalage entre elles.
* Reprise exacte des blocs codés : un `block.json` peut déclarer lui-même ce
  que ses attributs deviennent, dans une clé `blocsCreator`.
* Un bouton « Tout reprendre en main » : plus un seul bloc codé en un clic.
* Deux types de champs de plus : point de cadrage et type de publication.

= 3.0.0 =
* Reprise en main des blocs codés : un bloc écrit à la main devient une
  définition modifiable, sans changer d'identifiant ni d'aspect. Réversible.
* Écran « Blocs disponibles » : cocher ou décocher les blocs de WordPress et
  des autres extensions pour ne garder dans l'inséreur que ce qui sert.
* Apparitions au défilement : quinze gestes d'entrée, réglables bloc par bloc,
  sur n'importe quel bloc.
* Une seule catégorie dans l'inséreur : celle que vous choisissez parmi les
  sections existantes. Deux sections de même nom ne sont plus créées, et les
  blocs déjà rangés dans l'ancienne déménagent à la montée de version.
* Les clés de champs gardent leur casse : `imageId` reste `imageId`.

= 2.0.0 =
* Création de blocs par déclaration de champs, sans code.
* Écran unique listant les blocs générés et les blocs codés.
* Vingt-deux types de champs, dont le répéteur et les blocs imbriqués.
* Génération du fichier de rendu, avec un exemple par champ.
* Aperçu par rendu serveur dans l'éditeur.
* Import et export JSON.
* Découverte des blocs codés dans le thème, dans wp-content et dans les packs.
