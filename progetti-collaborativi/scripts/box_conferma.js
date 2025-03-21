const Conferma = {
    elRiqConferma: null,
    btnOk: null,
    btnNo: null,
    btnChiudi: null,
    handleClick: null,
    handleOkClick: null,
    handleCancelClick: null,

    apri(opzioni) {
        // Definizione dei valori di default e sovrascrittura con le opzioni fornite
        opzioni = Object.assign({}, {
            titoloBox: '',
            messaggioBox: '',
            testoOk: 'Conferma',
            testoNo: 'Annulla',
            allOk: function() {},
            alNo: function() {}
        }, opzioni);
        // blocco scroll esterno al contenitore 
        lockExtScroll();

        // Creazione dell'elemento HTML del BOX
        const elHTML = `
            <div class="conferma">
                <div class="box-conferma">
                    <div class="intestazione-conferma">
                        <span class="titolo-conferma">${opzioni.titoloBox}</span>
                        <button class="chiudi-conferma">&times;</button>
                    </div>
                    <div class="contenuto-conferma">${opzioni.messaggioBox}</div>
                    <div class="bottoni-conferma">
                        <button class="bottone-conferma bottone-conferma--ok bottone-conferma--fill">${opzioni.testoOk}</button>
                        <button class="bottone-conferma bottone-conferma--cancel">${opzioni.testoNo}</button>
                    </div>
                </div>
            </div>
        `;

        // Creazione di un elemento template
        const template = document.createElement('template');
        template.innerHTML = elHTML;

        // Estrazione dei riferimenti agli elementi all'interno del template
        this.elRiqConferma = template.content.querySelector('.conferma');
        this.btnChiudi = template.content.querySelector('.chiudi-conferma');
        this.btnOk = template.content.querySelector('.bottone-conferma--ok');
        this.btnNo = template.content.querySelector('.bottone-conferma--cancel');

        // Aggiunta degli event listener
        this.handleClick = (e) => {
            if (e.target === this.elRiqConferma) {
                opzioni.alNo();
                this._chiudi();
            }
        };

        this.handleOkClick = (e) => {
            if (e.target === this.btnOk) {
                opzioni.allOk();
                this._chiudi();
            }
        };

        this.handleCancelClick = (e) => {
            if (e.target === this.btnNo || e.target === this.btnChiudi) {
                opzioni.alNo();
                this._chiudi();
            }
        };

        this.elRiqConferma.addEventListener('click', this.handleClick);
        this.btnOk.addEventListener('click', this.handleOkClick);
        [this.btnNo, this.btnChiudi].forEach(el => {
            el.addEventListener('click', this.handleCancelClick);
        });

        // Aggiunta del template al documento HTML
        document.body.appendChild(template.content);
    },

    _chiudi() {
        // Rimozione degli event listener
        this.elRiqConferma.removeEventListener('click', this.handleClick);
        this.btnOk.removeEventListener('click', this.handleOkClick);
        this.btnNo.removeEventListener('click', this.handleCancelClick);
        this.btnChiudi.removeEventListener('click', this.handleCancelClick);

        // sblocco scroll esterno al contenitore 
        unlockExtScroll();
        
        // Aggiungiamo la classe per l'animazione di chiusura
        this.elRiqConferma.classList.add('conferma--chiudi');
        this.elRiqConferma.addEventListener('animationend', () => {
            document.body.removeChild(this.elRiqConferma);
            console.log("Hai chiuso la finestra!");
            this.elRiqConferma, this.btnOk, this.btnNo, this.btnChiudi, this.handleClick, this.handleOkClick, this.handleCancelClick = null;
        });
       
    }
};

// Esempio di Utilizzo dell'oggetto Conferma
// Conferma.apri({
//     titoloBox: "Titolo della conferma",
//     messaggioBox: "Messaggio della conferma",
//     testoOk: "OK",
//     testoNo: "Annulla",
//     allOk: function() {
//         console.log("Hai premuto OK");
//     },
//     alNo: function() {
//         console.log("Hai premuto Annulla");
//     }
// });
