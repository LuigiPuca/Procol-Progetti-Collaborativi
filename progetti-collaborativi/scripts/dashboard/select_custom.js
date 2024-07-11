// script per creare un select item funzionante meglio di quello standard
// Un grazie a https://www.freecodecamp.org/

// creo un oggetto che mi evidenza gli elementi che ci interessano
let selectElementi = {
    anteprima: document.querySelector('[role="combobox"]'),
    dropwdown: document.querySelector('[role="listbox"]'),
    opzioni: document.querySelectorAll('[role="option"]'),
    inputNascosto: document.querySelector('input[type="hidden"]'),
};

let isTendinaAperta = false;
let iOpzioneCorrente = 0;
let lastCharPremuto = '';	
let lastIndiceCorrispondente = 0;

const toggleTendina = () => {
    selectElementi.dropwdown.classList.toggle('--attivo');
    isTendinaAperta =!isTendinaAperta;
    selectElementi.anteprima.setAttribute('aria-expanded', isTendinaAperta.toString());

    if (isTendinaAperta) {
        focusOpzioneCorrente();
    } else {
        selectElementi.anteprima.focus();
    }
};

const handleKeyPress = (e) => {
    e.preventDefault();
    const { key } = e;
    const openKeys = ['ArrowDown', 'ArrowUp', 'Enter', ' '];

    if (!isTendinaAperta && openKeys.includes(key)) {
        toggleTendina();
    } else if (isTendinaAperta) {
        switch (key) {
            case 'Escape': 
                toggleTendina();
                break;
            case 'ArrowDown':
                spostaFocusGiu();
                break;
            case 'ArrowUp':
                spostaFocusSopra();
                break; 
            case 'Enter':
            case ' ':
                selectOpzioneCorrente();
                break;
            default:
                // altri bottoni per la ricerca alfanumerica
                handleAlphanumericPress(key)
                break;
        }
    }
};

const handleDocumentInteraction = (e) => {
    // controlliamo se il target dell'evento é contenuto nell'anteprima o nel dropdown
    const isClickSuAnteprima = selectElementi.anteprima? selectElementi.anteprima.contains(e.target) : null;
    const isClickSuTendina = selectElementi.anteprima? selectElementi.dropwdown.contains(e.target): null;
    // se il click é sull'anteprima oppure sulla tendina (quando la tendina é aperta)
    if (isClickSuAnteprima || (!isClickSuTendina && isTendinaAperta)){ 
        // ... apriamo, oppure chiudiamo, la tendina
        toggleTendina();
    }
    // verificare se il click é su un'opzione o sul genitore piú vicino di ruolo "option"
    const opzioneCliccata = e.target.closest('[role="option"]');
    if (opzioneCliccata) {
        selectOpzioneDa(opzioneCliccata);
    }
};

//imposto come spostare il focus giu e su con i tasti, in maniera anche ciclica
const spostaFocusGiu = () => {
    if (iOpzioneCorrente < selectElementi.opzioni.length - 1) {
        iOpzioneCorrente++; 
    } else {
        //riparto dal primo
        iOpzioneCorrente = 0; 
    }
    focusOpzioneCorrente();
};

const spostaFocusSopra = () => {
    if (iOpzioneCorrente > 0) {
        iOpzioneCorrente--; 
    } else {
        //riparto dall'ultimo
        iOpzioneCorrente = selectElementi.opzioni.length - 1; 
    }
    focusOpzioneCorrente();
};

// sia che io voglia spostare il focus giu o su mi serve una funzione che conferma il focus
const focusOpzioneCorrente = () => {
    //si rileva che la nodelist non presenta elementi vuol dire che c'è un problema e pertanto riassegniamo gli eventi
    if (!selectElementi.opzioni.length > 0) {
        riassegnaEventi();
        // console.log(selectElementi.opzioni); // verifichiamo che la nodelist ora sia giusta dalla console
    }
    const opzioneCorrente = selectElementi.opzioni[iOpzioneCorrente];
    opzioneCorrente.classList.add('--corrente');
    opzioneCorrente.focus();

    //Sposta l'opzione corrente lungo la vista
    opzioneCorrente.scrollIntoView({
        block: 'nearest',
    });

    selectElementi.opzioni.forEach((opzione) => {
        if (opzione !== opzioneCorrente) {
            opzione.classList.remove('--corrente');
        }
    });
};

const selectOpzioneCorrente = () => {
    const opzioneSelezionata = selectElementi.opzioni[iOpzioneCorrente];
    selectOpzioneDa(opzioneSelezionata);
};

const selectOpzioneDa = (elOpzione) => {
    const stringaOpzione = elOpzione.textContent;

    selectElementi.anteprima.textContent = (stringaOpzione === '') ? 'Seleziona' : stringaOpzione;
    selectElementi.anteprima.value = (stringaOpzione === '') ? '' : stringaOpzione;
    selectElementi.opzioni.forEach(opzione => {
        opzione.classList.remove('--attivo');
        opzione.setAttribute('aria-selected', 'false');
    });

    elOpzione.classList.add('--attivo');
    elOpzione.setAttribute('aria-selected', 'true');

    toggleTendina();
};

// vogliamo estrarre 
const handleAlphanumericPress = (key) => {
    const charPremuto = key.toLowerCase();

    if (lastCharPremuto !== charPremuto) {
        lastIndiceCorrispondente = 0;
    }

    const matchingOpzioni = Array.from(selectElementi.opzioni).filter((option) => 
    option.textContent.toLowerCase().startsWith(charPremuto));

    if (matchingOpzioni.length) {
        if (lastIndiceCorrispondente === matchingOpzioni.length) {
            lastIndiceCorrispondente = 0;
        } 
        let value = matchingOpzioni[lastIndiceCorrispondente];
        const index = Array.from(selectElementi.opzioni).indexOf(value);
        iOpzioneCorrente = index;
        focusOpzioneCorrente();
        lastIndiceCorrispondente += 1;
    }
    lastCharPremuto = charPremuto;
};

if(selectElementi.anteprima) {
    selectElementi.anteprima.addEventListener('keydown', handleKeyPress); //attenzione qui#
}
document.addEventListener('click', handleDocumentInteraction);

function riassegnaEventi() {
    if(selectElementi.anteprima) {
        selectElementi.anteprima.removeEventListener('keydown', handleKeyPress);
    }
    document.removeEventListener('click', handleDocumentInteraction);
    selectElementi = {
        anteprima: document.querySelector('[role="combobox"]'),
        dropwdown: document.querySelector('[role="listbox"]'),
        opzioni: document.querySelectorAll('[role="option"]'),
        inputNascosto: document.querySelector('input[type="hidden"]'),
    };
    if(selectElementi.anteprima) {
        selectElementi.anteprima.addEventListener('keydown', handleKeyPress);
    }
    document.addEventListener('click', handleDocumentInteraction);
    // Assegna altri eventi se necessario
}

// Chiamare la funzione dopo che gli elementi sono stati riaggiunti
riassegnaEventi();










