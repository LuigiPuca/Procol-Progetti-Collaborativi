let touchScreen = 'ontouchstart' in window;
let apriTendina = touchScreen ? 'click' : 'click';
let chiudiTendina = touchScreen ? 'touchstart' : 'mouseleave';
let navbar = document.querySelector('.ul-nav');
let dropdownBtns = document.querySelectorAll('.dropbtn');
let dropdownContents = document.querySelectorAll('.dropdown-content');
let tendinaAperta;
let rectLeftStart;

// Funzione debounce ci serve per non avere un movimento impazzito della tendina quando si sposta
function debounce(func, wait) {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, arguments), wait);
    };
}

dropdownBtns.forEach(function(dropdownBtn){
    dropdownBtn.addEventListener(apriTendina, function(event){
        const dropdownParent = dropdownBtn.parentElement;
        const dropdownContent = dropdownParent.children[1];
        dropdownContents.forEach(altriContent => {
            if (altriContent !== dropdownContent) {
                altriContent.style.setProperty("display", "none");
                dropdownContent.style.removeProperty("animation-name", "---openingTendina");
            }
        });
        dropdownContent.style.position = touchScreen ? 'relative' : 'fixed'; // Imposta la posizione fissa
        if (dropdownContent.style.display === 'none' || !dropdownContent.style.display) {
            tendinaAperta = dropdownBtn;
            if (touchScreen) {
                dropdownContent.style.setProperty('flex-direction', 'row');
                dropdownContent.style.setProperty('white-space', 'nowrap');
            }

            if (!touchScreen) {
                dropdownContent.style.width = dropdownBtn.offsetWidth + 'px';
                dropdownContent.style.display = 'block';
                dropdownContent.style.setProperty("animation-name", "---openingTendina");
                sposta();
            } else {
                let navRect = navbar.getBoundingClientRect();
                let elementiCopiati = document.querySelector('.--copia');
                if (elementiCopiati) elementiCopiati.remove();
                copiaBtn();
                spostaTouch();
                diffRect = navRect.bottom - rect.top;
                document.querySelectorAll('.ul-nav li > a, .dropbtn:not(.no-move), [class*="scroll-a"]').forEach(function(elemento){
                    elemento.style.display = 'none';
                    if (navRect.width < dropdownContent.getBoundingClientRect().width){
                        navbar.style.width = dropdownContent.getBoundingClientRect().width;
                    }
                });
            }

            function sposta() {
                let rect = dropdownBtn.getBoundingClientRect();
                let dropdownRect = dropdownContent.getBoundingClientRect();
                let viewportWidth = window.innerWidth;
                document.querySelector('.dropdown-content').style.removeProperty("animation-name");
                if (rect.left > viewportWidth / 2) {
                    document.documentElement.style.setProperty('--rect-left-start', (rect.left + rect.width - dropdownRect.width) + 'px');
                    // dropdownContent.style.left = (rect.left + rect.width - dropdownRect.width) + 'px';
                    document.documentElement.style.setProperty('--rect-left-end', (rect.left + rect.width - dropdownRect.width) + 'px');
                } else if (rect.left < viewportWidth / 2) {
                    document.documentElement.style.setProperty('--rect-left-start', (rect.left) + 'px');
                    // dropdownContent.style.left = (rect.left) + 'px';
                    document.documentElement.style.setProperty('--rect-left-end', (rect.left) + 'px');
                }
                if (dropdownRect.left < 0) {
                    document.documentElement.style.setProperty('--rect-left-start', '10px');
                    // dropdownContent.style.left = '10px';
                    document.documentElement.style.setProperty('--rect-left-end', '10px');
                } else if (dropdownRect.right > viewportWidth) {
                    document.documentElement.style.setProperty('--rect-left-start', (viewportWidth - dropdownRect.width - 10) + 'px');
                    document.documentElement.style.setProperty('--rect-left-end', (viewportWidth - dropdownRect.width - 10) + 'px');
                    // dropdownContent.style.left = (viewportWidth - dropdownRect.width - 10) + 'px';
                }
                dropdownContent.style.setProperty("animation-name", "---openingTendina, ---aggiornaTendina");
                rectLeftStart = dropdownContent.getBoundingClientRect().left + 'px';
                
                dropdownContent.style.top = (rect.bottom + 10) + 'px';

                if (eventMaxiHandlers.has(window)) {
                    window.removeEventListener('resize', eventMaxiHandlers.get(window).resize);
                }
                const handlers = { resize: debounce(() => resizeDropdownAuto(dropdownContent, dropdownBtn), 200) };
                eventMaxiHandlers.set(window, handlers);
                window.addEventListener('resize', handlers.resize);
            }

            function spostaTouch() {
                rect = dropdownBtn.getBoundingClientRect();
                navbar.scrollLeft = 0;   // Mostro la tendina
            }

            function copiaBtn() {
                let isCopiaEsistente = document.querySelector('.--return-navbar');
                if (isCopiaEsistente) {
                    isCopiaEsistente.remove();
                }
                let testoClonato = dropdownBtn.textContent ? dropdownBtn.textContent : 'Utente';
                let altezzaClonata = dropdownBtn.offsetHeight;
                const elementoClonato = dropdownContent.children[0].cloneNode(false);
                const listenerOriginali = dropdownContent.querySelectorAll('a');
                const contenutoCopiato = dropdownContent.cloneNode(true);
                const listenerCopia = contenutoCopiato.querySelectorAll('a');
                contenutoCopiato.id = 'dCopia';
                contenutoCopiato.classList.add('--copia');
                contenutoCopiato.style.display = 'flex';
                contenutoCopiato.style.setProperty('max-width', "fit-content");
                elementoClonato.setAttribute('href', 'javascript:void(0)');
                elementoClonato.textContent = "Chiudi";
                elementoClonato.className = '--return-navbar';
                listenerCopia.forEach(function(figlioCopia, indice) {
                    figlioCopia.addEventListener('click', function(e) {
                        elementoClonato.click();
                        e.preventDefault();
                        listenerOriginali[indice].click();
                    });
                });
                let listaUL = document.querySelector('.ul-nav');
                listaUL.insertBefore(elementoClonato, listaUL.firstChild);
                listaUL.appendChild(contenutoCopiato);
                document.querySelector('#dCopia').style.top = 0 + 'px';
                elementoClonato.addEventListener('click', function(event){
                    elementoClonato.remove();
                    contenutoCopiato.remove();
                    document.querySelectorAll('.ul-nav li > a, .dropbtn:not(.no-move), [class*="scroll-a"]').forEach(function(elemento){
                        elemento.style.display = 'flex';
                    });
                });
            }

            if (!touchScreen && !this.classList.contains('no-move')) {
                dropdownContent.style.display = 'block';
                sposta();
            }
        } else {
            dropdownContent.style.display = 'none';
        }
    });
});

function resizeDropdownAuto(dropdownContent, dropdownBtn) {
    let rect = dropdownBtn.getBoundingClientRect();
    let dropdownRect = dropdownContent.getBoundingClientRect();
    let viewportWidth = window.innerWidth;
    document.documentElement.style.setProperty('--rect-left-start', rectLeftStart);
    if (rect.left > viewportWidth / 2) {
        // dropdownContent.style.left = (rect.left + rect.width - dropdownRect.width) + 'px';
        document.documentElement.style.setProperty('--rect-left-end', (rect.left + rect.width - dropdownRect.width) + 'px');
    } else if (rect.left < viewportWidth / 2) {
        // dropdownContent.style.left = (rect.left) + 'px';
        document.documentElement.style.setProperty('--rect-left-end', (rect.left) + 'px');
    }
    if (dropdownRect.left < 0) {
        // dropdownContent.style.left = '10px';
        document.documentElement.style.setProperty('--rect-left-end', '10px');
    } else if (dropdownRect.right > viewportWidth) {
        document.documentElement.style.setProperty('--rect-left-end', (viewportWidth - dropdownRect.width - 10) + 'px');
        // dropdownContent.style.left = (viewportWidth - dropdownRect.width - 10) + 'px';
    }
    document.querySelector('.dropdown-content').style.removeProperty("animation-name");
    setTimeout(() => { 
        document.documentElement.style.setProperty('--rect-left-start', dropdownRect.left + 'px');
        dropdownContent.style.setProperty("animation-name", "none, ---aggiornaTendina");
        rectLeftStart = dropdownContent.getBoundingClientRect().left + 'px';
    }, 1);
    dropdownContent.style.top = (rect.bottom + 10) + 'px';
}

window.addEventListener('scroll', debounce(function() {
    if (tendinaAperta) {
        let dropdownBtn = tendinaAperta;
        let dropdownContent = dropdownBtn.parentElement.children[1];
        resizeDropdownAuto(dropdownContent, dropdownBtn);
    }
}, 200));

navbar.addEventListener('scroll', debounce(function() {
    dropdownContents.forEach(function(dropdownContent) {
        dropdownContent.style.display = 'none';
    });
}, 200));

window.addEventListener('click', chiudiTendine);

function chiudiTendine(event) {
    let self = event.target;
    if (!self.matches('.dropbtn') && !self.closest('.dropdown-content') && !self.closest('.navbar')) {
        dropdownContents.forEach(function(dropdownContent) {
            dropdownContent.style.display = 'none';
        });
    }
}
