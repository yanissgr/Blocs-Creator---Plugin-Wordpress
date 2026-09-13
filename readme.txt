=== Blocs Creator ===
Contributors: yanissinger
Tags: blocks, gutenberg, custom blocks, fields, acf
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 4.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build Gutenberg blocks by declaring their fields, then draw them in a plain PHP file of your own theme.

== Description ==

Blocs Creator separates two things most tools mix together.

**What a block contains** is declared in the admin area: a name, some fields, a
type per field. Not a single line of code.

**What a block looks like** is written in a PHP file of your theme. The plugin
calls that file with the submitted values, and then steps aside. There is no
visual builder generating markup you will have to fight later: your HTML stays
yours.

The plugin ships with a French admin interface. Translations are welcome.

= What you get =

* One screen listing **every** block on the site — the ones declared here and
  the ones hand-written in a file. Each with its name, its fields, its template
  and the number of pages using it.
* Twenty-four field types, from plain text to repeaters, including images,
  links, posts, focal point and inner blocks.
* A render file created for you when the block is published, with a starting
  point for every declared field — and never rewritten afterwards.
* A faithful preview in the editor: the block is displayed through its server
  render, exactly the code that will run on the site. A toggle in the toolbar
  switches to the form to fill the fields in.
* JSON import and export, to carry your blocks from one site to another.
* **Take over** a hand-written block: its declaration becomes an editable
  definition, its render file is installed in your theme, and its name does not
  change. One button takes over every coded block at once, and the operation
  can be undone block by block.
* **Inserter housekeeping**: one checkbox per block — those of WordPress, those
  of other plugins — so that the "+" only offers what is actually used.
* **Entrances**: nine scroll-triggered scenes, chosen once for the block, with
  a preview at the moment of choice.

= Field types =

Text, long text, rich text, number, yes/no, select, button group, checkboxes,
heading level, color, icon, focal point, image, gallery, file, link, post,
posts, post type, term, group, repeater, inner blocks, note.

= Writing a template =

One file, about fifteen functions:

`
<section <?php echo blocs_creator_attributs( 'temoignages' ); ?>>

    <?php printf( '<h%1$d>%2$s</h%1$d>', blocs_creator_niveau( 'niveau' ), esc_html( blocs_creator_champ( 'titre' ) ) ); ?>

    <?php foreach ( blocs_creator_boucle( 'lignes' ) as $ligne ) : ?>
        <blockquote><?php echo wp_kses_post( $ligne['citation'] ); ?></blockquote>
        <cite><?php echo esc_html( $ligne['auteur'] ); ?></cite>
    <?php endforeach; ?>

    <?php if ( blocs_creator_lien_rempli( 'cta' ) ) : ?>
        <a <?php echo blocs_creator_lien_attrs( 'cta' ); ?>><?php echo esc_html( blocs_creator_lien_titre( 'cta' ) ); ?></a>
    <?php endif; ?>

</section>
`

The full list of functions lives under **Blocs Creator → Write a template**,
along with what each field type returns.

= Blocks you already coded =

If you already have hand-written blocks, Blocs Creator finds them and registers
them: drop a folder carrying a `block.json` into `blocs/` of your theme, into
`wp-content/blocs-creator/blocs/`, or into a pack of the plugin. They show up in
the same list as the others, marked as coded.

And you can **take them over**, one at a time or all at once. The action
translates the `block.json` into a definition — its attributes become fields —
copies its `rendu.php` into your theme as a template, and keeps its name, its
stylesheets and its variations. The block is then edited as if you had created
it here. "Give back to code" undoes everything: the folder takes over again,
and it was never touched.

For the takeover to be **exact**, a coded block can describe by itself what its
attributes should become, in a `blocsCreator` key of its `block.json`:

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

Without that key, the manifest is read as well as it can be: types are guessed,
and an attribute no field knows how to carry is kept as it is rather than
dropped. The confirmation screen shows the translation before applying it.

= Available blocks =

**Settings → Available blocks** lists every registered block, grouped by origin,
with a checkbox. Unchecking a block removes it from the inserter — and from the
inserter only: pages already carrying it keep displaying and editing fine.

Two families resist: your own blocks, and blocks that only exist inside another
one. What gets stored is the list of what you remove, never the list of what you
keep: a block arriving with a newly installed plugin is therefore available
right away.

= Entrances =

An entrance belongs to the block, not to the page: it is chosen once — on the
block screen for the ones you create, in the settings for those of WordPress and
other plugins — and every occurrence enters the same way, everywhere. A preview
plays it at the moment of choice.

An entrance is a scene, not a gesture: each of the nine says what the block does
AND what its parts do — its heading, its text, its cards — with an offset
between them.

The class is added at render time, not in the stored content: a static block
therefore keeps exactly the markup it had, and removing the plugin leaves
nothing behind. Nothing animates for someone who asked for reduced motion in
their device settings, and nothing is ever hidden if JavaScript fails to load.

== Installation ==

1. Drop the `blocs-creator` folder into `wp-content/plugins/`.
2. Activate the plugin.
3. Open **Blocs Creator** in the menu, then **Add a block**.

A `packs/` folder may hold blocks shipped with the plugin. On a fresh install,
delete that folder: the plugin will not notice.

== Frequently Asked Questions ==

= Where is the render file of my block? =

In `wp-content/themes/<your-theme>/blocs/<name>.php`. The exact path is written
in the right-hand column of the block screen, and in the list. The folder can be
changed in the settings.

= Will the plugin overwrite my file? =

Never. It creates the file if it does not exist, and never touches it again —
even if you add or remove fields.

= Can I load a stylesheet along with a block? =

Yes. Put a `<name>.css` next to the template: it is loaded automatically, and
only on the pages carrying the block.

= What happens if I rename a block already in use? =

Pages using it will no longer recognize it. The screen warns you how many pages
are concerned before you change anything.

= What if I uninstall the plugin? =

By default nothing is deleted: your definitions stay in the database and your
templates stay in the theme. A checkbox in the settings erases everything on
uninstall, if that is what you want.

== Screenshots ==

1. Every block of the site, generated and coded, in a single list.
2. The field builder.
3. A generated block in the editor: its server render, and its fields.
4. The help screen for writing a template.

== Changelog ==

= 4.2.0 =
* **Everything the plugin declares now carries a full prefix.** Classes went
  from `BC_*` to `Blocs_Creator_*`, and the template helpers from `bc_champ()`
  to `blocs_creator_champ()`. A two-letter prefix is a collision waiting to
  happen, and the WordPress guidelines ask for four characters at least.
  Existing templates must be updated — the block screen shows the new lines.
* Option names, post type, hooks, query arguments and CSS classes are
  **unchanged**: nothing stored in the database or in page content moves.
* `is_writable()` gives way to `wp_is_writable()`, the WordPress wrapper that
  also answers correctly on Windows.
* The pack strings now use the plugin text domain, so they are translated along
  with the rest.
* Translation loading is left to WordPress, which has been doing it by itself
  since 4.6.
* This readme is now in English, as the plugin directory requires.

= 4.1.0 =
* **The plugin insists when a write does not go through.** Settings are written,
  read back, compared; if the read-back does not return what was just written,
  the row is written straight to the database. A `pre_update_option` filter set
  by another plugin, an object cache that never clears: things invisible from
  inside the plugin, all giving the same dead button.
* **A diagnostic panel, on the settings screen.** A screen coming back unchanged
  can have four very different causes, and they all look alike from the browser.
  The report tells them apart: version, PHP limits, presence of the row in the
  database, object cache, active plugins, and above all the **log of the last
  eight attempts**. If it stays empty after a click, the request never reaches
  the plugin — and hosting is where to look. The whole thing copies with one
  button.
* The diagnostic is reachable from the save bar, which never leaves the screen,
  and opens by itself right after a failed save. At the bottom of a page listing
  a hundred and thirty blocks, nobody ever found it.

= 4.0.0 =

Four fixes, all with the same root: the plugin could do things it did not say,
and refused things it was asked for.

* **Settings save, and say so.** The form no longer goes through `options.php`:
  it calls its own handler, which writes, READS BACK, compares, and announces
  the result. The open tab comes back with it.
* **A definition always deletes.** The action is called "Delete", and it
  deletes — even when the original coded block has vanished from disk, the very
  case where "Give back to code" rightly refused and left the definition stuck
  in the list. The confirmation says what it costs, and how many posts use it. A
  copy of a block no longer remembers being born from a takeover.
* **A tenth scene: "Composed".** The other nine apply one gesture to every part
  of a block. This one lets the TEMPLATE hand out the roles, part by part:
  `data-bc-part="zoom"` on the image, `"haut"` on the text, `"pinceau"` on a
  rule. Eight variants — up, down, left, right, zoom, badge, fade, brush.
* **A part's rank restarts at each container**, and at each row of a grid. Two
  halves meant to cross therefore start together, and the fourth card of a
  three-column grid no longer waits for a fourth delay. A rank already set by
  the template is respected.
* **The block sheet.** A box on a block screen gathers everything needed to draw
  it — name, file to write, each field with the line that fetches it — and
  copies with one button. It is the bridge between the two halves of the plugin.

= 3.2.0 =
* The settings screen says what it did: a confirmation is displayed after
  saving, and you come back to the tab you were looking at.
* The "Available blocks" tab no longer sends one field per block but a single
  one: on a busy site the form exceeded `max_input_vars` and arrived truncated —
  unchecked blocks came back, and saving seemed to have no effect. A truncated
  submission now touches nothing.
* That same screen finally reads: a summary on top, an All / Available / Removed
  filter, groups that fold, and counters following the clicks.
* A block screen opens on its three-step path — name it, declare the fields,
  draw it — with the real state of the drawing file. Each field recalls the line
  that fetches it in the template.
* A theme folder locked against writing is announced, and the starting code is
  shown to copy rather than a button that fails.
* A taken-over block deletes: its row action is called "Delete", and a "Give
  everything back to code" button undoes a takeover in one go.
* A template can name the parts that enter the scene itself, by putting
  `data-bc-part` on its elements. A scene animating only the parts of a block
  that offers none now brings in the whole block rather than playing nothing.

= 3.1.0 =
* Entrances are set on the block, no longer on the page: a block always enters
  the same way, everywhere. A preview plays them at the moment of choice.
* Nine worked scenes instead of fifteen gestures: each animates the block AND
  its parts, with an offset between them.
* Exact takeover of coded blocks: a `block.json` can declare by itself what its
  attributes become, in a `blocsCreator` key.
* A "Take everything over" button: not a single coded block left, in one click.
* Two more field types: focal point and post type.

= 3.0.0 =
* Takeover of coded blocks: a hand-written block becomes an editable definition,
  without changing its name or its look. Reversible.
* "Available blocks" screen: check or uncheck the blocks of WordPress and other
  plugins to keep in the inserter only what is useful.
* Scroll entrances: fifteen gestures, set block by block, on any block.
* A single category in the inserter: the one you pick among existing sections.
  Two sections with the same name are no longer created, and blocks already
  filed under the old one move over on upgrade.
* Field keys keep their case: `imageId` stays `imageId`.

= 2.0.0 =
* Block creation by declaring fields, without code.
* A single screen listing generated blocks and coded blocks.
* Twenty-two field types, including the repeater and inner blocks.
* Generation of the render file, with one example per field.
* Server-render preview in the editor.
* JSON import and export.
* Discovery of coded blocks in the theme, in wp-content and in packs.
