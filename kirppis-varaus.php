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
        PRIMARY KEY (id),
        KEY paikka_id (paikka_id)
    ) $charset_collate;";

    dbDelta($sql);
}

// Varauksen luonti.Tarkistetaan onko paikka vapaa ja tallennetaan tiedot tietokantaan
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


//AJAX. mahdollistaa varauksen luonnin ilma sivun uudelleenlatausta
add_action('wp_ajax_luo_varaus', 'luo_varaus_ajax');
add_action('wp_ajax_nopriv_luo_varaus', 'luo_varaus_ajax');

//haetaan lomakkeen tiedot ja luodaan varaus
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


//Tuodaan tyylit ja javascript

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
        <h3 class="keskitetty-teksti">PAIKAN VARAUSLOMAKE<br> 11.11.1111</h3>
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


