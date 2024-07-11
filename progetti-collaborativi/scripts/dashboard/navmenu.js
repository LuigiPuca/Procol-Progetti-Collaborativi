var view = document.querySelector('.view');
var navmenu = document.querySelector('.navmenu');
var navToggle = document.querySelector('.navtoggle');
var navbar = document.querySelector('.navbar');
var mainWrapper = document.querySelector('.main-wrapper');
var main = document.querySelector('.main');
var mediaQuery = window.matchMedia('(max-width: 835px)');
let isPaginaAppenaCaricata = true;

setTimeout(() => {isPaginaAppenaCaricata = false}, 2000)

function adattaNavmenu(mediaQuery) {
    if (mediaQuery.matches) {
        if (view.classList.contains('--maxi')) {
            view.classList.remove('--maxi', '--abilita');
        }
        (view.classList.contains('--abilita')) ? menuAperto() : menuChiuso();
    } else {
        if (view.classList.contains('--mini')) {
            view.classList.remove('--mini', '--abilita');
        }
        (view.classList.contains('--abilita')) ? menuChiuso() : menuAperto();
    }
}


navToggle.addEventListener('click', () => {
    (mediaQuery.matches) ? view.classList.add('--mini') : view.classList.add('--maxi');
    (view.classList.contains('--abilita')) ? view.classList.remove('--abilita') : view.classList.add('--abilita');
    adattaNavmenu(mediaQuery);
});

function menuChiuso() {
    navmenu.classList.remove('--aperto');
    navmenu.classList.add('--chiuso');
    navbar.classList.remove('--nma'); 
    navbar.classList.add('--nmc');
    mainWrapper.classList.remove('--nma');
    mainWrapper.classList.add('--nmc');
    navToggle.classList.add('--nmac');
    navToggle.classList.remove('--nmaa');
}

function menuAperto() {
    navmenu.classList.remove('--chiuso');
    navmenu.classList.add('--aperto');
    navbar.classList.remove('--nmc');
    navbar.classList.add('--nma');
    mainWrapper.classList.remove('--nmc');
    mainWrapper.classList.add('--nma');
    navToggle.classList.add('--nmaa');
    navToggle.classList.remove('--nmac');
}

if(mediaQuery.matches) {
    adattaNavmenu(mediaQuery);
}

mediaQuery.addEventListener('change', adattaNavmenu);

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        const style = document.createElement('style');
        style.textContent = `
        .--chiuso, .--nmc {
            animation-duration: 0.5s; /* Durata animazione dopo il caricamento completo del DOM */
        }
        table {
            animation-duration: 0.45s;
        }
        .--zoomedIn {
            animation-duration: 1s !important;
        }
        .main:has(.--zoomedIn)>*:not(.--zoomed), .main:has(.--zoomedSlide)>*:not(.--zoomed) {
            animation-duration: 0.6s;
        }
        `;
        document.head.appendChild(style);
    
    }, 3000);
    // Imposta la durata dell'animazione a 0.5s dopo il caricamento completo del DOM
    
  });

