<?php

    if (!defined('ABSPATH')) {
        exit;
    }

    function vahvistus_email($email, $etunimi, $sukunimi, $paikka_id) {
        $email = sanitize_email($email);
        $etunimi = sanitize_text_field($etunimi);
        $sukunimi = sanitize_text_field($sukunimi);
        $paikka_id = sanitize_text_field($paikka_id);

        // Sähköpostin otsikko
        $subject = 'Pöytävaraus vahvistettu';

        // Sähköpostin sisältö
        $message = "
        Hei $etunimi $sukunimi,

        Pöytävarauksesi on vastaanotettu onnistuneesti.

        Varattu pöytä:
        $paikka_id

        Kiitos varauksestasi!

        Terveisin,
        Torppis-kirppis
        ";

        // Headerit
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8'
        );

        // Lähetetään sähköposti
        return wp_mail($email, $subject, $message, $headers);
    }

