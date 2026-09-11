# Mettre Blocs Creator 3.1 en ligne

Dans ce dossier :

| Fichier | Pour quoi |
|---|---|
| `blocs-creator-3.1.0-vierge.zip` | **Tes autres sites.** Le plugin seul, sans rien de Salama. |
| `blocs-salama.json` | **Le site Salama.** Tes treize blocs, à importer une fois. |

Les paquets précédents sont dans `archives/`.

Pour Salama, tu envoies les fichiers en FTP : il n'y a donc pas de zip à
installer. Ce qu'il reste à faire tient en une page.

---

## Ce qui change dans cette version

**Le bloc Tarifs existe pour de bon.** Ce n'est pas un bloc codé : c'est un bloc
créé dans Blocs Creator, `salama/tarif`. Ses champs se règlent dans le
back-office, son dessin est dans `salama-theme/blocs/tarif.php`. Un tableau
prestations / prix / complément, qui tient aussi bien en pleine page qu'à côté
du formulaire de contact. Le prix se tape en chiffres — « 45 » —, l'euro est
ajouté à l'affichage.

**Tes douze blocs codés sont devenus des blocs normaux.** Chacun déclare
maintenant, dans son `block.json`, ce que chaque attribut doit devenir : le
point de cadrage de la bannière reste un point de cadrage, le sens de « Texte et
image » reste deux icônes, la source d'un carrousel reste un menu des types de
publication. **Rien n'est perdu, rien n'est devenu « champ de texte ».** Tu peux
leur ajouter un champ, changer leur icône, leur description, leur catégorie.

**Les apparitions se règlent sur le bloc, plus sur la page.** Tu choisis une
fois, à l'écran du bloc, et toutes ses occurrences entrent de la même façon
partout. Neuf scènes, et une scène anime le bloc **et ses parties** : le titre,
puis le chapô, puis le bouton. Un aperçu la joue pendant que tu choisis.

Chacun de tes blocs arrive déjà avec l'apparition qui correspond à ce que le
thème lui faisait faire — bannière en cascade, « Texte et image » en croisement,
grilles de cartes en pastilles. Tu peux toutes les changer.

---

## Ce qu'il faut envoyer

Deux dossiers, en écrasant :

```
wp-content/plugins/blocs-creator/
wp-content/themes/salama-theme/
```

Dans le thème, trois choses sont nouvelles et indispensables :

- `blocs/tarif.php` — le dessin du bloc Tarifs ;
- `blocs/salama-*.php` — les douze dessins des blocs repris (ils sont la copie
  exacte des `rendu.php` du plugin : c'est pour ça que rien ne change) ;
- `assets/css/composants.css` et `inc/template-tags.php`, modifiés.

---

## Puis, une fois, dans le back-office

### 1. Importer les treize blocs

**Blocs Creator → Outils → Importer** → `blocs-salama.json`.

C'est ce qui fait basculer tes blocs codés en blocs modifiables. Leurs
identifiants ne changent pas (`salama/banniere` reste `salama/banniere`), leur
aspect non plus : **tes pages ne verront pas la différence.**

> Si tu préfères ne rien importer, rien ne casse : les blocs codés continuent de
> servir exactement comme avant. Tu peux aussi faire le basculement depuis le
> back-office de production, sans fichier : **Tous les blocs → Tout reprendre en
> main**. L'import a un avantage — il apporte en plus le bloc Tarifs et les
> apparitions déjà choisies.

### 2. Vider le cache

Si un cache est actif, vide-le : les feuilles de style ont changé.

---

## Vérifier, dans cet ordre

- [ ] **Tous les blocs** liste **13 blocs**, tous marqués « Repris du code » ou
      « Généré ». Plus aucun « Codé ».
- [ ] La page d'accueil s'affiche normalement, et ses sections entrent au
      défilement.
- [ ] Dans l'éditeur, le « + » propose **une seule** catégorie **Salama**.
- [ ] Le bloc **Tarifs** s'insère, ses lignes s'ajoutent, un prix « 45 »
      s'affiche « 45 € ».
- [ ] Ouvre **Blocs Creator → Bannière** : le panneau **Apparition** montre
      « Cascade » et l'aperçu la joue. Change-la, publie, va voir l'accueil.
- [ ] Ouvre une page dans l'éditeur et sélectionne la bannière : ses champs sont
      dans la colonne de droite, **y compris le point de cadrage sur la photo**.

---

## Si quelque chose ne va pas

**Un bloc ne te plaît plus une fois repris.** Dans **Tous les blocs**, ligne du
bloc → **Rendre au code**. La définition est supprimée, le dossier du plugin
reprend la main, et il n'a jamais été touché. Les pages ne bougent pas.

**Tu veux revenir en arrière complètement.** Renvoie l'ancien
`wp-content/plugins/blocs-creator/` par FTP. Les définitions restent en base
sans gêner — un plugin plus ancien ne les lit pas.

---

## Les Réservations

Le type de publication **Réservations** est passé sous ACF en local. En ligne,
il n'y est pas encore, et rien ne presse : le code du pack le déclare toujours
en secours, donc le menu et tes prestations sont là.

Pour passer sous ACF en ligne aussi : en local, **ACF → Types de publication →
Réservations → Exporter** ; en ligne, **ACF → Types de publication →
Importer**. La clé (`salama_resa`) est la même des deux côtés, tes prestations
restent en place.

---

## Sur tes prochains sites

`blocs-creator-3.1.0-vierge.zip` — téléverser, activer, c'est tout.

Pour emporter des blocs d'un site à l'autre : **Outils → Exporter** d'un côté,
**Outils → Importer** de l'autre. L'export ne contient que les définitions ; les
fichiers de rendu se copient avec le thème.

---

## Deux choses à ne pas oublier

**Les identifiants de blocs sont définitifs.** Renommer `salama/carte` après
coup casserait les pages qui s'en servent. L'écran t'avertit du nombre de pages
concernées avant que tu ne changes quoi que ce soit.

**Désinstaller ne supprime rien, par défaut.** Tes définitions restent en base
et tes gabarits dans le thème.
