function richiestaEstrazioneInfo(){
    console.log("richiesta per estrazione info effettuata");
    // era dashboard.php
    NuovaRichiestaHttpXML.mandaRichiesta('GET', 'services/controllers/retrieve.php?dashboard', true, 'Content-Type', 'application/json', '', estrazioneInfo);
    setTimeout(() => {
        NuovaRichiestaHttpXML.verificaUtenteConnesso();
    }, 100);  
}

function esciDaDashboard() {
    NuovaRichiestaHttpXML.mandaRichiesta('POST', './services/controllers/logout.php', true, 'Content-Type', 'application/x-www-form-urlencoded', 'disconnessione=true&no_DB=true', esci);
}

let giorniOrdinati = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
let valoriW1 = [0, 0 ,0 ,0 ,0 ,0 ,0];
let valoriW2 = [0, 0 ,0 ,0 ,0 ,0 ,0]; 
let valoriW3 = [0, 0, 0, 0, 0, 0, 0];
let projSuHTML = [];
let projDaDB = [];
let teamSuHTML = [];
let teamDaDB = [];
let acSuHTML = [];
let acDaDB = [];
let arSuHTML = [];
let arDaDB = [];

function esci() {
    const rispostaServer = JSON.parse(xhr.responseText);
    console.log('Richiesta disconnessione completata con successo');
    if (rispostaServer.isUtenteConnesso) {
        msgN = 'Successo: Utente disconnesso';
        typeN = 'successo-notifica';
        console.log(msgN);
    } else {
        //L'utente è già disconnesso
        msgN = "Errore: Già sei stato disconnesso";
        typeN = 'errore-notifica';
        console.log(msgN);
    }
    var messaggioInviato = msgN;
    var tipoInviato = typeN;
    // Salvo il messaggio e il tipo di notifica in localStorage, in modo da poter visualizzarli subito dopo il reindirizzamento
    localStorage.setItem('messaggio', messaggioInviato);
    localStorage.setItem('tipoMessaggio', tipoInviato )
    window.location.href = 'portal.html';
}

bottoneEsci.addEventListener('click', esciDaDashboard); 
function estrazioneInfo() {
    // console.log(xhr.responseText);
    const rispostaServer = JSON.parse(xhr.responseText);
    console.log('Richiesta completata con successo');
    if (!rispostaServer) {
        // si viene reindirizzati alla home
        
        window.location.href = 'home.html';
        return;
    }
    // Creo una variabile per memorizzare il messaggio di sistema
    let sm = rispostaServer.messaggio;
    const info = rispostaServer.dati;
    console.log(info);
    if (!sm || sm.includes('negato')) {
        sm = sm ? "Accesso Negato: Sei stato reindirizzato!" : "Oops! Sembra ci sia un problema di connessione. Controlla la tua connessione a internet e al DB, e ricarica!";
        Notifica.appari({messaggioNotifica: sm, tipoNotifica: 'special-notifica'});
        localStorage.setItem('messaggio', sm);
        localStorage.setItem('tipoMessaggio', 'info-notifica');
        window.location.href = 'portal.html';
        return;
    } else if (sm.includes('Errore')) {
        Notifica.appari({messaggioNotifica: sm, tipoNotifica: 'special-errore-notifica'})
    }
    if (!rispostaServer.isAdmin) {
        console.log(rispostaServer);
        localStorage.setItem('messaggio', 'Accesso negato: connessione assente o permessi non sufficienti a visualizzare la pagina!');
        localStorage.setItem('tipoMessaggio', 'info-notifica');
        window.location.href = 'portal.html';
        return;
    } else {
        let infoUI = document.querySelectorAll('.tile h5')[0]; // dove inseriremo il numero di utenti totali
        let infoUA = document.querySelectorAll('.tile h5')[1]; // quelli online
        let infoTM = document.querySelectorAll('.tile h5')[2]; // numero di team creati
        let infoSC = document.querySelectorAll('.tile h5')[3]; // le schede attività create
        let infoPA = document.querySelectorAll('.tile h5')[4]; // il numero di progetti assegnati sui totali
        infoUI.textContent = info['numero_utenti_iscritti'];
        infoUA.textContent = info['numero_connessi_last24h'];
        infoTM.textContent = info['numero_team_creati'];
        infoSC.textContent = info['numero_commenti'];
        infoPA.textContent = `${info['numero_progetti_con_team']} / ${info['numero_progetti']}`; // ci serve 
        projDaDB = info['lista_progetti']; 
        verificaProgetti();
        teamAssegnabili = info['team_assegnabili'];
        teamDaDB = info['lista_team'];
        verificaTeam();
        acDaDB = info['schede_completate'];
        verificaAttivitaCompletate();
        arDaDB = info['schede_in_ritardo'];
        verificaAttivitaInRitardo();
    }
    // document.getElementById('isAdmin').textContent = rispostaServer.isAdmin ? 'Admin' : 'Non Admin';
    // document.getElementById('numero_utenti').innerHTML = '<br>' + info[0] + '<br>' + info[1] + '<br>' + info[2] + '<br>' + info[3] + '<br>' + info[4] + '<br>' + info[5] + '<br>' + info[6] + '<br>' + info[7] + "" + info[10];
    giorniOrdinati = info['giorni_ordinati'];
    valoriW1 = info['commenti_weekly'];
    valoriW2 = info['schede_completate_weekly'];
    valoriW3 = info['schede_in_ritardo_weekly'];
    disegnaGrafici();   
}

// La funzione richiestaEstrazioneInfo() va chiamata non solo quando viene caricata la pagina...
window.onload = richiestaEstrazioneInfo();

// ...ma anche quando viene recuperata dalla cache del browser (come quando usiamo il tasto "indietro")
window.addEventListener('pageshow', function(evento) {
    // Se la pagina viene recuperare dalla cache del browser evento.persisted sará vero.
    // typeof window.performance != 'undefined' verifica se é definita l'API delle prestazioni 
    // Se definita verifichiamo se il tipo di navigazione, nell'ultima intereazione, é di tipo back_forward
    var navCronologica = evento.persisted || (typeof window.performance != 'undefined' && performance.getEntriesByType("navigation")[0].type === "back_forward");
    if (navCronologica) {
        console.log('La pagina è stata recuperata dalla cache del browser.');
        // rieseguiamo la funzione interessata
        richiestaEstrazioneInfo();
    }
});

// Ottengo il messaggio e il tipo salvati nel localStorage del browser prima del reindirizzamento
var messaggioRicevuto = localStorage.getItem('messaggio');
var tipoRicevuto = localStorage.getItem('tipoMessaggio');
if (messaggioRicevuto && tipoRicevuto) {
    Notifica.appari({messaggioNotifica: messaggioRicevuto, tipoNotifica: tipoRicevuto});
    // Rimuovo le variabili dallo storage del browser
    localStorage.removeItem('messaggio');
    localStorage.removeItem('tipoMessaggio');
}

let refreshBtn = document.querySelector(".aggiorna");
refreshBtn.addEventListener("click", () => {
    richiestaEstrazioneInfo(); 
});

function verificaProgetti() {
    let suEntrambi = [];
    let soloSuHTML = [];
    let soloDaDB = []; 
    projSuHTML.forEach(elementoHTML => {
        if (projDaDB.includes(elementoHTML)) {
            suEntrambi.push(elementoHTML);
        } else {
            soloSuHTML.push(elementoHTML);
        }
    });
    projDaDB.forEach(elementoDB => {
        if (!soloSuHTML.includes(elementoDB)) {
            soloDaDB.push(elementoDB);
        }
    });
    // Tutti gli elementi presenti solo sulla pagina HTML e non presenti sul DB verranno rimossi
    soloSuHTML.forEach(elementoHTML => {
        // console.log(`#proj-${elementoHTML['id_progetto']}`);
        let liTupla = document.querySelector(`#proj-${elementoHTML['id_progetto']}`);
        if (liTupla) {
            liTupla.remove();
        }
    });
    // Tutti gli elementi presenti sul DB e non presenti sulla pagina HTML verranno aggiunti
    soloDaDB.forEach(elementoDB => {
        updateProgetti(elementoDB);
    });
    // Tutti gli elementi presenti sul DB ma anche presenti già nella pagina HTML saranno aggiornati
    suEntrambi.forEach(suEntrambi => {
        let liTupla = document.querySelector(`[id="proj-${suEntrambi['id_progetto']}]`);
        if (liTupla) {
            updateProgetti(suEntrambi);
        }
    });
    // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
    projSuHTML = projDaDB;
    // dobbiamo inoltre chiamare la funzione che mi permette di riassegnare a tutti gli elementi creati/aggiornati di poter creare form relativi
    elAnteprimaRiassegnazione();
}

function verificaTeam() {
    let suEntrambi = [];
    let soloSuHTML = [];
    let soloDaDB = []; 
    teamSuHTML.forEach(elementoHTML => {
        if (teamDaDB.includes(elementoHTML)) {
            suEntrambi.push(elementoHTML);
        } else {
            soloSuHTML.push(elementoHTML);
        }
    });
    teamDaDB.forEach(elementoDB => {
        if (!soloSuHTML.includes(elementoDB)) {
            soloDaDB.push(elementoDB);
        }
    });
    // Tutti gli elementi presenti solo sulla pagina HTML e non presenti sul DB verranno rimossi
    soloSuHTML.forEach(elementoHTML => {
        console.log(`#team-${elementoHTML['sigla_team']}`);
        let liTupla = document.querySelector(`#team-${elementoHTML['sigla_team']}`);
        if (liTupla) {
            liTupla.remove();
        }
    });
    // Tutti gli elementi presenti sul DB e non presenti sulla pagina HTML verranno aggiunti
    soloDaDB.forEach(elementoDB => {
        updateTeam(elementoDB);
    });
    // Tutti gli elementi presenti sul DB ma anche presenti già nella pagina HTML saranno aggiornati
    suEntrambi.forEach(suEntrambi => {
        let liTupla = document.querySelector(`[id="team-${suEntrambi['sigla_team']}]`);
        if (liTupla) {
            updateTeam(suEntrambi);
        }
    });
    // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
    teamSuHTML = teamDaDB;
    // dobbiamo inoltre chiamare la funzione che mi permette di riassegnare a tutti gli elementi creati/aggiornati di poter creare form relativi
    elAnteprimaRiassegnazione();
}

function verificaAttivitaCompletate() {
    let suEntrambi = [];
    let soloSuHTML = [];
    let soloDaDB = []; 
    acSuHTML.forEach(elementoHTML => {
        if (acDaDB.includes(elementoHTML)) {
            suEntrambi.push(elementoHTML);
        } else {
            soloSuHTML.push(elementoHTML);
        }
    });
    acDaDB.forEach(elementoDB => {
        if (!soloSuHTML.includes(elementoDB)) {
            soloDaDB.push(elementoDB);
        }
    });
    // Tutti gli elementi presenti solo sulla pagina HTML e non presenti sul DB verranno rimossi
    soloSuHTML.forEach(elementoHTML => {
        // console.log(`#prac-${elementoHTML['id_progetto']}-${elementoHTML['uuid_scheda']}`);
        let liTupla = document.querySelector(`#prac-${elementoHTML['id_progetto']}-${elementoHTML['uuid_scheda']}`);
        if (liTupla) {
            liTupla.remove();
        }
    });
    // Tutti gli elementi presenti sul DB e non presenti sulla pagina HTML verranno aggiunti
    soloDaDB.forEach(elementoDB => {
        updateAc(elementoDB);
    });
    // Tutti gli elementi presenti sul DB ma anche presenti già nella pagina HTML saranno aggiornati
    suEntrambi.forEach(suEntrambi => {
        let liTupla = document.querySelector(`#prac-${suEntrambi['id_progetto']}-${suEntrambi['uuid_scheda']}`);
        if (liTupla) {
            updateAc(suEntrambi);
        }
    });
    // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
    acSuHTML = acDaDB;
}

function verificaAttivitaInRitardo() {
    let suEntrambi = [];
    let soloSuHTML = [];
    let soloDaDB = []; 
    arSuHTML.forEach(elementoHTML => {
        if (arDaDB.includes(elementoHTML)) {
            suEntrambi.push(elementoHTML);
        } else {
            soloSuHTML.push(elementoHTML);
        }
    });
    arDaDB.forEach(elementoDB => {
        if (!soloSuHTML.includes(elementoDB)) {
            soloDaDB.push(elementoDB);
        }
    });
    // Tutti gli elementi presenti solo sulla pagina HTML e non presenti sul DB verranno rimossi
    soloSuHTML.forEach(elementoHTML => {
        // console.log(`#prar-${elementoHTML['id_progetto']}-${elementoHTML['uuid_scheda']}`);
        let liTupla = document.querySelector(`#prar-${elementoHTML['id_progetto']}-${elementoHTML['uuid_scheda']}`);
        if (liTupla) {
            liTupla.remove();
        }
    });
    // Tutti gli elementi presenti sul DB e non presenti sulla pagina HTML verranno aggiunti
    soloDaDB.forEach(elementoDB => {
        // console.log('questo va');
        updateAr(elementoDB);
    });
    // Tutti gli elementi presenti sul DB ma anche presenti già nella pagina HTML saranno aggiornati
    suEntrambi.forEach(suEntrambi => {
        let liTupla = document.querySelector(`#prar-${suEntrambi['id_progetto']}-${suEntrambi['uuid_scheda']}`);
        if (liTupla) {
            updateAr(suEntrambi);
        }
    });
    // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
    arSuHTML = arDaDB;
}

function updateProgetti(suElArray) {
    let datetimeLocal = suElArray['scadenza'].replace(/_/g, 'T');
    let dateOggi = oggi();
    let colore = suElArray['sigla_team'] ? confrontoDate(dateOggi, datetimeLocal) : "grigio";
    let teamConc = suElArray['sigla_team'] ? `<div class="assegnato-pot">${suElArray['sigla_team']} | ${suElArray['team']}</a></div>` : "";
    let projEl = `
    <li id="proj-${suElArray['id_progetto']}" colore="${colore}" nmp="${suElArray['nome']}" dep="${suElArray['descrizione']}" scp="${suElArray['scadenza']}" stp="${suElArray['sigla_team']}" tmp="${suElArray['team']}">
        <div class="nome-pot">${suElArray['nome']}</div>
        ${teamConc}
        <a href="board.html?proj=${suElArray['id_progetto']}">Vai</a>
    </li>
    `
    let contenitoreElProj = document.querySelector('#ap .ul-inner');
    const template = document.createElement('template');
    template.innerHTML = projEl;
    contenitoreElProj.insertBefore(template.content, contenitoreElProj.firstChild);
    // console.log("primo figlio" + contenitoreElProj.firstChild.innerHTML);
}

function updateTeam(suElArray) {
    let colore = (suElArray['numero_progetti'] > 0) ? "viola" : "blu";
    let teamEl = `
    <li id="team-${suElArray['sigla_team']}" colore="${colore}" sgt="${suElArray['sigla_team']}" nmt="${suElArray['nome_team']}" npt="${suElArray['numero_progetti']}" rst="${suElArray['responsabile_team']}" aut="${suElArray['anagrafica_utente']}">
        <div class="nome-pot">${suElArray['sigla_team']} | ${suElArray['nome_team']}</div>
        <div class="assegnato-pot">${suElArray['anagrafica_utente']} | ${suElArray['responsabile_team']}</a></div>
    </li>
    `
    let contenitoreElTeam = document.querySelector('#at .ul-inner');
    const template = document.createElement('template');
    template.innerHTML = teamEl;
    contenitoreElTeam.insertBefore(template.content, contenitoreElTeam.firstChild);
    // console.log("primo figlio" + contenitoreElTeam.firstChild.innerHTML);
}

function updateAc(suElArray) {
    let colore = "verde";
    let acEl = `
    <li id="prac-${suElArray['id_progetto']}-${suElArray['uuid_scheda']}" colore="${colore}" tac="${suElArray['titolo']}" sac="${suElArray['spostamento']}">
        <div class="nome-ac">${suElArray['titolo']}</div>
        <div class="spostato-ac">${suElArray['spostamento']}</div>
        <a href="board.html?proj=${suElArray['id_progetto']}&post=${suElArray['uuid_scheda'].toLowerCase()}">Vai</a>
    </li>
    `
    let contenitoreElAc = document.querySelector('#ac .ul-inner2');
    const template = document.createElement('template');
    template.innerHTML = acEl;
    contenitoreElAc.insertBefore(template.content, contenitoreElAc.firstChild);
    // console.log("primo figlio" + contenitoreElAc.firstChild.innerHTML);
}

function updateAr(suElArray) {
    // console.log('anche questo va');
    let colore = "rosso";
    let arEl = `
    <li id="prar-${suElArray['id_progetto']}-${suElArray['uuid_scheda']}" colore="${colore}" tar="${suElArray['titolo']}" sar="${suElArray['spostamento']}">
        <div class="nome-ar">${suElArray['titolo']}</div>
        <div class="spostato-ar">${suElArray['spostamento']}</div>
        <a href="board.html?proj=${suElArray['id_progetto']}&post=${suElArray['uuid_scheda'].toLowerCase()}">Vai</a>
    </li>
    `
    let contenitoreElAr = document.querySelector('#ar .ul-inner2');
    const template = document.createElement('template');
    template.innerHTML = arEl;
    contenitoreElAr.insertBefore(template.content, contenitoreElAr.firstChild);
    // console.log("primo figlio" + contenitoreElAr.firstChild.innerHTML);
}

function oggi(){
    let today = new Date();
    let dd = String(today.getDate()).padStart(2, '0');
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    let yyyy = today.getFullYear();
    let hh = String(today.getHours()).padStart(2, '0');
    let min = String(today.getMinutes()).padStart(2, '0');
    let sec = String(today.getSeconds()).padStart(2, '0');
    let todayString = yyyy + '-' + mm + '-' + dd + ' ' + hh + ':' + min + ':' + sec;
    return todayString;
}

function confrontoDate(oggi, scadenza) {
    var oggiConvertito = new Date(oggi.split('T')[0]);
    var scadenzaConvertita = new Date(scadenza.split('T')[0]);
    let differenzaGiorni = Math.floor((scadenzaConvertita - oggiConvertito) / (1000 * 60 * 60 * 24));
    switch (true) {
        case differenzaGiorni < 0:
            return "rosso";
        case differenzaGiorni <= 15:
            return "arancione";
        case differenzaGiorni <= 30:
            return "giallo";
        default:
            return "verde";
    }
}
