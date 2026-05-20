<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use Dompdf\Dompdf;

function laske_viitenumero($pohja) {
    $numerosarja = (string)$pohja; // ei etunollia
    $kertoimet = [7, 3, 1];
    $summa = 0;
    $digits = array_reverse(str_split($numerosarja));

    foreach ($digits as $i => $digit) {
        $summa += (int)$digit * $kertoimet[$i % 3];
    }

    $tarkiste = (10 - ($summa % 10)) % 10;
    return $numerosarja . $tarkiste;
}

function generoi_laskunumero($varaus_id) {
    return 'L' . date('Ym') . '-' . str_pad($varaus_id, 4, '0', STR_PAD_LEFT);
}

function generoi_lasku_pdf($etunimi, $sukunimi, $email, $paikka_id, $viitenumero, $laskunumero) {
    $hinta = 20.00;
    $erapaiva = date('d.m.Y', strtotime('+14 days'));
    $pvm = date('d.m.Y');

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: sans-serif; font-size: 13px; color: #222; }
            h2 { margin-bottom: 0.2em; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 1em; }
            .erittely th { background: #eeeeee; }
            .erittely th, .erittely td { border: 1px solid #ccc; padding: 6px; }
            .tiedot td { padding: 3px 8px 3px 0; }
        </style>
    </head>
    <body>
        <h2>LASKU</h2>
        <table class="tiedot">
            <tr><td><b>Laskun numero:</b></td><td>' . $laskunumero . '</td></tr>
            <tr><td><b>Päivämäärä:</b></td><td>' . $pvm . '</td></tr>
            <tr><td><b>Eräpäivä:</b></td><td>' . $erapaiva . '</td></tr>
        </table>

        <h3>Laskutettava</h3>
        <table class="tiedot">
            <tr><td><b>Nimi:</b></td><td>' . htmlspecialchars($etunimi) . ' ' . htmlspecialchars($sukunimi) . '</td></tr>
            <tr><td><b>Sähköposti:</b></td><td>' . htmlspecialchars($email) . '</td></tr>
        </table>

        <h3>Erittely</h3>
        <table class="erittely">
            <thead>
                <tr><th>Kuvaus</th><th>Hinta (sis. ALV)</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Pöytäpaikka – ' . htmlspecialchars($paikka_id) . '</td>
                    <td>' . number_format($hinta, 2, ',', '') . ' €</td>
                </tr>
            </tbody>
        </table>

        <h3>Maksutiedot</h3>
        <table class="tiedot">
            <tr><td><b>Saaja:</b></td><td>Torppis-kirppis</td></tr>
            <tr><td><b>IBAN:</b></td><td>FI00 0000 0000 0000 00</td></tr>
            <tr><td><b>Summa:</b></td><td>' . number_format($hinta, 2, ',', '') . ' €</td></tr>
            <tr><td><b>Viitenumero:</b></td><td>' . $viitenumero . '</td></tr>
            <tr><td><b>Eräpäivä:</b></td><td>' . $erapaiva . '</td></tr>
        </table>
    </body>
    </html>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function vahvistus_email($email, $etunimi, $sukunimi, $paikka_id, $varaus_id) {
    $email     = sanitize_email($email);
    $etunimi   = sanitize_text_field($etunimi);
    $sukunimi  = sanitize_text_field($sukunimi);
    $paikka_id = sanitize_text_field($paikka_id);

    $paikka_numero = preg_replace('/[^0-9]/', '', $paikka_id);
    $viitenumero   = laske_viitenumero($paikka_numero);
    $laskunumero   = generoi_laskunumero($varaus_id);
    $laskutus_paalla = get_option('kirppis_laskutus_paalla', '0');

    //lisää tämä väliaikaisesti
    $pdf_debug = generoi_lasku_pdf($etunimi, $sukunimi, $email, $paikka_id, $viitenumero, $laskunumero);
    file_put_contents(plugin_dir_path(__FILE__) . 'debug-lasku-' . $varaus_id . '.pdf', $pdf_debug);

    $subject = 'Pöytävaraus vahvistettu';

    $message = "
Hei $etunimi $sukunimi,

Pöytävarauksesi on vastaanotettu onnistuneesti.

Varattu pöytä: $paikka_id
";

    if ($laskutus_paalla === '1') {
        $message .= "Viitenumero:   $viitenumero\n";
        $message .= "Lasku on liitetty tähän sähköpostiin PDF-muodossa.\n";
    }

    $message .= "
Kiitos varauksestasi!

Terveisin,
Torppis-kirppis
    ";

    // Lähetetään ilman liitettä
    if ($laskutus_paalla !== '1') {
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($email, $subject, $message, $headers);
    }

    // Lähetetään PDF-liitteen kanssa
    $pdf_data  = generoi_lasku_pdf($etunimi, $sukunimi, $email, $paikka_id, $viitenumero, $laskunumero);
    $boundary  = md5(uniqid(time()));

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
    ]);

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $message . "\r\n";

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/pdf; name=\"lasku_$paikka_numero.pdf\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"lasku_$paikka_numero.pdf\"\r\n\r\n";
    $body .= chunk_split(base64_encode($pdf_data)) . "\r\n";
    $body .= "--$boundary--";

    // Tallennetaan lasku lähetetyksi tietokantaan
    global $wpdb;
    $wpdb->update(
        $wpdb->prefix . 'varaukset',
        ['lasku_lahetetty' => 1, 'laskunumero' => $laskunumero],
        ['id' => $varaus_id]
    );

    return wp_mail($email, $subject, $body, $headers);
}