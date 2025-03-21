const boxLogin = document.querySelector('.box-login');
const schedaRegistrazione = document.querySelector('.scheda--registrazione');
const schedaAccesso = document.querySelector('.scheda--accesso');
const btnApri = document.querySelector('.popup--apri');
const btnChiudi = document.querySelector('.popup--chiudi');
const barraIntestazione = document.querySelector('.header-login');
const barraInferiore = document.querySelector('.footer-login');
let windowHeight = window.innerHeight;
let popupHeight = boxLogin.offsetHeight;
let diff = windowHeight - popupHeight;
let diffMin = 0;
//per evitare bug grafici a causa di brave mobile quando si aprono input
let inputs = document.querySelectorAll('input');

schedaRegistrazione.addEventListener('click', apriSchedaReg);
schedaAccesso.addEventListener('click', apriSchedaAcc);
btnApri.addEventListener('click', apriPopup);
btnChiudi.addEventListener('click', chiudiPopup);
window.addEventListener('resize', () => {
    checkDimensioni();
});
inputs.forEach(input => {
    /* verifico se un input é pieno o meno */
    input.addEventListener('change', function() {
        if (input.value.length > 0) {
            input.classList.add('--non-vuoto');
        } else {
            input.classList.remove('--non-vuoto');
        }
    });
    /* parte di codice valida solo con Brave per iOS per risolvere bug grafici ad apparizione tastiera */
    if (navigator.userAgent.indexOf('like Mac OS X')!= -1 && navigator.userAgent.indexOf('Safari')== -1){
        input.addEventListener('focus', () => {
            document.querySelector('.--no').classList.remove('--errore-dimensione');
            boxLogin.classList.add('--iOSBrave'); 
        });
        input.addEventListener('blur', () => {
            document.querySelector('.--no').classList.add('--errore-dimensione');
            // boxLogin.classList.remove('--iOSBrave'); 
        });
    }
});

function apriSchedaReg() {
    boxLogin.classList.add('--attivo');  
    checkDimensioni();
    //per evitare bug grafici a causa di autofill di safari
    setTimeout(() => {
        boxLogin.classList.add('--errore-autofill');
    }, 500)
}

function apriSchedaAcc() {
    //per evitare bug grafici a causa di autofill di safari
    boxLogin.classList.remove('--errore-autofill');
    setTimeout(() => {
        boxLogin.classList.remove('--attivo');
        checkDimensioni();
    }, 10)
    
}
function apriPopup() {
    boxLogin.classList.add('--popup-attivo');
    btnApri.classList.add('--disabilitato');
    checkDimensioni();
}

function chiudiPopup() {
    boxLogin.classList.remove('--popup-attivo');
    btnApri.classList.remove('--disabilitato');
    checkDimensioni();
}

function checkDimensioni() {
    // console.log(window.devicePixelRatio);
    // console.log(boxLogin.offsetHeight);
    // console.log(window.innerHeight);
    let isAnyInputFocused;
    // Verifichiamo se ci sono elementi input in focus 
    inputs.forEach(function(input) {
        if (input === document.activeElement) {
            isAnyInputFocused = true;
            return; // Usciamo dal ciclo forEach una volta trovato un elemento in focus
        }
    });
    //Se nessun elemento é in focus verifichiamo se mostrare il messaggio che si suggerisce di ridimensionare o ruota la viewport
    if (!isAnyInputFocused) {
        setTimeout( () => {
            adjustDimensioni();
        }, 110);
    } 
    
    // popupHeight = boxLogin.classList.contains('--attivo')? "440" : "660";
}
function adjustDimensioni() {
    // disabilito i bottoni momentaneamente per evitare glitch a click continuii
    schedaRegistrazione.classList.add('disabled')
    schedaAccesso.classList.add('disabled')
    // Se il box di Login/registrazione é visibile calcoliamo le dimensioni della finestra e del box
    if (boxLogin.classList.contains('--popup-attivo')) {
        windowHeight = window.innerHeight;
        popupHeight = boxLogin.offsetHeight;
        // console.log(popupHeight);
        // stabiliamo la differenza minima tra le due altezze tale che il popup é ancora visibile
        diffMin = '150';
        diff = windowHeight - popupHeight;
        // console.log(true);
        if (diff > diffMin) {
            barraIntestazione.classList.remove('--barra-chiusa');
            barraInferiore.classList.remove('--barra-chiusa');
        } else {
            barraIntestazione.classList.add('--barra-chiusa');
            barraInferiore.classList.add('--barra-chiusa');
        }
    } else if (!boxLogin.classList.contains('--popup-attivo')) {
        // console.log(false);
        barraIntestazione.classList.remove('--barra-chiusa');
        barraInferiore.classList.remove('--barra-chiusa');
    }
    setTimeout(function() {
        // Riabilito il 'bottone' dopo 0.3 secondi
        schedaRegistrazione.classList.remove('disabled')
        schedaAccesso.classList.remove('disabled')
    }, 300);
    
}



