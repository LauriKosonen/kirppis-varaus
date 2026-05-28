<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Varauksen luonti AJAX:lla

add_action( 'wp_ajax_luo_varaus', 'luo_varaus_ajax' );
add_action( 'wp_ajax_nopriv_luo_varaus', 'luo_varaus_ajax' );

function luo_varaus_ajax() {
    $paikka_id = sanitize_text_field( $_POST['paikka_id'] );
    $etunimi = sanitize_text_field( $_POST['etunimi'] );
    $sukunimi = sanitize_text_field( $_POST['sukunimi'] );
    $email = sanitize_email( $_POST['email'] );

    $result = luo_varaus( $paikka_id, $etunimi, $sukunimi, $email );

    if ( $result['success'] ) {
        wp_send_json_success( $result['message'] );
    } else {
        wp_send_json_error( $result['message'] );
    }
}


// Admin-AJAX: asetukset

add_action( 'wp_ajax_tallenna_page_id', 'tallenna_page_id_ajax' );

function tallenna_page_id_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_page_id_nonce', 'nonce' );

    update_option( 'kirppis_varaus_page_id', intval( $_POST['page_id'] ) );
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}

add_action( 'wp_ajax_tallenna_navi_asetus', 'tallenna_navi_asetus_ajax' );

function tallenna_navi_asetus_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_navi_asetus_nonce', 'nonce' );

    $arvo = sanitize_text_field( $_POST['arvo'] ) === '1' ? '1' : '0';
    update_option( 'kirppis_navi_paalla', $arvo );
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}


//päivämäärän tallennus
add_action( 'wp_ajax_tallenna_tapahtuma_pvm', 'tallenna_tapahtuma_pvm_ajax' );

function tallenna_tapahtuma_pvm_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_tapahtuma_pvm_nonce', 'nonce' );

    $pvm = sanitize_text_field( $_POST['pvm'] );
    if ( $pvm && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $pvm ) ) {
        wp_send_json_error( 'Virheellinen päivämäärä' );
    }
    update_option( 'kirppis_tapahtuma_pvm', $pvm );
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}

//  laskutusasetukset
add_action( 'wp_ajax_tallenna_laskutusasetus', 'tallenna_laskutusasetus_ajax' );

function tallenna_laskutusasetus_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_laskutusasetus_nonce', 'nonce' );

    $arvo = sanitize_text_field( $_POST['arvo'] ) === '1' ? '1' : '0';
    update_option( 'kirppis_laskutus_paalla', $arvo );
    wp_send_json_success();
}

//varausten määrän rajoitus per henkilö
add_action( 'wp_ajax_tallenna_henkilorajoitus', 'tallenna_henkilorajoitus_ajax' );

function tallenna_henkilorajoitus_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_henkilorajoitus_nonce', 'nonce' );

    $arvo = sanitize_text_field( $_POST['arvo'] ) === '1' ? '1' : '0';
    update_option( 'kirppis_henkilorajoitus_paalla', $arvo );
    wp_send_json_success();
}

//hinnan päivitys
add_action( 'wp_ajax_tallenna_hinta', 'tallenna_hinta_ajax' );

function tallenna_hinta_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'tallenna_hinta_nonce', 'nonce' );

    $hinta = floatval( str_replace( ',', '.', sanitize_text_field( $_POST['hinta'] ) ) );
    update_option( 'kirppis_poyta_hinta', $hinta );
    wp_send_json_success();
}

//maksetuksi merkintä
add_action( 'wp_ajax_merkitse_maksetuksi', 'merkitse_maksetuksi_ajax' );

function merkitse_maksetuksi_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'merkitse_maksetuksi_nonce', 'nonce' );

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'varaukset',
        [ 'maksettu' => 1 ],
        [ 'id' => intval( $_POST['varaus_id'] ) ]
    );
    wp_send_json_success();
}

// Kaikkien varausten poisto
add_action( 'wp_ajax_poista_kaikki_varaukset', 'poista_kaikki_varaukset_ajax' );

function poista_kaikki_varaukset_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Ei oikeuksia' );
    check_ajax_referer( 'poista_kaikki_varaukset_nonce', 'nonce' );

    global $wpdb;
    $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}varaukset" );
    wp_send_json_success();
}


// Sivun näkyvyys navigaatiossa. Sivu piilotetaan kun se on kytketty pois päätä hallintapaneelissa
// Tai tapahtumapäivä on mennyt.

function kirppis_paivita_sivun_nakyvyys() {
    $navi_paalla = get_option( 'kirppis_navi_paalla', '0' );
    $tapahtuma_pvm = get_option( 'kirppis_tapahtuma_pvm', '' );

    $pvm_mennyt = false;
    if ( $tapahtuma_pvm ) {
        $nyt = current_time( 'timestamp' );
        $tapahtuma_ts = strtotime( $tapahtuma_pvm . ' 00:00:00' );
        if ( $nyt >= $tapahtuma_ts ) {
            $pvm_mennyt = true;
        }
    }

    $nayta = ( $navi_paalla === '1' ) && ! $pvm_mennyt;
    $varaus_page_id = get_option( 'kirppis_varaus_page_id', 0 );

    if ( ! $varaus_page_id ) {
        return;
    }

    $tila = $nayta ? 'publish' : 'private';
    $sivu = get_post( $varaus_page_id );
    if ( $sivu && $sivu->post_status !== $tila ) {
        wp_update_post( [ 'ID' => $varaus_page_id, 'post_status' => $tila ] );
    }
}

// Ajetaan päivittäin puoliyöllä
add_action( 'kirppis_tarkista_pvm_cron', 'kirppis_paivita_sivun_nakyvyys' );

if ( ! wp_next_scheduled( 'kirppis_tarkista_pvm_cron' ) ) {
    $seuraava_puoliyo = strtotime( 'tomorrow midnight', current_time( 'timestamp' ) );
    $wp_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
    wp_schedule_event( $seuraava_puoliyo - $wp_offset, 'daily', 'kirppis_tarkista_pvm_cron' );
}