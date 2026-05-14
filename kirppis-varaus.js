document.addEventListener('DOMContentLoaded', () => {

    console.log("JS ladattu");

    console.log("AJAX URL:", ajax_object.ajax_url);

    //haetaan varatut pöydät tietokannasta AJAX:lla ja päivitetään kartta ja dropdown sen mukaan
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

    //kartan värit
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

    //dropdownien luonti vapaista pöydistä ja tapahtumien käsittelijät
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

            menu.innerHTML = '';

            vapaatPoydat.forEach(poyta => {

                const li = document.createElement('li');

                li.innerText = poyta.id;

                menu.appendChild(li);
            });

            const options = dropdown.querySelectorAll('.menu li');

            select.addEventListener('click', () => {

                select.classList.toggle('select-clicked');
                nuoli.classList.toggle('nuoli-rotate');
                menu.classList.toggle('menu-open');
            });

            options.forEach(option => {

                option.addEventListener('click', () => {

                    selected.innerText = option.innerText;

                    document.getElementById('paikka').value = option.innerText;

                    select.classList.remove('select-clicked');
                    nuoli.classList.remove('nuoli-rotate');
                    menu.classList.remove('menu-open');

                    options.forEach(opt => {
                        opt.classList.remove('active');
                    });

                    option.classList.add('active');
                });
            });
        });
    }

    //formin kenttien tallennus tietokantaan
    jQuery('#varaus-form').on('submit', function(e) {
        e.preventDefault();

        const etunimi = jQuery('#etunimi').val();
        const sukunimi = jQuery('#sukunimi').val();
        const email = jQuery('#email').val();
        const paikka = jQuery('#paikka').val();

        if (!paikka) {
            alert("Valitse paikka ennen kuin jatkat");
            return;
        }

        document.getElementById('vahvistus-etunimi').innerText = etunimi;
        document.getElementById('vahvistus-sukunimi').innerText = sukunimi;
        document.getElementById('vahvistus-email').innerText = email;
        document.getElementById('vahvistus-paikka').innerText = paikka;

        //Modalin avaus
        document.getElementById('maksu-modal').classList.remove('hidden');
        //lukitse rullaus
        document.body.classList.add('modal-open');
    });
    //modalin sulkeminen
    document.getElementById('close-modal').addEventListener('click', () => {
        document.getElementById('maksu-modal').classList.add('hidden');
        //rullaus lukituksen poisto
        document.body.classList.remove('modal-open');
    });

    jQuery('#maksu-form').on('submit', function(e) {
        e.preventDefault();


        console.log("Lähetetään:", {
            paikka: jQuery('#paikka').val(),
            etunimi: jQuery('#etunimi').val(),
            sukunimi: jQuery('#sukunimi').val(),
            email: jQuery('#email').val(),
        });

        const data = {
            action: 'luo_varaus',
            paikka_id: jQuery('#paikka').val(),
            etunimi: jQuery('#etunimi').val(),
            sukunimi: jQuery('#sukunimi').val(),
            email: jQuery('#email').val()
        };

        console.log("Lähetetään:", data);

        jQuery.post(ajax_object.ajax_url, data, function(response) {
            console.log("Vastaus:", response);

            if (response.success) {
                alert("Varaus onnistui! Vahvistus lähetetty sähköpostiin.");
                    // Päivitetään kartta ja dropdownit uudestaan
                    jQuery.post(ajax_object.ajax_url, {
                        action: 'hae_varatut_poydat'
                    }, function(response) {
                        if (response.success) {
                            const varatutPoydat = response.data;
                            paivitaKartta(varatutPoydat);
                            luoDropdownit(varatutPoydat);

                            // Tyhjennetään lomake
                            // jQuery('#etunimi').val('');
                            // jQuery('#sukunimi').val('');
                            // jQuery('#email').val('');
                            // jQuery('#paikka').val('');
                            // document.querySelector('.selected').innerText = 'Valitse paikkanumero';
                            // jQuery('#paikka').val('');
                            document.getElementById('maksu-modal').classList.add('hidden');
                        }
                    });

            } else {
                alert(response.message);
            }
        });
    });

});

