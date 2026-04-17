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

        status VARCHAR(20) NOT NULL,
        payment_reference VARCHAR(255),

        reserved_until DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        KEY paikka_id (paikka_id)
    ) $charset_collate;";

    dbDelta($sql);
}

function luo_varaus($paikka_id, $etunimi, $sukunimi, $email) {
    global $wpdb;
    $table = $wpdb->prefix . 'varaukset';

    // Tarkista onko paikka jo varattu
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table
        WHERE paikka_id = %s
        AND (
            status = 'paid'
            OR (status = 'pending' AND reserved_until > NOW())
        )
    ", $paikka_id));

    if ($existing > 0) {
        return ['success' => false, 'message' => 'Paikka on jo varattu'];
    }

    // Luo varaus (10 min voimassa)
    $reserved_until = current_time('mysql', 1);
    $reserved_until = date('Y-m-d H:i:s', strtotime($reserved_until . ' +10 minutes'));

    $wpdb->insert($table, [
        'paikka_id' => $paikka_id,
        'etunimi' => $etunimi,
        'sukunimi' => $sukunimi,
        'email' => $email,
        'status' => 'pending',
        'reserved_until' => $reserved_until
    ]);

    return ['success' => true, 'message' => 'Varaus luotu'];
}
//väliaikainen testi. poista myöhemmin
// add_action('init', function() {
//     $result = luo_varaus('Paikka-1', 'Matti', 'Meikäläinen', 'matti@testi.fi');

//     echo '<pre>';
//     print_r($result);
//     echo '</pre>';
//     exit;
// });

// tyylit
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'kirppis-styles',
        plugin_dir_url(__FILE__) . 'styles.css'
    );

    wp_enqueue_script(
        'kirppis-js',
        plugin_dir_url(__FILE__) . 'kirppis-varaus.js',
        [],
        false,
        true // tärkeä: footeriin
    );
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
                ' . $svg . '
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
                    <input type="text" name="name" required>
                </div>
                <div class="nimi-kentta">
                    <label>Sukunimi:</label>
                    <input type="text" name="name" required>
                </div>
            </div>

            <div class="email-rivi">
                <div class="email-kentta">
                    <label>Sähköposti:</label>
                    <input type="email" name="email" required>
                </div>
            </div>

            <div class="alin-rivi">
                <div class="poyta-kentta">
                    <label>Paikkanumero:</label>
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
                    <button type="submit">Maksa varaus</button>
                </div>
            </div>

        </form>
    </div>

    <?php
    return ob_get_clean();
});


