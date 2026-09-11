# Mettre Blocs Creator 3 en ligne

Deux paquets dans ce dossier :

| Fichier | Pour quoi |
|---|---|
| `blocs-creator-3.0.0.zip` | **Le site Salama.** Le plugin et tes quatorze blocs. |
| `blocs-creator-3.0.0-vierge.zip` | **Tes autres sites.** Le plugin seul, sans rien de Salama. |

Les paquets de la version 2 sont dans `archives/`, au cas où.

---

## Ce que la version 3 apporte

**Tu peux reprendre la main sur tes blocs codés.** Jusqu'ici, les blocs écrits
à la main — bannière, carte, carrousel… — se listaient dans Blocs Creator mais
ne s'y modifiaient pas. Maintenant, chacun porte une action **Reprendre la
main** : ses attributs deviennent des champs modifiables, son fichier de rendu
s'installe dans ton thème, et son identifiant ne change pas. Un écran te montre
exactement ce que ça va donner avant de confirmer, et **Rendre au code** défait
tout.

**Une seule catégorie « Salama » dans l'inséreur.** Le plugin n'ajoute plus sa
section quand une autre porte déjà le même nom, et les blocs qui étaient rangés
dans le doublon déménagent tout seuls à l'activation.

**Tu choisis ce que le « + » propose.** *Réglages → Blocs disponibles* liste
les cent trente blocs enregistrés sur le site — ceux de WordPress, ceux de
Yoast, les tiens — avec une case à cocher chacun. Décocher n'abîme rien : les
pages qui portent déjà le bloc continuent de s'afficher.

**Chaque bloc peut entrer en scène.** Un panneau **Apparition** dans la colonne
de droite, sur n'importe quel bloc : quinze gestes au choix, une durée, un
retard. Ça marche aussi sur les blocs de WordPress et ceux des autres
extensions.

**Un bloc Tarifs.** Un tableau prestations / prix / complément, qui tient aussi
bien en pleine page qu'à côté du formulaire de contact dans une colonne.

**Les Réservations passent sous ACF.** Le type de publication se règle
maintenant dans *ACF → Types de publication*, comme Ateliers et Retraites.

---

## Avant de commencer

### 1. Sauvegarder

Le site est en ligne : prends une sauvegarde complète. All-in-One WP Migration
est déjà installé — **Exporter → Fichier** suffit.

### 2. Installer

**Extensions → Ajouter une extension → Téléverser une extension** →
`blocs-creator-3.0.0.zip` → **Installer maintenant** → **Remplacer par la
version téléversée**.

C'est une mise à jour : tes définitions, tes réglages et tes gabarits restent
en place.

---

## En ligne, l'ordre compte

Le type de publication **Réservations** est passé sous ACF *en local*. En
production, il n'y est pas encore — il faut donc le déclarer là-bas aussi, une
fois le plugin installé.

Deux chemins, au choix :

**Le plus simple** — laisser le code faire. Si tu ne fais rien, le pack
continue de déclarer le type lui-même, comme avant : le menu **Réservations**
reste là, tes trois prestations aussi. Tu perds seulement le réglage par ACF.

**Le complet** — recréer le type dans ACF, en local puis en ligne :

1. En local, **ACF → Types de publication → Réservations → Exporter**, ce qui
   donne un fichier JSON.
2. En ligne, **ACF → Types de publication → Importer**, et déposer ce fichier.

La clé du type (`salama_resa`) est la même des deux côtés : tes prestations
déjà saisies restent en place, ACF reprend simplement la main sur les réglages.

> Si jamais les deux se marchaient dessus, c'est ACF qui gagne : la déclaration
> du pack s'efface dès que le type existe déjà.

---

## Vérifier que tout va bien

Dans cet ordre, ça prend trois minutes :

- [ ] L'écran **Tous les blocs** liste **14 blocs codés**, chacun avec sa coche
      verte dans la colonne Gabarit, et une action **Reprendre la main**.
- [ ] Dans l'éditeur d'une page, le « + » ne propose **qu'une seule** catégorie
      **Salama**.
- [ ] Le bloc **Salama — Tarifs** s'insère, et **Salama — Ligne de tarif**
      s'ajoute à l'intérieur. Un prix tapé « 45 » s'affiche « 45 € ».
- [ ] Sélectionne un paragraphe : la colonne de droite montre un panneau
      **Apparition**. Choisis « Monte », enregistre, va voir la page.
- [ ] **Réglages → Blocs disponibles** affiche la liste par extension. Décoche
      un bloc que tu n'utilises jamais, enregistre, et vérifie qu'il a disparu
      du « + ».
- [ ] Le menu **Réservations** est toujours là avec ses prestations.
- [ ] La page d'accueil et une page à carrousel s'affichent normalement.

---

## Reprendre un bloc en main, concrètement

Prends-en **un seul** pour commencer, et de préférence un bloc simple —
**Salama — Titre avec flag** est un bon premier essai.

1. **Tous les blocs** → sur la ligne du bloc, **Reprendre la main**.
2. L'écran montre ce que ça va donner : les champs, ce qui est conservé tel
   quel, ce qui change. Lis la section « Ce qui change ».
3. **Reprendre la main sur ce bloc.** Tu arrives sur l'écran du bloc, avec ses
   champs, exactement comme un bloc que tu aurais créé.
4. Va voir une page qui porte ce bloc : rien ne doit avoir bougé.

Si le résultat ne te plaît pas : **Rendre au code**, dans la liste. La
définition disparaît, le dossier reprend la main, et il n'a jamais été touché.

> **Ce que tu perds en reprenant la main**, c'est l'éditeur sur mesure du bloc :
> le sélecteur de point focal de la bannière, les deux icônes qui montrent le
> sens de « Texte et image ». Le bloc se règle ensuite par le formulaire commun,
> et son aperçu passe par le rendu serveur — toujours fidèle, mais moins direct.
>
> Ce que tu gagnes : pouvoir lui ajouter un champ, changer son icône, sa
> catégorie ou sa description sans ouvrir un fichier.
>
> Les blocs qui gagnent le plus à être repris sont les plus simples — Titre avec
> flag, Lien avec icône, Contact. Ceux dont l'éditeur fait beaucoup — Bannière,
> Texte et image — sont mieux servis par leur code.

---

## Sur tes prochains sites

`blocs-creator-3.0.0-vierge.zip` — téléverser, activer, c'est tout. Aucune
trace de Salama.

Pour emporter des blocs d'un site à l'autre : **Outils → Exporter** d'un côté,
**Outils → Importer** de l'autre. L'export ne contient que les définitions ;
les fichiers de rendu se copient avec le thème.

---

## Deux choses à ne pas oublier

**Les identifiants de blocs sont définitifs.** Renommer `salama/carte` après
coup casserait les pages qui s'en servent. L'écran t'avertit du nombre de pages
concernées avant que tu ne changes quoi que ce soit.

**Désinstaller ne supprime rien, par défaut.** Tes définitions restent en base
et tes gabarits dans le thème. Une case dans **Réglages** permet de tout
effacer à la désinstallation, si un jour c'est ce que tu veux.
