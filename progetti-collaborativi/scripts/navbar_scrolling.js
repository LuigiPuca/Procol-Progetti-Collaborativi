//funzionamento dello scrolling della navbar
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.ul-nav');
    const scrollLeft = document.querySelector('.scroll-a-sx');
    const scrollRight = document.querySelector('.scroll-a-dx');
    const valoreInVw = 0.05;
    const valoreInPx = valoreInVw * window.innerWidth; /* conversione da vw a px */
    let scrollInterval, resizeTimeout, tocco, pressione, rilascio, touchScreen;
    let scrollManuale = false; // Variabile per memorizzare lo stato dello scorrimento

    // Funzione per controllare la visibilità delle frecce di scroll
    function controllaVisibilitaFrecce() {
        scrollLeft.style.color = navbar.scrollLeft > 0 ? 'rgba(192, 204, 193, 1)' : 'rgba(192, 204, 193, 0)';
        scrollRight.style.color = navbar.scrollLeft < navbar.scrollWidth - navbar.clientWidth ? 'rgba(192, 204, 193, 1)' : 'rgba(192, 204, 193, 0)';
    }

    // Lo schermo é Touch? Si -> Trigger di sinistra; No -> Trigger di destra
    touchScreen = 'ontouchstart' in window;
    tocco = touchScreen ? 'touchstart' : 'click';
    pressione = touchScreen ? 'touchstart' : 'mousedown';
    rilascio = touchScreen ? 'touchend' : 'mouseup';

    // Funzione per lo scorrimento continuo verso sinistra
    function scrollLeftContinuous() {
        navbar.scrollLeft -= valoreInPx;
    }

    // Funzione per lo scorrimento continuo verso destra
    function scrollRightContinuous() {
        navbar.scrollLeft += valoreInPx;
    }

    // Funzione di callback per gestire l'evento di pressione e rilascio
    function gestisciEventoScroll(elemento, azione) {
        clearInterval(scrollInterval); // Assicura che non ci siano intervalli attivi
        if (azione === 'pressione') {
            scrollInterval = setInterval(elemento === 'sinistra' ? scrollLeftContinuous : scrollRightContinuous, 30); // Avvia lo scorrimento continuo
        } else if (azione === 'rilascio') {
            clearInterval(scrollInterval); // Interrompe lo scorrimento continuo
        }
    }

    // Funzioni, richiamanti il callback, chiamate ad un certo evento soddisfatto
    scrollLeft.addEventListener(tocco, scrollLeftContinuous);
    scrollLeft.addEventListener(pressione, () => gestisciEventoScroll('sinistra', 'pressione'));
    scrollLeft.addEventListener(rilascio, () => gestisciEventoScroll(null, 'rilascio'));
    scrollRight.addEventListener(tocco, scrollRightContinuous);
    scrollRight.addEventListener(pressione, () => gestisciEventoScroll('destra', 'pressione'));
    scrollRight.addEventListener(rilascio, () => gestisciEventoScroll(null, 'rilascio'));

    // Evento scroll per controllare la visibilità delle frecce di scroll
    navbar.addEventListener('scroll', function() {
        if (!scrollManuale) {
            clearInterval(scrollInterval); // Interrompi lo scorrimento continuo se l'utente sta scorrendo manualmente
        }
        controllaVisibilitaFrecce();
    });

    // Evento per tracciare lo stato dello scorrimento manuale
    navbar.addEventListener(tocco, function() {
        scrollManuale = false;
    });
    navbar.addEventListener(pressione, function() {
        scrollManuale = true;
    });
    navbar.addEventListener(rilascio, function() {
        scrollManuale = false;
    });

    // Controlla la visibilità delle frecce all'avvio
    controllaVisibilitaFrecce();
    // Evento per controllare la visibilità in caso di ridimensionamento finestra
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout); // Cancella il timeout precedente se presente
    
        resizeTimeout = setTimeout(function() {
            controllaVisibilitaFrecce();
        }, 1000); // Imposta il tempo di attesa in millisecondi prima di eseguire il codice
    });
});
