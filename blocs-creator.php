<?php
/**
 * Plugin Name:       Blocs Creator
 * Plugin URI:        https://github.com/yanissgr/Blocs-Creator---Plugin-Wordpress
 * Description:       Créez vos propres blocs Gutenberg en déclarant leurs champs, comme avec ACF, puis dessinez-les dans un simple fichier PHP. Chaque bloc entre en scène à sa façon, l'IA aide à rédiger articles et sections, et des modules prêts à servir (maintenance, bandeau cookies, brouillons de formulaires…) s'activent d'une case.
 * Version:           5.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Yanis Singer
 * Author URI:        https://yanis-singer.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blocs-creator
 * Domain Path:       /languages
 *
 * Blocs Creator sépare deux choses que la plupart des outils mélangent :
 *
 *   - CE QUE LE BLOC CONTIENT se déclare dans le back-office. Un nom, des
 *     champs, un type par champ. Aucune ligne de code.
 *   - CE À QUOI LE BLOC RESSEMBLE s'écrit dans un fichier PHP du thème. Le
 *     plugin l'appelle avec les valeurs saisies, et se retire.
 *
 * Cette frontière est le plugin. Tout le reste — l'écran de liste, le
 * constructeur de champs, l'aperçu dans l'éditeur, l'import/export — n'est là
 * que pour la tenir.
 *
 * Autour, depuis la 5.0, ce qu'on réécrivait à chaque site : le moteur
 * d'apparitions, l'IA de l'éditeur, les outils qui font voyager les blocs
 * avec le thème (definitions.json, migrations, WP-CLI) et des modules
 * optionnels. Ce qui est du dessin propre à un projet — les micro-
 * interactions, l'allure d'un en-tête — reste dans le thème.
 *
 * Avant de modifier ce plugin, lire CLAUDE.md ; avant de construire un thème
 * avec lui, lire docs/guide-theme.md.
 *
 * @package BlocsCreator
 * @author  Yanis Singer
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'BLOCS_CREATOR_VERSION', '5.0.0' );
define( 'BLOCS_CREATOR_FICHIER', __FILE__ );
define( 'BLOCS_CREATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOCS_CREATOR_URL', plugin_dir_url( __FILE__ ) );

require_once BLOCS_CREATOR_DIR . 'includes/class-blocs-creator-plugin.php';

/**
 * Retourne l'instance unique du plugin.
 *
 * Point d'entrée public : un thème ou un autre plugin passe par là plutôt que
 * par les classes, qui peuvent bouger.
 *
 * @return Blocs_Creator_Plugin
 */
function blocs_creator() {
	return Blocs_Creator_Plugin::instance();
}

blocs_creator()->demarrer();

register_activation_hook( __FILE__, array( 'Blocs_Creator_Plugin', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'Blocs_Creator_Plugin', 'desactivation' ) );
