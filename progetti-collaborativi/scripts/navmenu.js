// Vogliamo verificare se il bordo superiore del nav-filler é visibile nella viewport
const navFiller = document.querySelector('.nav-filler');
const navWrapper = document.querySelector('.nav-wrapper');
const navMenuInner = document.querySelector('.nav-container');
const navMenuBtn = document.querySelector('.button-navmenu');

navMenuInner.addEventListener('click', goToReport)
function goToReport(e) {
    let self = e.target;
    if (self.matches('[id^="repo-"], [id^="repo-"] *')) {
        link = (self.closest('[id^="repo-"]').dataset.href);
        url = new URL(window.location.href);
        if (url.pathname.includes('/board.html')) {
            link = link.replace("board.html?", "");
            link = link.split('#');
            url.search = (link.length > 0) ? link[0] : url.search;
            url.hash = (link.length > 1) ? "#" + link[1] : "";
            let procolLogo = document.querySelector('.procol img');
            pushState(url);
            if (procolLogo) procolLogo.click();
        } else window.location.href = `${link}`;
    }
}

navMenuBtn.addEventListener('click', toggleMenu)
function toggleMenu() {
    if (window.matchMedia('(min-width: 768px)').matches) {
        document.querySelector('.grid-container').classList.toggle('--menu-maxi');
    } else {
        document.querySelector('.grid-container').classList.toggle('--menu-mini');
    }    
}

  