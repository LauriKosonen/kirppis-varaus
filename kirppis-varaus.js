document.addEventListener('DOMContentLoaded', () => {
    // TESTIDATA (myöhemmin backendista)
    const varatutPoydat = ['Paikka-2', 'Paikka-5', 'Paikka-18'];

    console.log("JS ladattu");

    console.log("AJAX URL:", ajax_object.ajax_url);

    //haetaan svg kartasta kaikki pöydät "rect" elementtien mukaan
    const kaikkiPoydat = document.querySelectorAll('svg rect[id^="Paikka-"]');

    const vapaatPoydat = [];

    kaikkiPoydat.forEach(poyta => {
        if (!varatutPoydat.includes(poyta.id)) {
            vapaatPoydat.push(poyta);
        }
    });

    //muutetaan id:t numeroiksi
    vapaatPoydat.sort((a, b) => {
        const numA = parseInt(a.id.match(/\d+/));
        const numB = parseInt(b.id.match(/\d+/));
        return numA - numB;
    });

    //kartan värit
    kaikkiPoydat.forEach(poyta => {
        if (varatutPoydat.includes(poyta.id)) {
            poyta.style.fill = 'orange';
        } else {
            poyta.style.fill = 'green';
        }
    });

    //kaikki dropdownit
    const dropdowns = document.querySelectorAll('.dropdown');

    // Loopataan dropdownit
    dropdowns.forEach(dropdown => {
        const select = dropdown.querySelector('.select');
        const nuoli = dropdown.querySelector('.nuoli');
        const menu = dropdown.querySelector('.menu');
        const selected = dropdown.querySelector('.selected');

        // Tyhjennetään menu
        menu.innerHTML = '';

        // Lisätään vapaat pöydät dropdowniin
        vapaatPoydat.forEach(poyta => {
            const li = document.createElement('li');
            li.innerText = poyta.id;
            menu.appendChild(li);
        });

        // Haetaan uudet optionit
        const options = dropdown.querySelectorAll('.menu li');

        // Dropdown auki/kiinni
        select.addEventListener('click', () => {
            select.classList.toggle('select-clicked');
            nuoli.classList.toggle('nuoli-rotate');
            menu.classList.toggle('menu-open');
        });

        // Option/vaihtoehdon valinta
        options.forEach(option => {
            option.addEventListener('click', () => {
                selected.innerText = option.innerText;

                document.getElementById('paikka').value = option.innerText;

                console.log("Valittu paikka:", option.innerText);

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
                alert('Varaus tallennettu tietokantaan!');
                document.getElementById('maksu-modal').classList.add('hidden');
            } else {
                alert(response.message);
            }
        });
    });

});

