let boardSuHTML = [];
let reportsSuHTML = [];
const innerBoard = document.querySelector(".board");
const innerReports = document.querySelector(".nav-container");
let ruoloUtente;
let boardId;

function ricavaDatiBoard(dati, messaggio, ruolo) {
    let scadenza = dati.progetto.scadenza_progetto.split(" ");
    document.getElementById('nome-progetto').innerText = dati.progetto.nome_progetto;
    document.getElementById('descrizione-progetto').innerText = dati.progetto.descrizione_progetto;
    document.getElementById('scadenza-progetto').innerText = `Scadenza Progetto: ${scadenza[0]} alle ${scadenza[1]}`;
    boardId = dati['id_progetto'];
    let boardDaDB = dati.stati;
    let reportsDaDB = dati.reports;
    ruoloUtente = ruolo;
    console.log(boardDaDB);
    if (!boardDaDB) boardDaDB = [];
    verificaElementi(boardDaDB, boardSuHTML, "board", innerBoard).then((elementiSuHTML) => {
        boardSuHTML = elementiSuHTML;
        aggiornoIcone();
        //verifichiamo se abbiamo a che fare con un post da aprire
        ricavaDatiPost();
    }).catch((errore) => {
        console.trace("Errore: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore: " + errore.message, tipoNotifica: "special-errore-notifica"});
    });
    if (!reportsDaDB) reportsDaDB = [];
    verificaElementi(reportsDaDB, reportsSuHTML, "reports", innerReports).then((elementiSuHTML) => {
        reportsSuHTML = elementiSuHTML;
    }).catch((errore) => {
        console.trace("Errore: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore: " + errore.message, tipoNotifica: "special-errore-notifica"});
    });        
}

function verificaElementi(elementiDaDB, elementiSuHTML, tipologia, contenitore) {
    return new Promise((resolve, reject) => {
        console.log("su html", boardSuHTML);
        const suEntrambi = [];
        const soloSuHTML = [];
        const soloDaDB = []; 
        elementiSuHTML.forEach(elementoHTML => {
            if (elementiDaDB.includes(elementoHTML)) {
                suEntrambi.push(elementoHTML);
            } else {
                soloSuHTML.push(elementoHTML);
            }
        });
        elementiDaDB.forEach(elementoDB => {
            if (!soloSuHTML.includes(elementoDB)) {
                soloDaDB.push(elementoDB);
            }
        });
        // Tutti gli elementi presenti solo sulla pagina HTML e non presenti sul DB verranno rimossi
        soloSuHTML.forEach(elementoHTML => {
            let tupla;
            if (tipologia === "board") {
                let idType = `${elementoHTML.id_progetto}-${elementoHTML.stato}`.replace(/ /g, '_');
                tupla = document.querySelector(`#type-${idType}`);
            } else if (tipologia === "reports") {
                let idRepo = `${elementoHTML.uuid_report}`;
                tupla = document.querySelector(`#repo-${idRepo}`);
            } else if (tipologia === "thread") {
                let idReply = `${elementoHTML.uuid_commento}`;
                tupla = document.querySelector(`#repl-${idReply}`);
            }
            if (tupla) tupla.remove();
        });
        // Tutti gli elementi presenti sul DB e non presenti sulla pagina HTML verranno aggiunti
        soloDaDB.forEach(elementoDB => { 
            update(elementoDB, tipologia, contenitore);
        });
        // Tutti gli elementi presenti sul DB ma anche presenti già nella pagina HTML saranno aggiornati
        suEntrambi.forEach(suEntrambi => {
            let tupla;
            if (tipologia === "board") {
                let idType = `${suEntrambi.id_progetto}-${suEntrambi.stato}`.replace(/ /g, '_');
                tupla = document.querySelector(`#type-${idType}`);
            } else if (tipologia === "reports") {
                let idRepo = `${suEntrambi.uuid_report}`;
                tupla = document.querySelector(`#repo-${idRepo}`);
            } else if (tipologia === "thread") {
                let idReply = `${suEntrambi.uuid_commento}`;
                tupla = document.querySelector(`#repl-${idReply}`);
            }
            if (tupla) update(suEntrambi, tipologia, contenitore);
        });
        elementiSuHTML = elementiDaDB;
        toggleAddBtn();
        // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
        resolve(elementiSuHTML);
    });
    
}

function update(elDiArray, tipologia, contenitore) {
    let elHTML = '';
    if (tipologia === 'board') {
        // uuid_scheda' => $uuid_scheda, 'titolo' => $titolo, 'descrizione' => $descrizione, 'ordine_schede' => $ordine_schede
        Board.aggiungiCategoria({idProgetto: elDiArray['id_progetto'], categoria: elDiArray['stato'], ordineCategoria: elDiArray['ordine_stati'], colore: elDiArray['colore_hex'], isVisibile: elDiArray['isVisibile']});
        let idType = `${elDiArray.id_progetto}-${elDiArray.stato}`.replace(/ /g, '_');
        let categoria = document.querySelector(`#type-${idType} .posts`);
        
        elDiArray.schede.forEach( scheda => {
            Board.aggiungiScheda({categoria: categoria, uuidScheda: scheda['uuid_scheda'], titoloScheda: scheda['titolo'], descrizioneScheda: scheda['descrizione'], id_progetto: elDiArray['id_progetto'], ordineScheda: scheda['ordine_schede']});
        });
    } else if (tipologia === 'reports') {
        elHTML = aggiornoReport(elDiArray.uuid_report, elDiArray.timestamp, elDiArray.attore, elDiArray.descrizione, elDiArray.link, elDiArray.bersaglio, elDiArray.team_responsabile, elDiArray.stato, elDiArray.attore_era, elDiArray.bersaglio_era, elDiArray.colore_hex, elDiArray.titolo_scheda, elDiArray.isBersaglioMe, elDiArray.isAttoreMe, elDiArray.incaricato, elDiArray.isIncaricatoMe);
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        contenitore.appendChild(template.content);
    } else if (tipologia === 'thread') {
        elHTML = aggiornoCommenti(elDiArray.uuid_commento, elDiArray.contenuto, elDiArray.mittente, elDiArray.nome_mittente, elDiArray.cognome_mittente, elDiArray.sesso_mittente, elDiArray.ruolo_mittente, elDiArray.inviato, elDiArray.destinatario, elDiArray.nome_destinatario, elDiArray.cognome_destinatario, elDiArray.modificato_da, elDiArray.nome_editore, elDiArray.cognome_editore, elDiArray.modificato_il, elDiArray.is_modificabile, elDiArray.uuid_in_risposta);
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        (contenitore.children.length > 0 ) ? contenitore.insertBefore(template.content, contenitore.firstChild) : contenitore.appendChild(template.content);
    }
    
}

function scrollToFirstChild(container) {
    primoFiglio = container.firstElementChild;
    if (primoFiglio) primoFiglio.scrollIntoView({ behavior: 'smooth', inline: 'start' });
}

function ricavaDatiPost() {
    if (isParametroUgualeA('proj').isPostPresente) {
        let progetto = isParametroUgualeA('proj').progetto;
        let scheda = isParametroUgualeA('proj').scheda;
        console.log( progetto + "+" + scheda);
        let postClick = document.querySelector(`[id^='type-${progetto}-'] #post-${scheda} img.massimizza`);
        if (!postClick) {
            let url = new URL(window.location.href);
            url.hash = "";
            url.searchParams.delete('post');
            pushState(url);
            throw new Error("Il post potrebbe essere archiviato o non esistere!");
        }
        postClick.click();
    }
}

function aggiornoReport(uuid_report, timestamp, attore, descrizione, link, bersaglio, team_responsabile, stato, attore_era, bersaglio_era, colore_hex, titolo_scheda, isBersaglioMe, isAttoreMe, incaricato, isIncaricatoMe) {
    let resoconto;
    if (!attore) attore = "<del>" + attore_era + "</del>";
    if (!bersaglio) bersaglio = "<del>" + bersaglio_era.split("-")[0] + "</del>";
    const realBersaglio = bersaglio;
    bersaglio = (isIncaricatoMe) ? "a te assegnata" : "di " + bersaglio;
    if (!stato) stato = "<del>" + bersaglio_era.split("-")[3] + "</del>";
    if (!titolo_scheda) titolo_scheda = "che aveva UUID: <del>" + bersaglio_era.split("-")[4] + "</del>";
    let colore = (colore_hex) ? colore_hex.slice(0, -2) + "4d" : "#ffffff4d";
    // if (isAttoreMe) return;
    if (descrizione === "Creazione Scheda") {
        resoconto = `${attore} ha creato la scheda "${titolo_scheda}"`;
    } else if (descrizione === "Archiviazione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ha archiviato la tua scheda "${titolo_scheda}"` : `${attore} ha archiviato la scheda, ${bersaglio}, "${titolo_scheda}"`;
    } else if (descrizione === "Eliminazione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ha eliminato la tua scheda "${titolo_scheda}"` : `${attore} ha eliminato la scheda, ${bersaglio}, "${titolo_scheda}"`;
    } else if (descrizione === "Cambiamento Stato") {
        resoconto = (isBersaglioMe) ? `${attore} ha cambiato lo stato della tua scheda "${titolo_scheda}" in "${stato}"` : `${attore} ha cambiato lo stato della scheda, ${bersaglio}, "${titolo_scheda}" in "${stato}"`;
    } else if (descrizione === "Aggiunta Descrizione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ha aggiunto una descrizione alla tua scheda "${titolo_scheda}"` : `${attore} ha aggiunto una descrizione alla scheda, ${bersaglio}, "${titolo_scheda}"`; 
    } else if (descrizione === "Modifica Descrizione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ha modificato la descrizione della tua scheda "${titolo_scheda}"` : `${attore} ha modificato la descrizione della scheda, ${bersaglio}, "${titolo_scheda}"`;
    } else if (descrizione === "Creazione Commento") {
        resoconto = (isBersaglioMe) ? `${attore} ha aggiunto un commento alla tua scheda "${titolo_scheda}"` : `${attore} ha aggiunto un commento alla scheda, ${bersaglio}, "${titolo_scheda}"`;
    } else if (descrizione === "Modifica Commento") {
        resoconto = (isBersaglioMe) ? `${attore} ha modificato un commento della tua scheda "${titolo_scheda}"` : `${attore} ha modificato un commento della scheda, ${bersaglio}, "${titolo_scheda}"`;
    } else if (descrizione === "Risposta Commento") {
        resoconto = (isBersaglioMe) ? `${attore} ha risposto, nella tua scheda "${titolo_scheda}", ad un commento ${bersaglio}` : `${attore} ha risposto ad un commento nella scheda "${titolo_scheda}"`;
    } else if (descrizione === "Eliminazione Commento") {
        resoconto = (isBersaglioMe) ? `${attore} ha eliminato un tuo commento` : `${attore} ha eliminato un commento, a ${realBersaglio}, nella scheda "${titolo_scheda}"`;
    } else if (descrizione === "Revocazione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ti ha revocato dall'incarico della scheda "${titolo_scheda}"}` : `${attore} ha revocato l'incarico della scheda a ${realBersaglio}`;
    } else if (descrizione === "Assegnazione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ti ha assegnato all'incarico della scheda "${titolo_scheda}"` : `${attore} ha assegnato l'incarico della scheda a ${realBersaglio}`;
    } else if (descrizione === "Riassegnazione Scheda") {
        resoconto = (isBersaglioMe) ? `${attore} ti ha riassegnato l'incarico della scheda "${titolo_scheda}"` : `${attore} ha riassegnato l'incarico della scheda a ${realBersaglio}`;
    } else if (descrizione === "Creazione Categoria") {
        resoconto = `${attore} ha creato la categoria ${stato}`;
    } else if (descrizione === "Eliminazione Categoria") {
        resoconto = `${attore} ha eliminato la categoria ${stato}`;
    } else if (descrizione === "Oscuramento Categoria") {
        resoconto = `${attore} ha oscurato la categoria ${stato}`;
    } else if (descrizione === "Visualizzazione Categoria") {
        resoconto = `${attore} ha reso visibile la categoria ${stato}`;
    } else return;
    let elHTML = `
    <div id="repo-${uuid_report}" data-uuid="${uuid_report}" data-href="${link}" style="background-color: ${colore}; border: 2px groove ${colore}; "><h6>${formattaDataCon(true, timestamp)}</h6><h2>${resoconto}</h2></div>
    `;
    return elHTML;
}