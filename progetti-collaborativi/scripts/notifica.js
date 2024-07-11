const Notifica = {
    btnIgnora: null,
    handleClick: null,
    handleTimeOut: null,
    btnDismiss: null,
    boxNotifica: null,

    appari(opzioni) {
        // Definizione dei valori di default e sovrascrittura con le opzioni fornite
        opzioni = Object.assign({}, {
            messaggioNotifica: '',
            tipoNotifica: 'special-notifica', //si aggiunge come classe dell'elemento
        }, opzioni);

        // Creazione dell'elemento HTML del riquadro di notifica
        const elHTML = `
            <div class="box-notifica ${opzioni.tipoNotifica}">
                <div class="contenuto-notifica"><p>${opzioni.messaggioNotifica}</p></div>
                <button class="ignora-notifica">&times;</button>
            </div>
        `;

        // Associazione ad un elemento template
        const notifiche = document.querySelector('.notifica');
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        notifiche.appendChild(template.content);

        // Estrazione dei riferimenti agli elementi all'interno del template
        this.btnIgnora = notifiche.querySelectorAll('.ignora-notifica');
        let boxNotifiche = notifiche.querySelectorAll('.box-notifica');

        this.btnIgnora.forEach((singolo) => {
            this.handleClick = (e) => {
                if (e.target === singolo) {
                    this.btnDismiss = e.target;
                    this.boxNotifica = e.target.parentNode;
                    this._ignora();
                    singolo.removeEventListener('click', this.handleClick);
                }
            };
            // Aggiunta degli event listener
            singolo.addEventListener('click', this.handleClick);
        });

        boxNotifiche.forEach((boxN) => {
            this.handleTimeOut = (e) => {
                if (e.target === boxN) {
                    
                    setTimeout(() => {
                        this.boxNotifica = e.target;
                        this._ignora();
                    }, 7000);
                    // this._ignora();
                    // boxN.removeEventListener('animationend', this.handleTimeOut);
                }
            };
            boxN.addEventListener('animationend', this.handleTimeOut);
        });
    },

    _ignora() {
        console.log(this.boxNotifica);
        console.log(this.btnDismiss);
        altezzaNotifica = this.boxNotifica.offsetHeight;
        document.documentElement.style.setProperty('--altezza-notifica', "" + altezzaNotifica + "px");
        // Aggiungiamo la classe per l'animazione di chiusura
        this.boxNotifica.classList.add('notifica-ignora');
        console.log("altezzaNotifica: " + altezzaNotifica);
        verificaNotifiche();
    }
};

function verificaNotifiche() {
    ignoraNotifica = document.querySelectorAll('.notifica-ignora');
    ignoraNotifica.forEach((singolo) => {
        // console.log(singolo);
        singolo.addEventListener('animationend', animazioneDismiss);
        function animazioneDismiss(e) {
            console.log("Hai chiuso la notifica!");
            // console.log(e.target);
            e.target.remove();
            e.target.removeEventListener('animationend', animazioneDismiss);
        }
    });
}