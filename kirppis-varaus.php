<?php
/**
 * Plugin Name: Kirppis varausjärjestelmä
 * Description: Pöydänvarausjärjestelmä kirpputoreille
 * Version: 1.1
 * Author: Lauri Kosonen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Siivotaan cron pluginin deaktivoinnissa
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('kirppis_tarkista_pvm_cron');
});

require_once plugin_dir_path(__FILE__) . '/email.php';
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Rekisteröidään laskutus-asetus
add_action('admin_init', function() {
    register_setting('kirppis_asetukset', 'kirppis_laskutus_paalla');
});

// tietokanta taulun rekisteröinti ja luonti
register_activation_hook(__FILE__, 'varaus_plugin_create_table');

function varaus_plugin_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'varaukset';
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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

    dbDelta($sql);
}

// Varauksen luonti. Tarkistetaan onko paikka vapaa ja tallennetaan tiedot tietokantaan
function luo_varaus($paikka_id, $etunimi, $sukunimi, $email) {
    global $wpdb;
    $table = $wpdb->prefix . 'varaukset';

    // Tarkistetaan onko paikka varattu
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE paikka_id = %s
    ", $paikka_id));

    if ($existing > 0) {
        return ['success' => false, 'message' => 'Paikka on jo varattu'];
    }

    // Aikaleimat
    $now = current_time('mysql');

    // Tietokantaan tallennus
    $inserted = $wpdb->insert($table, [
        'paikka_id' => $paikka_id,
        'etunimi' => $etunimi,
        'sukunimi' => $sukunimi,
        'email' => $email,
        'luotu' => $now
    ]);
    $varaus_id = $wpdb->insert_id;

    if (!$inserted || $wpdb->last_error) {
        return [
            'success' => false,
            'message' => 'DB error: ' . $wpdb->last_error
        ];
    }

    vahvistus_email(
        $email,
        $etunimi,
        $sukunimi,
        $paikka_id,
        $varaus_id
    );

    return [
        'success' => true,
        'message' => 'Varaus luotu',
    ];
}

// Haetaan varatut pöydät tietokannasta karttaa varten
function hae_varatut_poydat() {

    global $wpdb;

    $taulu = $wpdb->prefix . 'varaukset';

    $varaukset = $wpdb->get_col("
        SELECT paikka_id
        FROM $taulu
    ");

    wp_send_json_success($varaukset);
}

add_action('wp_ajax_hae_varatut_poydat', 'hae_varatut_poydat');
add_action('wp_ajax_nopriv_hae_varatut_poydat', 'hae_varatut_poydat');


// AJAX: mahdollistaa varauksen luonnin ilman sivun uudelleenlatausta
add_action('wp_ajax_luo_varaus', 'luo_varaus_ajax');
add_action('wp_ajax_nopriv_luo_varaus', 'luo_varaus_ajax');

// Haetaan lomakkeen tiedot ja luodaan varaus
function luo_varaus_ajax() {
    $paikka_id = sanitize_text_field($_POST['paikka_id']);
    $etunimi   = sanitize_text_field($_POST['etunimi']);
    $sukunimi  = sanitize_text_field($_POST['sukunimi']);
    $email     = sanitize_email($_POST['email']);

    $result = luo_varaus($paikka_id, $etunimi, $sukunimi, $email);

    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}

// AJAX: Varaussivun ID:n tallennus
add_action('wp_ajax_tallenna_page_id', 'tallenna_page_id_ajax');

function tallenna_page_id_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }
    check_ajax_referer('tallenna_page_id_nonce', 'nonce');

    $page_id = intval($_POST['page_id']);
    update_option('kirppis_varaus_page_id', $page_id);
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}

// AJAX: Navigaatiokytkimen tallennus
add_action('wp_ajax_tallenna_navi_asetus', 'tallenna_navi_asetus_ajax');

function tallenna_navi_asetus_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }
    check_ajax_referer('tallenna_navi_asetus_nonce', 'nonce');

    $arvo = sanitize_text_field($_POST['arvo']) === '1' ? '1' : '0';
    update_option('kirppis_navi_paalla', $arvo);
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}

// AJAX: Tapahtumapäivämäärän tallennus
add_action('wp_ajax_tallenna_tapahtuma_pvm', 'tallenna_tapahtuma_pvm_ajax');

function tallenna_tapahtuma_pvm_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }
    check_ajax_referer('tallenna_tapahtuma_pvm_nonce', 'nonce');

    $pvm = sanitize_text_field($_POST['pvm']);
    // Validoidaan päivämäärä (muoto YYYY-MM-DD)
    if ($pvm && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pvm)) {
        wp_send_json_error('Virheellinen päivämäärä');
    }
    update_option('kirppis_tapahtuma_pvm', $pvm);
    // Tarkistetaan tuleeko sivu piilottaa heti
    kirppis_paivita_sivun_nakyvyys();
    wp_send_json_success();
}

/**
 * Päivittää varaussivun näkyvyyden navigaatiossa.
 * Sivu piilotetaan jos:
 *   – navigaatiokytkin on pois päältä, TAI
 *   – tapahtumapäivä on mennyt (tai tänään eli alkaa keskiyöllä)
 */
function kirppis_paivita_sivun_nakyvyys() {
    $navi_paalla   = get_option('kirppis_navi_paalla', '0');
    $tapahtuma_pvm = get_option('kirppis_tapahtuma_pvm', '');

    // Tarkistetaan onko tapahtumapäivä jo mennyt
    $pvm_mennyt = false;
    if ($tapahtuma_pvm) {
        // Käytetään WordPress-aikavyöhykettä
        $nyt = current_time('timestamp');
        $tapahtuma_ts = strtotime($tapahtuma_pvm . ' 00:00:00');
        if ($nyt >= $tapahtuma_ts) {
            $pvm_mennyt = true;
        }
    }

    $nayta = ($navi_paalla === '1') && !$pvm_mennyt;

    // Haetaan varaussivun slug tai ID option-taulukkosta
    $varaus_page_id = get_option('kirppis_varaus_page_id', 0);
    if (!$varaus_page_id) {
        return;
    }

    // Päivitetään sivun tila: publish = näkyvissä, private = piilotettu
    $tila = $nayta ? 'publish' : 'private';
    $sivu = get_post($varaus_page_id);
    if ($sivu && $sivu->post_status !== $tila) {
        wp_update_post([
            'ID'          => $varaus_page_id,
            'post_status' => $tila,
        ]);
    }
}

// WP Cron: tarkistetaan päivittäin puoliyöllä suljetaanko sivu
add_action('kirppis_tarkista_pvm_cron', 'kirppis_paivita_sivun_nakyvyys');

if (!wp_next_scheduled('kirppis_tarkista_pvm_cron')) {
    // Ajoitetaan seuraavaan puoliyöhön (UTC, WP hoitaa timezone-offsetin sisäisesti)
    $seuraava_puoliyo = strtotime('tomorrow midnight', current_time('timestamp'));
    // Muunnetaan UTC:ksi (wp_schedule_event käyttää UTC:tä)
    $wp_offset   = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $seuraava_utc = $seuraava_puoliyo - $wp_offset;
    wp_schedule_event($seuraava_utc, 'daily', 'kirppis_tarkista_pvm_cron');
}

// Merkitään varaus maksetuksi admin-puolelta
add_action('wp_ajax_merkitse_maksetuksi', 'merkitse_maksetuksi_ajax');

function merkitse_maksetuksi_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }

    check_ajax_referer('merkitse_maksetuksi_nonce', 'nonce');

    $id = intval($_POST['varaus_id']);

    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'varaukset',
        ['maksettu' => 1],
        ['id' => $id]
    );

    wp_send_json_success();
}

// AJAX: Hinnan tallennus
add_action('wp_ajax_tallenna_hinta', 'tallenna_hinta_ajax');

function tallenna_hinta_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }

    check_ajax_referer('tallenna_hinta_nonce', 'nonce');

    $hinta = floatval(str_replace(',', '.', sanitize_text_field($_POST['hinta'])));
    update_option('kirppis_poyta_hinta', $hinta);

    wp_send_json_success();
}

// AJAX: Kaikkien varausten poistaminen
add_action('wp_ajax_poista_kaikki_varaukset', 'poista_kaikki_varaukset_ajax');

function poista_kaikki_varaukset_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }

    check_ajax_referer('poista_kaikki_varaukset_nonce', 'nonce');

    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}varaukset");

    wp_send_json_success();
}

// AJAX: Laskutusasetuksen tallennus checkboxilta ilman nappia
add_action('wp_ajax_tallenna_laskutusasetus', 'tallenna_laskutusasetus_ajax');

function tallenna_laskutusasetus_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Ei oikeuksia');
    }

    check_ajax_referer('tallenna_laskutusasetus_nonce', 'nonce');

    $arvo = sanitize_text_field($_POST['arvo']) === '1' ? '1' : '0';
    update_option('kirppis_laskutus_paalla', $arvo);

    wp_send_json_success();
}


// Tuodaan tyylit ja javascript
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'kirppis-styles',
        plugin_dir_url(__FILE__) . 'styles.css'
    );

    wp_enqueue_script(
        'kirppis-js',
        plugin_dir_url(__FILE__) . 'kirppis-varaus.js',
        ['jquery'],
        null,
        true
    );

    wp_localize_script('kirppis-js', 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});

add_action('admin_menu', function() {
    add_menu_page(
        'Varausjärjestelmä',        // sivun otsikko
        'Varausjärjestelmä',        // valikon nimi
        'manage_options',           // oikeudet
        'kirppis-varaukset',        // slug
        'kirppis_varaukset_sivu',   // funktio joka renderöi sivun
        'dashicons-calendar-alt',   // ikoni
        20                          // sijainti
    );
});

// admin sivu dashboardiin
require_once plugin_dir_path(__FILE__) . '/hallintapaneeli.php';

// varaus sivun pohja
add_shortcode('varaus_pohja', function($atts) {

    $atts = shortcode_atts([
        'kartta' => ''
    ], $atts);

    ob_start();
    ?>

    <div class="varaus-pohja">
        <?php echo do_shortcode('[kirppis_varauslomake]'); ?>
        <?php
        // valitaan kartta parametrin perusteella
        if ($atts['kartta'] === 'kortetalo') {
            echo do_shortcode('[kartta_kortetalo]');
        }
        // tähän mahdollisesti lisää karttoja myöhemmin
        // if ($atts['kartta'] === 'xxxx') {
        //     echo do_shortcode('[kartta_xxxx]');
        // }
        ?>

    </div>

    <?php
    return ob_get_clean();
});


// KARTAT
// Shortcode kortetalon pöytäkartalle
add_shortcode('kartta_kortetalo', function() {
    $svg_path = plugin_dir_path(__FILE__) . 'assets/poytakartta_kortetalo.svg';

    if (file_exists($svg_path)) {
        $svg = file_get_contents($svg_path);
        
        return '
            <div class="kartta-pohja">
                <h3 class="kartta-otsikko">PAIKKAKARTTA</h3>

                <div class="svg-wrapper">
                    ' . $svg . '
                </div>

                <div class="kartta-legend">
                    <div class="legend-item">
                        <span class="color-box green"></span>
                        Vapaa
                    </div>

                    <div class="legend-item">
                        <span class="color-box orange"></span>
                        Varattu
                    </div>
                </div>
            </div>
        ';
    }
    else {
        return '<p>Karttaa ei löytynyt.</p>';
    }

});

// Shortcode jollekin muulle pöytäkartalle
// add_shortcode('kartta_xxxx', function() {
//     $svg_path = plugin_dir_path(__FILE__) . 'assets/poytakartta_xxxx.svg';

//     if (file_exists($svg_path)) {
//         $svg = file_get_contents($svg_path);
//         return '<div class="kartta_xxxx">' . $svg . '</div>';
//     }
//     else {
//         return '<p>Karttaa ei löytynyt.</p>';
//     }

// });


// varauslomake
add_shortcode('kirppis_varauslomake', function() {

    ob_start();
    ?>

    <div class="lomake-pohja">
        <?php
        $tapahtuma_pvm_raw = get_option('kirppis_tapahtuma_pvm', '');
        $tapahtuma_pvm_naytto = $tapahtuma_pvm_raw
            ? date_i18n('j.n.Y', strtotime($tapahtuma_pvm_raw))
            : '';
        ?>
        <h3 class="keskitetty-teksti">PAIKAN VARAUSLOMAKE<?php if ($tapahtuma_pvm_naytto) echo '<br>' . esc_html($tapahtuma_pvm_naytto); ?></h3>
        <p class="keskitetty-teksti">Täytä yhteystietosi ja valitse haluamasi paikkanumero alasvetolaatikosta. Vapaat ja varatut paikat näkyvät pöytäkartassa. Alasvetolaatikko näyttää vain vapaat paikat. Voit maksaa pöytävarauksen joko Mobilepay:lla tai korttimaksulla</p>

        <form id="varaus-form">

            <div class="nimi-rivi">
                <div class="nimi-kentta">
                    <label>Etunimi:</label>
                    <input type="text" id="etunimi" required>
                </div>
                <div class="nimi-kentta">
                    <label>Sukunimi:</label>
                    <input type="text" id="sukunimi" required>
                </div>
            </div>

            <div class="email-rivi">
                <div class="email-kentta">
                    <label>Sähköposti:</label>
                    <input type="email" id="email" required>
                </div>
            </div>

            <div class="alin-rivi">
                <div class="poyta-kentta">
                    <label>Paikkanumero:</label>

                    <!--piilotettu input johon tallennetaan valinta -->
                    <input type="hidden" id="paikka">

                    <div class="dropdown">
                        <div class="select">
                            <span class="selected">Valitse paikkanumero</span>
                            <div class="nuoli"></div>
                        </div>
                        <ul class="menu"></ul>
                    </div>

                </div>

                <div class="maksu-kentta">
                    <label>näkymätön css</label>
                    <button type="submit">Siirry maksamaan</button>
                </div>
            </div>

    </form>
    </div>
    <!--vahvistus modal ikkuna -->
    <div id="ilmoitus-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-ikoni">✓</div>
            <h3>Varaus onnistui!</h3>
            <p>Sähköpostiisi on lähetetty vahvistusviesti varauksen tiedoilla.</p>
            <button id="close-ilmoitus-modal">Sulje</button>
        </div>
    </div>
    

    <?php
    
    return ob_get_clean();

});