let progettiSuHTML = [];
let schedeSuHTML = [];
let reportsSuHTML = [];
const innerProgetti = document.querySelector('.progetti');
const innerSchedeAssegnate = document.querySelector('.schede-assegnate');
const innerReports = document.querySelector(".nav-container");

function ricavaDatiHome(dati, messaggio, chi, ruolo) {
    document.getElementById('welcome-msg').innerText = 'Bentornat' + chi['suffisso'] + ", " + chi['nome'] + " " + chi['cognome'] + ".";
    let progettiDaDB = dati.progetti;
    let schedeDaDB = dati.schede_assegnate;
    let reportsDaDB = dati.reports;
    if (!progettiDaDB) progettiDaDB = [];
    verificaElementi(progettiDaDB, progettiSuHTML, "progetti", innerProgetti).then((elementiSuHTML) => {
        progettiSuHTML = elementiSuHTML;
    }).catch((errore) => {
        console.trace("Errore: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore: " + errore.message, tipoNotifica: "special-errore-notifica"});
    });
    if (!schedeDaDB) schedeDaDB = [];
    verificaElementi(schedeDaDB, schedeSuHTML, "schede-assegnate", innerSchedeAssegnate).then((elementiSuHTML) => {
        schedeSuHTML = elementiSuHTML;
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
            if (tipologia === "progetti") {
                tupla = document.querySelector(`#post-${elementoHTML['id']}`); 
            } else if (tipologia === "schede-assegnate") {
                tupla = document.querySelector(`#assign-${elementoHTML['id']}-${elementoHTML['uuid'].toLowerCase()}`);
            } else if (tipologia === "reports") {
                let idRepo = `${elementoHTML.uuid_report}`;
                tupla = document.querySelector(`#repo-${idRepo}`);
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
            if (tipologia === "progetti") {
                tupla = document.querySelector(`#post-${suEntrambi['id']}`); 
            } else if (tipologia === "schede-assegnate") {
                tupla = document.querySelector(`#assign-${suEntrambi['id']}-${suEntrambi['uuid'].toLowerCase()}`);
            } else if (tipologia === "reports") {
                let idRepo = `${suEntrambi.uuid_report}`;
                tupla = document.querySelector(`#repo-${idRepo}`);
            }
            if (tupla) update(suEntrambi, tipologia, contenitore);
        });
        elementiSuHTML = elementiDaDB;
        // Finito tutto, tutti gli elementi in HTML coincideranno con tutti gli elementi nel DB 
        resolve(elementiSuHTML);
    });
}

function update(elDiArray, tipologia, contenitore) {
    let elHTML = '';
    if (tipologia === 'progetti') {
        elHTML = `
        <a href="board.html?proj=${elDiArray['id']}" class="progetto" id="post-${elDiArray['id']}"><h1>${elDiArray['nome_progetto']}</h1></a>
        `;
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        (contenitore.children.length > 0 ) ? contenitore.insertBefore(template.content, contenitore.firstChild) : contenitore.appendChild(template.content);
    } else if (tipologia === 'schede-assegnate') {
        elHTML = `
        <a href="board.html?proj=${elDiArray['id']}&post=${elDiArray['uuid'].toLowerCase()}" class="scheda-assegnata" id="assign-${elDiArray['id']}-${elDiArray['uuid'].toLowerCase()}"><h1>${elDiArray['nome_scheda']}</h1></a>
        `;
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        (contenitore.children.length > 0 ) ? contenitore.insertBefore(template.content, contenitore.firstChild) : contenitore.appendChild(template.content);
    } else if (tipologia === 'reports') {
        elHTML = aggiornoReport(elDiArray.uuid_report, elDiArray.timestamp, elDiArray.attore, elDiArray.descrizione, elDiArray.link, elDiArray.bersaglio, elDiArray.team_responsabile, elDiArray.stato, elDiArray.attore_era, elDiArray.bersaglio_era, elDiArray.colore_hex, elDiArray.titolo_scheda, elDiArray.isBersaglioMe, elDiArray.isAttoreMe, elDiArray.incaricato, elDiArray.isIncaricatoMe, elDiArray.progetto);
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        contenitore.appendChild(template.content);
    }
}

function aggiornoReport(uuid_report, timestamp, attore, descrizione, link, bersaglio, team_responsabile, stato, attore_era, bersaglio_era, colore_hex, titolo_scheda, isBersaglioMe, isAttoreMe, incaricato, isIncaricatoMe, progetto) {
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
    <div id="repo-${uuid_report}" data-uuid="${uuid_report}" data-href="${link}" style="background-color: ${colore}; border: 2px groove ${colore}; "><h6>${formattaDataCon(true, timestamp)}</h6><h6>[${progetto}]</h6><h2>${resoconto}</h2></div>
    `;
    return elHTML;
}