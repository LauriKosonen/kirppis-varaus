<?php
/**
 * Plugin Name: Kirppis varausjärjestelmä
 * Description: Pöydänvarausjärjestelmä kirpputoreille
 * Version: 1.0
 * Author: Lauri Kosonen
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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

        status VARCHAR(20) NOT NULL DEFAULT 'pending_payment',
        payment_reference VARCHAR(255) NOT NULL,

        reserved_until DATETIME,
        created_at DATETIME NOT NULL,

        PRIMARY KEY (id),
        KEY paikka_id (paikka_id),
        KEY payment_reference (payment_reference)
    ) $charset_collate;";

    dbDelta($sql);
}

// Varauksen luonti.Tarkistetaan onko paikka vapaa ja tallennetaan tiedot tietokantaan
function luo_varaus($paikka_id, $etunimi, $sukunimi, $email) {
    global $wpdb;
    $table = $wpdb->prefix . 'varaukset';

    $current_time = current_time('mysql');

    // Tarkistetaan onko paikka varattu
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE paikka_id = %s
        AND (
            status = 'paid'
            OR (status = 'pending_payment' AND reserved_until > %s)
        )
    ", $paikka_id, $current_time));

    if ($existing > 0) {
        return ['success' => false, 'message' => 'Paikka on jo varattu'];
    }

    // Aikaleimat
    $now = current_time('mysql');
    $reserved_until = date('Y-m-d H:i:s', strtotime($now . ' +10 minutes'));

    //Payment repherence
    $payment_reference = 'VARAUS-' . wp_generate_uuid4();

    // Tietokantaan tallennus
    $inserted = $wpdb->insert($table, [
        'paikka_id' => $paikka_id,
        'etunimi' => $etunimi,
        'sukunimi' => $sukunimi,
        'email' => $email,

        'status' => 'pending_payment',
        'payment_reference' => $payment_reference,

        'reserved_until' => $reserved_until,
        'created_at' => $now
    ]);

    if (!$inserted || $wpdb->last_error) {
        return [
            'success' => false,
            'message' => 'DB error: ' . $wpdb->last_error
        ];
    }

    return [
        'success' => true,
        'message' => 'Varaus luotu',
        'payment_reference' => $payment_reference
    ];
}

//toimii vain julkisessa ympäristössä??? permalinks pitää olla päällä???
add_action('rest_api_init', function () {
    register_rest_route('varaus/v1', '/mobilepay-webhook', [
        'methods' => 'POST',
        'callback' => 'mobilepay_webhook_handler',
        'permission_callback' => '__return_true'
    ]);
});

//Webhookin käsittely
function mobilepay_webhook_handler($request) {
    global $wpdb;
    $table = $wpdb->prefix . 'varaukset';

    // haetaan JSON-data
    $data = $request->get_json_params();

    if (!$data) {
        return new WP_REST_Response(['error' => 'No data'], 400);
    }

    // debug
    error_log('MobilePay webhook: ' . print_r($data, true));

    // haetaan maksun tiedot mobilepayn payloadista
    $payment_reference = $data['merchantReference'] ?? $data['orderId'] ?? null;
    $status = $data['status'] ?? null;

    if (!$payment_reference) {
        return new WP_REST_Response(['error' => 'Missing reference'], 400);
    }

    //tarkistetaan että varaus löytyy
    $reservation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE payment_reference = %s",
        $payment_reference
    ));

    if (!$reservation) {
        return new WP_REST_Response(['error' => 'Reservation not found'], 404);
    }

    // ONNISTUNUT MAKSU
    //Mahdollinen duplicate esto
    if ($reservation->status === 'paid') {
        return new WP_REST_Response(['already_processed' => true], 200);
    }
    if ($status === 'PAID' || $status === 'CAPTURED') {

        $wpdb->update(
            $table,
            [
                'status' => 'paid'
            ],
            [
                'payment_reference' => $payment_reference
            ]
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    // epäonnistunut maksu
    if ($status === 'CANCELLED' || $status === 'EXPIRED') {

        $wpdb->update(
            $table,
            [
                'status' => 'expired'
            ],
            [
                'payment_reference' => $payment_reference
            ]
        );

        return new WP_REST_Response(['cancelled' => true], 200);
    }

    return new WP_REST_Response(['ignored' => true], 200);
}

//AJAX. mahdollistaa varauksen luonnin ilma sivun uudelleenlatausta
//toinen näistä turhaa?????????????????????????????????????????????????????????????????????????
add_action('wp_ajax_luo_varaus', 'luo_varaus_ajax');
add_action('wp_ajax_nopriv_luo_varaus', 'luo_varaus_ajax');

//haetaan lomakkeen tiedot ja luodaan varaus
function luo_varaus_ajax() {
    $paikka_id = $_POST['paikka_id'];
    $etunimi = $_POST['etunimi'];
    $sukunimi = $_POST['sukunimi'];
    $email = $_POST['email'];

    $result = luo_varaus($paikka_id, $etunimi, $sukunimi, $email);

    wp_send_json($result);
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

// sivu dashboardiin
function kirppis_varaukset_sivu() {
    echo '<h1>Pöytävarausjärjestelmä</h1>';
    echo '<p>kartta väliaikaisesti tässä</p>';
    $svg_path = plugin_dir_path(__FILE__) . 'assets/poytakartta_kortetalo.svg';

    if (file_exists($svg_path)) {
        $svg = file_get_contents($svg_path);
        echo '<div style="max-width:1000px;">';
        echo $svg;
        echo '</div>';
    } else {
        echo '<p>SVG-tiedostoa ei löytynyt.</p>';
    }

}

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
    <!--maksu modal ikkuna -->
    <div id="maksu-modal" class="modal hidden">
        <div class="modal-content">

            <form id="maksu-form">

                <h3>Vahvista varaus</h3>
                <p>Tarkista että antamasi tiedot ovat oikein. Painamalla "Maksa varaus" sinut ohjataan MobilePay:n maksupalveluun suorittamaan varausmaksu.</p>

                <div class="vahvistus-tiedot">
                    <p><strong>Paikka:</strong> <span id="vahvistus-paikka"></span></p>
                    <p><strong>Etunimi:</strong> <span id="vahvistus-etunimi"></span></p>
                    <p><strong>Sukunimi:</strong> <span id="vahvistus-sukunimi"></span></p>
                    <p><strong>Sähköposti:</strong> <span id="vahvistus-email"></span></p>
                </div>

                <div class="modal-napit">
                    <button type="submit" id="maksu-button">Maksa varaus</button>
                    <button type="button" id="close-modal">Sulje</button>
                </div>

                <button type="submit" id="testi-button">Tallenna varaus (TESTI)</button>

            </form>


        </div>
    </div>

    <?php
    return ob_get_clean();
});


