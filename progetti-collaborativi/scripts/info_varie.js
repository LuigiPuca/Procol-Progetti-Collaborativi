let tastoDiProva = document.querySelector('.procol img');
tastoDiProva.addEventListener('click', () => {
    richiestaEstrazioneInfo();
    
});
function richiestaEstrazioneInfo(){
    // Ottengo il messaggio e il tipo salvati nel localStorage del browser prima del reindirizzamento
    let messaggioRicevuto = localStorage.getItem('messaggio');
    let tipoRicevuto = localStorage.getItem('tipoMessaggio');
    if (messaggioRicevuto && tipoRicevuto) {
        NuovaRichiestaHttpXML.verificaUtenteConnesso();
        Notifica.appari({
            messaggioNotifica: messaggioRicevuto,
            tipoNotifica: tipoRicevuto,
        });
        // Rimuovo le variabili dallo storage del browser
        localStorage.removeItem('messaggio');
        localStorage.removeItem('tipoMessaggio');
    }
    const currentURL = window.location.pathname;
    const homeURLs = [
        "/progetti-collaborativi/",
        "/progetti-collaborativi/home.html"
    ];
    const teamURL = "/progetti-collaborativi/team.html";
    const boardURL = "/progetti-collaborativi/board.html";
    let queryString = `./services/info_varie.php`;
    let data = "";
    if (homeURLs.includes(currentURL)) {
        let sezione = document.querySelector('[page="home"]');
        sezione.classList.add('--sez-attiva');
        data = 'home=true';
    } else if(teamURL === currentURL) {
        let sezione = document.querySelector('[page="team"]');
        sezione.classList.add('--sez-attiva');
        data = 'team=true';
    } else if (boardURL === currentURL) {
        let sezione = document.querySelector('[page="board"]');
        sezione.classList.add('--sez-attiva');
        data = 'board=true';
        if(isParametroUgualeA('proj').presente){
            const idProgetto = isParametroUgualeA('proj').progetto;
            if(idProgetto) {
                queryString = `./services/info_varie.php?proj=${idProgetto}`;
                console.log("la querystring è", queryString);
            } else {
                window.location.href = 'home.html';
            }
        } else {
            localStorage.setItem('messaggio', "Oops, accesso negato. La pagina potrebbe non esistere");
            localStorage.setItem('tipoMessaggio', "special-notifica");
            window.location.href = 'home.html';
        }
    } else {
        alert("Non sei su una pagina esistente");
    }
    NuovaRichiestaHttpXML.mandaRichiesta('POST', queryString, true, 'Content-Type', 'application/x-www-form-urlencoded', data, function() {
        verificaConnessione()
            .then(rispostaServer => {
                if (rispostaServer) {
                    // Salvo i dati in un array
                    if (rispostaServer.sezione === 'home') {
                        ricavaDatiHome(rispostaServer.dati, rispostaServer.messaggio, rispostaServer.chi, rispostaServer.ruolo);
                    } else if (rispostaServer.sezione === 'team') {
                        ricavaDatiTeam(rispostaServer.dati, rispostaServer.messaggio, rispostaServer.ruolo, rispostaServer.team);
                    } else if (rispostaServer.sezione === 'board' && rispostaServer.dati.progetto) {
                        ricavaDatiBoard(rispostaServer.dati, rispostaServer.messaggio, rispostaServer.ruolo);
                    }
                    let tendinaProgetti = document.getElementById('d0');
                    let contenutoTendina = rispostaServer.dati.progetti ? rispostaServer.dati.progetti.map(progetto => `<a href="board.html?proj=${progetto.id}">${progetto.nome_progetto}</a>`).join('') : '';
                    let btnProgetti = document.getElementById('d0').parentElement.parentElement;
                    (contenutoTendina) ? btnProgetti.classList.remove("--hidden") : btnProgetti.classList.add('--hidden');
                    tendinaProgetti.innerHTML = contenutoTendina;
                }
            }).catch(errore => {
                Notifica.appari({messaggioNotifica: "Ops! Sembra ci sia un problema di connessione. Controlla la tua connessione a internet e al DB, e ricarica!", tipoNotifica: "special-notifica"});
                // console.error('Errore in verifica connessione', errore);
                console.trace("Errore in verifica connessione: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
            });
    });
}
function verificaConnessione() {
    return new Promise((resolve, reject) => {
        // console.log(xhr.responseText);
        const rispostaServer = JSON.parse(xhr.responseText);
        console.log('Richiesta verifica accesso completata con successo');
        if (rispostaServer.messaggio.startsWith('Errore: Progetto inesistente o inaccessibile') || rispostaServer.messaggio.startsWith('Errore: Il progetto \u00e8 inaccessibile o inesistente')) {
            // reindirizzamento alla home
            localStorage.setItem('messaggio', rispostaServer.messaggio);
            localStorage.setItem('tipoMessaggio', "special-errore-notifica");
            window.location.href = 'home.html';
        }
        if (rispostaServer.isUtenteConnesso && !rispostaServer.isSessioneScaduta) {
            //L'utente è già connesso
            btnApri.classList.add('--hidden');
            bottoneEsci.classList.remove('--hidden');
            // Se la connessione é recente, notifica con un messaggio di benvenuto
            if (rispostaServer.isSessioneRecente) {
                msgN = "Bentornat" + rispostaServer.chi['suffisso'] + ", " + rispostaServer.chi['nome'] + " " + rispostaServer.chi['cognome'] + "."
                Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'special-notifica',});
            }
            // Decido quali elementi mostrare in base al ruolo
            if (rispostaServer.ruolo === 'admin') {
                document.querySelector('.admin-dashboard').classList.remove('--hidden');
            } else {
                document.querySelector('.admin-dashboard').classList.add('--hidden');
            }
            // E quali in base all'esistenza di un team
            if (rispostaServer.team) {
                document.querySelector('.team-nav').classList.remove('--hidden');
            } else {
                document.querySelector('.team-nav').classList.add('--hidden');
            }     
            
            let iconaUtente = document.querySelector('.img-container');
            if (rispostaServer.chi.cognome && rispostaServer.chi.cognome && rispostaServer.chi.suffisso) {
                let nome = rispostaServer.chi.nome;
                let cognome = rispostaServer.chi.cognome;
                let suffisso = rispostaServer.chi.suffisso;
                let primaLetteraNome = nome.charAt(0).toUpperCase();
                let primaLetteraCognome = cognome.charAt(0).toUpperCase();  
                iconaUtente.innerHTML = `<div class="icona-utente" genere="${suffisso}">${primaLetteraNome} ${primaLetteraCognome}</div>`;
            } else {
                iconaUtente.innerHTML = `<img src="assets/svg-images/dashboard/account.svg"`;
            }
            // risolviamo la promise passando la risposta al server
            resolve(rispostaServer);
            
            
        } else if (rispostaServer.isSessioneScaduta && !rispostaServer.isUtenteConnesso) {
            msgN = "Attenzione: La sessione è scaduta. Sei stato disconnesso automaticamente.";
            Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'special-attenzione-notifica',});
            console.log('Attenzione: la sessione è scaduta');
            btnApri.classList.remove('--hidden');
            bottoneEsci.classList.add('--hidden');
            localStorage.setItem('messaggio', "Oops accesso negato! Sessione scaduta.");
            localStorage.setItem('tipoMessaggio', "info-notifica");
            window.location.href = 'portal.html';
            resolve(null);
        } else {
            console.log('Nessun utente connesso');
            btnApri.classList.remove('--hidden');
            bottoneEsci.classList.add('--hidden');
            localStorage.setItem('messaggio', "Oops accesso negato! Nessuna sessione attiva.");
            localStorage.setItem('tipoMessaggio', "info-notifica");
            window.location.href = 'portal.html';
            resolve(null);
        }
    });
    
}
function esciDaDashboard() {
    NuovaRichiestaHttpXML.mandaRichiesta('POST', './services/controllers/logout.php', true, 'Content-Type', 'application/x-www-form-urlencoded', 'disconnessione=true', esci);
}
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

window.addEventListener('popstate', richiestaEstrazioneInfo);
window.addEventListener('load', richiestaEstrazioneInfo);


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
    const valoreStringa = hasStringa ? parseInt(paramValues[stringa], 10) : null;
    const hasProj = urlParams.has('proj');
    const valoreProj = hasProj ? parseInt(paramValues['proj'], 10) : null;
    const hasPost = urlParams.has('proj') && urlParams.has('post');
    let valorePost = hasPost ? (/^[0-9A-Fa-f]{32}$/.test(paramValues['post']) ? paramValues['post'] : null) : null;
    valorePost = hasPost ? paramValues['post'] : null;
    // if (isNaN(num) || num < 0) {
    //     // Se il risultato non è un numero o è negativo, restituisci 0 o un altro valore predefinito
    //     return null;
    // }
    // console.log("Numero totale di parametri:", paramKeys.length);
    // console.log("Valori dei parametri:", urlParams.toString());
    // console.log("Parametro presente:", hasStringa);
    // console.log("Parametro 'post' presente:", hasPost);
    if (hasPost) {
        // console.log('valore del POST:', valorePost);
    }
    const isSingoloParam = (numParams === 1 && hasStringa);
    const isDoppioParamConPagina = (numParams === 2 && hasPost);
    // console.log("Vero o falso:", isSingoloParam || isDoppioParamConPagina);
    return {
        presente: isSingoloParam || isDoppioParamConPagina,
        isPostPresente: isDoppioParamConPagina,
        progetto: valoreStringa,
        scheda: valorePost
    }
}

function pushState(urlCorrente) {
    window.history.pushState({}, '', urlCorrente);
    richiestaEstrazioneInfo();
}