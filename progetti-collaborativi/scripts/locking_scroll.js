//per bloccare lo scroll della pagina quando si apre un popup specifico
const bodyTarget = document.body;
const pageTarget = document.documentElement;

//creo la funzione che mi blocca lo scroll, assegnando una certa classe al body e alla pagina html
function lockExtScroll() {
    bodyTarget.classList.remove("overlay-chiuso");
    pageTarget.classList.remove("overlay-chiuso");
    bodyTarget.classList.add("overlay-aperto");
    pageTarget.classList.add("overlay-aperto");
}

//creo la funzione che mi sblocca lo scroll, facendo l'assegnazione inversa 
function unlockExtScroll() {
    let isOverlayEsistente = document.querySelector('.overlay-container');
    if (!isOverlayEsistente) {
        bodyTarget.classList.remove("overlay-aperto");
        pageTarget.classList.remove("overlay-aperto");
        bodyTarget.classList.add("overlay-chiuso");
        pageTarget.classList.add("overlay-chiuso");
    } else {
        if (isOverlayEsistente.style.display !== 'flex') {
            bodyTarget.classList.remove("overlay-aperto");
            pageTarget.classList.remove("overlay-aperto");
            bodyTarget.classList.add("overlay-chiuso");
            pageTarget.classList.add("overlay-chiuso");
        }
    }
    
}
