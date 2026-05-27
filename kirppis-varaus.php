<?php
/**
 * Plugin Name: Kirppis varausjärjestelmä
 * Description: Pöydänvarausjärjestelmä kirpputoreille
 * Version: 1.2
 * Author: Lauri Kosonen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// Vendor ja sähköposti
// ---------------------------------------------------------------------------

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'email.php';

// ---------------------------------------------------------------------------
// Moduulit
// ---------------------------------------------------------------------------

require_once plugin_dir_path( __FILE__ ) . 'includes/tietokanta.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/hallintapaneeli.php';

// ---------------------------------------------------------------------------
// Aktivointi- ja deaktivointikoukut
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, 'varaus_plugin_create_table' );

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'kirppis_tarkista_pvm_cron' );
} );

// ---------------------------------------------------------------------------
// Asetukset
// ---------------------------------------------------------------------------

add_action( 'admin_init', function() {
    register_setting( 'kirppis_asetukset', 'kirppis_laskutus_paalla' );
} );

// ---------------------------------------------------------------------------
// Admin-valikko
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function() {
    add_menu_page(
        'Paikanvarausjärjestelmä',
        'Paikanvarausjärjestelmä',
        'manage_options',
        'kirppis-varaukset',
        'kirppis_varaukset_sivu',
        'dashicons-calendar-alt',
        20
    );
} );

// ---------------------------------------------------------------------------
// Skriptit ja tyylit
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'kirppis-styles',
        plugin_dir_url( __FILE__ ) . 'styles.css'
    );

    wp_enqueue_script(
        'kirppis-js',
        plugin_dir_url( __FILE__ ) . 'kirppis-varaus.js',
        [ 'jquery' ],
        null,
        true
    );

    wp_localize_script( 'kirppis-js', 'ajax_object', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ] );
} );