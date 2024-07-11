
const dettagli = document.querySelectorAll('.comment');
let accessoSchedaLivello = 0;
let orderByTimeCommenti = "ASC";

function aggiornoEventi() {
        const aggiornaBoard = document.querySelector('.board');
        if (eventMaxiHandlers.has(aggiornaBoard)) {
            const handlers = eventMaxiHandlers.get(aggiornaBoard);
            aggiornaBoard.removeEventListener('mouseover', handlers.mouseover);
            aggiornaBoard.removeEventListener('click', handlers.click);
        }
        const handlers = {
            mouseover: columnElHover.bind(aggiornaBoard),
            // mouseleave: columnElLeave.bind(ascoltatore),
            click: columnElClick.bind(aggiornaBoard),
        }
        eventMaxiHandlers.set(aggiornaBoard, handlers);
        aggiornaBoard.addEventListener('mouseover', handlers.mouseover);
        aggiornaBoard.addEventListener('click', handlers.click);

}

function columnElHover(event) {
    let self = event.target;
    if (self.matches('.titolo, .descrizione')) {
        self.classList.contains('titolo') ? isOverXFlow(self) : isOverYFlow(self);
    }
}

function columnElClick(event) {
    // console.log('click');
    let self = event.target;
    if (self.matches('.elimina-categoria')) {
        operazioneCategoria(self, 'elimina_categoria', 'Eliminazione Categoria', 'Saranno eliminate definitivamente tutte le Schede al suo interno. Sei sicuro/a di voler eliminare', 'Elimina', "\"Eliminazione\" non operabile. Conflitto rilevato nella categoria!");
    } else if (self.matches('.mostra')) {
        operazioneCategoria(self, 'mostra_categoria', 'Mostra Categoria', 'La Categoria sarà visualizzabile a qualsiasi utente? Sei sicuro/a di voler mostrare', 'Mostra', "\"Mostra categoria\" non operabile. Conflitto rilevato nella categoria!");
    } else if (self.matches('.nascondi')) {
        operazioneCategoria(self, 'nascondi_categoria', 'Nascondi Categoria', 'La Categoria sarà oscurata a qualsiasi semplice utente? Sei sicuro/a di voler nascondere', 'Nascondi', "\"Nascondi pagina\" non operabile. Conflitto rilevato nella categoria!");
    } else if (self.matches('.sinistra')) {
        interazioneCategorie(self, 'sposta_categoria_sinistra', 'Sposta Categoria a Sinistra', 'Sei sicuro/a di voler ordinare la Categoria', 'Sposta', "\"Sposta a sinistra\" non operabile. Conflitto rilevato nella selezione delle categorie!");
    } else if (self.matches('.destra')) {
        interazioneCategorie(self, 'sposta_categoria_destra', 'Sposta Categoria a Destra', 'Sei sicuro/a di voler ordinare la Categoria ', 'Sposta', "\"Sposta a destra\" non operabile. Conflitto rilevato nella selezione delle categorie!");
    } else if (self.matches('.elimina')) {
        eliminaScheda(self);
    } else if (self.matches('.su, .giu')) {
        spostaScheda(self, self.matches('.su') ? 'su' : 'giu');
    } else if (self.matches('.massimizza')) {
        massimizzaScheda(self);
    } else return;
}

function validaStringheDiProj(projString, erroreGenerico) {
    projString = projString.replace(/_/g, " ");
    if (!projString.startsWith("type-")) throw new Error(erroreGenerico);
    const subStrings = projString.split('-');
    if (!(subStrings.length === 3 && typeof subStrings[1] === 'string' && typeof subStrings[2] === 'string')) throw new Error(erroreGenerico);
    return subStrings;
}

function validaStringheDiPost(erroreGenerico) {

}

function aperturaBoxConferma(titoloBox, messaggioBox, testoOk, allOk) {
    Conferma.apri({
        titoloBox: titoloBox,
        messaggioBox: messaggioBox,
        testoOk: testoOk,
        testoNo: "Annulla",
        allOk: allOk
    })
}

function mandaRichiestaProj(operazione, subStrings, subStrings2 = null) {
    const jsonData = subStrings2 ? 
        JSON.stringify({ operazione: operazione, id_progetto: subStrings[1], categoria: subStrings[2], categoriaTarget: subStrings2[2] }) :
        JSON.stringify({ operazione: operazione, id_progetto: subStrings[1], categoria: subStrings[2] });
    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/board_crud.php", true, 'Content-Type', 'application/json', jsonData, rispostaBoardCRUD);
}

function operazioneCategoria(self, operazione, titoloBox, subMsgBox, testoOk, erroreGenerico) {
    try {
        const subStrings = validaStringheDiProj(self.closest('[id^="type-"]').id, erroreGenerico);
        const messaggioBox = `${subMsgBox} "${subStrings[2]}?"`;
        aperturaBoxConferma(titoloBox, messaggioBox, testoOk, () => {
            mandaRichiestaProj(operazione, subStrings);
            console.log(`Hai premuto ${testoOk} Categoria`);
        });
    } catch (errore) {
        console.trace(`Errore DOM: ${errore.name}\nMessaggio: ${errore.message}\nStack: ${errore.stack}`);
        Notifica.appari({ messaggioNotifica: `Errore DOM: ${errore.message}`, tipoNotifica: "special-errore-notifica" });
    }
}

function interazioneCategorie(self, operazione, titoloBox, subMsgBox, testoOk, erroreGenerico) {
    try {
        const subStrings = validaStringheDiProj(self.closest('[id^="type-"]').id, erroreGenerico);
        const target = self.closest('[id^="type-"]')[operazione === 'sposta_categoria_sinistra' ? 'previousElementSibling' : 'nextElementSibling'];
        if (!target || !target.classList.contains('column')) throw new Error(erroreGenerico);
        const subStrings2 = validaStringheDiProj(target.id, erroreGenerico);
        const messaggioBox = `${subMsgBox} "${subStrings[2]}" subito ${operazione === 'sposta_categoria_sinistra' ? 'prima' : 'dopo'} di "${subStrings2[2]}"?`;
        aperturaBoxConferma(titoloBox, messaggioBox, testoOk, () => {
            mandaRichiestaProj(operazione, subStrings, subStrings2);
            console.log(`Hai premuto ${testoOk} Categoria`);
        })
    } catch (errore) {
        if (errore.message.includes('classList')) errore.message = erroreGenerico;
        console.trace(`Errore DOM: ${errore.name}\nMessaggio: ${errore.message}\nStack: ${errore.stack}`);
        Notifica.appari({ messaggioNotifica: `Errore DOM: ${errore.message}`, tipoNotifica: "special-errore-notifica" });
    }
}


// eliminazione scheda
function eliminaScheda(self) {
    const erroreGenerico = "\"Eliminazione\" non operabile. Conflitto rilevato nella categoria o nella scheda!";
    try {
        self = self.closest('[id^="post-"]');
        let postString = self.id;
        let titoloScheda = self.querySelector("p.titolo").innerText;
        let projString = self.closest('[id^="type-"]').id;
        projString = projString.replace(/_/g, " ");
        if (projString.startsWith("type-") === false || postString.startsWith("post-") === false) throw new Error(erroreGenerico);
        subPostStrings = postString.split('-');
        subProjStrings = projString.split('-');
        // console.log("questi", subPostStrings, subProjStrings);
        if ((subPostStrings.length === 2 && /^[0-9A-Fa-f]+$/.test(subPostStrings[1])) === false || (subProjStrings.length === 3 && typeof subProjStrings[1] === 'string' && typeof subProjStrings[2] === 'string') === false) throw new Error(erroreGenerico);
        const jsonData = JSON.stringify({operazione: "elimina_scheda", id_progetto: subProjStrings[1], categoria: subProjStrings[2], uuid_scheda: subPostStrings[1]});
        const messaggio = (/^eliminate$/i.test(subProjStrings[2])) ? "eliminare definitivamente" : "archiviare la scheda, in modo che sia visibile solo a te e ai tuoi diretti superiori,";
        const azione = (/^eliminate$/i.test(subProjStrings[2])) ? "Eliminazione definitiva" : "Archiviazione";
        const testoOk = (/^eliminate$/i.test(subProjStrings[2])) ? "Elimina" : "Archivia";
        Conferma.apri({
            titoloBox: `${azione} Scheda Attività`,
            messaggioBox: `Sei sicuro/a di voler ${messaggio} la seguente scheda "${titoloScheda}"?`,
            testoOk: `${testoOk}`,
            testoNo: "Annulla",
            allOk: function() {
                NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaBoardCRUD);
                minimizzaScheda();
                console.log("Hai premuto Elimina Scheda Attività");
            },  
        });  
    } catch (errore) {
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore DOM: " + errore.message, tipoNotifica: "special-errore-notifica"});
    }
    
}

function spostaScheda(self, direzione) {
    const erroreGenerico = `"Sposta ${direzione} non operabile. Conflitto rilevato nella categoria o nella selezione delle schede`;
    try {
        self = self.closest('[id^="post-"]');
        let postString = self.id;
        let titoloScheda = self.querySelector("p.titolo").innerText;
        let projString = self.closest('[id^="type-"]').id;
        let target = (direzione === 'su') ? 
            ((self.previousElementSibling.classList.contains('post-it')) ? self.previousElementSibling : false) : 
            ((self.nextElementSibling.classList.contains('post-it')) ? self.nextElementSibling : false); 
        if (!target || !projString.startsWith("type-")) throw new Error(erroreGenerico);
        let titoloSchedaTarget = target.querySelector("p.titolo").innerText;
        target = target.id;
        projString = projString.replace(/_/g, " ");
        subProjStrings = projString.split('-');
        subPostStrings1 = postString.split('-');
        subPostStrings2 = target.split('-');
        if ((subPostStrings1.length === 2 && /^[0-9A-Fa-f]+$/.test(subPostStrings1[1])) === false || (subPostStrings1.length === 2 && /^[0-9A-Fa-f]+$/.test(subPostStrings1[1])) === false || (subProjStrings.length === 3 && typeof subProjStrings[1] === 'string' && typeof subProjStrings[2] === 'string') === false) throw new Error(erroreGenerico);
        const jsonData = JSON.stringify({operazione: `sposta_scheda_${direzione}`, id_progetto: subProjStrings[1], categoria: subProjStrings[2] , uuid_scheda: subPostStrings1[1], uuid_scheda_target: subPostStrings2[1]});
        Conferma.apri({
            titoloBox: `"Sposta Scheda ${direzione === 'su' ? "sopra" : "sotto"}"`,
            messaggioBox: `Sei sicuro/a di voler ordinare la Scheda "${titoloScheda}" subito ${direzione === 'su' ? "prima" : "dopo"} di "${titoloSchedaTarget}"?`,
            testoOk: "Sposta",
            testoNo: "Annulla",
            allOk: function() {
                NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaBoardCRUD);
                console.log(`Hai premuto Sposta Scheda ${direzione}`);
            }
        });
    } catch (errore) {
        if (errore.message.includes('classList')) errore.message = erroreGenerico;
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore DOM: " + errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function massimizzaScheda(self){
    const erroreGenerico = "\"Massimizza\" non operabile. Conflitto rilevato nella categoria o nella scheda!";
    try {
        let selfID = self.closest('[id^="post-"]').id;
        let subPostStrings = selfID.split('-');
        let projID = self.closest('[id^="type-"]').id;
        projID = projID.replace(/_/g, ' ');
        let subProjStrings = projID.split('-');
        if ((subPostStrings.length === 2 && /^[0-9A-Fa-f]{32}$/.test(subPostStrings[1])) === false || (subProjStrings.length === 3 && typeof subProjStrings[1] === 'string' && typeof subProjStrings[2] === 'string') === false) throw new Error(erroreGenerico);
        const jsonData = JSON.stringify({operazione: "massimizza_scheda", id_progetto: subProjStrings[1], categoria: subProjStrings[2], uuid_scheda: subPostStrings[1], order_by : orderByTimeCommenti});
        let currentUrl = new URL(window.location.href);
        currentUrl.search = `proj=${subProjStrings[1]}&post=${subPostStrings[1]}`;
        window.history.pushState({}, '', currentUrl);
        NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/posts.php", true, 'Content-Type', 'application/json', jsonData, rispostaPosts);
    } catch (errore) {
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore DOM: " + errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function rispostaPosts() {
    try {
        console.log(xhr.responseText);
        let rispostaServer;
        if(!(rispostaServer = JSON.parse(xhr.responseText))) throw new Error(erroreGenerico);
        if(rispostaServer['messaggio'].startsWith('Errore')) throw new Error(rispostaServer['messaggio']);
        const overlayContainer = document.querySelector('.overlay-container');
        aggiornoScheda(rispostaServer['dati'], rispostaServer['accessoLivello']);
        if (overlayContainer.style.display !== 'flex') {
            overlayContainer.classList.add("--opening");
            overlayContainer.style.display = 'flex';
            if (eventMaxiHandlers.has(overlayContainer)) {
                const handlers = eventMaxiHandlers.get(overlayContainer);
                overlayContainer.removeEventListener('animationend', handlers.openEnd);
            }
            const handlers = {
                openEnd: waitOpening.bind(overlayContainer),
            }
            eventMaxiHandlers.set(overlayContainer, handlers);
            overlayContainer.addEventListener('animationend', handlers.openEnd);
            lockExtScroll();
            
        }
        aggiornaCommentiThread();
        
        // aggiornoIcone();
        //qui copiamo il riferimento al post
    } catch (errore) {
        console.trace("Errore" + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function waitOpening(event) {
    const overlayContainer = this;
    overlayContainer.removeEventListener('animationend', eventMaxiHandlers.get(overlayContainer).openEnd);
    overlayContainer.classList.remove('--opening');
}








