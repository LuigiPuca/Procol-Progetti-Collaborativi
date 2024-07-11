// Verifico se il testo straborda verticalmente
let overflowHorizontal = 0;
let overflowVertical = 0;


function isOverYFlow(container) {
    if (container.scrollHeight > container.clientHeight) {
         //preferiamo uan velocita in px/s dove i px sono calcolati dal 10% dell'altezza del contenitore
        let tvy = (container.clientWidth) * 0.05; // facciamo il 5%
        // Calcolo di quanto il testo straborda verticalmente
        overflowVertical = (container.scrollHeight - container.clientHeight);
        let tty = (overflowVertical / tvy);
        // console.log("Il testo si muoverà verticalmente di " + overflowVertical + " pixel in " + tty + " secondi");
        document.documentElement.style.setProperty('--tly', overflowVertical + "px");
        document.documentElement.style.setProperty('--tty', tty + "s");
        document.documentElement.style.setProperty('--ttyFast', (tty * 0.125) + "s");
    } else {
        overflowVertical = 0;
        // console.log("Il testo si muoverà verticlamente di " + overflowVertical + " pixel in " + 0 + " secondi");
        document.documentElement.style.setProperty('--tly', overflowVertical + "px");
        document.documentElement.style.setProperty('--tty', "0s");
        document.documentElement.style.setProperty('--ttyFast', "0s");
    }
}

// Verifico se il testo straborda orizzontalmente
function isOverXFlow(container) {
    // console.log(container.scrollWidth + ">" + container.clientWidth)
    if (container.scrollWidth > container.clientWidth) {
        //preferiamo uan velocita in px/s dove i px sono calcolati dal 10% della larghezza del contenitore
        let tvx = (container.clientWidth) * 0.1; // facciamo il 10%
        // Calcolo di quanto il testo straborda orizzontalmente e lo moltiplico per due. Calcolo anche la ttx
        overflowHorizontal = (container.scrollWidth - container.clientWidth);
        let ttx = (overflowHorizontal / tvx);
        // console.log("Il testo si muoverà orizzontalmente di " + overflowHorizontal + " pixel in " + ttx + " secondi");
        document.documentElement.style.setProperty('--tlx', "-" + overflowHorizontal + "px");
        document.documentElement.style.setProperty('--ttx', ttx + "s");
        document.documentElement.style.setProperty('--ttxFast', (ttx * 0.125) + "s");

    } else {
        overflowHorizontal = 0;
        // console.log("Il testo si muoverà orizzontalmente di " + overflowHorizontal + " pixel in " + 0 + " secondi");
        document.documentElement.style.setProperty('--tlx', "-" + overflowHorizontal + "px");
        document.documentElement.style.setProperty('--ttx', "0s");
        document.documentElement.style.setProperty('--ttxFast', "0s");
    }
}




