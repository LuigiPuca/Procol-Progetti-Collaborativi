/*SEZIONE DEDICATA AD INSERIMENTO DI NUOVE SCHEDE*/
function addPost(event) {
    toggleAddBtn();
    //aggiungo un form nel genitore del bottone che ho cliccato
    const btn = event.target;
    const colonnaInModifica = btn.parentElement.parentNode; 
    const form = document.createElement('form');
    const pattern = /^[a-zA-Z0-9]{1}[$£€¥&@#ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿa-zA-Z0-9 \s \' \. \, \: \; \! \? \% \-]{0,49}$/;
    const submitID = 'crea-scheda';
    const regexps = [/^.+$/, /^.{1,50}$/, /^[a-zA-Z0-9][$£€¥&@#ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿa-zA-Z0-9 \s \' \. \, \: \; \! \? \% \-]*$/, /^[^$£€¥&@#ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿ \s \' \. \, \: \; \! \? \% \-].*$/];
    const messaggi = ["-Titolo obbligatorio", "-Massimo 50 caratteri", "-Oltre agli spazi e le lettere accentate, gli unici simboli utilizzabili sono ' £ € ¥ & @ #. , : ; ! ? % - ", "-Primo Carattere non ammette spazi, simboli o lettere accentate"];
    form.id = '--post-form'; //classe aggiunta al form
    form.setAttribute("method", "post"); //metodo post settato al form
    colonnaInModifica.appendChild(form); //form inserito all'ultimo posto della colonna
    toggleAddBtn();

    /* Aggiunta dell'input + tasti (tutto preformattato grazie a CSS) attraverso testo interno*/
    const innerHTML = `
    <input type="text" id="titolo" name="titolo" placeholder="Inserire titolo scheda" required minlength="1" maxlength="50" autocomplete="off" autofocus/>
    <button type="button" id="esci">✗</button>
    <button type="button" id="conferma">➔</button>
    <button type="submit" id="crea-scheda" name="crea_scheda" style="display: none;" disabled>➔</button>
    <span id="contatore">50</span>
    `; 
    let postForm = document.getElementById('--post-form');
    let template = document.createElement('template');
    template.innerHTML = innerHTML;
    postForm.appendChild(template.content);
    //Aggiunta di un listener per il submit del form appena creato
    postForm.addEventListener('submit', creaScheda);
    //Focus automatico impostato all'apparizione della casella di input
    document.getElementById('titolo').focus();
    //viene centrata la casella di input quando essa appare e quando si ritorna in focus
    const focussato = document.getElementById('titolo');
    accentra(focussato);
    focussato.addEventListener('focus', (event) => { accentra(event.target); });
    const inputElement = document.getElementById('titolo');
    //Check in real time per verifica rispetto vincoli (colore del bordo che cambia e conteggio caratteri rimanenti)
    inputElement.addEventListener('input', realtime.bind(null, 25, pattern));
    //Check per verificare se invio o esc sono premuti
    
    handleButtons(inputElement, pattern, submitID, regexps, messaggi);
}

function toggleAddBtn() {
    let formTemporaneo = document.querySelector('#--type-form, #--post-form');
    // console.log("esiste il form temporaneo?", formTemporaneo);
    document.querySelectorAll('.add-post').forEach(function(addButton) {
        addButton.style.display = (!formTemporaneo) ? 'block' : 'none';
         //facciamo sparire tutti i bottoni + al click            
    }); 
    const acBtn = document.querySelector('#add-category');
    acBtn.style.display = (!formTemporaneo) ? 'block' : 'none';
         //facciamo sparire il testo del bottone di aggiunta categoria al click  
}

function addCategory(event) {
    toggleAddBtn();
    //aggiungo un form nel genitore del bottone che ho cliccato
    const btnContainer = document.querySelector('#a-c-Container'); 
    const form = document.createElement('form');
    const pattern = /^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/;
    const submitID = 'crea-categoria';
    const regexps = [/^.+$/, /^.{1,20}$/, /^[a-zA-Z0-9\s]+$/, /^[^\s].*$/];
    const messaggi = ["-Titolo obbligatorio", "-Massimo 20 caratteri", "-Solo Caratteri Alfanumerici e spazi", 
    "-No Spazio Iniziale"];
    form.id = '--type-form'; //classe aggiunta al form
    form.setAttribute("method", "post"); //metodo post settato al form
    btnContainer.appendChild(form); //form inserito all'ultimo posto della colonna
    toggleAddBtn();

    /* Aggiunta dell'input + tasti (tutto preformattato grazie a CSS) attraverso testo interno*/
    const innerHTML = `
    <input type="text" id="titolo" name="titolo" placeholder="Inserire titolo categoria" required minlength="1" maxlength="20" autocomplete="off" autofocus/> 
    <button type="button" id="esci">✗</button>
    <button type="button" id="conferma">➔</button>
    <button type="submit" id="crea-categoria" name="crea_categoria" style="display: none;" disabled>➔</button>
    <span id="contatore">20</span>
    <label for="colore">Seleziona un colore:</label>
    <input type="color" id="colore" name="colore" value="#4d057b">
    <label for="opacita">Opacità:</label>
    <input type="range" id="opacita" name="opacita" min="0" max="1" step="0.01" value="1">
    <input type="hidden" id="hexColor" name="hex_color" value="">
    `;
    let typeForm = document.getElementById('--type-form');
    let template = document.createElement('template');
    template.innerHTML = innerHTML;
    typeForm.appendChild(template.content);
    // Aggiunta di un listener per il submit del form appena creato
    typeForm.addEventListener('submit', creaCategoria);
    //Focus automatico impostato all'apparizione della casella di input
    document.getElementById('titolo').focus();
    //viene centrata la casella di input quando essa appare e quando si ritorna in focus
    const focussato = document.getElementById('titolo');
    accentra(focussato);
    focussato.addEventListener('focus', (event) => { accentra(event.target); });
    const inputElement = document.getElementById('titolo');
    //Check in real time per verifica rispetto vincoli (colore del bordo che cambia e conteggio caratteri rimanenti)
    inputElement.addEventListener('input', realtime.bind(null, 10, pattern));
    //Check per verificare se invio o esc sono premuti 
    
    handleButtons(inputElement, pattern, submitID, regexps, messaggi);
}

function rispostaBoardCRUD() {
    // console.log(xhr.responseText);
    const rispostaServer = JSON.parse(xhr.responseText);
    console.log('Richiesta per creazione categoria completata con successo');
    if (rispostaServer.isUtenteConnesso && !rispostaServer.isSessioneScaduta) {
        const inputElement = document.getElementById('titolo');
        if (rispostaServer.messaggio.includes('Errore')) {
            Notifica.appari({messaggioNotifica: rispostaServer.messaggio, tipoNotifica: "special-errore-notifica"});
            if(inputElement) inputElement.style.outline = "2px inset rgba(236, 31, 31, 0.2)";
            if(inputElement) setTimeout(() => {inputElement.style.outline = "none";}, 1000);
        } else {
            notificationType = (rispostaServer.messaggio.includes('Attenzione')) ? "special-attenzione-notifica" : "special-successo-notifica";
            Notifica.appari({messaggioNotifica: rispostaServer.messaggio, tipoNotifica: notificationType});
            if(inputElement) inputElement.blur();
            let formTemporaneo = document.querySelector('#--type-form, #--post-form');
            if(formTemporaneo) formTemporaneo.remove(); //distruzione form e dei suoi listener
                      
        }
    } else if (!rispostaServer.isUtenteConnesso && !rispostaServer.isSessioneScaduta) {
        msgN = 'Attenzione: Nessuna sessione esistente. <a href="login.html" target="_blank">Accedi</a>';
        Notifica.appari({messaggioNotifica: msgN, tipoNotifica: 'special-attenzione-notifica',});
    }
    richiestaEstrazioneInfo();
    toggleAddBtn(); //Riapparizione dei pulsanti "+"
    aggiornoIcone();
    aggiornoEventi();
}

// Funzione per convertire opacità in formato HEX
function convertToHexOpacitySlider(opacita) {
    // Convertiamo il valore dell'opacità in un valore HEX compreso tra 0 e 255
    const valoreOpacita = Math.round(opacita * 255);
    return valoreOpacita.toString(16).padStart(2, '0');
}

//per accentrare la visualizzazione, ad un determinato evento, verso un preciso target
function accentra(target) {
    let inputRect = target.getBoundingClientRect();
    // console.log(inputRect);
    let absoluteElementTop = inputRect.bottom + window.scrollY;
    let middle = absoluteElementTop - (window.innerHeight / 2);
    window.scrollTo({
        top: middle,
        behavior: "smooth"
    });
}

function realtime(avgLungh, regexp, e) {
    //se non é touch l'accentramento automatico non funziona al meglio, quindi lo forziamo noi
    if(!touchScreen) { 
        setTimeout(accentra(e.target) , 1000);
    }
    const testoInserito = e.target.value; 
    const dimTesto = e.target.value.length; //dimensione stringa inserita
    const maxLungh = parseInt(e.target.getAttribute("maxLength")); //lunghezza massima consentita
    const cRestanti = maxLungh - dimTesto; //numero di caratteri restanti
    const contatore = document.getElementById('contatore');
    //aggiorno il testo del contatore con i caratteri restanti
    document.getElementById('contatore').textContent = cRestanti;   
    if ((regexp.test(testoInserito)) && (testoInserito.length < avgLungh)){
        //celeste se l'input é consentito
        e.target.style.border = "2px inset rgb(134, 154, 211)"; 
        contatore.style.color = "rgb(134, 154, 211)";
    } else if(regexp.test(testoInserito) && (testoInserito.length <= maxLungh)) {
        //arancione se ci si avvicina al limite max di caratteri 
        e.target.style.border = "2px inset rgb(241, 116, 33)";
        contatore.style.color = "rgb(241, 116, 33)";
    } else { 
        //rosso se l'input non é consentito
        e.target.style.border = "2px inset rgb(236, 31, 31)";
        contatore.style.color = "rgb(236, 31, 31)";
        if (!(/^\s*$/.test(testoInserito))){
            //Errore se caratteri non consentiti sono stati inseriti... 
            document.getElementById('contatore').innerText = "Err"; 
        } //altrimenti 0 per campo vuoto
    }
}

function handleButtons(inputElement, pattern, submitID, regexps, messaggi) {
    inputElement.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('conferma').click();
        } else if (event.key === 'Escape') {
            document.getElementById('esci').click();
        }
    });
    //premi esci => form e contenuto eliminato, riapparsa del bottone di aggiunta categoria e dei bottoni "+"
    document.getElementById('esci').addEventListener('click',function(e) {
        //distruggo il form ed i listener ad esso relativi
        e.target.parentElement.remove();
        toggleAddBtn(); //fa riapparire i bottoni "+"
        // inputElement.removeEventListener('input', realtime.bind(null, 25, pattern));
    });

    //premi conferma => input valido? Crea scheda con titolo dato dall'input. Altrimenti, errore.
    document.getElementById('conferma').addEventListener('click', function(e) {
        const valoreInput = inputElement.value; //salvataggio temporaneo dell'input
        //confronto in real time tra input e pattern. Se input é valido...
        if (pattern.test(valoreInput)) {
            let submitBtn = document.getElementById(submitID);
            if (submitBtn) submitBtn.disabled = false;
            if (!submitBtn.disabled) submitBtn.click();  
        } else {
            //...Altrimenti mostra suggerimenti per evitare errori
            const erroriRilevati = [];
            let warning = "Ricorda di rispettare i seguenti criteri:";
            regexps.forEach((regExp, index) => {
                if (!regExp.test(valoreInput)) {
                    erroriRilevati.push(messaggi[index]);
                }
            });
            //se ci sono errori, scorro i messaggi di suggerimento
            if (erroriRilevati.length > 0) {
                erroriRilevati.forEach((messaggio) => {
                    warning += "<br>" + messaggio;
                });
            }
            Notifica.appari({messaggioNotifica: warning, tipoNotifica: "special-attenzione-notifica"});
            document.getElementById('titolo').focus(); //ancora focus automatico      
        }
    });
}

function creaScheda(event) {
    const erroreGenerico = "Non puoi eseguire quest'operazione perchè la categoria in cui stai operando non rispetta le specifiche!";
    try {
        event.preventDefault();
        const submit = event.target.querySelector('button[type=submit]');
        let postInfo = event.target.parentElement.id;
        postInfo = postInfo.replace(/_/g," ");
        if (!postInfo.startsWith("type-")) throw new Error(erroreGenerico);
        infos = postInfo.split('-');
        if (!(infos.length === 3 && typeof infos[1] === 'string' && typeof infos[2] === 'string')) throw new Error(erroreGenerico);
        // ... catturo i dati del form (ad eccetto del submit che peró poi aggiungo manualmente) in un oggetto FormData
        const formData = new FormData(event.target);
        formData.append("operazione", submit.name);
        formData.append("board_id", infos[1]);
        formData.append("category_name", infos[2]);
        // formData.forEach(function(value, key){
        //     console.log(key, value);
        // });
        // i dati catturati li voglio convertire in un oggetto classico e poi in un formato JSON 
        const formDataObj = {};
        for (let coppia of formData.entries()) {
            formDataObj[coppia[0]] = coppia[1];
        }
        const jsonData = JSON.stringify(formDataObj);
        // mando una richiesta http
        NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/board_crud.php", true, 'Content-Type', 'application/json', jsonData, rispostaBoardCRUD);
    } catch (errore) {
        console.trace("Errore DOM: " + errore.name+ "\nMessaggio: " + errore.message + "\nStack: " + errore.stack);
        Notifica.appari({messaggioNotifica: "Errore DOM: " + errore.message, tipoNotifica: "special-errore-notifica"});
    }
}

function creaCategoria(event){
    event.preventDefault();
    const submit = event.target.querySelector('button[type=submit]');
    // ... catturo i dati del form (ad eccetto del submit che peró poi aggiungo manualmente) in un oggetto FormData
    const formData = new FormData(event.target);
    formData.append("operazione", submit.name);
    formData.append("board_id", boardId);
    const colore = formData.get("colore");
    const opacita = convertToHexOpacitySlider(formData.get("opacita"));
    formData.delete("colore");
    formData.delete("opacita");
    formData.set("hex_color", colore + opacita);
    // formData.forEach(function(value, key){
    //     console.log(key, value);
    // });
    // i dati catturati li voglio convertire in un oggetto classico e poi in un formato JSON 
    const formDataObj = {};
    for (let coppia of formData.entries()) {
        formDataObj[coppia[0]] = coppia[1];
    }
    const jsonData = JSON.stringify(formDataObj);
    // mando una richiesta http
    NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/board_crud.php", true, 'Content-Type', 'application/json', jsonData, rispostaBoardCRUD);
}

