
const gridEl = document.querySelector('.overlay-grid');
const corpoCommento = document.getElementsByClassName("corpo-commento")[1];
const overlayPost = corpoCommento.parentNode;
const allargaOStringi = document.getElementsByClassName("allarga-stringi")[0];
const stringi = allargaOStringi.firstElementChild;
const allarga = allargaOStringi.lastElementChild;
stringi.addEventListener('click', stringiPost);
allarga.addEventListener('click', allargaPost);
const eventHandlers = new WeakMap();
const idProgettoTarget = document.getElementById("id-progetto-target");
const submitButton = document.getElementById("submit-reply");
const categoriaTarget = document.getElementById("categoria-target");
const uuidSchedaTarget = document.getElementById("uuid-scheda-target");
const uuidCommentoTarget = document.getElementById("uuid-commento-target");

const assignForm = gridEl.querySelector('.overlay-assign');

const Triggers = {
    form : {
        "form" : ['submit', document.getElementById("--reply-form"), mandaRisposta],
        "annulla-reply" : ['click', document.getElementById("annulla-reply"), annullaRisposta],
        "conferma-reply" : ['click', document.getElementById("conferma-reply"), verificaRisposta],
        "submit-reply" : ['click', document.getElementById("submit-reply"), azionaFormRisposta],
    },
    operazioni : {
        "aggiungi-descrizione" : ['click', document.querySelector(".overlay-grid .overlay-post .corpo-commento h6"), rispondiA, "Aggiunta descrizione al post di: ", document.querySelector(".overlay-post .utente"), document.querySelector(".overlay-post .corpo-commento p"), "Aggiungi una descrizione"],
        "modifica-descrizione" : ['click', document.querySelector(".overlay-grid .overlay-menu #md"), rispondiA, "Modifica descrizione al post di: ", document.querySelector(".overlay-post .utente"), document.querySelector(".overlay-post .corpo-commento p"), "Modifica descrizione post" ],
        "aggiungi-commento" : ['focus', document.querySelector(".overlay-grid #--reply-box .textarea"), rispondiA, "In risposta al post di: ", document.querySelector(".overlay-post .utente"), document.querySelector(".overlay-post .corpo-commento p"), "Aggiungi un commento"],
    },
    visibilita: {
        "sezione-post" : ['flex', document.querySelector(".overlay-post"), 'none'],
        "sezione-commenti" : ['block', document.querySelector(".overlay-comments"), 'none'],
        "sezione-in-risposta" : ['none', document.querySelector(".overlay-in-risposta"), 'flex']
    },
    inRisposta: {
        "a-focus" : [document.querySelector(".overlay-in-risposta .utente"), document.querySelector(".overlay-in-risposta .corpo-commento p")],
        "a-target" : [document.querySelector(".overlay-post .utente"), document.querySelector(".overlay-post .corpo-commento p")]
    },
    ottieni: {
        "ottieni-membri" : ['#as', "2", '--a', gridEl.querySelector('.overlay-assign'), "--assign-form", "assegna-membro"],
        "ottieni-stati" : ['#sp', "1", '--s', gridEl.querySelector('.overlay-shift'), "--shift-form", "cambia-stato"],
        "ottieni-progresso": ['#pg', "2", '--d', gridEl.querySelector('.overlay-duration'), "--duration-form", "imposta-durata"],
        "ottieni-report" : ['#rs', "2", '--r', gridEl.querySelector('.overlay-report'), "--report-container", null]
    },
    formDaSvuotare: {
        "assignForm" : gridEl.querySelector('.overlay-assign'),
        "shiftForm": gridEl.querySelector('.overlay-shift'),
        "durationForm" : gridEl.querySelector('.overlay-duration'),
        "reportForm": gridEl.querySelector('.overlay-report'),
    }
}
const visibilitaEl = [Triggers.form["annulla-reply"], Triggers.form["conferma-reply"], Triggers.visibilita["sezione-post"], Triggers.visibilita["sezione-commenti"], Triggers.visibilita["sezione-in-risposta"]];
const textarea = Triggers.operazioni['aggiungi-commento'][1];
const sezioneCommenti = Triggers.visibilita['sezione-commenti'][1];
const sezioneMenuScheda = document.querySelector(".overlay-menu");
textarea.addEventListener('input', checkCaratteri.bind(textarea));
sezioneCommenti.addEventListener('click', operazioneCommento);
sezioneMenuScheda.addEventListener('click', operazioneMenuScheda);

function aggiornaCommentiThread() {
    goTo();
    Object.entries(Triggers.operazioni).forEach(([operazione, [trigger, selettore, azione, messaggio, target, commentoTarget, placeholder]]) => {
        if (isEsistenteEdIstanzaDi(selettore, Node)) {
            const errorGenerico = "Non puoi eseguire quest'operazione perchè il post con cui vuoi interagire non rispetta le specifiche!";
            const messaggioTarget = messaggio + target.textContent;
            let uuid_target_scheda = gridEl.id;
            if (!uuid_target_scheda.startsWith("over-") || 
                (uuid_target_scheda = uuid_target_scheda.split("-")).length !== 4 || 
                !uuid_target_scheda.slice(1).every(part => typeof part === 'string')) {
                throw new Error(errorGenerico);
            }
            idProgettoTarget.value = uuid_target_scheda[1];
            categoriaTarget.value = uuid_target_scheda[2].replace(/_/g,' ');
            uuidSchedaTarget.value = uuid_target_scheda[3];
            uuidCommentoTarget.value = "";
            submitButton.dataset.operazione = "";
            const azioneBindata = eventHandlers.get(selettore);
            if (azioneBindata) {
                selettore.removeEventListener(trigger, azioneBindata);
            }
            const nuovaAzioneBindata = azione.bind(this, operazione, selettore, messaggioTarget, commentoTarget, placeholder);
            eventHandlers.set(selettore, nuovaAzioneBindata);
            selettore.addEventListener(trigger, nuovaAzioneBindata);
        }
        
     });
}
function operazioneCommento(e) {
    let self = e.target;
    try {
        if (self.matches('.elimina-commento, .modifica-commento, .rispondi-commento')) {
            gestisciCommento(self);
        } else if (self.matches('.ritorno-commento')) {
            let collegamentoA = self.dataset.href;
            const getElemento = document.getElementById(`repl-${collegamentoA}`);
            history.pushState(null, '', `#repl=${collegamentoA}`);
            getElemento.scrollIntoView({behavior: "smooth", block: "start", inline: "nearest"});
            // mettiamo temporaneamente in focus il commento
            getElemento.classList.add("--attenzione");
            setTimeout(() => getElemento.classList.remove("--attenzione"), 3000);
        } else if (self.matches('details, details *')) {
            collapseToggle(this);
        }
    } catch (errore) {
        if (errore.message.includes("reading 'id'") || errore.message.includes("textContent")) errore.message = "Errore DOM: Conflitto rilevato nel thread!"
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function operazioneMenuScheda(e) {
    let self = e.target;
    try {
        let subStrGrid = (gridEl.id).replace(/_/g," ").split('-');
        if (subStrGrid.length !== 4) throw new Error("reading 'id'");
        if (self.matches('#pr, #pr *, #mr, #mr *')) {
            let pr = document.getElementById('pr');
            let mr = document.getElementById('mr');
            if (self.matches('#pr, #pr *') && !pr.classList.contains('--active-order')){
                pr.classList.add('--active-order');
                mr.classList.remove('--active-order');
                orderByTimeCommenti = 'DESC';
                esciDaOperazione(false, true);
            } else if (self.matches('#mr, #mr *') && !mr.classList.contains('--active-order')) {
                pr.classList.remove('--active-order');
                mr.classList.add('--active-order');
                orderByTimeCommenti = 'ASC';
                esciDaOperazione(false, true);
            } else {
                return;
            }
        } else if (self.matches('#el, #el *') && accessoSchedaLivello > 2) {
            let idPost = (gridEl.id).split('-');
            if (idPost.length !== 4) throw new Error("reading 'id'");
            idPost = idPost[3].toLowerCase();
            const eliminaBtn = document.querySelector(`#post-${idPost} .icon-tray .elimina`);
            if (eliminaBtn) eliminaBtn.click();
        } else {
            for (const [operazione, [selettore, livelloDiRif, sezInApertura, formDaCreare, opSuccessiva]] of Object.entries(Triggers.ottieni)) {
                if (self.matches(`${selettore}, ${selettore} *`) && accessoSchedaLivello > livelloDiRif && !gridEl.classList.contains(sezInApertura)) {
                    Object.entries(Triggers.formDaSvuotare).forEach(([form, selettore]) => { selettore.innerHTML = ""});
                    gridEl.classList.remove(...['--a', '--s', '--d', '--r'].filter(el => el !== sezInApertura));
                    gridEl.classList.add(sezInApertura);
                    let datiDaPassare = { operazione: operazione, id_progetto: subStrGrid[1], categoria: subStrGrid[2], uuid_scheda: subStrGrid[3]};
                    const jsonData = JSON.stringify(datiDaPassare);
                    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaPostsCRUD.bind(this, true));
                    break;
                }
            }
        }
    } catch (errore) {
        if (errore.message.includes("classList") || errore.message.includes("reading 'id'") || errore.message.includes("textContent")) errore.message = "Errore DOM: Conflitto rilevato nel Menu!"
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function checkCaratteri(e) {
    self = this;
    boxShadowRed = 'inset 0px 25.6px 57.6px rgba(0, 0, 0, 0.466), inset 0px 0px 10.2px rgba(233, 4, 111, 0.7)';
    boxShadowOrange = 'inset 0px 25.6px 57.6px rgba(0, 0, 0, 0.466), inset 0px 0px 10.2px rgba(233, 141, 4, 0.7)';
    boxShadowYellow = 'inset 0px 25.6px 57.6px rgba(0, 0, 0, 0.466), inset 0px 0px 10.2px rgba(233, 233, 4, 0.7)';
    boxShadowBlue = 'inset 0px 25.6px 57.6px rgba(0, 0, 0, 0.466), inset 0px 0px 10.2px rgba(4, 206, 233, 0.7)';
    const limiteMax = 4000;
    const testoCorrente = self.textContent || self.innerText;
    if (testoCorrente.length > limiteMax) { 
        this.textContent = testoCorrente.slice(0, 4000);
        if (self.parentElement.style.getPropertyValue('box-shadow') !== boxShadowRed) {
            self.parentElement.style.setProperty('box-shadow', boxShadowRed);
            setTimeout(() => { self.parentElement.style.setProperty('box-shadow', 'none');}, 2000);
        }
    } else if (testoCorrente.length > limiteMax * 0.975) {
        if (self.parentElement.style.getPropertyValue('box-shadow') !== boxShadowOrange) {
            self.parentElement.style.setProperty('box-shadow', boxShadowOrange);
            setTimeout(() => { self.parentElement.style.setProperty('box-shadow', 'none');}, 2000);
        }
    } else if (testoCorrente.length > limiteMax * 0.95) {
        if (self.parentElement.style.getPropertyValue('box-shadow') !== boxShadowYellow) {
            self.parentElement.style.setProperty('box-shadow', boxShadowYellow);
            setTimeout(() => { self.parentElement.style.setProperty('box-shadow', 'none');}, 2000);
        }
    } else {
        if (self.parentElement.style.getPropertyValue('box-shadow') !== boxShadowBlue) {
            self.parentElement.style.setProperty('box-shadow', boxShadowBlue);
            setTimeout(() => { self.parentElement.style.setProperty('box-shadow', 'none');}, 2000);
        }
    }

}


function gestisciCommento(self) { 
    let subStrReply = (self.closest('[id^="repl-"]').id).split('-');
    let subStrGrid = (self.closest('[id^="over-"]').id).replace(/_/g," ").split('-');
    let subStrOwn = (self.closest('[id^="repl-"]').querySelector('.utente')).textContent;
    if (subStrReply.length !== 2 || subStrGrid.length !== 4) throw new Error("reading 'id'");
    if (self.matches('.elimina-commento')) {
        Conferma.apri({
            titoloBox: "Eliminazione commento",
            messaggioBox: "Sei sicuro di voler eliminare questo commento?",
            testoOk: "Elimina",
            testoNo: "Annulla",
            allOk: function() {
                let datiDaPassare = { operazione: 'elimina-commento', id_progetto: subStrGrid[1], categoria: subStrGrid[2], uuid_scheda: subStrGrid[3], uuid_commento: subStrReply[1]};
                const jsonData = JSON.stringify(datiDaPassare);
                NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaPostsCRUD);
                console.log("Hai premuto elimina commento");
            }  
        });
    } else {
        let operazione = self.matches('.modifica-commento') ? 'modifica-commento' : 'rispondi-commento';
        let messaggioTarget = `${self.matches('.modifica-commento') ? 'Modifica' : 'Risposta'} al commento di: ${subStrOwn}`;
        let commentoTarget = self.matches('.modifica-commento') || self.matches('.rispondi-commento') ? self.closest('[id^="repl-"]').querySelector('.corpo-commento p') : false;
        let placeholder = self.matches('.modifica-commento') ? 'Modifica descrizione commento' : 'Rispondi al commento';
        idProgettoTarget.value = subStrGrid[1];
        categoriaTarget.value = subStrGrid[2];
        uuidSchedaTarget.value = subStrGrid[3];
        uuidCommentoTarget.value = subStrReply[1];
        submitButton.dataset.operazione = "";
        rispondiA(operazione, self, messaggioTarget, commentoTarget, placeholder);
    }
}

function rispondiA(operazione, selettore, messaggioTarget, commentoTarget, placeholder) {
    svuotaHash();
    if (gridEl.classList.contains('--a', '--s', '--d', '--r')) gridEl.classList.remove('--a', '--s', '--d', '--r');
    try {
        console.log(`Azione ${operazione} con elemento ${selettore.id} cliccata`);
        let conteggio = 0;
        visibilitaEl.forEach((elemento, indice) => {
            if (indice < 2) {
                if (elemento[1].style.visibility === "visible") conteggio +=1;
            } else {
                if (elemento[1].style.display === elemento[2]) conteggio +=1;
            }
            console.log(`ad indice ${indice} il conteggio è arrivato a ${conteggio}`)
        });
        if ((submitButton.dataset.operazione && operazione !== "aggiungi-commento") || gridEl.classList.contains('--a', '--s', '--d', '--r')){
            Notifica.appari({messaggioNotifica: "Prima di iniziare un'operazione bisogna chiudere quella corrente.", tipoNotifica: "special-attenzione-notifica"});
            return;
        } else if (conteggio === 0) {
            visibilitaEl.forEach((elemento, indice) => {
                console.log("il selettore:", selettore)
                if (indice < 2) {
                    elemento[1].style.visibility = "visible";
                } else {
                    console.log('la proprietà da impostare è,', elemento[2])
                    elemento[1].style.display = elemento[2];
                }
            });
            Triggers.inRisposta['a-focus'][0].textContent = messaggioTarget;
            Triggers.inRisposta['a-focus'][1].textContent = commentoTarget ? commentoTarget.textContent : "";
            textarea.setAttribute("placeholder", placeholder);
            submitButton.dataset.operazione = operazione;
            if (operazione !== 'aggiungi-commento') Triggers.operazioni['aggiungi-commento'][1].focus();
            if (operazione === 'modifica-descrizione' || operazione === 'modifica-commento') textarea.textContent = commentoTarget.innerText;
            Object.entries(Triggers.form).forEach(([chiave, [trigger, selettore, azione]]) => {
                const azioneBindata = azione.bind(this, chiave, selettore);
                eventHandlers.set(selettore, azioneBindata);
                // selettore.removeEventListener(trigger, azioneBindata);
                selettore.addEventListener(trigger, azioneBindata);
            });
        }
        // usiamo operazione come chiave per sapere quale azione é stata cliccata
    } catch (errore) {
        Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: "special-errore-notifica"});
    } 
}

function annullaRisposta(chiave, selettore) {
    Conferma.apri({
        titoloBox: "Operazione in corso",
        messaggioBox: "Sei sicuro di voler uscire?",
        testoOk: "Esci",
        testoNo: "Annulla",
        allOk: function() {
            esciDaOperazione();
            console.log("Hai premuto esci da operazione in corso");
        }  
    });
}

function esciDaOperazione(chiusuraOverlay, forzaRiavvio) {
    gridEl.classList.remove('--a','--s', '--d', '--r');
    submitButton.dataset.operazione = "";
    submitButton.disabled = true;
    Triggers.operazioni['aggiungi-commento'][1].setAttribute("placeholder", "Aggiungi un commento");
    Triggers.operazioni['aggiungi-commento'][1].textContent = "";
    Triggers.operazioni['aggiungi-commento'][1].blur();
    Triggers.inRisposta['a-focus'][0].textContent = "In risposta al";
    Triggers.inRisposta['a-focus'][1].innerHTML = "";
    
    Object.entries(Triggers.form).forEach(([chiave, [trigger, selettore, azione]]) => {
        const azioneBindata = eventHandlers.get(selettore);
        if (azioneBindata) {
            selettore.removeEventListener(trigger, azioneBindata);
            eventHandlers.delete(selettore);
        }
    });
    visibilitaEl.forEach((elemento, indice) => {
        let selettore = elemento[1];
        console.log("il selettore:", selettore)
        if (indice < 2) {
            selettore.style.visibility = "hidden";
        } else {
            console.log('la proprietá da impostare è,', elemento[0])
            selettore.style.display = elemento[0];
        }
    });
    document.getElementById("submit-reply").dataset.operazione = "";
    if (chiusuraOverlay === true) {
        let currentUrl = new URL(window.location.href);
        currentUrl.search = `proj=${boardId}`;
        currentUrl.hash = "";
        pushState(currentUrl);
    } else {
        svuotaHash(forzaRiavvio);
    }
}

function verificaRisposta() {
    
    textarea.blur();
    if (/^\s*$/.test(textarea.innerHTML)) {
        Notifica.appari({messaggioNotifica: "Il campo di testo non può essere vuoto.", tipoNotifica: "special-errore-notifica"});
    } else if (textarea.innerHTML.length > 4000) {
        Notifica.appari({messaggioNotifica: "Il campo di testo non può contenere più di 4000 caratteri.", tipoNotifica: "special-errore-notifica"});
    } else {
        Triggers.form['submit-reply'][1].disabled = false;
        if (!Triggers.form['submit-reply'][1].disabled) {
            Triggers.form['submit-reply'][1].click();
        }
    }
    // textarea.addEventListener('input', checkCaratteri);
    // let stringaVuota = /^\s*$/;
    // let contieneCaratteriVietati = /[<>\\]/;
}

function azionaFormRisposta() {
    
}

function stringiPost() {
    if (stringi.style.display !== "none") {
        stringi.style.display = "none";
        allarga.style.display = "inline-block";
        overlayPost.style.setProperty("max-height", "calc(25% - 0.5em)");
        corpoCommento.style.setProperty("overflow-y", "hidden");
    }
}

function allargaPost() {
    if (allarga.style.display !== "none") {
        allarga.style.display = "none";
        stringi.style.display = "inline-block";
        overlayPost.style.height = "auto";
        overlayPost.style.setProperty("max-height", "min-content");
        corpoCommento.style.setProperty("overflow-y", "auto");
    }
}
function collapseToggle(elementiDaCollapse) {
    elementiDaCollapse = elementiDaCollapse.querySelectorAll("details");
    if (elementiDaCollapse.length > 0) {
        elementiDaCollapse.forEach(toggle => {
            toggle.querySelector('.collapse').innerHTML = (toggle.open) ? '(Nascondi Commento)' : '(Mostra Commento)';
        });
    }
}

function mandaRisposta(chiave, selettore, event) {
    event.preventDefault();
    let descrizioneReply = sanitizzaInput(textarea.innerHTML);
    console.log(event.target);
    // catturo tutti i dati disponibili nel formData
    const formData = new FormData(event.target);
    formData.append(document.getElementById("submit-reply").name, document.getElementById("submit-reply").dataset.operazione);
    formData.append("descrizione", descrizioneReply);
    // formData.forEach(function(value, key){
    //     console.log(key, value);
    // });
    const formDataObj = {};
    // i dati che ho catturati li voglio in un formato JSON pertanto convertiamo prima tutto in un oggetto
    for (let coppia of formData.entries()) { //  Pertanto facciamo un'iterazione per ogni coppia ... 
        formDataObj[coppia[0]] = coppia[1]; //.. catturandone chiave [0] e valore [1], e salviamo tutto nel nostro oggetto
    }
    // Convertiamo l'oggetto JavaScript in una stringa JSON
    // ... mando una richiesta http
    const jsonData = JSON.stringify(formDataObj);
    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaPostsCRUD );
}

function rispostaPostsCRUD(isObtainMode) {
    let currentUrl = new URL(window.location.href);
    try {
        // console.log(xhr.responseText);
        let rispostaServer;
        if(!(rispostaServer = JSON.parse(xhr.responseText))) throw new Error(erroreGenerico);
        if(rispostaServer['messaggio'].includes('visualizzare')) {
            currentUrl.search = `proj=${rispostaServer['progetto_analizzato']}`;
            currentUrl.hash = "";
            pushState(currentUrl);
            // inseriremo qui il click del minimizza scheda
            throw new Error(rispostaServer['messaggio']);
        }
        if (rispostaServer['messaggio'].startsWith('Errore')) throw new Error(rispostaServer['messaggio']);
        if (rispostaServer['messaggio']) Notifica.appari({messaggioNotifica: rispostaServer['messaggio'], tipoNotifica: "special-successo-notifica"});
        if (isObtainMode !== true) {
            // questo vale se siamo in modalità di sola operazione con php e il db(default)
            esciDaOperazione();
            currentUrl.search = `proj=${rispostaServer['progetto_analizzato']}&post=${rispostaServer['post_analizzato']}`;
            pushState(currentUrl);
        } else {
            // altrimenti in modalità obtain dove, oltre ad operare con php e il db, andiamo ad ottenere gli elementi interessati
            for (const [operazione, [selettore, livelloDiRif, sezInApertura, sezDaRiempire, formDaCreare, opSuccessiva]] of Object.entries(Triggers.ottieni)) {
                
                if (operazione === rispostaServer['operazione']) {
                    obtainPer(rispostaServer['dati'], sezDaRiempire, formDaCreare, opSuccessiva);
                    break;
                }
            }
        }
        //qui copiamo il riferimento al post
    } catch (errore) {
        if (gridEl) gridEl.classList.remove('--a', '--s', '--d', '--r');
        Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function obtainPer(dati, sezDaRiempire, formDaCreare, opSuccessiva) {
    let elHTML = (opSuccessiva) ? `<form action="/services/posts.php" method="post" id="${formDaCreare}">` : `<div id="${formDaCreare}">`;
    if (formDaCreare === "--assign-form") {
        elHTML += `<h1>Incarica Membro</h1>
            <p>Assegna l'utente incaricato al progresso della scheda.</p>
            <div class="selezione-membri">
        `;
        dati.membri.forEach(membro => {
            let primaLetteraNome = membro['nome'].charAt(0).toUpperCase();
            let primaLetteraCognome = membro['cognome'].charAt(0).toUpperCase();
            let isLeader = membro['isLeader'];
            let genere = membro['genere'];
            let isSelected = (membro['email'] === dati['incaricato']) ? "--selected" : "";
            elHTML += `
            <div class="membro ${isSelected}" data-email="${membro['email']}" id="mail-${membro['email']}" leader="${isLeader}" genere="${genere}"><div class="icona-membro">${primaLetteraNome} ${primaLetteraCognome}</div><div class="id-membro"><h1>${membro['nome']} ${membro['cognome']}</h1><p>${membro['email']}</p></div></div>
            `;
        });
        let defaultDataInizio = (dati.inizio_mandato) ? dati.inizio_mandato.replace(/_/g,"T") : daOggi();
        let defaultDataFine = (dati.fine_mandato) ? dati.fine_mandato.replace(/_/g,"T") : daOggi(0, 0, 0, 0, 5, 0);
        isDisabled = (dati.incaricato) ? "" : "disabled";
        elHTML += `</div>
        <div class="--box-temporale">
            <div class="inizio-incarico">
                <label for="inizio-incarico">Data Inizio Incarico</label>
                <input type="datetime-local" step="any" id="inizio-incarico" name="inizio_incarico" value="${defaultDataInizio}" min="1970-01-01T00:00:00" valore="${defaultDataInizio}" required ${isDisabled}>
            </div>
            <div class="fine-incarico">
                <label for="fine-incarico">Data Fine Incarico</label>
                <input type="datetime-local" step="any" id="fine-incarico" name="fine_incarico" value="${defaultDataFine}" min="1970-01-01T00:00:00" valore="${defaultDataFine}" required ${isDisabled}>
            </div>
        </div><input type="hidden" name="membro_assegnato" value=${dati['incaricato']}>`;
    } else if (formDaCreare === "--shift-form") {
        elHTML += `<h1>Sposta scheda</h1>
            <p>Sposta la scheda ad un nuovo stato per evidenziare il suo progresso.</p>
            <div class="selezione-stati">
        `;
        dati.stati.forEach(stato => {
            elHTML += `
            <div class="stato ${stato.isSelezionato}" data-stato="${stato.stato}" style="border: 2px groove ${stato.colore_hex}"><p>${stato['stato']}</p></div>
            `;
        });
        elHTML += `</div>
        <input type="hidden" name="stato_assegnato" value=${dati['stato_attuale']}>`;
    } else if (formDaCreare === "--duration-form") {
        elHTML += `<h1>Programma Scheda</h1>
            <p>Programma quando è suggerito iniziare la scheda (Avvio), quando questa dovrebbe finire (Scadenza) e, se risulta completata, quando è terminata effettivamente finita(Chiusa).</p>
        `;
        let isChiusa = (dati.stato !== "Completate") ? "disabled" : "";
        let isChecked = (dati.scadenza) ? "checked" : "";
        let isDisabled = (!dati.scadenza) ? "disabled" : "";
        let defaultDataInizio = (dati.inizio) ? dati.inizio.replace(/_/g,"T") : daOggi();
        let defaultDataScadenza = (dati.scadenza) ? dati.scadenza.replace(/_/g,"T") : daOggi(0, 0, 0, 0, 5, 0);
        let defaultDataFine = (dati.fine) ? dati.fine.replace(/_/g,"T") : daOggi(0, 0, 0, 0, 5, 0);
        elHTML += `
        <div class="--box-temporale">
            <div class="inizio-scheda">
                <label for="inizio-scheda">Data di avvio</label>
                <input type="datetime-local" step="any" id="inizio-scheda" name="inizio_scheda" value="${defaultDataInizio}" min="1970-01-01T00:00:00" valore="${defaultDataInizio}" required>
            </div>
            <div class="scadenza-scheda">
                <label for="scadenza-scheda"><input type="checkbox" id="enableDate" style="accent-color: rgb(72, 39, 115)" ${isChecked}> Data di Scadenza</label>
                <input type="datetime-local" step="any" id="scadenza-scheda" name="scadenza_scheda" value="${defaultDataScadenza}" min="1970-01-01T00:00:00" valore="${defaultDataScadenza}" required ${isDisabled}>
            </div>
            <div class="fine-scheda">
                <label for="fine-scheda">Data Completamento</label>
                <input type="datetime-local" step="any" id="fine-scheda" name="fine_scheda" value="${defaultDataFine}" min="1970-01-01T00:00:00" valore="${defaultDataFine}" required ${isChiusa}>
            </div>
        </div>`;
    } else if (formDaCreare === "--report-container") {
        elHTML += `<h1>Report - Resoconto Attività</h1>
        <p>Reseconto delle ultime 50 attività che riguardano la scheda.</p>
        <div class="selezione-report">
        `;   
        dati.reports.forEach(report => { 
            let resoconto;
            if (!report["attore"]) report["attore"] = "<del>" + report["attore_era"] + "</del>";
            if (!report["bersaglio"]) report["bersaglio"] = "<del>" + report["bersaglio_era"].split("-")[0] +"<del>";
            let colore = (report["colore_hex"]) ? report["colore_hex"].slice(0, -2) + "4d" : "#ffffff4d";
            if (report.descrizione === "Creazione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai creato la scheda` : `${report["attore"]} ha creato la scheda`;
            } else if (report.descrizione === "Archiviazione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai archiviato la scheda` : `${report["attore"]} ha archiviato la scheda`;
            } else if (report.descrizione === "Cambiamento Stato") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai cambiato lo stato della scheda in "${report["stato"]}"` : `${report["attore"]} ha cambiato lo stato della scheda in "${report["stato"]}"`;
            } else if (report.descrizione === "Aggiunta Descrizione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai aggiunto una descrizione alla scheda` : `${report["attore"]} ha aggiunto una descrizione alla scheda`; 
            } else if (report.descrizione === "Modifica Descrizione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai modificato la descrizione della scheda` : `${report["attore"]} ha modificato la descrizione della scheda`;
            } else if (report.descrizione === "Creazione Commento") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai aggiunto un commento alla scheda` : `${report["attore"]} ha aggiunto un commento alla scheda`;
            } else if (report.descrizione === "Modifica Commento") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai modificato un commento della scheda` : `${report["attore"]} ha modificato un commento della scheda`;
            } else if (report.descrizione === "Risposta Commento") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai risposto ad un commento di ${report["bersaglio"]}` : `${report["attore"]} ha risposto ad un commento di ${report["bersaglio"]}`;
            } else if (report.descrizione === "Eliminazione Commento") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai eliminato un commento di ${report["bersaglio"]}` : `${report["attore"]} ha eliminato un commento di ${report["bersaglio"]}`;
            } else if (report.descrizione === "Revocazione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai revocato l'incarico della scheda a ${report["bersaglio"]}` : `${report["attore"]} ha revocato l'incarico della scheda a ${report["bersaglio"]}`;
            } else if (report.descrizione === "Assegnazione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai assegnato l'incarico della scheda a ${report["bersaglio"]}` : `${report["attore"]} ha assegnato l'incarico della scheda a ${report["bersaglio"]}`;
            } else if (report.descrizione === "Riassegnazione Scheda") {
                resoconto = (dati["mia_email"] === report["attore"]) ? `Hai riassegnato l'incarico della scheda a ${report["bersaglio"]}` : `${report["attore"]} ha riassegnato l'incarico della scheda a ${report["bersaglio"]}`;
            } else return;
            elHTML += `
            <div class="report-scheda" data-uuid="${report["uuid-report"]}" data-href="${report.link}" style="background-color: ${colore}"><h6>${formattaDataCon(true, report.timestamp)}</h6><h2>${resoconto}</h2></div>
            `;
        });
        elHTML += `</div>`;
    }
    elHTML += `
        <div class="--box-conferma">
            <button type="button" class="--esci-form">Esci</button>
            ${(opSuccessiva) ? `<div class="vertical-rule"></div><button type="button" class="--conferma-form">Conferma</button>` : ""}
        </div>
        ${(opSuccessiva) ? `<input type="submit" name="submit_form" class="submit-form" style="visibility: hidden; position: absolute;" disabled></form>` : "</div>"}
    `
    const template = document.createElement('template');
    template.innerHTML = elHTML;
    sezDaRiempire.appendChild(template.content);
    let form = document.querySelector(`#${formDaCreare}`);
    if (formDaCreare === "--assign-form") {
        form.querySelector('.selezione-membri').addEventListener('click', selezionaMembro);
    } else if (formDaCreare === "--shift-form") {
        form.querySelector('.selezione-stati').addEventListener('click', selezionaStato);
    } else if (formDaCreare === "--duration-form") {
        form.querySelector('#enableDate').addEventListener('click', abilitaScadenza);
    } else if (formDaCreare === "--report-container") {
        form.querySelector('.selezione-report').addEventListener('click', reindirizzaDaReport);
    }
    
    form.querySelector('.--esci-form').addEventListener('click', esciDaOperazione.bind(null, false, true));
    if (opSuccessiva) form.querySelector('.--conferma-form').addEventListener('click', confermaOperazione.bind(this, formDaCreare, opSuccessiva));
}

function selezionaMembro(e) {
    let self = e.target;
    let membri = Triggers.formDaSvuotare['assignForm'].querySelectorAll('.membro');
    let emailMembro = document.querySelector('[name="membro_assegnato"]');
    let dataInizio = Triggers.formDaSvuotare['assignForm'].querySelector('[name="inizio_incarico"]');
    let dataFine = Triggers.formDaSvuotare['assignForm'].querySelector('[name="fine_incarico"]');
    if (self.matches('.membro, .membro *')) {
        self = self.closest('.membro');
        let check = (self.classList.contains('--selected'));
        membri.forEach(membro => membro.classList.remove('--selected'));
        if (check) {
            self.classList.remove('--selected');
            [dataInizio, dataFine].forEach(input => input.disabled = true);
            emailMembro.value = "";
        } else {
            self.classList.add('--selected');
            [dataInizio, dataFine].forEach(input => input.disabled = false);
            emailMembro.value = self.getAttribute('data-email');
        }
    } else return;
}

function selezionaStato(e) {
    let self = e.target;
    let stati = Triggers.formDaSvuotare['shiftForm'].querySelectorAll('.stato');
    let stato_assegnato = document.querySelector('[name="stato_assegnato"]');
    if (self.matches('.stato, .stato *')) {
        self = self.closest('.stato');
        let check = (self.classList.contains('--selected'));
        stati.forEach(stato => stato.classList.remove('--selected'));
        if (check) {
            self.classList.remove('--selected');
            stato_assegnato.value = "";
        } else {
            self.classList.add('--selected');
            stato_assegnato.value = self.getAttribute('data-stato');
        }
    }
}

function abilitaScadenza() {
    let scadenza = document.querySelector('#scadenza-scheda');
    let check = (this.checked);
    scadenza.disabled = (!check) ? true : false;
}

function reindirizzaDaReport(e) {
    let self = e.target;
    if (self.matches('.report-scheda, .report-scheda *')) {
        link = (self.closest('.report-scheda').dataset.href);
        window.location.href = `${link}`;
    }
}

function confermaOperazione(tipo, opSuccessiva, e) {
    try {
        let submitButton2 = document.querySelector('.submit-form');
        if (tipo === "--assign-form" || tipo === "--shift-form" || tipo === "--duration-form") {
            document.querySelector(`#${tipo}`).addEventListener('submit', sendForm.bind(this, opSuccessiva));
            let risultato = opSuccessivaCheck(opSuccessiva);
            if (!risultato.isValido) throw new Error(risultato.errore);
        } else {
            esciDaOperazione(false, true);
            throw new Error("Operazione non riconosciuta");
        }
        submitButton2.disabled = false;
        if (!submitButton2.disabled) {
            submitButton2.click();
            submitButton2.disabled = true;
        }
    } catch (errore) {
            errore.message = errore.message.includes("Ricorda") ? errore.message : "Errore DOM: " + errore.message;
            let tipo = errore.message.includes("Ricorda") ? "special-attenzione-notifica" : "special-errore-notifica";
            console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
            Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: tipo});
    }
}

function opSuccessivaCheck(opSuccessiva) {
    if (opSuccessiva === "assegna-membro") {
        const emailMembro = document.querySelector('[name="membro_assegnato"]').value;
        let emailRegExp = /^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/;
        const dataInizio = document.querySelector('[name="inizio_incarico"]');
        const dataFine = document.querySelector('[name="fine_incarico"]');
        if (emailMembro) {
            if (!emailRegExp.test(emailMembro)) return {isValido: false, errore: "Si è verificato un errore nel recupero di un'email valida!"};
            let diff_ms = new Date(dataFine.value) - new Date(dataInizio.value); // Differenza in millisecondi
            // Verifichiamo che le date distino almeno di un minuto (in millisecondi)
            if (diff_ms < (60 * 1000) || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(dataFine.value) || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(dataInizio.value)) return {isValido: false, errore: "Ricorda di rispettare i seguenti criteri:<br>-La data di fine incarico deve essere successiva a quella di inizio di almeno 1 minuto, ed entrambe devono essere valide"};
        }
        return {isValido: true}
    } else if (opSuccessiva === "cambia-stato") {
        let stato_assegnato = document.querySelector('[name="stato_assegnato"]').value;
        if (stato_assegnato && /^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/.test(stato_assegnato)) return {isValido: true};
        return {isValido: false, errore: "Ricorda di scegliere uno stato valido!"};
    } else if (opSuccessiva === "imposta-durata") {
        let dataInizio = document.querySelector('[name="inizio_scheda"]');
        let dataScadenza = document.querySelector('[name="scadenza_scheda"]');
        let dataFine = document.querySelector('[name="fine_scheda"]');
        if (!dataScadenza.disabled) {
            let diff_ms = new Date(dataScadenza.value) - new Date(dataInizio.value);
            if (diff_ms < (60 * 1000) || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(dataScadenza.value)) return {isValido: false, errore: "Ricorda di rispettare i seguenti criteri:<br>-La data di avvio, scadenza e completamento devono essere valide<br>-La data di scadenza e completamento, se abilitate, devono essere di almeno 1 minuto successive a quella di avvio"};
        }
        if (!dataFine.disabled) {
            let diff_ms = new Date(dataFine.value) - new Date(dataInizio.value);
            if (diff_ms < (60 * 1000) || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(dataFine.value)) return {isValido: false, errore: "Ricorda di rispettare i seguenti criteri:<br>-La data di avvio, scadenza e completamento devono essere valide<br>-La data di scadenza e completamento, se abilitate, devono essere di almeno 1 minuto successive a quella di avvio"};
        }
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(dataInizio.value)) return {isValido: false, errore: "Ricorda di rispettare i seguenti criteri:<br>-La data di avvio, scadenza e completamento devono essere valide<br>-La data di scadenza e completamento, se abilitate, devono essere di almeno 1 minuto successive a quella di avvio"};
        return {isValido: true};     
    } else return {isValido: false, errore: "Operazione non riconosciuta"};
}

function sendForm(operazione, event) {
    event.preventDefault();
    // console.log(event.target);
    let subStrGrid = (gridEl.id).replace(/_/g," ").split('-');
    if (subStrGrid.length !== 4) throw new Error("reading 'id'");
    // catturo tutti i dati disponibili nel formData
    const formData = new FormData(event.target);
    formData.append("operazione", operazione);
    formData.append("id_progetto", subStrGrid[1]);
    formData.append("categoria", subStrGrid[2]);
    formData.append("uuid_scheda", subStrGrid[3]);
    // formData.forEach(function(value, key){
    //     console.log(key, value);
    // });
    const formDataObj = {};
    // i dati che ho catturati li voglio in un formato JSON pertanto convertiamo prima tutto in un oggetto
    for (let coppia of formData.entries()) { //  Pertanto facciamo un'iterazione per ogni coppia ... 
        formDataObj[coppia[0]] = coppia[1]; //.. catturandone chiave [0] e valore [1], e salviamo tutto nel nostro oggetto
    }
    // Convertiamo l'oggetto JavaScript in una stringa JSON
    // ... mando una richiesta http
    const jsonData = JSON.stringify(formDataObj);
    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaPostsCRUD );
}

function sanitizzaInput(testoInput) {
    const tempEl = document.createElement('div');
    tempEl.textContent = testoInput;
    return tempEl.innerHTML;
}

function daOggi(incrementaGiorni = 0, incrementaMesi = 0, incrementaAnni = 0, incrementaOre = 0, incrementaMinuti = 0, incrementaSecondi = 0) {
    let today = new Date();
    
    // Per incrementare la data e l'ora corrente
    today.setSeconds(today.getSeconds() + incrementaSecondi);
    today.setMinutes(today.getMinutes() + incrementaMinuti);
    today.setHours(today.getHours() + incrementaOre);
    today.setDate(today.getDate() + incrementaGiorni);
    today.setMonth(today.getMonth() + incrementaMesi);
    today.setFullYear(today.getFullYear() + incrementaAnni);
    
    let dd = String(today.getDate()).padStart(2, '0');
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    let yyyy = today.getFullYear();
    let hh = String(today.getHours()).padStart(2, '0');
    let min = String(today.getMinutes()).padStart(2, '0');
    let ss = String(today.getSeconds()).padStart(2, '0');
    
    let todayString = `${yyyy}-${mm}-${dd}T${hh}:${min}:${ss}`;
    // console.log(todayString);
    return todayString;
}

function isEsistenteEdIstanzaDi(variabile, istanzaDi) {
    return variabile ? variabile instanceof istanzaDi : false;
}

function goTo() {
    let hash = location.hash;
    hash = hash.substring(6);
    const getElemento = document.getElementById(`repl-${hash}`);
    if (getElemento) {
        // console.log(getElemento);
        getElemento.scrollIntoView({behavior: "smooth", block: "start", inline: "nearest"});
        // mettiamo temporaneamente in focus il commento
        getElemento.classList.add("--attenzione");
        setTimeout(() => getElemento.classList.remove("--attenzione"), 3000);
    }
}

function svuotaHash(forceRestart) {
    let currentUrl = new URL(window.location.href);
    currentUrl.hash = "";
    history.pushState(null, '', currentUrl);
    if (forceRestart === true) pushState(currentUrl);
}