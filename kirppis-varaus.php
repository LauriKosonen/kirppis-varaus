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


