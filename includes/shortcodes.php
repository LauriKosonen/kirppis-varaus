<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Pääpohja: yhdistää lomakkeen ja kartan

add_shortcode( 'varaus_pohja', function( $atts ) {
    $atts = shortcode_atts( [ 'kartta' => '' ], $atts );

    ob_start();
    ?>
    <div class="varaus-pohja">
        <?php echo do_shortcode( '[kirppis_varauslomake]' ); ?>
        <?php
        if ( $atts['kartta'] === 'kortetalo' ) {
            echo do_shortcode( '[kartta_kortetalo]' );
        }
        // Lisää tähän uusia karttoja tarvittaessa:
        // if ( $atts['kartta'] === 'xxxx' ) { echo do_shortcode( '[kartta_xxxx]' ); }
        ?>
    </div>
    <?php
    return ob_get_clean();
} );


// Kartat
add_shortcode( 'kartta_kortetalo', function() {
    $svg_path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/poytakartta_kortetalo.svg';

    if ( ! file_exists( $svg_path ) ) {
        return '<p>Karttaa ei löytynyt.</p>';
    }

    $svg = file_get_contents( $svg_path );

    return '
        <div class="kartta-pohja">
            <h3 class="kartta-otsikko">PAIKKAKARTTA</h3>
            <div class="svg-wrapper">' . $svg . '</div>
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
} );

// Pohja uudelle kartalle:
// add_shortcode( 'kartta_xxxx', function() {
//     $svg_path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/poytakartta_xxxx.svg';
//     if ( ! file_exists( $svg_path ) ) return '<p>Karttaa ei löytynyt.</p>';
//     $svg = file_get_contents( $svg_path );
//     return '<div class="kartta-pohja">...</div>';
// } );


// Varauslomake
add_shortcode( 'kirppis_varauslomake', function() {

    $laskutus_paalla = get_option( 'kirppis_laskutus_paalla', '0' );
    $hinta = (float) get_option( 'kirppis_poyta_hinta', 0 );
    $hinta_teksti = $hinta > 0 ? number_format( $hinta, 2, ',', '' ) . ' €' : '';

    $tapahtuma_pvm_raw = get_option( 'kirppis_tapahtuma_pvm', '' );
    $tapahtuma_pvm_naytto = $tapahtuma_pvm_raw
        ? date_i18n( 'j.n.Y', strtotime( $tapahtuma_pvm_raw ) ) : '';

    ob_start();
    ?>
    <div class="lomake-pohja">

        <h3 class="keskitetty-teksti">
            PAIKAN VARAUSLOMAKE
            <?php if ( $tapahtuma_pvm_naytto ) echo '<br>' . esc_html( $tapahtuma_pvm_naytto ); ?>
        </h3>

        <p class="keskitetty-teksti">
            Täytä yhteystietosi ja valitse haluamasi paikkanumero alasvetolaatikosta.
            Vapaat ja varatut paikat näkyvät pöytäkartassa.
            <?php if ( $laskutus_paalla === '1' && $hinta_teksti ) : ?>
                Paikanvaraus maksaa <strong><?php echo esc_html( $hinta_teksti ); ?></strong>
                ja maksu tapahtuu sähköpostin mukana toimitetulla laskulla.
            <?php endif; ?>
        </p>

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
                    <button type="submit">
                        Varaa paikka
                        <?php if ( $laskutus_paalla === '1' && $hinta_teksti ) echo '(' . esc_html( $hinta_teksti ) . ')'; ?>
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!-- Vahvistusmodaali -->
    <div id="ilmoitus-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-ikoni">✓</div>
            <h3>Varaus onnistui!</h3>
            <p>
                Sähköpostiisi on lähetetty vahvistusviesti varauksen tiedoilla.
                <?php if ( $laskutus_paalla === '1' ) echo ' Varauksen maksu tapahtuu sähköpostin mukana toimitetulla laskulla.'; ?> Tarkista myös roskaposti kansio, jos et näe viestiä saapuneissa.
            </p>
            <button id="close-ilmoitus-modal">Sulje</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );