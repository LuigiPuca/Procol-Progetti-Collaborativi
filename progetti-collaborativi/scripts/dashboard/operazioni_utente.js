function gestisciUtente(bottone, operazione, team = null) {
    noanimazione = true;
    // console.log(bottone);
    const checkboxAttivi = document.querySelectorAll('.lista-utenti td input[type="checkbox"]:checked');
    const dati = {
        operazione: operazione,
        emails: []
    };

    if (team) {
        dati.team = team;
    }

    checkboxAttivi.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const email = row.cells[3].textContent;
        dati.emails.push({ email: email });
    });
    if (checkboxAttivi.length > 0) {
        Conferma.apri({
            titoloBox: stringheDiConferma(operazione).titolo,
            messaggioBox: stringheDiConferma(operazione).messaggio,
            testoOk: stringheDiConferma(operazione).pulsante,
            testoNo: "Annulla",
            allOk: function() {
                // console.log(`Sto eseguendo l'operazione ${operazione}`, dati);
                const jsonData = JSON.stringify(dati);
                // console.log("Che sono diventati", jsonData);
                NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/dashboard_crud.php", true, 'Content-Type', 'application/json', jsonData, verificaOperazione);

                function verificaOperazione() {
                    // console.log("================================");
                    // console.log(xhr.responseText);
                    const rispostaServer = JSON.parse(xhr.responseText);
                    const sm = rispostaServer.messaggio;
                    richiestaEstrazioneInfoExtra();
                    utentiSub.click();
                    if (sm.includes('Errore') || sm.includes('negato')) {
                        Notifica.appari({ messaggioNotifica: sm, tipoNotifica: 'special-errore-notifica' });
                    } else if (sm.includes('Successo')) {
                        Notifica.appari({ messaggioNotifica: sm, tipoNotifica: 'special-successo-notifica' });
                    }
                    if (!rispostaServer.isAdmin) {
                        window.location.href = 'login.html';
                    }
                }
            },
            alNo: function() {
                console.log("Hai premuto Annulla");
            }
        });
    } else {
        Notifica.appari({ messaggioNotifica: 'Attenzione: Devi selezionare almeno un utente', tipoNotifica: 'special-attenzione-notifica' });
    }
    
}

// Funzioni specifiche che chiamano la funzione generica con i parametri appropriati

function eliminaUtente(bottone) {
    gestisciUtente(bottone, "elimina");
}

function promuoviUtente(bottone) {
    gestisciUtente(bottone, "promuovi");
}

function declassaUtente(bottone) {
    gestisciUtente(bottone, "declassa");
}

function rimuoviUtenteDaTeam(bottone) {
    gestisciUtente(bottone, "rimuovi dal team");
}

function assegnaUtenteAlTeam(bottone) {
    let valoreTeamSelezionato = document.querySelector('.selezione-utente-team [role="combobox"]');
    if (valoreTeamSelezionato.value.includes('|')) {
        let valori = valoreTeamSelezionato.value.split('|');
        let sigla = valori[0].trim();
        gestisciUtente(bottone, "aggiungi al team", sigla);
    } else {
        Notifica.appari({ messaggioNotifica: 'Errore: Devi selezionare un team valido', tipoNotifica: 'special-attenzione-notifica' });
    }
}

function recenti(bottone) {
    orderBy = 'DESC';
    noanimazione = true;
    mostraContenutoOverlay.call(mappaPulsanti['reportOpen'].bottone, 'reportOpen');
    let operazioni = trovaPulsanti();
    operazioni.forEach(operazione => {operazione.disabled = false;});
    setTimeout(() => {
        let operazioni = trovaPulsanti();
        operazioni.forEach( operazione => {if (operazione.classList.contains('--opz-sort-attiva')) {operazione.disabled = true;}});
        bottone.disabled = true;
    }, 1000);
}

function datati(bottone) {
    orderBy = 'ASC';
    noanimazione = true;
    aggiornaPulsanti(bottone);
}

function selezionaTutti(bottone) {
    filters = [];
    noanimazione = true;
    aggiornaPulsanti(bottone);
}

function selezionaSolo(bottone, stringa) {
    const isAlreadyAttivo = bottone.classList.contains('--opz-filter-attiva');
    !isAlreadyAttivo ? filters.push(stringa) : toggleFiltri(stringa);
    noanimazione = true;
    aggiornaPulsanti(bottone);
}
function selezionaSoloSessioni(bottone) {
    selezionaSolo(bottone, 'sessione');
}

function selezionaSoloUtenti(bottone) {
    selezionaSolo(bottone, 'utente');
}

function selezionaSoloProgetti(bottone) {
    selezionaSolo(bottone, 'progetto');
}

function selezionaSoloTeam(bottone) {
    selezionaSolo(bottone, 'team');
}

function selezionaSoloSchede(bottone) {
    selezionaSolo(bottone, 'scheda');
}

function trovaPulsanti() {
    let pulsanti = document.querySelectorAll('.operazione');
    return [...pulsanti];
}

function aggiornaPulsanti(bottone) {
    mostraContenutoOverlay.call(mappaPulsanti['reportOpen'].bottone, 'reportOpen');
    setTimeout(() => {
        let operazioni = trovaPulsanti();
        operazioni.forEach( operazione => {if (operazione.classList.contains('--opz-sort-attiva')) {operazione.disabled = true;}});
        bottone.disabled = true;
    }, 1000);
}

function toggleFiltri(stringa) {
    filters = filters.filter(item => item !== stringa);
}

function stringheDiConferma(operazione) {
    let operazioni = ['elimina', 'promuovi', 'declassa', 'rimuovi dal team', 'aggiungi al team'];
    const posizione = operazioni.indexOf(operazione);
    let titoli = [
        "Eliminazione Utenti",
        "Promozione Utenti",
        "Declassamento Utenti",
        "Rimozione Utenti da Team",
        "Aggiunta Utenti al Team"
    ]
    let messaggi = [
        "Vuoi eliminare gli utenti selezionati?",
        "Vuoi promuovere gli utenti selezionati?",
        "Vuoi declassare gli utenti selezionati?",
        "Vuoi rimuovi gli utenti selezionati da qualsiasi team?",
        "Vuoi aggiungere gli utenti selezionati al team prefissato?"
    ];
    let pulsante = [
        "Elimina",
        "Promuovi",
        "Declassa",
        "Rimuovi",
        "Aggiungi"	
    ]
    return {
        titolo: titoli[posizione],
        messaggio : messaggi[posizione],
        pulsante : pulsante[posizione]
    }
}