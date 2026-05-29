<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Tietokanta taulun luonti pluginin aktivoinnissa
function varaus_plugin_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'varaukset';
    $charset_collate = $wpdb->get_charset_collate();
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        paikka_id VARCHAR(50) NOT NULL,
        etunimi VARCHAR(100) NOT NULL,
        sukunimi VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        luotu DATETIME NOT NULL,
        lasku_lahetetty TINYINT(1) NOT NULL DEFAULT 0,
        laskunumero VARCHAR(30) DEFAULT NULL,
        maksettu TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY paikka_id (paikka_id)
    ) $charset_collate;";

    dbDelta( $sql );
}

// Varauksen luonti: tarkistukset + tallennus + sähköposti
function luo_varaus( $paikka_id, $etunimi, $sukunimi, $email ) {
    global $wpdb;
    $table = $wpdb->prefix . 'varaukset';

    // Onko paikka jo varattu?
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE paikka_id = %s",
        $paikka_id
    ) );

    if ( $existing > 0 ) {
        return [ 'success' => false, 'message' => 'Paikka on jo varattu' ];
    }

    // Henkilörajoitus: yksi varaus per henkilö
    $rajoitus_paalla = get_option( 'kirppis_henkilorajoitus_paalla', '0' );
    if ( $rajoitus_paalla === '1' ) {
        $nimi_varaus = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE etunimi = %s AND sukunimi = %s",
            $etunimi,
            $sukunimi
        ) );

        if ( $nimi_varaus > 0 ) {
            return [ 'success' => false, 'message' => 'Olet jo tehnyt varauksen. Kukin henkilö voi tehdä vain yhden varauksen.' ];
        }
    }

    // Tallennetaan tietokantaan
    $inserted = $wpdb->insert( $table, [
        'paikka_id' => $paikka_id,
        'etunimi' => $etunimi,
        'sukunimi' => $sukunimi,
        'email' => $email,
        'luotu' => current_time( 'mysql' ),
    ] );
    $varaus_id = $wpdb->insert_id;

    if ( ! $inserted || $wpdb->last_error ) {
        return [ 'success' => false, 'message' => 'DB error: ' . $wpdb->last_error ];
    }

    // Lähetetään sähköposti WP Cronin kautta taustalla
    wp_schedule_single_event( time(), 'kirppis_laheta_vahvistus', [
        $email, $etunimi, $sukunimi, $paikka_id, $varaus_id
    ]);

    return [ 'success' => true, 'message' => 'Varaus luotu' ];
}

// Haetaan varattujen paikkojen ID:t karttaa varten
function hae_varatut_poydat() {
    global $wpdb;
    $taulu = $wpdb->prefix . 'varaukset';
    $varaukset = $wpdb->get_col( "SELECT paikka_id FROM $taulu" );
    wp_send_json_success( $varaukset );
}

add_action( 'wp_ajax_hae_varatut_poydat', 'hae_varatut_poydat' );
add_action( 'wp_ajax_nopriv_hae_varatut_poydat', 'hae_varatut_poydat' );