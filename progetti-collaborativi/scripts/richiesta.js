let bottoneEsci = document.querySelector(".logout");
let xhr; // Ho definito la variabile di richiesta xhr qui
let msgN, typeN; // Queste mi servono per le variabili di notifica



const NuovaRichiestaHttpXML = {
    //richiesta http asincrona verso un server senza ricaricare la pagina
    mandaRichiesta: function(method, url, async, header, valoreHeader, data, callback) {
        xhr = new XMLHttpRequest(); // Assegna xhr qui
        xhr.open(method, url, async);
        xhr.setRequestHeader(header, valoreHeader);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    callback(); 
                } else {
                    msgN = 'Errore: Connessione con il server fallita. Verificare connessione internet e riprovare.'
                    console.log(msgN);
                    Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'errore-notifica',});
                }
            }
        }
        xhr.send(data);
    },

    _isUtenteConnesso: function() {	
        const rispostaServer = JSON.parse(xhr.responseText);
        console.log('Richiesta verifica accesso completata con successo');
        if (rispostaServer.isUtenteConnesso && !rispostaServer.isSessioneScaduta) {
            //L'utente è già connesso
            btnApri.classList.add('--uc');
            bottoneEsci.classList.add('--uc');
            // Se la connessione é recente, notifica con un messaggio di benvenuto
            if (rispostaServer.isSessioneRecente) {
                msgN = "Bentornat" + rispostaServer.chi['suffisso'] + ", " + rispostaServer.chi['nome'] + " " + rispostaServer.chi['cognome'] + "."
                Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'info-notifica',});
            }
        } else if (rispostaServer.isSessioneScaduta && !rispostaServer.isUtenteConnesso) {
            msgN = "Attenzione: La sessione è scaduta. Sei stato disconnesso automaticamente.";
            Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'errore-notifica',});
            console.log('Attenzione: la sessione è scaduta');
            btnApri.classList.remove('--uc');
            bottoneEsci.classList.remove('--uc');
        } else {
            console.log('Nessun utente connesso');
            btnApri.classList.remove('--uc');
            bottoneEsci.classList.remove('--uc');
        }
    },

    verificaUtenteConnesso: () => {
        NuovaRichiestaHttpXML.mandaRichiesta('POST', './services/session.php', true, 'Content-Type', 'application/x-www-form-urlencoded', '', NuovaRichiestaHttpXML._isUtenteConnesso);
    },
}

function logout() {
    NuovaRichiestaHttpXML.mandaRichiesta('POST', './services/logout.php', true, 'Content-Type', 'application/x-www-form-urlencoded', 'disconnessione==true', diLogout);
}

function diLogout() {
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
    NuovaRichiestaHttpXML.verificaUtenteConnesso();
    Notifica.appari({
        messaggioNotifica: msgN,
        tipoNotifica: typeN,
    });
}

