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

    $laskutus_paalla = get_option('kirppis_laskutus_paalla', '0');

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

    $tallennettu_hinta = get_option('kirppis_poyta_hinta', '');

    // Kaikki varaukset
    $varaukset = $wpdb->get_results("
        SELECT * FROM $taulu
        ORDER BY CAST(SUBSTRING_INDEX(paikka_id, '-', -1) AS UNSIGNED) ASC
    ");

    echo '<div class="wrap">';
    echo '<h1 style="margin-bottom: 1em;">Paikkavaraukset</h1>';

    // Laskutusasetukset – tallennetaan AJAX:lla ilman nappia
    $laskutus_nonce = wp_create_nonce('tallenna_laskutusasetus_nonce');
    echo '<div style="margin-bottom: 20px;" class="no-print">';
    echo '<label style="font-size: 1.1em; font-weight: bold; cursor: pointer;">';
    echo '<input type="checkbox" id="kirppis_laskutus_checkbox" value="1" '
        . checked('1', $laskutus_paalla, false) . ' style="margin-right: 8px;">';
    echo 'Laskutus päällä – lasku lähetetään sähköpostin liitteenä';
    echo '</label>';
    echo ' <span id="laskutus-tila" style="color: #666; font-style: italic; margin-left: 8px;"></span>';
    echo '</div>';

    echo '<script>
    document.getElementById("kirppis_laskutus_checkbox").addEventListener("change", function() {
        var arvo = this.checked ? "1" : "0";
        var tila = document.getElementById("laskutus-tila");
        tila.textContent = "Tallennetaan...";
        jQuery.post(ajaxurl, {
            action: "tallenna_laskutusasetus",
            arvo: arvo,
            nonce: "' . $laskutus_nonce . '"
        }, function(response) {
            if (response.success) {
                tila.textContent = "✓ Tallennettu";
                setTimeout(function(){ tila.textContent = ""; }, 2000);
                // Päivitetään ilmoitusbanneri
                var banneri = document.getElementById("laskutus-banneri");
                if (arvo === "1") {
                    banneri.className = "notice notice-warning is-dismissible";
                    banneri.innerHTML = "<p>⚠️ Laskutus on päällä – varausvahvistuksiin liitetään PDF-lasku.</p>";
                } else {
                    banneri.className = "notice notice-info is-dismissible";
                    banneri.innerHTML = "<p>Laskutus ei ole päällä – sähköpostit lähetetään ilman laskua.</p>";
                }
            } else {
                tila.textContent = "Virhe tallennuksessa.";
            }
        });
    });
    </script>';

    $banneri_class = $laskutus_paalla === '1' ? 'notice notice-warning is-dismissible' : 'notice notice-info is-dismissible';
    $banneri_teksti = $laskutus_paalla === '1'
        ? '<p>⚠️ Laskutus on päällä – varausvahvistuksiin liitetään PDF-lasku.</p>'
        : '<p>Laskutus ei ole päällä – sähköpostit lähetetään ilman laskua.</p>';
    echo '<div id="laskutus-banneri" class="' . $banneri_class . '">' . $banneri_teksti . '</div>';

    $hinta_nonce = wp_create_nonce('tallenna_hinta_nonce');
    echo '<div class="no-print" style="margin-bottom: 1.5em; display: flex; align-items: center; gap: 0.5em;">';
    echo '<label for="poyta_hinta" style="font-weight:600;">Varauksen hinta (€):</label>';
    echo '<input type="text" id="poyta_hinta" value="' . esc_attr($tallennettu_hinta !== '' ? number_format((float)$tallennettu_hinta, 2, ',', '') : '') . '" placeholder="0,00" style="width:90px;">';
    echo '<button id="tallenna_hinta_btn" class="button button-secondary">Tallenna hinta</button>';
    echo '<span id="hinta-tila" style="color: #666; font-style: italic; margin-left: 8px;"></span>';
    echo '</div>';

    echo '<script>
    document.getElementById("tallenna_hinta_btn").addEventListener("click", function() {
        var arvo = document.getElementById("poyta_hinta").value;
        var tila = document.getElementById("hinta-tila");
        tila.textContent = "Tallennetaan...";
        jQuery.post(ajaxurl, {
            action: "tallenna_hinta",
            hinta: arvo,
            nonce: "' . $hinta_nonce . '"
        }, function(response) {
            if (response.success) {
                tila.textContent = "\u2713 Tallennettu";
                setTimeout(function(){ tila.textContent = ""; }, 2000);
            } else {
                tila.textContent = "Virhe tallennuksessa.";
            }
        });
    });
    </script>';

    // Manuaalinen lisäys
    echo '<h2 class="no-print">Lisää varaus manuaalisesti</h2>';
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

    // Tulosta-nappi ja Poista kaikki -nappi
    $poista_kaikki_nonce = wp_create_nonce('poista_kaikki_varaukset_nonce');
    echo '<div class="no-print" style="margin-bottom: 1em; display: flex; gap: 0.5em; align-items: center;">';
    echo '<button onclick="window.print()" class="button button-primary">Tulosta varauslista</button>';
    echo '<button id="poista_kaikki_btn" class="button" style="background:#d63638; border-color:#d63638; color:white;">Poista kaikki varaukset</button>';
    echo '</div>';

    echo '<script>
    document.getElementById("poista_kaikki_btn").addEventListener("click", function() {
        if (!confirm("Haluatko varmasti poistaa KAIKKI varaukset? Tätä ei voi perua.")) return;
        var btn = this;
        btn.disabled = true;
        jQuery.post(ajaxurl, {
            action: "poista_kaikki_varaukset",
            nonce: "' . $poista_kaikki_nonce . '"
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert("Virhe poistamisessa.");
                btn.disabled = false;
            }
        });
    });
    </script>';

    $maksettu_nonce = wp_create_nonce('merkitse_maksetuksi_nonce');
    echo '<script>
    function merkitseMaksetuksi(varausId, btn) {
        if (!confirm("Merkitäänkö varaus maksetuksi?")) return;
        btn.disabled = true;
        jQuery.post(ajaxurl, {
            action: "merkitse_maksetuksi",
            varaus_id: varausId,
            nonce: "' . $maksettu_nonce . '"
        }, function(response) {
            if (response.success) {
                var td = btn.closest("td").previousElementSibling;
                td.innerHTML = "<span style=\"color:green;\">✓ Maksettu</span>";
                btn.remove();
            } else {
                alert("Virhe merkinnässä.");
                btn.disabled = false;
            }
        });
    }
    </script>';

    // Taulukko
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>Paikka</th>';
    echo '<th>Etunimi</th>';
    echo '<th>Sukunimi</th>';
    echo '<th>Sähköposti</th>';
    echo '<th>Luotu</th>';
    echo '<th>Lasku</th>';
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

        $lasku_status = $varaus->lasku_lahetetty
            ? '<span style="color: green;">✓ Lähetetty (' . esc_html($varaus->laskunumero) . ')</span>'
            : '<span style="color: #999;">–</span>';

        if (!empty($varaus->maksettu)) {
            $lasku_status = '<span style="color: green; font-weight:bold;">✓ Maksettu</span>';
        }

        echo '<td>' . $lasku_status . '</td>';

        echo '<td class="no-print">';
        echo '<a href="' . admin_url('admin.php?page=kirppis-varaukset&edit=' . $varaus->id) . '"
            class="button button-secondary">Muokkaa</a> ';
        echo '<a href="' . wp_nonce_url(
            admin_url('admin.php?page=kirppis-varaukset&delete=' . $varaus->id),
            'delete_varaus_' . $varaus->id
        ) . '"
            class="button button-secondary"
            onclick="return confirm(\'Haluatko varmasti poistaa varauksen?\')">Poista</a>';

        if (empty($varaus->maksettu) && !empty($varaus->lasku_lahetetty)) {
            echo ' <button class="button button-secondary" onclick="merkitseMaksetuksi(' . $varaus->id . ', this)">Merkitse maksetuksi</button>';
        }

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