let root = document.documentElement;
let contHeight = 0;
let elHTML;
let teamAssegnabili = [];

elAnteprimaRiassegnazione();

function elAnteprimaRiassegnazione() {
    let elAnteprima = document.querySelectorAll('.ul-inner>li');
    elAnteprima.forEach(li => {
        li.removeEventListener('click', creaForm);
    });
    elAnteprima.forEach(li => {
        li.addEventListener('click', creaForm);
    });
}

let createBtns = document.querySelectorAll('button[class^="crea-"]');
createBtns.forEach(btn => {
    btn.addEventListener('click', creaForm);
});



function creaForm() {
    // console.log("click");
    let target = this;
    let contenitoreTarget = target.parentElement.parentElement.parentNode;
    let opzioniTeamAssegnabili = `<li role="option"></li>`;
    teamAssegnabili.forEach(opzione => {
        let valoreOpz = opzione['team_da_assegnare'];
        opzioniTeamAssegnabili += `<li role="option">${valoreOpz}</li>`;
    })
    // vediamo se il focus é un fratello o 
    let focus = target.nextElementSibling;
    let innerUL = contenitoreTarget.querySelector('.ul-inner');
    check = (target.tagName.toLowerCase() === 'button') ? ((target.tagName.toLowerCase() === 'button') && innerUL.firstElementChild.tagName.toLowerCase() !== 'form') : (focus.nodeType === Node.ELEMENT_NODE && focus.tagName.toLowerCase() === 'li');
        if (check) {
            // console.log(contenitoreTarget = target.parentElement.parentElement.parentNode);
            if (contenitoreTarget.id === 'ap' || contenitoreTarget.classList.contains("anteprima-progetti")) {
                // bisogna verificare si bisogna creare un form per creazione modifica progetto.
                (target.tagName.toLowerCase() === 'button') ? perCreazioneProj(innerUL, opzioniTeamAssegnabili) : perModificaProj(target, opzioniTeamAssegnabili);
            } else {
                // bisogna verificare si bisogna creare un form per creazione modifica team.
                (target.tagName.toLowerCase() === 'button')? perCreazioneTeam(innerUL) : perModificaTeam(target);
            }
            riassegnaEventi();
            Form.riassegnaEventi();
            tempForm = document.querySelector('.--appear');
            if(tempForm) {
                tempForm.removeEventListener('animationend', overflowCustomSelect);
                tempForm.addEventListener('animationend', overflowCustomSelect);
            }
           
            function overflowCustomSelect() {
                // rendo l'overflow del .custom-select visibile
                let ov = document.querySelector('.custom-select');
                ov.style.overflow = 'visible';
                ov.style.setProperty('z-index', '85');
                distanzaDalBordoTop(document.getElementById('--edit'), -35);
            }
            
        } else {
            // voglio evitare glitch grafici quando premo piú volte il pulsante di chiusura
            // Quindi controllo se l'animazione per l'elemento creato é in corso
            let trueFocus = document.getElementById('--edit');
            if (trueFocus) {
                focus = trueFocus;
            }
            var stileFocus = window.getComputedStyle(focus);
            var isAnimazioneInCorso = stileFocus.animationName !== 'none';
            if (!isAnimazioneInCorso || (isAnimazioneInCorso && focus.classList.contains('--appear'))) {
                contHeight = focus.offsetHeight;
                root.style.setProperty('--contHeight', `${contHeight}px`);
                if (isAnimazioneInCorso && focus.classList.contains('--appear')) {
                    focus.classList.remove('--appear');
                }
                focus.classList.add('--dismiss');
                let figli = focus.querySelectorAll('*');
                figli.forEach(figlio => {
                    figlio.classList.add('--dismissRush');
                });
                focus.addEventListener('animationend', () => {
                    focus.remove();
                }); 
            } 
        }   
}

// Creo una funzione che crea un template, il cui contenuto viene inserito in posizioni definite
function creazioneTemplate(elementoHTML, precede = true, riferimento, isContenitore = true, id) {
    var isFormAlreadyAperto = document.getElementById(id);
    if (isFormAlreadyAperto) {
        isFormAlreadyAperto.remove();
        // se la tendina di selezione del team é aperta purtroppo non si chiude sempre correttamente quindi mettiamo un timeout che si occupa di chiuderlo dopo la rimozione di un form 
        setTimeout(() => {
            let listbox = document.querySelector('[role="listbox"]');
            if (listbox) {
                console.log('listbox clicked')
                if (listbox.classList.contains('--attivo')) {
                    listbox.classList.remove('--attivo');
                }
            }
        }, 100);
    } 
    const template = document.createElement('template');
    template.innerHTML = elementoHTML;
    if (isContenitore) {
        // se il riferimento é un contenitore (isContenitore = true), allora decido se il nuovo figlio precede o meno i suoi fratelli
        precede ? riferimento.insertBefore(template.content, riferimento.firstChild) : riferimento.appendChild(template.content);
    } else {
        // se il riferimento é un fratello (isContenitore = false), allora decido se il nuovo elemento precede o meno i suoi fratelli
        precede ? riferimento.before(template.content) : riferimento.after(template.content);
    }
    nuovo = document.getElementById(id)
    if(nuovo) {
        nuovo.classList.add('--appear');
    }
}

function distanzaDalBordoTop(elemento, offsetH = 0) {
    let contenitore = elemento.parentElement;
    let distanzaDaTop = elemento.offsetTop - contenitore.offsetTop;
    // Se la distanza tra bordo superiore del contenitore e quelo del figlio è diversa da zero, allora riduco la distanza a zero
    if (distanzaDaTop !== 0) {
        contenitore.scroll({
            top: distanzaDaTop + offsetH,
            behavior: 'smooth' // Per avere uno scroll animato
        });
    }
}

// zona per animazione testo //
function bounceScrolling(e) {
    isOverXFlow(e.target);
}

function perCreazioneProj(target, opzioniTeamAssegnabili) {
    elHTML = `
    <form id='--edit' class="proj-selector" method="post" action="services/dashboard_crud.php">
        <div class="nome-progetto">
            <label for="nome-progetto"><h3 nome-progetto=""></h3></label>
            <input type="text" name="nome_progetto" id="nome-progetto" minlength="1" maxlength="50" placeholder="Scegli Nome Progetto">   
        </div>
        <div class="descrizione-progetto">
            <label for="descrizione-progetto"><h3 descrizione-progetto=""></h3></label>
            <textarea name="descrizione_progetto" id="descrizione-progetto" minlength="0" maxlength="255" placeholder="Dai una descrizione"></textarea>
        </div>
        <div class="selezione-team custom-select">
            <label for="selezione-team">Team Responsabile</label>
            <button type="button" role="combobox" id="seleziona" value="" valore="" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false">Assegna a</button>
            <ul role="listbox" id="listbox">
                ${opzioniTeamAssegnabili}
            </ul>
            <input type="hidden" name="selezione_team" id="selezione-team" value="">
        </div>
        <div class="scadenza-progetto">
            <label for="scadenza-progetto">Scadenza</label>
            <input type="datetime-local" step="any" id="scadenza-progetto" name="scadenza_progetto" value="${Form._dataOdierna(0,1)}" min="1970-01-01T00:00:00" valore="${Form._dataOdierna(0,1)}">
        </div>
        <div class="--form-creazione">
            <button type="button" class="--esci-creazione">Esci</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--conferma-creazione">Crea</button>
        </div>
        <input type="submit" name="create_progetto" style="display: none;" disabled>
    </form>
    `
    distanzaDalBordoTop(target.firstChild);
    creazioneTemplate(elHTML, true, target, true, '--edit');
}

function perModificaProj(target, opzioniTeamAssegnabili) {
    // Mi porto prima con l'elemento selezionato piú in alto possibile purché visibile
    distanzaDalBordoTop(target);
    let idProj = target.getAttribute("id");
    let idFoProj = idProj.substring(5); // Ci serve da inviare l'id senza la parola proj-
    let nameProj = target.getAttribute("nmp");
    let descProj = target.getAttribute("dep");
    let placProj = descProj ? "" : "Nessuna Descrizione";
    let deadProj = target.getAttribute("scp");
    let abbTProj = target.getAttribute("stp");
    let teamProj = target.getAttribute("tmp");
    let concProj = (abbTProj === "null" || abbTProj === "" ) ? "" : `${abbTProj} | ${teamProj}`;
    let selProj = (abbTProj === "null" || abbTProj === "" ) ? "Seleziona" : `${abbTProj} | ${teamProj}`;
    elHTML = `
    <form id='--edit' idProj="${idProj}" class="proj-selector" method="post" action="services/dashboard_crud.php">
        <div class="nome-progetto">
            <label for="nome-progetto"><h3 nome-progetto="">${nameProj}</h3></label>
            <input type="text" name="nome_progetto" id="nome-progetto" disabled>   
        </div>
        <div class="descrizione-progetto">
            <label for="descrizione-progetto"><h3 descrizione-progetto="">${descProj}</h3></label>
            <textarea name="descrizione_progetto" id="descrizione-progetto" placeholder="${placProj}" disabled></textarea>
        </div>
        <div class="selezione-team custom-select">
            <label for="selezione-team">Team Responsabile</label>
            <button type="button" role="combobox" id="seleziona" class="--arrow-invisibile" value="${concProj}" valore="${concProj}" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false" disabled>${selProj}</button>
            <ul role="listbox" id="listbox">
                <li role="option">${concProj}</li>
                ${opzioniTeamAssegnabili}
            </ul>
            <input type="hidden" name="selezione_team" id="selezione-team" value="" disabled>
        </div>
        <div class="scadenza-progetto">
            <label for="scadenza-progetto">Scadenza</label>
            <input type="datetime-local" step="any" id="scadenza-progetto" name="scadenza_progetto" value="${deadProj}" min="1970-01-01T00:00:00" valore="${deadProj}" disabled>
        </div>
        <div class="--form-disattivo">
            <button type="button" class="--elimina-tupla">Elimina</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--abilita-modifica">Modifica</button>
        </div>
        <div class="--form-attivo --invisibile">
            <button type="button" class="--disabilita-modifica">Annulla</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--submit-form">Conferma</button>
        </div>
        <input type="hidden" name="id_progetto" value="${idFoProj}" valore=${idFoProj} disabled>
        <input type="submit" name="edit_progetto" style="display: none;" disabled>
    </form>
    `
    // Creo un template il cui contenuto sará copiato prima del fratello successivo dell'elemento cliccato
    creazioneTemplate(elHTML, false, target, false, '--edit');
    let labelForNome = document.querySelector('[nome-progetto]');
    if (labelForNome) {
        labelForNome.removeEventListener("mouseover", bounceScrolling);
        labelForNome.addEventListener("mouseover", bounceScrolling);
    }
}

function perCreazioneTeam(target) {
    elHTML = `
    <form id='--edit' class="team-selector" method="post" action="services/dashboard_crud.php">
        <div class="sigla-team">
            <label for="sigla-team"><h3 sigla-team=""></h3></label>
            <input type="text" name="sigla_team" id="sigla-team" minlength="3" maxlength="3" placeholder="Scegli Sigla Team">   
        </div>
        <div class="nome-team">
            <label for="nome-team"><h3 nome-team=""></h3></label>
            <input type="text" name="nome_team" id="nome-team" minlength="1" maxlength="20" placeholder="Scegli Nome Team">   
        </div>
        <div class="selezione-responsabile custom-select">
            <label for="selezione-responsabile">Responsabile Team</label>
            <button type="button" role="combobox" id="seleziona" value="" valore="" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false">Assegna a</button>
            <ul role="listbox" id="listbox">
            </ul>
            <input type="hidden" name="selezione_responsabile" id="selezione-responsabile" value="">
        </div>
        <div class="--form-creazione">
            <button type="button" class="--esci-creazione">Esci</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--conferma-creazione">Crea</button>
        </div>
        <input type="submit" name="create_team" style="display: none;" disabled>
    </form>
    `
    distanzaDalBordoTop(target.firstChild);
    creazioneTemplate(elHTML, true, target, true, '--edit');
    //Dobbiamo fare una richiesta http per aggiornare e ricercare gli utenti assegnabili come capo
    ricercaUtentiLiberi();
}

function perModificaTeam(target) {
    // Mi porto prima con l'elemento selezionato piú in alto possibile purché visibile
    distanzaDalBordoTop(target);
    let idTeam = target.getAttribute("id");
    let idFoTeam= idTeam.substring(5); // Ci serve da inviare l'id senza la parola team-
    let siglTeam = target.getAttribute("sgt");
    let nameTeam = target.getAttribute("nmt");
    let pla1Team = siglTeam ? "" : "Sigla Team"
    let pla2Team = nameTeam ? "" : "Nome Team";
    let numPTeam = target.getAttribute("npt");
    let respTeam = target.getAttribute("rst");
    let anutTeam = target.getAttribute("aut");
    let concTeam = `${anutTeam} | ${respTeam}`;
    // let selTeam = (abbTProj === "null" || abbTProj === "" ) ? "Seleziona" : `${abbTProj} | ${teamProj}`;
    elHTML = `
    <form id='--edit' idTeam="${idTeam}" class="team-selector" method="post" action="services/dashboard_crud.php">
        <div class="nProgetti-team">
            <h3 nProgetti-team="">Numero Progetti Assegnati: ${numPTeam}</h3>
        </div>
        <div class="sigla-team">
            <label for="sigla-team"><h3 sigla-team="">Sigla: ${siglTeam}</h3></label> 
        </div>
        <div class="nome-team">
            <label for="nome-team"><h3 nome-team="">${nameTeam}</h3></label>
            <input type="text" name="nome_team" id="nome-team" minlength="1" maxlength="20" disabled>   
        </div>
        <div class="selezione-responsabile custom-select">
            <label for="selezione-responsabile">Responsabile Team</label>
            <button type="button" role="combobox" id="seleziona" class="--arrow-invisibile" value="${concTeam}" valore="${concTeam}" tabindex="0" aria-controls="listbox" aria-haspopup="listbox" aria-expanded="false" disabled>${concTeam}</button>
            <ul role="listbox" id="listbox">
            </ul>
            <input type="hidden" name="selezione_responsabile" id="selezione-responsabile" value="" disabled>
        </div>
        <div class="--form-disattivo">
            <button type="button" class="--elimina-tupla">Elimina</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--abilita-modifica">Modifica</button>
        </div>
        <div class="--form-attivo --invisibile">
            <button type="button" class="--disabilita-modifica">Annulla</button>
            <div class="vertical-rule"></div>
            <button type="button" class="--submit-form">Conferma</button>
        </div>
        <input type="hidden" name="id_team" value="${idFoTeam}" valore=${idFoTeam} disabled>
        <input type="submit" name="edit_team" style="display: none;" disabled>
    </form>
    `
    // Creo un template il cui contenuto sará copiato prima del fratello successivo dell'elemento cliccato
    creazioneTemplate(elHTML, false, target, false, '--edit');
    //Dobbiamo fare una richiesta http per aggiornare e ricercare gli utenti assegnabili come capo
    ricercaUtentiLiberi(concTeam, siglTeam);
    let labelForNome = document.querySelector('[nome-progetto]');
    if (labelForNome) {
        labelForNome.removeEventListener("mouseover", bounceScrolling);
        labelForNome.addEventListener("mouseover", bounceScrolling);
    }
}

function ricercaUtentiLiberi(primaOpzione = "", alreadyIn = "N/A") {
    let datiNonJson = {
        recupera_team: alreadyIn
    };
    let datiJson = JSON.stringify(datiNonJson);
    let opzioniRespAssegnabili = `<li role="option">${primaOpzione}</li>`
    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/dashboard.php", true, 'Content-Type', 'application/json', datiJson , ricercaTeam);
    function ricercaTeam() {
        // console.log(xhr.responseText);
        const rispostaServer = JSON.parse(xhr.responseText);
        // Creo una variabile per memorizzare il messaggio di sistema
        const sm = rispostaServer.messaggio;
        const info = rispostaServer.info;
        if (sm.includes('Errore') || sm.includes('negato')) {
            Notifica.appari({messaggioNotifica: sm, tipoNotifica: 'special-errore-notifica'})
        }
        if (!rispostaServer.isAdmin) {
            window.location.href = 'login.html';
        } else {
            let respAssegnabili = info[13];
            respAssegnabili.forEach(opzione => {
                let anagraficaOpz = opzione['anagrafica']
                let valoreOpz = opzione['email'];
                opzioniRespAssegnabili += `<li role="option">${anagraficaOpz} | ${valoreOpz}</li>`;
                // console.log(opzioniRespAssegnabili);
            });
            let listbox = document.getElementById('listbox');
            creazioneTemplate(opzioniRespAssegnabili, true, listbox, true);
        } 
    }
}