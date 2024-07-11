let tastoMinimizza = document.querySelector('.minimizza');

let tastoRicarica = document.querySelector(".ricarica");
let cima = document.querySelector(".cima");
let fondo = document.querySelector(".fondo");

let threadSuHTML = [];
let innerThread = document.querySelector(".overlay-comments");
let tastoMinimizzaTouch = document.querySelector('#sheet-button');
let overlayIcons = document.querySelector(".overlay-icons");

function aggiornoScheda(dati, accessoLivello) {
    tastoMinimizzaTouch.removeEventListener('click', minimizzaScheda);
    tastoMinimizzaTouch.addEventListener('click', minimizzaScheda);
    overlayIcons.removeEventListener('click', whenOverlayIsAperto);
    overlayIcons.addEventListener('click', whenOverlayIsAperto);
    accessoSchedaLivello = accessoLivello;
    const elementiDaVisualizzare = ["OP", "AS", "AT", "AZ", "rs", "md", "pg", "as", "el"];
    elementiDaVisualizzare.forEach( (elemento, index) =>{
        elemento = document.querySelector(`#${elemento}`);
        let offset = 0;
        if (accessoSchedaLivello > 3) {
            offset = 8;
        } else if (accessoSchedaLivello > 2) {
            offset = (dati.schedaOverlay['stato'] === 'Elimina') ? 7 : 8;
        } else if (accessoSchedaLivello === 2) {
            offset = 4;
        } else if (accessoSchedaLivello === 1) {
            offset = 2;
        }
        index <= offset ? elemento.classList.remove('--hidden') : elemento.classList.add('--hidden');
    });
    let overlayGrid = document.querySelector('.overlay-grid');
    overlayGrid.id = `over-${dati.schedaOverlay['id_progetto']}-${dati.schedaOverlay['stato'].replace(/ /g,"_")}-${dati.schedaOverlay['uuid_scheda']}`;
    let autore =  overlayGrid.querySelector('.overlay-post .info-commento .utente');
    let nomeAutore = (dati.schedaOverlay['nome_autore']);
    let cognomeAutore = (dati.schedaOverlay['cognome_autore']);
    let incaricato = (dati.schedaOverlay['incaricato']) ? (dati.schedaOverlay['incaricato']) + " (" + (dati.schedaOverlay['nome_incaricato']) + " " + dati.schedaOverlay['cognome_incaricato'] + ")" : "Utente";
    let avatar = overlayGrid.querySelector('.overlay-post .info-commento .avatar');
    let isAutoreLeader = 0;
    let sessoAutore = "";
    let emailAutore = overlayGrid.querySelector('.overlay-post .info-commento .email-utente');
    const elementiDati = [ 
        {selettore: '#ut p', etichetta: '', data: incaricato},
        {selettore: '#st p', etichetta: '', data: dati.schedaOverlay['stato']},
        {selettore: '#ai p', etichetta: 'Dal', data: dati.schedaOverlay['inizio_mandato']},
        {selettore: '#af p', etichetta: 'Al', data: dati.schedaOverlay['fine_mandato']},
        {selettore: '#di p', etichetta: 'Avvio', data: dati.schedaOverlay['data_inizio']},
        {selettore: '#ds p', etichetta: 'Scadenza', data: dati.schedaOverlay['scadenza']},
        {selettore: '#df p', etichetta: 'Chiusa', data: dati.schedaOverlay['data_fine']}
    ];
    const dataInvio = overlayGrid.querySelector('.overlay-post .info-commento .data-invio');
    const statoFrom = document.querySelector(`#type-${dati.schedaOverlay['id_progetto']}-${dati.schedaOverlay['stato'].replace(/ /g,"_")}`);
    if (statoFrom) {
        let colore = statoFrom.dataset.colore.slice(0,7);
        document.documentElement.style.setProperty('--colore-stato', colore + "41");
        document.documentElement.style.setProperty('--colore-stato-hover', colore + "41");
        document.documentElement.style.setProperty('--colore-stato-box', 'inset 0px 0px 16.4px #D8e5d141');
    } else {
        document.documentElement.style.setProperty('--colore-stato-', "#271a2e41");
        document.documentElement.style.setProperty('--colore-stato-hover', "#D8e5d141");
        document.documentElement.style.setProperty('--colore-stato-box', 'none');
    }
    if (nomeAutore && cognomeAutore) {
        let primaLetteraNome = nomeAutore.charAt(0).toUpperCase();
        let primaLetteraCognome = cognomeAutore.charAt(0).toUpperCase();
        avatar.innerHTML = `${primaLetteraNome} ${primaLetteraCognome}`;
        autore.style.color = 'white';
        autore.innerHTML = `${dati.schedaOverlay['nome_autore']} ${dati.schedaOverlay['cognome_autore']}`;
        isAutoreLeader = (dati.schedaOverlay['ruolo_autore'] === "admin" || dati.schedaOverlay['ruolo_autore'] === "capo_team") ? 1 : 0;
        sessoAutore = dati.schedaOverlay['sesso_autore'] !== "femmina" ? "maschio" : "femmina";
        emailAutore.innerHTML = `${dati.schedaOverlay['autore']}`;
        dataInvio.innerHTML = formattaDataCon(true, dati.schedaOverlay['creazione']);
        if (dati.schedaOverlay['ultima_modifica']) {
            let modificato_il = dati.schedaOverlay['ultima_modifica'];
            // controllo se l'utente che ha modificato é lo stesso che ha creato il post
            let isSelfOEliminato = (dati.schedaOverlay['autore'] && dati.schedaOverlay['modificato_da'] !== dati.schedaOverlay['autore']) ? `da ${dati.schedaOverlay['nome_editor']} ${dati.schedaOverlay['cognome_editor']} - ${dati.schedaOverlay['modificato_da']}` : "";
            let editore = (dati.schedaOverlay['modificato_da']) ? isSelfOEliminato : "da Utente Eliminato"
            let orarioModifica = formattaDataCon(true, modificato_il);
            if(!["oggi", "ieri", "pochi", "secondi", "minuti"].some(parola => orarioModifica.includes(parola))) orarioModifica = "il " + orarioModifica;
            dataInvio.innerHTML += ` (modificato ${orarioModifica} ${editore}) `
        }
        // dataInvio.innerHTML = dati.schedaOverlay['creazione'];
    } else {
        avatar.innerHTMl = "#";
        autore.style.color = "grey";
        autore.innerHTML = `utente eliminato`;
        emailAutore.innerHTML = "";
        dataInvio.innerHTML = "";
    }
    elementiDati.forEach(({ selettore, etichetta, data }) => {
        let formatta = (selettore === '#ut p' || selettore === '#st p') ? false : true;
        const menuBtn = overlayGrid.querySelector(selettore);
        aggiungiListener(menuBtn, etichetta, data, formatta);
    });
    avatar.setAttribute('leader', isAutoreLeader);
    avatar.setAttribute('genere', sessoAutore);
    let titoloPost = overlayGrid.querySelector('.titolo-post');
    titoloPost.innerHTML = dati.schedaOverlay['titolo'];
    let descrizionePost = overlayGrid.querySelector('.overlay-post .corpo-commento p')
    descrizionePost.innerHTML = dati.schedaOverlay['descrizione'];
    const noDescrizione = document.querySelector(".overlay-post .corpo-commento h6");
    if (!descrizionePost.innerHTML) {
        noDescrizione.classList.add('--no-descrizione');
    } else {
        noDescrizione.classList.remove('--no-descrizione');
    }
    let threadDaDB = dati.schedaOverlay.commenti;
    console.log(threadDaDB);
    if (!threadDaDB) threadDaDB = [];
    verificaElementi(threadDaDB, threadSuHTML, "thread", innerThread).then((elementiSuHTML) => {
        threadSuHTML = elementiSuHTML;
        //verifichiamo se abbiamo a che fare con un post da aprire
        // ricavaDatiPost();
    }).catch((errore) => {
        console.trace("Errore: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore: " + errore.message, tipoNotifica: "special-errore-notifica"});
    });
}

function aggiornoCommenti(uuid_commento, contenuto, mittente, nome_mittente, cognome_mittente, sesso_mittente, ruolo_mittente, inviato, destinatario, nome_destinatario, cognome_destinatario, modificato_da, nome_editore, cognome_editore, modificato_il, is_modificabile, uuid_in_risposta) {
    let isLeader = (ruolo_mittente === "capo_team" || ruolo_mittente === "admin") ? 1 : 0;
    let siglaLettere;
    let style;
    if (nome_mittente && cognome_mittente) {
        siglaLettere = nome_mittente.charAt(0).toUpperCase() + cognome_mittente.charAt(0).toUpperCase();
        style = "color: white;";
    } else {
        siglaLettere = "X";
        style = "color: grey;";
        sesso_mittente = "none";
        mittente = "";
    }
    let rispondendoA = "";
    if (uuid_in_risposta) {
        let rispondendoAlCommento = document.querySelector(`#repl-${uuid_in_risposta} .corpo-commento p`);
        let anteprima = (rispondendoAlCommento) ? rispondendoAlCommento.textContent.substring(0, 300) : "";
        let anteprimaUtente = (rispondendoAlCommento) ? rispondendoAlCommento.closest(".comment").querySelector(".utente").textContent : "";
        let anteprimaEmail = (rispondendoAlCommento) ? rispondendoAlCommento.closest(".comment").querySelector(".email-utente").textContent : "";
        rispondendoA = (rispondendoAlCommento) ? `<p data-href="${uuid_in_risposta}" class="ritorno-commento" title="ritorno al commento \in risposta di${anteprimaEmail}"><b> ↱ "</b>${anteprimaUtente}": ${anteprima} </p>` : "";
        // : `<p data-href="${uuid_in_risposta}" class="ritorno-commento" title="ritorno al commento \in risposta di utente eliminato"><b> ↱ "</b>$Utente eliminato": ${anteprima}</p>` :
    }    
    let orarioInvio = formattaDataCon(true, inviato);
    if (modificato_il) {
        let isSelfOEliminato = (mittente && modificato_da !== mittente) ? `da ${nome_editore} ${cognome_editore} - ${modificato_da}` : '';
        let editore = (modificato_da) ? isSelfOEliminato : "da Utente Eliminato";
        let orarioModifica = formattaDataCon(true, modificato_il);
        if(!["oggi", "ieri", "pochi", "secondi", "minuti"].some(parola => orarioModifica.includes(parola))) orarioModifica = "il " + orarioModifica;
        orarioInvio += ` (modificato ${orarioModifica} ${editore})`;
    }
    let operazioni = (is_modificabile) ? `
        <button type="button" class="modifica-commento" data-toggle="reply-form" data-target="repl-${uuid_commento}">Modifica</button>
        <button type="button" class="elimina-commento" data-target="repl-${uuid_commento}">Elimina</button>
    ` : `` ;

    const elHTML = `
    <div class="comment-container" id="repl-${uuid_commento}">
        ${rispondendoA}
        <details open="" class="comment">
            <summary>
                <div class="intestazione-commento">
                    <div class="info-commento">
                        <div class="user-frame">
                            <div class="avatar" leader="${isLeader}" genere="${sesso_mittente}">${siglaLettere}</div> 
                            <div class="altre-info">
                                <p class="utente" style="color: white;">${nome_mittente} ${cognome_mittente}</p>
                                <a href="mailto:${mittente}" target="_blank" class="email-utente">${mittente}</a>
                                <p class="data-invio">${orarioInvio}</p>
                            </div>
                        </div>    
                        <p class="collapse">(Nascondi Commento)</p>
                    </div>
                </div>
            </summary>
            <div class="corpo-commento">
                <p>${contenuto}</p>
                <button type="button" class="rispondi-commento" data-toggle="reply-form" data-target="repl-${uuid_commento}">Rispondi</button>
                ${operazioni}
            </div>
        </details>
    </div>
    `
    return elHTML;


}

function aggiornoSchedaInChiusura() {
    overlayIcons.removeEventListener('click', whenOverlayIsAperto);
}

function whenOverlayIsAperto(e) {
    let self = e.target;
    if (self.matches('.minimizza')) {
        minimizzaScheda();
    } else if (self.matches('.ricarica')) {
        ricaricaScheda();
    } else if (self.matches('.cima')) {
        cimaScheda();
    } else if (self.matches('.fondo')) {
        fondoScheda();
    } else if (self.matches('.indietro')) {
        menuIndietro();
    } else if (self.matches('.burger')) {
        menuBurger(self);
    }
}

function minimizzaScheda(){
    const overlayContainer = document.querySelector('.overlay-container');
    overlayContainer.classList.add("--closing");
    if (eventMaxiHandlers.has(overlayContainer)) {
        const handlers = eventMaxiHandlers.get(overlayContainer);
        overlayContainer.removeEventListener('animationend', handlers.closeEnd);
    }
    const handlers = {
        closeEnd: waitClosing.bind(overlayContainer),
    }
    eventMaxiHandlers.set(overlayContainer, handlers);
    overlayContainer.addEventListener('animationend', handlers.closeEnd);
    esciDaOperazione(true);
}

function waitClosing(event) {
    const overlayContainer = this;
    overlayContainer.removeEventListener('animationend', eventMaxiHandlers.get(overlayContainer).closeEnd);
    overlayContainer.classList.remove('--closing');
    overlayContainer.style.display = 'none';
    unlockExtScroll();
}
function cimaScheda( ){
    const overlayComments = document.querySelector('.overlay-comments');
    overlayComments.scrollTop = 0;
}

function fondoScheda() {
    let overlayComments = document.querySelector('.overlay-comments');
    overlayComments.scrollTop = overlayComments.scrollHeight;
}

function ricaricaScheda() {
    window.location.reload();
}

function menuIndietro(){
    gridEl.classList.remove('--on');  
}

function menuBurger() {
    gridEl.classList.add('--on');
}

function aggiungiListener(elemento, etichetta, data, formatta) {
    if (formatta) data = formattaDataCon(false, data);
    elemento.innerHTML = data ? `${etichetta} ${data}` : etichetta;
    
    // Rimuovi l'eventuale vecchio listener
    if (eventMaxiHandlers.has(elemento.parentElement)) {
        elemento.parentElement.removeEventListener('mouseover', eventMaxiHandlers.get(elemento.parentElement).mouseover);
    }

    // Crea il nuovo listener e salvalo nella WeakMap
    const handlers = {
        mouseover: () => isOverXFlow(elemento)
    };

    eventMaxiHandlers.set(elemento.parentElement, handlers);
    elemento.parentElement.addEventListener('mouseover', handlers.mouseover);
}

function coloreContrasto(hex) {
    hex = hex.replace('#', '');
    let j = 0;
    const rgb = [0, 0, 0];
    for (let i = 0; i < hex.length; i+=2) { 
        rgb[j] = parseInt(hex.substr(i, 2), 16);
        j++;
    }
    // notiamo che il verde é il tono piú grande perché é allo spettro di frequenza piú facilmete percepibile dall'occhio umano
    const luminanza = (0.299 * rgb[0] + 0.587 * rgb[1] + 0.114 * rgb[2]);
    return luminanza > 0.5 ? '#000000' : '#ffffff';
}