document.addEventListener('DOMContentLoaded', () => {

    console.log("JS ladattu");
    console.log("AJAX URL:", ajax_object.ajax_url);

    // Kartan värien päivitys varattujen pöytien perusteella
    function paivitaKartta(varatutPoydat) {
        const kaikkiPoydat = document.querySelectorAll('svg rect[id^="Paikka-"]');
        kaikkiPoydat.forEach(poyta => {
            if (varatutPoydat.includes(poyta.id)) {
                poyta.style.fill = 'orange';
            } else {
                poyta.style.fill = 'green';
            }
        });
    }

    // Dropdownien luonti vapaista pöydistä ja tapahtumien käsittelijät
    function luoDropdownit(varatutPoydat) {
        const kaikkiPoydat = document.querySelectorAll('svg rect[id^="Paikka-"]');
        const vapaatPoydat = [];

        kaikkiPoydat.forEach(poyta => {
            if (!varatutPoydat.includes(poyta.id)) {
                vapaatPoydat.push(poyta);
            }
        });

        vapaatPoydat.sort((a, b) => {
            const numA = parseInt(a.id.match(/\d+/));
            const numB = parseInt(b.id.match(/\d+/));
            return numA - numB;
        });

        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {

            // Poistetaan vanhat event listenerit kloonaamalla elementti
            const oldSelect = dropdown.querySelector('.select');
            const newSelect = oldSelect.cloneNode(true);
            oldSelect.parentNode.replaceChild(newSelect, oldSelect);

            const select = dropdown.querySelector('.select');
            const nuoli = dropdown.querySelector('.nuoli');
            const menu = dropdown.querySelector('.menu');
            const selected = dropdown.querySelector('.selected');

            // Tyhjennetään vanha lista ja lisätään vapaat pöydät
            menu.innerHTML = '';

            vapaatPoydat.forEach(poyta => {
                const li = document.createElement('li');
                li.innerText = poyta.id;
                menu.appendChild(li);
            });

            const options = dropdown.querySelectorAll('.menu li');

            // Dropdown auki/kiinni
            select.addEventListener('click', () => {
                select.classList.toggle('select-clicked');
                nuoli.classList.toggle('nuoli-rotate');
                menu.classList.toggle('menu-open');
            });

            // Valinnan tallennus piilotettuun input-kenttään
            options.forEach(option => {
                option.addEventListener('click', () => {
                    selected.innerText = option.innerText;
                    document.getElementById('paikka').value = option.innerText;
                    select.classList.remove('select-clicked');
                    nuoli.classList.remove('nuoli-rotate');
                    menu.classList.remove('menu-open');
                    options.forEach(opt => opt.classList.remove('active'));
                    option.classList.add('active');
                });
            });
        });
    }

    // Haetaan varatut pöydät tietokannasta AJAX:lla ja päivitetään kartta ja dropdown
    function paivitaKarttaJaDropdown() {
        jQuery.post(ajax_object.ajax_url, {
            action: 'hae_varatut_poydat'
        }, function(response) {
            if (response.success) {
                const varatutPoydat = response.data;
                paivitaKartta(varatutPoydat);
                luoDropdownit(varatutPoydat);
                console.log("Varatut pöydät:", varatutPoydat);
            }
        });
    }

    // Haetaan varatut pöydät sivun latautuessa
    paivitaKarttaJaDropdown();

    // Vahvistusmodaalin sulkeminen
    document.getElementById('close-ilmoitus-modal').addEventListener('click', () => {
        document.getElementById('ilmoitus-modal').classList.add('hidden');
        document.body.classList.remove('modal-open');
    });

    // Formin kenttien tallennus tietokantaan
    jQuery('#varaus-form').on('submit', function(e) {
        e.preventDefault();

        const paikka = jQuery('#paikka').val();

        if (!paikka) {
            alert("Valitse paikka ennen kuin jatkat");
            return;
        }

        const data = {
            action: 'luo_varaus',
            paikka_id: jQuery('#paikka').val(),
            etunimi: jQuery('#etunimi').val(),
            sukunimi: jQuery('#sukunimi').val(),
            email: jQuery('#email').val()
        };

        // Lähetetään varaus ja päivitetään kartta
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            if (response.success) {
                // Avataan vahvistusmodaali
                document.getElementById('ilmoitus-modal').classList.remove('hidden');
                document.body.classList.add('modal-open');
            } else {
                alert(response.data);
            }
            // Päivitetään kartta ja dropdown aina riippumatta tuloksesta
            paivitaKarttaJaDropdown();
        });

    });

});