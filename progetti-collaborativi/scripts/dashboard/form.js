// codifichiamo gli input text per creazione progetti e team
let Form = {
    id: document.getElementById('--edit'),
    selectorID: document.querySelector('#--edit [type="hidden"][name^="id_"]'),
    teamSigla: document.querySelector('#--edit [type="hidden"][name="sigla_progetto"]'),
    siglaTeam: document.querySelector('#--edit input[type="text"][name^="sigla_"]'),
    nome: document.querySelector('#--edit input[type="text"][name^="nome_"]'),
    descrizione: document.querySelector('#--edit textarea'),
    scadenza: document.querySelectorAll('#--edit [type="datetime-local"]')[0],
    selezione: document.querySelector('#--edit [role="combobox"]'),
    opzione: document.querySelector('#--edit [type="hidden"][name^="selezione_"]'),
    back : document.querySelector('#--edit .--esci-creazione'),
    create : document.querySelector('#--edit .--conferma-creazione'),
    editBoxOff: document.querySelector('#--edit .--form-disattivo'),
    deleteBtn: document.querySelector('#--edit .--elimina-tupla'),
    editOn: document.querySelector('#--edit .--abilita-modifica'),
    editBox: document.querySelector('#--edit .--form-attivo'),
    editOff: document.querySelector('#--edit .--disabilita-modifica'),
    dummy: document.querySelector('#--edit .--submit-form'),
    submit: document.querySelector('#--edit input[type="submit"]'),

    stampa() {
        console.log("1" + this.nome);
        console.log("2" + this.descrizione);
        console.log("3" + this.scadenza);
        console.log("4" + this.selezione);
        console.log("5" + this.opzione);
    },

    // Funzione che ci permette di riassegnare gli eventi ai nuovi elementi creati
    riassegnaEventi() {
        // var self = this;
        this._riassegnaElementi();
        let form = this.id;
        let selectorID = this.selectorID;
        let nome = this.nome;
        let textarea = this.descrizione;
        let scadenza = this.scadenza;
        let selezione = this.selezione;
        let opzione = this.opzione;
        let back = this.back;
        let create = this.create;
        let deleteBtn = this.deleteBtn;
        let editOn = this.editOn;
        let editOff = this.editOff;
        let dummy = this.dummy;
        let submit = this.submit;
        if (back) {
            back.removeEventListener('click', this._chiusuraForm.bind(this));
            back.addEventListener('click', this._chiusuraForm.bind(this));
        }
        if (create) {
            create.removeEventListener('click', this._validazioneForm.bind(this));
            create.addEventListener('click', this._validazioneForm.bind(this));
        }
        if (deleteBtn) {
            deleteBtn.removeEventListener('click', this._eliminaTupla.bind(this));
            deleteBtn.addEventListener('click', this._eliminaTupla.bind(this));
        }
        if (editOn) {
            editOn.removeEventListener('click', this._abilitaModificaForm.bind(this));
            editOn.addEventListener('click', this._abilitaModificaForm.bind(this)); //attenzione qui#1
        }
        if (editOff) {
            editOff.removeEventListener('click', this._disabilitaModificaForm.bind(this));
            editOff.addEventListener('click', this._disabilitaModificaForm.bind(this));
        }
        if (dummy) {
            dummy.removeEventListener('click', this._validazioneForm.bind(this));
            dummy.addEventListener('click', this._validazioneForm.bind(this));
        }
        if (form) {
            form.removeEventListener('submit', this._invioDatiForm.bind(this));
            form.addEventListener('submit', this._invioDatiForm.bind(this));
        }
        
    },

    _riassegnaElementi() {
        this.id = document.getElementById('--edit');
        this.selectorID = document.querySelector('#--edit [type="hidden"][name^="id_"]');
        this.teamSigla = document.querySelector('#--edit [type="hidden"][name="sigla_progetto"]'),
        this.siglaTeam = document.querySelector('#--edit input[type="text"][name^="sigla_"]'),
        this.nome = document.querySelector('#--edit input[type="text"][name^="nome_"]');
        this.descrizione = document.querySelector('#--edit textarea');
        this.scadenza = document.querySelectorAll('#--edit [type="datetime-local"]')[0];
        this.selezione = document.querySelector('#--edit [role="combobox"]');
        this.opzione = document.querySelector('#--edit [type="hidden"][name^="selezione_"]');
        this.back = document.querySelector('#--edit .--esci-creazione');
        this.create = document.querySelector('#--edit .--conferma-creazione');
        this.editBoxOff = document.querySelector('#--edit .--form-disattivo');
        this.deleteBtn = document.querySelector('#--edit .--elimina-tupla');
        this.editOn = document.querySelector('#--edit .--abilita-modifica');
        this.editBox = document.querySelector('#--edit .--form-attivo');
        this.editOff = document.querySelector('#--edit .--disabilita-modifica');
        this.dummy = document.querySelector('#--edit .--submit-form');
        this.submit = document.querySelector('#--edit input[type="submit"]');
    },

    _eliminaTupla() {
        let submit = this.submit;
        if (submit.name.startsWith('edit')) {
            console.log('ho premuto elimina tupla');
            submit.name = submit.name.replace("edit", "delete");
            submit.disabled = false;
            this.selectorID.disabled = false;
            if (!submit.disabled) {
                Conferma.apri({
                    titoloBox: "Conferma Eliminazione",
                    messaggioBox: "Vuoi eliminare dal Database l'elemento selezionato?",
                    testoOk: "Elimina",
                    testoNo: "Annulla",
                    allOk: function() {
                        submit.click();
                    },
                    alNo: function() {
                        console.log("Hai premuto Annulla");
                    }
                }); 
            }
        }
    },

    _abilitaModificaForm(e) {
        e.target.parentNode.classList.add("--invisibile");
        this.editBox.classList.remove("--invisibile");
        let inputs = [];
        // verifichiamo se dobbiamo modificare un progetto o un team
        if (this.id.classList.contains("proj-selector")) {
            inputs = [this.nome, this.descrizione, this.scadenza, this.selezione, this.opzione, this.selectorID];
            inputs[3].classList.remove("--arrow-invisibile");
            // inputs.forEach(input => console.log(input));
        } else if (this.id.classList.contains("team-selector")) {
            inputs = [this.selectorID, this.nome, this.selezione, this.opzione];
            inputs[2].classList.remove("--arrow-invisibile");
            // inputs.forEach(input => console.log(input));
        } else {
            return console.log("Abilitazione Modifica Form Fallita a livello JS");
        }
        inputs.forEach(input => {
            input.disabled = false;
            if (input.type === "text" || input.tagName.toLowerCase() == "textarea") {
                let label = input.parentNode.querySelector('h3');
                label.classList.add("--invisibile");
                input.value = label.textContent;
            } else {
                input.value = input.getAttribute("valore");
            }
        });
        this._noListenerDiModificaForm();
    },
    
    _disabilitaModificaForm(e) {
        e.target.parentNode.classList.add("--invisibile");
        this.editBoxOff.classList.remove("--invisibile");
        let inputs = [];
        if (this.id.classList.contains("proj-selector")) {
            inputs = [this.nome, this.descrizione, this.scadenza, this.selezione, this.opzione, this.selectorID];
            inputs[3].classList.add("--arrow-invisibile");
            // inputs.forEach(input => console.log(input));
        } else if (this.id.classList.contains("team-selector")) {
            inputs = [this.selectorID, this.nome, this.selezione, this.opzione];
            inputs[2].classList.add("--arrow-invisibile");
            // inputs.forEach(input => console.log(input));
        } else {
            return console.log("Disabilitazione Modifica Form Fallita a livello JS");
        }
        inputs.forEach(input => {
            input.disabled = true;
            if (input.type === "text" || input.tagName.toLowerCase() == "textarea") {
                let label = input.parentNode.querySelector('h3');
                label.classList.remove("--invisibile");
                input.value = "";
            } else {
                input.value = input.getAttribute("valore");
            }
        });
        this._noListenerDiModificaForm();
    },

    _validazioneForm() {
        let scadenza = (this.scadenza) ? this.scadenza : "";
        let inputs = [];
        let check = [];
        let scadenzaGiornoMin = [];
        let messaggiErr = [];
        if (scadenza !== "") {
            scadenza.value = this._completamentoData(scadenza.value);
            this.scadenza.min = this._dataOdierna();
            inputs = [this.nome, this.descrizione, this.scadenza, this.selezione, this.opzione, this.submit];
            inputs[4].value = (inputs[4].value) ? "" : inputs[3].value.substring(0, 3).toUpperCase();
            check = [this.nome, this.descrizione, this.scadenza, this.opzione];
            regexps = [
                /^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,48}[a-zA-ZÀ-ÿ])?$/,
                /^.{0,255}$/,
                /^\d{4}-\d{2}-\d{2}?(?:T\d{2})?(?::\d{2})?(?::\d{2})?/,
                /^[a-zA-Z0-9]{0,3}$/
            ];
            scadenzaGiornoMin = this._formattaDataDa(check[2].min);
            messaggiErr = [
                "-Nome: caratteri [1-50], solo lettere e spazi (non come unici caratteri)",
                "-Descrizione: caratteri [0-250]",
                "-Scadenza: la data non può essere in un intervallo temporale già superato",
                "-Team: Team non esistente"
            ];
        console.log(messaggiErr);
        } else if (scadenza === ""){
            if (!this.selectorID) {
                inputs = [this.siglaTeam, this.nome, this.selezione, this.opzione, this.submit];
            } else {
                inputs = [this.selectorID, this.nome, this.selezione, this.opzione, this.submit];
            }
            utente = inputs[2].value.split(" | ");
            // console.log(utente[0]);
            // console.log(utente[1]);
            inputs[3].value = utente[1];
            if (!this.selectorID) {
                check = [this.siglaTeam, this.nome, this.opzione];
            } else {
                check = [this.selectorID, this.nome, this.opzione];
            }
            regexps = [
                /^[a-zA-Z0-9]{1,3}$/,
                /^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,18}[a-zA-ZÀ-ÿ])?$/,
                /^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/
            ];
            messaggiErr = [
                "-Sigla Team: caratteri [1-3] solo lettere e numeri",
                "-Nome Team: caratteri [1-20], solo lettere e spazi (non come unici caratteri)",
                "-Mail: La mail non esiste"
            ];
        } else {
            return console.log("Validazione Form Fallita a livello JS")
        }
        let errori = [];
        let dataOdierna = this._dataOdierna();
        check.forEach(input => {
            if (!input.name.startsWith("descrizione") && !input.type.startsWith("datetime") && !input.type.startsWith("hidden") && input.value === "") {
                errori.push("Il campo" + input.name + "Non puó essere vuoto");
            } else if (input.name.startsWith("descrizione") &&  input.value === "") {
                input.placeholder = "Nessuna Descrizione";
            }
        });
        for (let i = 0; i < check.length; i++) {
            check[i].value = check[i].value.trim();
            if (!regexps[i].test(check[i].value) || (check[i] === this.scadenza && this._completamentoData(check[i].value) < dataOdierna)) {
                    errori.push(messaggiErr[i]);
            }
        }
        if (errori.length > 0) {
            let warning = ""
            errori.forEach(function (errore) { 
                warning += errore + "<br>";
            });
            Notifica.appari({messaggioNotifica: warning, tipoNotifica: 'special-attenzione-notifica',});
            // Notifica.appari({messaggioNotifica: this._completamentoData(check[2].value), tipoNotifica: 'special-attenzione-notifica',});
        } else if (errori.length === 0) {
            (scadenza !== "") ? this._inviaForm(inputs[5]) : this._inviaForm(inputs[4]);
        }
    },

    _noListenerDiModificaForm() {
        if (!this.editOn.classList.contains('--invisibile')) {
            this.editOff.removeEventListener('click', this._disabilitaModificaForm.bind(this));
            this.dummy.removeEventListener('click', this._validazioneForm.bind(this));
        } else {
            this.editOn.removeEventListener('click', this._abilitaModificaForm.bind(this));
            this.back.removeEventListener('click', this._chiusuraForm.bind(this));
        }
    },

    _inviaForm(submit) {
        console.log("Dati Inviati");
        if (!submit.name.startsWith('edit')) {
            console.log('ho premuto modifica tupla');
            submit.name = submit.name.replace("delete", "edit");
            submit.disabled = false;
            if (!submit.disabled) {
                submit.click();
            }
        } else {
            console.log("Dati Inviati");
            submit.disabled = false;
            if (!submit.disabled) {
                submit.click();
            }
        } 
    },

    _invioDatiForm(event) {
        // non voglio aprire una pagina php quindi blocco il comportamento di default del submit...
        event.preventDefault();
        // ...catturo i suoi dati in un oggetto FormData che peró non cattura il tasto di submit
        const formData = new FormData(event.target);
        // ... e quindi lo aggiungiamo manualmente 
        let submit = event.target.querySelector('input[type=submit]');
        formData.append(submit.name, "Invia");
        // ... con questo campo verifico nella console se i dati che sto per inviare sono esatti      
        // formData.forEach(function(value, key){
        //     console.log(key, value);
        // });
        // i dati che ho catturati li voglio in un formato JSON pertanto convertiamo prima tutto in un oggetto
        const formDataObj = {};
        // entries ci permette di poter iterare sul nostro oggetto, e quindi usare for.
        for (let coppia of formData.entries()) { //  Pertanto facciamo un'iterazione per ogni coppia ... 
            formDataObj[coppia[0]] = coppia[1]; //.. catturandone chiave [0] e valore [1], e salviamo tutto nel nostro oggetto
        }
        // Convertiamo l'oggetto JavaScript in una stringa JSON
        const jsonData = JSON.stringify(formDataObj);
        // ... mando una richiesta http
        NuovaRichiestaHttpXML.mandaRichiesta("POST", "./services/dashboard_crud.php", true, 'Content-Type', 'application/json', jsonData, this._verificaRisposta);
    },

    _verificaRisposta() {
        // console.log(xhr.responseText);
        const rispostaServer = JSON.parse(xhr.responseText);
        let sm = rispostaServer.messaggio;
        isAdmin = rispostaServer.isAdmin;
        if (!isAdmin) {
            Notifica.appari({messaggioNotifica: sm, tipoNotifica:'special-errore-notifica',});
            // Salvo il messaggio e il tipo di notifica in localStorage, in modo da poter visualizzarli subito dopo il reindirizzamento
            localStorage.setItem('messaggio', sm);
            localStorage.setItem('tipoMessaggio', "errore-notifica");
            window.location.href = 'login.html';
        } else if (sm.includes('Errore') || sm.includes('errore')) {
            Notifica.appari({messaggioNotifica: sm, tipoNotifica:'special-errore-notifica',});
            Form._chiusuraForm();
        } else {
            if (sm) Notifica.appari({messaggioNotifica: sm, tipoNotifica:'special-successo-notifica',});
            Form._chiusuraForm();
        }
    },

    _chiusuraForm() {
        let form = this.id;
        let stileForm = window.getComputedStyle(form);
        let isAnimazioneInCorso = stileForm.animationName !== 'none';
        if ((!isAnimazioneInCorso) || isAnimazioneInCorso && form.classList.contains('--appear')) {
            contHeight = form.offsetHeight;
            root.style.setProperty('--contHeight', `${contHeight}px`);
            if (isAnimazioneInCorso && form.classList.contains('--appear')) {
                form.classList.remove('--appear');
            }
            form.classList.add('--dismiss');
            let figli = form.querySelectorAll('*');
            figli.forEach(figlio => {
                figlio.classList.add('--dismissRush');
            });
            form.addEventListener('animationend', () => {
                form.remove();
                setTimeout(richiestaEstrazioneInfo(), 3000);  
            }); 
        }
    }, 

    _completamentoData(dataIncompleta) {
        var partiData = dataIncompleta.split('T');
        var data = partiData[0];
        var ora = partiData[1] ? partiData[1] : "00:00:00";
        if (ora.split(':').length === 1) {
            return data + "T" + ora + ":00:00";
        } else if (ora.split(':').length === 2) {
            return data + "T" + ora + ":00";
        } else {
            return data + "T" + ora;
        }
    },
    
    _dataOdierna(addOre = 0, addMinuti = 0, addSecondi = 0, addGiorni = 0, addMesi = 0, addAnni = 0) {
        let today = new Date();
        // controlliamo se aggiungiamo qualche valore rispetto alla data corrente
        if (addAnni) today.setFullYear(today.getFullYear() + addAnni);
        if (addMesi) today.setMonth(today.getMonth() + addMesi);
        if (addGiorni) today.setDate(today.getDate() + addGiorni);
        if (addOre) today.setHours(today.getHours() + addOre);
        if (addMinuti) today.setMinutes(today.getMinutes() + addMinuti);
        if (addSecondi) today.setSeconds(today.getSeconds() + addSecondi);
        let dd = String(today.getDate()).padStart(2, '0');
        let mm = String(today.getMonth() + 1).padStart(2, '0'); // Mesi da 0 a 11
        let yyyy = today.getFullYear();
        let hh = String(today.getHours()).padStart(2, '0');
        let min = String(today.getMinutes()).padStart(2, '0');
        let sec = String(today.getSeconds()).padStart(2, '0');
        let todayString = `${yyyy}-${mm}-${dd}T${hh}:${min}:${sec}`;
        // console.log(todayString);
        return todayString;
    }
    ,

    _formattaDataDa(localDateTime) {
        let mesi = ["Gen", "Feb", "Mar", "Apr", "Mag", "Giu", "Lug", "Ago", "Set", "Ott", "Nov", "Dic"]
        dataMinima = new Date(localDateTime);
        giorno = dataMinima.getDate();
        mese = mesi[dataMinima.getMonth()];
        anno = dataMinima.getFullYear();
        dataMinimaFormattata = `${giorno} ${mese} ${anno}`;
        return dataMinimaFormattata;
    }

}


Form.stampa();
Form.riassegnaEventi(); // il problema nasce nel richiamo qui#1