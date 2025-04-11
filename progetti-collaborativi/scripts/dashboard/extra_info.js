let urlParams;
let fratelli = main.children;
let overlayZoom = document.querySelector('.overlay-window');
let overlayContent = document.querySelector('.overlay-content');

let titoloOverlay = document.querySelector(".overlay-inner .titolo-header");
let breadcumbSection = document.querySelector(".bc-din");
let breadcumbBack = document.querySelector(".--bc-click");

let dashFromMenu = document.getElementById('dashboard');
let utentiSubFromMenu = document.getElementById('utentiSub');
let utentiOnFromMenu = document.getElementById('utentiOn');
let reportOpenFromMenu = document.getElementById('reportOpen');
let homeFromMenu = document.getElementById('home');

let numeroPagine = 1;
let paginaCorrente = 1;
let noanimazione = false;
let tempDataDaDB = [];
let orderBy = 'DESC';
let filters = [];

const mappaPulsanti = {
    utentiSub: {
        bottone: document.querySelector('div.tile.utenti-iscritti'),
        stringa: 'utenti-iscritti'
    },
    utentiOn: {
        bottone: document.querySelector('div.tile.ultimi-accessi'),
        stringa: 'ultimi-accessi'
    },
    reportOpen: {
        bottone: document.querySelector('div.tile.report-azioni'),
        stringa:'resoconto'
    }
}

// assegno eventi ai pulsanti del menu
dashFromMenu.addEventListener('click', rimuoviParametri);
homeFromMenu.addEventListener('click', () => {
    // naviga alla pagina principale
    window.location.href = './home.html';
});
for (const chiave in mappaPulsanti) {
    if (mappaPulsanti.hasOwnProperty(chiave)) {
        mappaPulsanti[chiave].bottone.addEventListener('click', mostraContenutoOverlay.bind(mappaPulsanti[chiave].bottone, chiave));
        document.getElementById(chiave).addEventListener('click', () => mappaPulsanti[chiave].bottone.click())
    }
}

function richiestaEstrazioneInfoExtra(parametro, page, sortBy = 'DESC') {
    // http://tuo-sito.com/pagina?resoconto=1&ordina=ASC&filtri[sessione]=1&filtri[utente]=1&filtri[team]=1

    let queryString = `services/dashboard_extrainfo.php?${parametro}&pagina=${page}&ordina=${sortBy}`;
    filters.forEach((filtro, indice) => {
        queryString += `&filtri[${encodeURIComponent(filtro)}]=attivo`;
    });
    NuovaRichiestaHttpXML.mandaRichiesta('GET', queryString, true, 'Accept', 'application/json', '', estrazioneInfoExtra);
}

function estrazioneInfoExtra() {
    // console.log('========',xhr.responseText);
    const rispostaServer = JSON.parse(xhr.responseText);
    // renderData(rispostaServer.info);
    // console.log('questo é il:', rispostaServer.data);
    numeroPagine = rispostaServer.numeroPagine;
    paginaCorrente = rispostaServer.paginaCorrente;
    // console.log('questo é invece il numero di pagine totali:', rispostaServer.numeroPagine);
    // console.log('infine questo é il numero della pagina corrente:', rispostaServer.paginaCorrente);
    // console.log('Richiesta completata con successo');
    // Creo una variabile per memorizzare il messaggio di sistema
    const sm = rispostaServer.messaggio;
    // Estraiamo i valori dell'oggetto data
    const values = Object.values(rispostaServer.data);
    // Verificiamo se tutti i valori nell'array sono vuoti
    // const isEmpty = values.every(value => Array.isArray(value) && value.length === 0);
    if (sm.includes('Errore') || sm.includes('negato')) {
        Notifica.appari({messaggioNotifica: sm, tipoNotifica: 'special-errore-notifica'})
    }
    if (!rispostaServer.isAdmin) {
        window.location.href = 'portal.html';
    } else {
        tempDataDaDB = rispostaServer.data;
    } 
}

function renderizzaPaginazione(pagineTotali, paginaCorrente) {
    const controls = document.querySelector('.impaginazione');
    controls.innerHTML = '';
    // creiamo funzione per aggiunta dei bottoni 
    const creaBtn = (text, page, disabled = false) => {
        if (!controls.querySelector(`button[data-page="${page}"]`)) {
            const button = document.createElement('button');
            button.classList.add('--pagBtn');
            button.textContent = text;
            button.disabled = disabled;
            // utilizzato l'api 'dataset' che funziona con qualsiasi attributo che inventiamo che inizia con "data-"
            button.dataset.page = page;
            if (!disabled) {
                button.addEventListener('click', () => aggiornaPagina(page));
            }
            controls.appendChild(button);
        }
    };
    // creiamo funzione per aggiunta dei punti sospensivi
    const createDots = () => {
        const dots = document.createElement('span');
        dots.textContent = '...';
        controls.appendChild(dots);
    };

    if (pagineTotali > 4) {
        // Casella di testo per l'inserimento manuale del numero di pagina
        const inputBox = document.createElement('input');
        inputBox.type = 'text';
        inputBox.inputMode = 'numeric';
        inputBox.pattern = '[0-9]*';
        inputBox.placeholder = 'N';
        inputBox.style.cssText = 'width: 50px;';
        //gestiamo cosa succede quando digitiamo nella casella di testo
        inputBox.oninput = function () {
            // innanzitutto eliminiamo tutto ció che non é un numero
            this.value = this.value.replace(/[^0-9]/g, '');
            if (parseInt(this.value) > pagineTotali) {
                // se il valore (convertito in intero) é maggiore delle pagine totali allora vado all'ultima pagina
                this.value = pagineTotali;
            } else if (parseInt(this.value) < 1) {
                // altrimenti se é minore della prima pagina vado alla prima pagina
                this.value = 1;
            }
        };

        // creo il bottone di conferma, e con un listener mi assicuro che sia inviato soltanto un valore compreso tra 1 e il numero di pagine totali
        const inputButton = document.createElement('button');
        inputButton.textContent = 'Vai';
        inputButton.classList.add('--pagBtn');
        inputButton.addEventListener('click', () => {
            const pageNumber = parseInt(inputBox.value);
            if (pageNumber >= 1 && pageNumber <= pagineTotali) {
                aggiornaPagina(pageNumber);
            } else {
                inputBox.value = '';
            }
        });
        // Aggiungiamo la casella di testo e il pulsante di conferma all'interno della casella di paginazione
        controls.appendChild(inputBox);
        controls.appendChild(inputButton);
        // Pulsante per la prima pagina
        if (paginaCorrente > 2) creaBtn('1', 1);
        // Se la pagina corrente è la quarta o successiva, aggiungiamo "..."
        if (paginaCorrente > 3) createDots();
        // Pulsante per la pagina precedente a quella corrente (se non è la prima)
        if (paginaCorrente > 1) creaBtn(paginaCorrente - 1, paginaCorrente - 1);
        // Pulsante per la pagina corrente
        creaBtn(paginaCorrente, paginaCorrente, true);
        // Pulsante per la pagina successiva a quella corrente (se non è l'ultima)
        if (paginaCorrente < pagineTotali) creaBtn(paginaCorrente + 1, paginaCorrente + 1);
        // Se la pagina corrente è una delle penultime due, aggiungiamo "..."
        if (paginaCorrente < pagineTotali - 2) createDots();
        // Pulsante per l'ultima pagina
        if (paginaCorrente < pagineTotali - 1) creaBtn(pagineTotali.toString(), pagineTotali);   
    } else {
        // Se ci sono 4 o meno pagine, mostriamo solo i pulsanti per ciascuna pagina
        for (let i = 1; i <= pagineTotali; i++) {
            creaBtn(i, i, i === paginaCorrente);
        }
    }
}


function aggiornaPagina(page) {
    noanimazione = true;
    console.log('la pagina cliccata è', page);
    // catturo in una variabile il link attuale
    let currentUrl = new URL(window.location.href);
    // catturo in una stringa la querystring
    let stringa = currentUrl.search;
    // verifico che la stringa abbia la parola pagina
    stringa = stringa.includes('pagina') ? stringa.replace(/pagina=[0-9]+/g, 'pagina=' + page) : `${stringa}&pagina=${page}`
    console.log("url stringa è"+ stringa);
    currentUrl.search = stringa;
    console.log("url corrente è"+ currentUrl);
    // aggiorno l'URL con la nuova querystring senza riavviare la pagina
    window.history.pushState({}, '', currentUrl);
    handlePopState(true);
}

// creo una funzione che mi libera tutte le selezioni dal menu
function liberaInVista() {
    const form = document.querySelector('.ul-inner form, .ul-inner2 form');
    if (form) form.remove();
    dashFromMenu.classList.remove('--inVista');
    utentiSubFromMenu.classList.remove('--inVista');
    utentiOnFromMenu.classList.remove('--inVista');
    reportOpenFromMenu.classList.remove('--inVista');
}
function isParametroUgualeA(stringa) {
    urlParams = new URLSearchParams(window.location.search);
    const paramKeys = [];
    const paramValues = {};
    
    urlParams.forEach(function(value, key) {
        paramKeys.push(key);
        paramValues[key] = value;
    });
    const numParams = paramKeys.length;
    const hasStringa = urlParams.has(stringa);
    const hasPagina = urlParams.has('pagina');
    const valorePagina = hasPagina ? parseInt(paramValues['pagina']) : null;
    
    // console.log("Numero totale di parametri:", paramKeys.length);
    // console.log("Valori dei parametri:", urlParams.toString());
    // console.log("Parametro presente:", hasStringa);
    // console.log("Parametro 'pagina' presente:", hasPagina);
    if (hasPagina) {
        // console.log('valore della pagina:', valorePagina);
    }
    const isSingoloParam = (numParams === 1 && hasStringa);
    const isDoppioParamConPagina = (numParams === 2 && hasStringa && hasPagina);
    // console.log("Vero o falso:", isSingoloParam || isDoppioParamConPagina);
    return {
        presente: isSingoloParam || isDoppioParamConPagina,
        pagina: valorePagina
    }
}

// decido in base al parametro quale click simulare
function handlePopState(hasNoAnimazione = false) {
    noanimazione = hasNoAnimazione;
    if (mediaQuery.matches && navmenu.classList.contains('--aperto')) navToggle.click();
    if (isParametroUgualeA('utenti-iscritti').presente) {
        // console.log("Utenti Iscritti On");
        utentiSub.click();
        overlayZoom.classList.add('--zoomed');
    } else if (isParametroUgualeA('ultimi-accessi').presente) {
        // console.log("Ultimi Acessi On");
        utentiOn.click();
        overlayZoom.classList.add('--zoomed');
    } else if (isParametroUgualeA('resoconto').presente) {
        // console.log("Resoconto On");
        reportOpen.click();
        overlayZoom.classList.add('--zoomed');
    } else {
        rimuoviParametri();
    }
    rimuoviAnimazioneFunction();
}

function rimuoviAnimazioneFunction() {
    let overlayZoomIn = document.querySelector('.--zoomedIn');
    if (overlayZoomIn) {
        function rimuoviAnimazione() {
            overlayZoomIn.classList.remove('--zoomedIn');
            overlayZoomIn.removeEventListener('animationend', rimuoviAnimazione);
        }
        overlayZoomIn.addEventListener('animationend', rimuoviAnimazione);
    }
}

window.addEventListener('popstate', handlePopState);
window.addEventListener('load', handlePopState.bind(this, true));

function parametroAs(stringa) {
    // catturo in una variabile il link attuale
    let currentUrl = new URL(window.location.href);
    // imposto la query string uguale proprio sulla stringa che ho catturato nel parametro
    currentUrl.search = stringa;
    // aggiorno l'URL con la nuova querystring senza riavviare la pagina
    window.history.pushState({}, '', currentUrl);
    // i dati della query ci serviranno per modificare alcuni elementi
    // intanto nel caso in cui ci siano trattini nella querystring faccio un split delle parole
    stringa = stringa.replace(/-/g, ' ').split(' ').map(parola => parola.charAt(0).toUpperCase() + parola.slice(1).toLowerCase()).join(' ');
    // vogliamo la query string senza la chiave pagina o ordine 
    if (stringa.includes('&')) {
        stringa.split('&').forEach( substringa => {
            if (!substringa.includes('pagina')) stringa = substringa;
        });
    }
    breadcumbSection.innerText = stringa;
    titoloOverlay.innerText = stringa;
    breadcumbBack.addEventListener('click', rimuoviParametri);
}

function rimuoviParametri() {
    if (mediaQuery.matches && navmenu.classList.contains('--aperto')) navToggle.click();
    var currentUrl = new URL(window.location.href);
    currentUrl.search = '';
    currentUrl.hash = '';
    window.history.pushState({}, '', currentUrl);
    const classe = overlayZoom.getAttribute('class');
    if (/--zoomed\w*/.test(classe)) {
        for ( let fratello of fratelli) {
            if (fratello ===  overlayZoom) {
                fratello.classList.add('--zoomedOut')
                fratello.classList.remove('--zoomed');
                fratello.addEventListener('animationend', azzeraAnimazione);
            } else {
                fratello.classList.remove('--zoom');
                fratello.style.display = 'flex';
            }
        }
        let verificaFigli = document.querySelector('.overlay-content');
        // console.log("ha figli", verificaFigli.hasChildNodes());
        if (verificaFigli.children.length > 0) {
            verificaFigli.firstElementChild.remove();
        }
    }
    liberaInVista();
    dashFromMenu.classList.add('--inVista');
    breadcumbSection.innerText = "";
    breadcumbBack.removeEventListener('click', rimuoviParametri);
}

function azzeraAnimazione() {
    this.removeEventListener('animationend', azzeraAnimazione);
    this.classList.remove('--zoomedOut');
}

function animazioneAlClick(elCliccato){
    overlayZoom.classList.add('--zoomedIn');
    elCliccato.scrollIntoView({behavior: 'smooth', block: 'start'});
    elCliccato.classList.add('--zoom');
}

function animazioneAlClickMenu() {
    overlayZoom.classList.add('--zoomedSlide');
    overlayZoom.addEventListener('animationend', overlaySlide);
}

function overlayShow(e) {
    overlayZoom.classList.add('--zoomed');
    rimuoviAnimazioneFunction();
    e.target.removeEventListener('animationend', overlayShow)
    for ( let fratello of fratelli) {
        if (fratello !== overlayZoom) {
            fratello.style.display = 'none';
        }
    }
}

function overlaySlide() {
    overlayZoom.classList.add('--zoomed');
    overlayZoom.classList.remove('--zoomedSlide');
    overlayZoom.removeEventListener('animationend', overlaySlide);
}
function mostraContenutoOverlay(tipo) {
    if (mediaQuery.matches && navmenu.classList.contains('--aperto')) navToggle.click();
    liberaInVista();
    let stringaTipo = mappaPulsanti[tipo].stringa;
    let pagina = isParametroUgualeA(stringaTipo).pagina;
    let presente = isParametroUgualeA(stringaTipo).presente;
    let isPaginaValida = pagina <= numeroPagine;
    // console.log('pagina valida', isPaginaValida);
    let bottoneMenu = document.querySelector('#' + tipo);
    richiestaEstrazioneInfoExtra(stringaTipo, pagina, orderBy);
    let dettagli = false;
    // console.log('vediamo chi è il tipo: ' + tipo);
    
    if (!overlayZoom.classList.contains('--zoomed') ) {
        bottoneMenu.classList.add('--inVista');
        pagina && presente && isPaginaValida ? parametroAs(`${stringaTipo}&pagina=${pagina}`) : parametroAs(stringaTipo);
        overlayZoom.classList.add('--zoomed');
        let elCliccato = this;
        console.log('ahahahha giusto?')
        console.log(elCliccato);
        noanimazione ? null : animazioneAlClick(elCliccato);
        noanimazione = false;
        elCliccato.addEventListener('animationend', overlayShow);
        dettagli = true;
    } else if ( overlayZoom.classList.contains('--zoomed')) {
        bottoneMenu.classList.add('--inVista');
        pagina && presente && isPaginaValida ? parametroAs(`${stringaTipo}&pagina=${pagina}`) : parametroAs(stringaTipo);
        noanimazione ? null : animazioneAlClickMenu();
        noanimazione = false;
        dettagli = true;
    }
    setTimeout(function() {
        if (dettagli) {
            // Aggiungiamo il contenuto dell'overlay in base al tipo
            addContenutoOverlay(tipo);
        }
        renderizzaPaginazione(numeroPagine, paginaCorrente);
    }, 300);
}

function addContenutoOverlay(tipo) {
    let verificaFigli = document.querySelector('.overlay-content');
    if (verificaFigli.children.length > 0) {
        verificaFigli.firstElementChild.remove();
    }

    let elHTMLinterno = ``;
    
    if (tipo === 'utentiSub') {
        elHTMLinterno = generaHTMLUtenti(tempDataDaDB);
    } else if (tipo === 'utentiOn') {
        elHTMLinterno = generaHTMLUltimiAccessi(tempDataDaDB);
    } else if (tipo === 'reportOpen') {
        elHTMLinterno = generaHTMLReport(tempDataDaDB, orderBy);
    }
    const template = document.createElement('template');
    template.innerHTML = elHTMLinterno;
    overlayContent.appendChild(template.content);
    riassegnaEventi();    
}

function generaHTMLUtenti(data) {
    let elHTMLinterno = ``;
    data.forEach(utente => {
        elHTMLinterno += `
        <tr>
            <td><input type="checkbox" style="accent-color: rgb(72, 39, 115)" name="check"></input></td>
            <td>${formattaDataDa(utente.ultimo_accesso)}</td>
            <td>${utente.anagrafica}</td>
            <td>${utente.email}</td>
            <td>${utente.ruolo}</td>
            <td>${(utente.team) ? utente.team : ""}</td>
            <td>${formattaDataDa(utente.data_creazione)}</td>
        </tr>
        `;
    });
    let elHTMLselezione = `<li role="option"></li>`;
    teamDaDB.forEach(team => {
        elHTMLselezione += `<li role="option">${team.sigla_team} | ${team.nome_team}</li>`;
    });
    return `
    <div class="overlay-content-inner">
        <div class="operazioni">
            <button class="operazione" onclick="eliminaUtente(this)">Elimina</button>
            <button class="operazione" onclick="promuoviUtente(this)">Promuovi</button>
            <button class="operazione" onclick="declassaUtente(this)">Degrada</button>
            <button class="operazione" onclick="rimuoviUtenteDaTeam(this)">Rimuovi da team</button>
            <button class="operazione" onclick="assegnaUtenteAlTeam(this)">Assegna a:</button>
            <div class="selezione-utente-team custom-select">
                <button type="button" role="combobox" id="seleziona" class="operazione --arrow-invisibile" value="" valore="" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false">Seleziona</button>
                <ul role="listbox" id="listbox">
                    ${elHTMLselezione}
                </ul>
            </div>
        </div>
        <div class="impaginazione"></div>
        <div class="tabella">
            <table class="lista-utenti">
                <tr>
                    <th><input type="checkbox" onclick="toggleAll(this)" style="accent-color: rgb(72, 39, 115)"></th>
                    <th>Ultimo Accesso</th>
                    <th>Anagrafica</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Team</th>
                    <th>Data Creazione</th>
                </tr>
                ${elHTMLinterno}
            </table>
        </div>
    </div>
    `;
}

function generaHTMLUltimiAccessi(data) {
    let elHTMLinterno = ``;
    data.forEach(utente => {
        elHTMLinterno += `
        <tr>
            <td><input type="checkbox" name="check" style="accent-color: rgb(72, 39, 115)"></input></td>
            <td>${formattaDataDa(utente.ultimo_accesso)}</td>
            <td>${utente.anagrafica}</td>
            <td>${utente.email}</td>
            <td>${utente.ruolo}</td>
            <td>${(utente.team) = (utente.team) ? utente.team : ""}</td>
            <td>${formattaDataDa(utente.data_creazione)}</td>
        </tr>
        `;
    });
    let elHTMLselezione = `<li role="option"></li>`;
    teamDaDB.forEach(team => {
        elHTMLselezione += `<li role="option">${team.sigla_team} | ${team.nome_team}</li>`;
    }); 
    return `
    <div class="overlay-content-inner">
        <div class="operazioni">
            <button class="operazione" onclick="eliminaUtente(this)">Elimina</button>
            <button class="operazione" onclick="promuoviUtente(this)">Promuovi</button>
            <button class="operazione" onclick="declassaUtente(this)">Degrada</button>
            <button class="operazione" onclick="rimuoviUtenteDaTeam(this)">Rimuovi da team</button>
            <button class="operazione" onclick="assegnaUtenteAlTeam(this)">Assegna a:</button>
            <div class="selezione-utente-team custom-select">
                <button type="button" role="combobox" id="seleziona" class="--arrow-invisibile" value="" valore="" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false">Seleziona</button>
                <ul role="listbox" id="listbox">
                    ${elHTMLselezione}
                </ul>
            </div>
        </div>
        <div class="impaginazione"></div>
        <div class="tabella">
            <table class="lista-utenti-online">
                <tr>
                    <th><input type="checkbox" onclick="toggleAll(this)" style="accent-color: rgb(72, 39, 115)"></th>
                    <th>Ultimo Accesso</th>
                    <th>Anagrafica</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Team</th>
                    <th>Data Creazione</th>
                </tr>
                ${elHTMLinterno}
            </table>
        </div>
    </div>
    `;
}

function generaHTMLReport(data, sort) {

    let isPrimaPiuRecente = (sort === 'DESC') ? "--opz-sort-attiva" : "";
    let isPrimaMenoRecente = (sort === 'ASC') ? "--opz-sort-attiva" : "";
    let isTutti = (filters.length === 0) ? "--opz-filter-attiva" : "";
    let isOnlySessione = (filters.includes('sessione')) ? "--opz-filter-attiva" : "";
    let isOnlyUtente = (filters.includes('utente')) ? "--opz-filter-attiva" : "";
    let isOnlyTeam = (filters.includes('team')) ? "--opz-filter-attiva" : "";
    let isOnlyProgetto = (filters.includes('progetto')) ? "--opz-filter-attiva" : "";
    let isOnlyScheda = (filters.includes('scheda')) ? "--opz-filter-attiva" : "";
    let elHTMLinterno = ``;
    if (data.length > 0) data.forEach(report => {
        let descrizione;
        let attore = (report.attore) ? report.attore : "<p class='barrato>" + report.attore_era + "</p>";
        let bersaglio_era = (report.bersaglio_era) ? report.bersaglio_era.split("-") : ``;
        let bersaglio = (report.utente) ? report.utente : (bersaglio_era[0] ? "<p class='barrato'>" + bersaglio_era[0] + "</p>" : "");
        let team = (report.team) ? report.team : (bersaglio_era[1] ? "<p class='barrato'>" + bersaglio_era[1] + "</p>" : "");
        let link = (report.link) ? `<a href="${report.link}">Click</a>` : ``;
        let progetto = (report.progetto) ? report.nome_progetto : (bersaglio_era[2] ? "<p class='barrato'>che aveva id: " + bersaglio_era[2] + "</p>" : "");
        let categoria = (report.categoria) ? report.categoria : (bersaglio_era[3] ? "<p class='barrato'>" + bersaglio_era[3] + "</p>" : "");
        let scheda = (report.scheda) ? report.titolo : (bersaglio_era[4] ? "<p class='barrato'>che aveva id: " + bersaglio_era[4] + "</p>" : "");
        if (report.tipo === 'sessione') {
            if (report.descrizione === 'Accesso') {
                descrizione = `${attore} ha fatto accesso`;
            }
        } else if (report.tipo === 'utente') {
            if (report.descrizione === 'Eliminazione Utente') {
                descrizione = `L'utente ${bersaglio} è stato eliminato da ${attore}`;
            } else if (report.descrizione === 'Declassamento Utente') {
                descrizione = `L'utente ${bersaglio} è stato degradato da ${attore}`;
            } else if (report.descrizione === 'Promozione Utente') {
                descrizione = `L'utente ${bersaglio} è stato promosso da ${attore}`;
            } 
        } else if (report.tipo === 'team') {
            if (report.descrizione === 'Rimozione Utente Da Team') {
                descrizione = `L'utente ${bersaglio} è stato rimosso dal team "${team}" da ${attore}`;
            } else if (report.descrizione === 'Aggiunta Utente Nel Team') {
                descrizione = `L'utente ${bersaglio} è stato aggiunto al team "${team}" da ${attore}`;
            } else if (report.descrizione === 'Creazione Team') {
                descrizione = `Il team "${team}" è stato creato da ${attore}`;
            } else if (report.descrizione === 'Assegnazione Team') {
                descrizione = `Il team "${team}" è stato assegnato da ${attore} a ${bersaglio}`;
            } else if (report.descrizione === 'Aggiornamento Team') {
                descrizione = `Il team "${team}" di ${bersaglio} è stato aggiornato da ${attore}`;
            } else if (report.descrizione === 'Eliminazione Team') {
                descrizione = `Il team "${team}" è stato eliminato da ${attore}`;
            }
        } else if (report.tipo === 'progetto') {            
            if (report.descrizione === 'Creazione Progetto') {
                descrizione = `Il progetto "${progetto}" è stato creato da ${attore}`;
            } else if (report.descrizione === 'Cancellazione Progetto') {
                descrizione = `Il progetto "${progetto}" è stato eliminato da ${attore}`;
            } else if (report.descrizione === 'Aggiornamento Progetto') {
                descrizione = `Il progetto "${progetto}" è stato aggiornato da ${attore}`;
            } else if (report.descrizione === 'Assegnazione Progetto') {
                descrizione = `Il progetto "${progetto}" è stato assegnato a "${team}" da ${attore}`;
            } else if (report.descrizione === 'Creazione Categoria') {
                descrizione = `La categoria "${categoria}" di "${progetto} è stata creata da ${attore}`;
            } else if (report.descrizione === 'Eliminazione Categoria') {
                descrizione = `La categoria "${categoria}" di "${progetto} è stata eliminata da ${attore}`;
            } else if (report.descrizione === 'Oscuramento Categoria') {
                descrizione = `La categoria "${categoria} di "${progetto} è stata oscurata da ${attore}`;
            } else if (report.descrizione === 'Visualizzazione Categoria') {
                descrizione = `La categoria "${categoria} di "${progetto} è stata resa visibile da ${attore}`;
            }
        }  else if (report.tipo === 'scheda') {            
            if (report.descrizione === 'Creazione Scheda') {
                descrizione = `La scheda "${scheda}" è stata creata da "${attore}`;
            } else if (report.descrizione === 'Archiviazione Scheda') {
                descrizione = `La scheda "${scheda}" è stata arcjovoata`;
            } else if (report.descrizione === 'Eliminazione Scheda') {
                descrizione = `La scheda "${scheda}" è stata eliminata da "${attore}";`;
            } else if (report.descrizione === 'Cambiamento Stato') {
                descrizione = `Lo stato della scheda "${scheda}" è stato cambiato in "${categoria}" ${attore}`;
            } else if (report.descrizione === 'Aggiunta Descrizione Scheda') {
                descrizione = `Una descrizione è stata aggiunta alla scheda "${scheda}" da "${attore}`
            } else if (report.descrizione === 'Modifica Descrizione Scheda') {
                descrizione = `La descrizione della scheda "${scheda} è stata modificata da "${attore}"`;
            } else if (report.descrizione === 'Creazione Commento') {
                descrizione = `Un commento è stato aggiunto alla scheda "${scheda}" da "${attore}"`;
            } else if (report.descrizione === 'Modifica Commento') {
                descrizione = `Un commento della scheda "${scheda}" è stato modificato da "${attore}"`;
            } else if (report.descrizione === 'Risposta Commento') {
                descrizione = `Una risposta è stata aggiunta al commento di ${bersaglio} nella scheda "${scheda}" da "${attore}"`;
            } else if (report.descrizione === 'Eliminazione Commento') {
                descrizione = `Un commento di ${bersaglio} nella scheda "${scheda}" è stato eliminato da "${attore}"`;
            } else if (report.descrizione === 'Revocazione Scheda') {
                descrizione = `L'incarico della scheda "${scheda}" è stato revocato a ${bersaglio} da "${attore}"`;
            } else if (report.descrizione === 'Assegnazione Scheda') {
                descrizione = `L'incarico della scheda "${scheda}" è stato assegnato a "${bersaglio}" da "${attore}"`;
            } else if (report.descrizione === 'Riassegnazione Scheda') {
                descrizione = `L'incarico della scheda "${scheda}" è stato riassegnato a ${bersaglio} da "${attore}"`;
            } 
        } 
        elHTMLinterno += `
        <tr>
            <td>${formattaDataDa(report.timestamp, true)}</td>
            <td>${descrizione}</td>
            <td>${link}</td>
        </tr>
        `;
    });
    return `
    <div class="overlay-content-inner">
        <div class="operazioni">
            <button class="operazione --sort ${isPrimaPiuRecente}" onclick="recenti(this)">Dal più recente</button>
            <button class="operazione --sort ${isPrimaMenoRecente}" onclick="datati(this)">Dal meno recente</button>
            <button class="operazione ${isTutti}" onclick="selezionaTutti(this)">Tutti</button>
            <button class="operazione ${isOnlySessione}" onclick="selezionaSoloSessioni(this)">Sessioni</button>
            <button class="operazione ${isOnlyUtente}" onclick="selezionaSoloUtenti(this)">Utenti</button>
            <button class="operazione ${isOnlyTeam}" onclick="selezionaSoloTeam(this)">Team</button>
            <button class="operazione ${isOnlyProgetto}" onclick="selezionaSoloProgetti(this)">Progetti</button>
            <button class="operazione ${isOnlyScheda}" onclick="selezionaSoloSchede(this)">Schede</button>
        </div>
        <div class="impaginazione"></div>
        <div class="tabella">
            <table class="lista-azioni">
                <tr>
                    <th>Timestamp</th>
                    <th>Descrizione</th>
                    <th>Link</th>
                </tr>
                ${elHTMLinterno}
            </table>
        </div>
    </div>
    `;
}

// function isAnimazioneInCorsoSu(elemento){ 
//     const computedStyle = window.getComputedStyle(elemento);
//     const animationName = computedStyle.getPropertyValue('animation-name');
//     const animationDuration = computedStyle.getPropertyValue('animation-duration');
//     const animationPlayState = computedStyle.getPropertyValue('animation-play-state');
//     return animationName !== 'none' && parseFloat(animationDuration) > 0 && animationPlayState !== 'paused';
// }

function toggleAll(stato) {
    checkboxes = document.querySelectorAll('tr input[type=checkbox]');
    checkboxes.forEach( box => {box.checked = stato.checked;});
}

function formattaDataDa(localDateTime, completa = false) {
    let mesi = ["Gen", "Feb", "Mar", "Apr", "Mag", "Giu", "Lug", "Ago", "Set", "Ott", "Nov", "Dic"]
    dataMinima = new Date(localDateTime);
    giorno = dataMinima.getDate();
    mese = mesi[dataMinima.getMonth()];
    anno = dataMinima.getFullYear();
    dataMinimaFormattata = `${giorno} ${mese} ${anno}`;
    if (completa = true) {
        ora = dataMinima.getHours().toString().padStart(2, '0');
        minuti = dataMinima.getMinutes().toString().padStart(2, '0');
        secondi = dataMinima.getSeconds().toString().padStart(2, '0');
        dataMinimaFormattata = `${giorno} ${mese} ${anno} ${ora}:${minuti}:${secondi}`;
    }
    return dataMinimaFormattata;
}