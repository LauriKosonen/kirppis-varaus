document.addEventListener('DOMContentLoaded', () => {
        // TESTIDATA (myöhemmin tämä tulee backendista)
    const varatutPoydat = ['Paikka-2', 'Paikka-5', 'Paikka-18'];

    console.log("JS ladattu");

    console.log("AJAX URL:", ajax_object.ajax_url);

    // Hae kaikki svg pöydät
    const kaikkiPoydat = document.querySelectorAll('svg rect[id^="Paikka-"]');

    // haetaan vapaat pöydät
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

    // Väritetään kartta (valinnainen mutta suositeltava)
    kaikkiPoydat.forEach(poyta => {
        if (varatutPoydat.includes(poyta.id)) {
            poyta.style.fill = 'orange';
        } else {
            poyta.style.fill = 'green';
        }
    });

    // Hae kaikki dropdownit
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

        // Hae uudet optionit (koska luotiin dynaamisesti)
        const options = dropdown.querySelectorAll('.menu li');

        // Dropdown auki/kiinni
        select.addEventListener('click', () => {
            select.classList.toggle('select-clicked');
            nuoli.classList.toggle('nuoli-rotate');
            menu.classList.toggle('menu-open');
        });

        // Option valinta
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

    jQuery('#varaus-form').on('submit', function(e) {
        e.preventDefault();

        document.getElementById('maksu-modal').classList.remove('hidden');
    });

    document.getElementById('close-modal').addEventListener('click', () => {
        document.getElementById('maksu-modal').classList.add('hidden');
    });

    document.getElementById('mobilepay-button').addEventListener('click', () => {
        console.log("MobilePay valittu");
    });

    document.getElementById('kortti-button').addEventListener('click', () => {
        console.log("Kortti valittu");
    });

    jQuery('#maksu-form').on('submit', function(e) {
        e.preventDefault();

        console.log("Lähetetään:", {
            paikka: jQuery('#paikka').val(),
            etunimi: jQuery('#etunimi').val(),
            sukunimi: jQuery('#sukunimi').val(),
            email: jQuery('#email').val()
        });

        jQuery.post(ajax_object.ajax_url, {
            action: 'luo_varaus',
            paikka_id: jQuery('#paikka').val(),
            etunimi: jQuery('#etunimi').val(),
            sukunimi: jQuery('#sukunimi').val(),
            email: jQuery('#email').val()
        }, function(response) {
            console.log("Vastaus:", response);

            if (response.success) {
                alert('Varaus onnistui!');
            } else {
                alert(response.message);
            }
        });
    });
});

