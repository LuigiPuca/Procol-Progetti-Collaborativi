

/* SEZIONE DEDICATA AI PULSANTI */
function aggiornoIcone() {
    // uso la delegazione degli eventi per ridurre impattare meno sulle performance
    const aggiorna = document.querySelectorAll('.icon-tray, .show-tray');
    aggiorna.forEach(ascoltatore => { 
        let figli = ascoltatore.querySelectorAll(":scope > img");
        if (ascoltatore.classList.contains('show-tray')) isTrayVisibile(ascoltatore);
        figli.forEach(figlio => { isIconaVisibile(figlio); });
        if (eventMaxiHandlers.has(ascoltatore)) {
            const handlers = eventMaxiHandlers.get(ascoltatore);
            ascoltatore.removeEventListener('mouseover', handlers.mouseover);
            ascoltatore.removeEventListener('mouseleave', handlers.mouseleave);
        }

        const handlers = {
            mouseover: isIconOver.bind(ascoltatore),
            mouseleave: isIconLeave.bind(ascoltatore)
        };

        eventMaxiHandlers.set(ascoltatore, handlers);
        ascoltatore.addEventListener('mouseover', handlers.mouseover);
        ascoltatore.addEventListener('mouseleave', handlers.mouseleave);
    });
}

function isIconaVisibile(icona) {
    let schedaFocus;
    let schedaTarget;
    if (['sinistra', 'destra', 'su', 'giu'].some(classe => icona.classList.contains(classe))) {
        schedaFocus = icona.closest('.post-it, .column');
        if (['sinistra', 'su'].some(antecedente => icona.classList.contains(antecedente))) {
            schedaTarget = schedaFocus.previousElementSibling;
        } else if (icona.classList.contains('giu')) {
            schedaTarget = schedaFocus.nextElementSibling;
        } else {
            schedaTarget = schedaFocus.nextElementSibling.classList.contains('column');
        }
        icona.style.display = (schedaTarget && (ruoloUtente === "capo_team" || ruoloUtente === "admin")) ? "inherit" : "none";
    } else if (icona.classList.contains('elimina') && ruoloUtente !== "capo_team" && ruoloUtente !== "admin") {
        icona.style.display = "none";
    } else {
        icona.style.display = "inherit";
    }
}

function isTrayVisibile(sezione) {
    sezione.style.visibility = (ruoloUtente !== "capo_team" && ruoloUtente !== "admin") ? "hidden" : "visible";
}
function isIconOver(event) {
    isNotIconOver();
    if (event.target.matches('img')) {
        event.target.classList.add('--icon-over');
        let linkCorrente = event.target.getAttribute('src');
        let linkNuovo = linkCorrente.replace("-leave", "-over");
        event.target.setAttribute('src', linkNuovo);
    } else return
}

function isIconLeave(event) {
    isNotIconOver();
}

function isNotIconOver() {
    let iconeVisibili = document.querySelectorAll('.--icon-over');
    if (iconeVisibili.length > 0) {
        iconeVisibili.forEach(icona => {
            let linkCorrente = icona.getAttribute('src');
            let linkNuovo = linkCorrente.replace("-over", "-leave");
            icona.setAttribute('src', linkNuovo);
            icona.classList.remove('--icon-over')
        });
    }
}
