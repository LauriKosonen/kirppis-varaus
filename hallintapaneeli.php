<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kirppis_varaukset_sivu() {

    echo '
        <style>
        @media print {
            #adminmenu,
            #adminmenuback,
            #adminmenuwrap,
            #wpadminbar,
            .button {
                display: none !important;
            }
            #wpcontent,
            #wpbody-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            body {
                background: white !important;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid black;
                padding: 6px;
            }
            .no-print {
                display: none !important;
            }
        }
        </style>
    ';

    global $wpdb;
    $taulu = $wpdb->prefix . 'varaukset';

    // Varauksen lisääminen
    if (isset($_POST['add_varaus'])) {
        $paikka = sanitize_text_field($_POST['paikka_id']);
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $taulu WHERE paikka_id = %s", $paikka)
        );
        if ($existing > 0) {
            echo '<div class="notice notice-error"><p>Paikka on jo varattu!</p></div>';
        } else {
            $wpdb->insert($taulu, [
                'paikka_id' => $paikka,
                'etunimi'   => sanitize_text_field($_POST['etunimi']),
                'sukunimi'  => sanitize_text_field($_POST['sukunimi']),
                'email'     => sanitize_email($_POST['email']),
                'luotu'     => current_time('mysql')
            ]);
            echo '<div class="notice notice-success is-dismissible"><p>Varaus lisätty.</p></div>';
        }
    }

    // Varauksen poistaminen
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        check_admin_referer('delete_varaus_' . $id);
        $wpdb->delete($taulu, ['id' => $id]);
    }

    // Varauksen muokkauksen tallennus
    if (isset($_POST['save_varaus'])) {
        $id = intval($_POST['varaus_id']);
        $wpdb->update(
            $taulu,
            [
                'paikka_id' => sanitize_text_field($_POST['paikka_id']),
                'etunimi'   => sanitize_text_field($_POST['etunimi']),
                'sukunimi'  => sanitize_text_field($_POST['sukunimi']),
                'email'     => sanitize_email($_POST['email']),
            ],
            ['id' => $id]
        );
        $redirect_url = admin_url('admin.php?page=kirppis-varaukset&updated=1');
        echo '<script>window.location.href = "' . esc_url($redirect_url) . '";</script>';
        return;
    }

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Varaus päivitetty.</p></div>';
    }


    // Kaikki varaukset
    $varaukset = $wpdb->get_results("
        SELECT * FROM $taulu
        ORDER BY CAST(SUBSTRING_INDEX(paikka_id, '-', -1) AS UNSIGNED) ASC
    ");

    echo '<div class="wrap">';
    echo '<h1>Paikkavaraukset</h1>';

    // Manuaalinen lisäys
    echo '<h2>Lisää varaus manuaalisesti</h2>';
    echo '<form method="post" style="margin-bottom:20px;" class="no-print">';
    echo '<input type="text" name="paikka_id" placeholder="Paikka-1" required> ';
    echo '<input type="text" name="etunimi" placeholder="Etunimi" required> ';
    echo '<input type="text" name="sukunimi" placeholder="Sukunimi" required> ';
    echo '<input type="email" name="email" placeholder="Email" required> ';
    echo '<input type="submit" name="add_varaus" class="button button-primary" value="Lisää varaus">';
    echo '</form>';

    if (empty($varaukset)) {
        echo '<p>Ei varauksia.</p>';
        echo '</div>';
        return;
    }

    // Taulukko
    echo '<button onclick="window.print()" style="margin-bottom: 1em" class="button button-primary">Tulosta varauslista</button>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>Paikka</th>';
    echo '<th>Etunimi</th>';
    echo '<th>Sukunimi</th>';
    echo '<th>Sähköposti</th>';
    echo '<th>Luotu</th>';
    echo '<th class="no-print">Toiminnot</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($varaukset as $varaus) {
        echo '<tr>';
        echo '<td>' . esc_html($varaus->paikka_id) . '</td>';
        echo '<td>' . esc_html($varaus->etunimi) . '</td>';
        echo '<td>' . esc_html($varaus->sukunimi) . '</td>';
        echo '<td>' . esc_html($varaus->email) . '</td>';
        echo '<td>' . esc_html($varaus->luotu) . '</td>';
        echo '<td class="no-print">';

        echo '<a href="' . admin_url('admin.php?page=kirppis-varaukset&edit=' . $varaus->id) . '"
            class="button button-secondary">Muokkaa</a> ';

        echo '<a href="' . wp_nonce_url(
            admin_url('admin.php?page=kirppis-varaukset&delete=' . $varaus->id),
            'delete_varaus_' . $varaus->id
        ) . '"
            class="button button-secondary"
            onclick="return confirm(\'Haluatko varmasti poistaa varauksen?\')">Poista</a>';

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Muokkauslomake
    if (isset($_GET['edit'])) {
        $id        = intval($_GET['edit']);
        $muokattava = $wpdb->get_row($wpdb->prepare("SELECT * FROM $taulu WHERE id = %d", $id));

        if ($muokattava) {
            echo '<h2>Muokkaa varausta</h2>';
            echo '<form method="post" style="margin-top:20px;" class="no-print">';
            echo '<input type="hidden" name="varaus_id" value="' . esc_attr($muokattava->id) . '">';
            echo '<input type="text"  name="paikka_id" value="' . esc_attr($muokattava->paikka_id) . '" required> ';
            echo '<input type="text"  name="etunimi"   value="' . esc_attr($muokattava->etunimi) . '" required> ';
            echo '<input type="text"  name="sukunimi"  value="' . esc_attr($muokattava->sukunimi) . '" required> ';
            echo '<input type="email" name="email"     value="' . esc_attr($muokattava->email) . '" required> ';
            echo '<input type="submit" name="save_varaus" class="button button-primary" value="Tallenna muutokset">';
            echo '</form>';
        }
    }

    echo '</div>';
}