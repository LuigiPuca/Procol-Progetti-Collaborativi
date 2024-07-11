const Board = {

    patternTitolo: /^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/,
    patternScheda: /^[a-zA-Z0-9]{1}[$£€¥&@#ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿa-zA-Z0-9 \s \' \. \, \: \; \! \? \% \-]{0,49}$/,

    aggiungiCategoria(opzioni) {
        opzioni = Object.assign({}, {
            idProgetto: '',
            categoria: '',
            ordineCategoria: '',
            colore: '',
            isVisibile: '',
        }, opzioni);

        let idType = `${opzioni.idProgetto}-${opzioni.categoria}`.replace(/ /g, '_');
        //Creazione dell'elemento HTML della categoria
        const elHTML = `
        <div class="column" id="type-${idType}" data-ordine-categorie="${opzioni.ordineCategoria}" data-colore="${opzioni.colore}" data-visibilita="${opzioni.isVisibile}">
            <div class="category" style="border: 2px groove ${opzioni.colore};">
                <div class="show-tray">
                    <img src="assets/svg-images/left-leave.svg" class="sinistra" alt="◀">
                    <img src="assets/svg-images/show-leave.svg" class="mostra" alt="show">
                    <img src="assets/svg-images/hide-leave.svg" class="nascondi" alt="hide">
                </div>   
                <h4>${opzioni.categoria}</h4>
                <div class="show-tray">
                    <img src="assets/svg-images/del-leave.svg" class="elimina-categoria" alt="del">
                    <img src="assets/svg-images/right-leave.svg" class="destra" alt="▶">
                </div>
            </div>
            <div class="posts"></div>
            <div><button class="add-post" onclick="addPost(event)">+</button></div>
        </div>
        `;

        //Associazione alla board

        const board = document.querySelector('.board');
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        (board.children.length > 0) ? board.insertBefore(template.content, board.firstChild) : board.appendChild(template.content);

        //distruggo i listener relativi al form
        // aggiornoIcone();
        aggiornoEventi();
    },

    aggiungiScheda(opzioni) {
        // Definizione dei valori di default e sovrascrittura con le opzioni fornite
        opzioni = Object.assign({}, {
            categoria: '',
            uuidScheda: '',
            titoloScheda: '',
            descrizioneScheda: '', 
            id_progetto: '',
            ordineScheda: '', 
        }, opzioni);

        // Creazione dell'elemento HTML della scheda
        const elHTML = `
        <div class="post-it" id="post-${opzioni.uuidScheda.toLowerCase()}" data-ordine-schede="${opzioni.id_progetto}-${opzioni.ordineScheda}">
            <div class="post-bar">
                <div class="tit-container"><p class="titolo">${opzioni.titoloScheda}</p></div>
                <div class="icon-tray">
                    <img src="assets/svg-images/del-leave.svg" class="elimina" alt="del">
                    <img src="assets/svg-images/maxi-leave.svg" class="massimizza" alt="✎">
                    <img src="assets/svg-images/down-leave.svg" class="giu" alt="▼">
                    <img src="assets/svg-images/up-leave.svg" class="su" alt="▲">
                </div>
            </div>
            <div class="des-container"><p class="descrizione">${opzioni.descrizioneScheda}</p>
            </div>
        </div>
        `;

        // Associazione ad un elemento categoria
        const categoria = opzioni.categoria;
        const template = document.createElement('template');
        template.innerHTML = elHTML;
        (categoria.children.length > 0) ? categoria.insertBefore(template.content, categoria.firstChild) : categoria.appendChild(template.content);
        // toggleAddBtn(); //Riapparizione dei pulsanti "+"
            
        //distruggo i listener relativi al form
        // aggiornoIcone();
        // aggiornoEventi();
    },

};

// function verificaNotifiche() {
//     meh = document.querySelectorAll('.notifica-ignora');
//     meh.forEach((singolo) => {
//         console.log(singolo);
//         singolo.addEventListener('animationend', animazioneDismiss);
//         function animazioneDismiss(e) {
//             console.log("Hai chiuso la notifica!");
//             console.log(e.target);
//             e.target.remove();
//             e.target.removeEventListener('animationend', animazioneDismiss);
//         }
//     });
// }


