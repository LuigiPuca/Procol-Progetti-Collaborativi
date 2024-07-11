// La funzione verificaUtenteConnesso() va chiamata non solo quando viene caricata la pagina...
window.onload = NuovaRichiestaHttpXML.verificaUtenteConnesso;
// ...ma anche quando viene recuperata dalla cache del browser (come quando usiamo il tasto "indietro")
window.addEventListener('pageshow', function(evento) {
    // Se la pagina viene recuperare dalla cache del browser evento.persisted sará vero.
    // typeof window.performance != 'undefined' verifica se é definita l'API delle prestazioni 
    // Se definita verifichiamo se il tipo di navigazione, nell'ultima intereazione, é di tipo back_forward
    var navCronologica = evento.persisted || (typeof window.performance != 'undefined' && performance.getEntriesByType("navigation")[0].type === "back_forward");
    if (navCronologica) {
        console.log('La pagina è stata recuperata dalla cache del browser.');
        // rieseguiamo la funzione interessata
        NuovaRichiestaHttpXML.verificaUtenteConnesso();
    }
});

bottoneEsci.addEventListener('click', logout);

// Ottengo il messaggio e il tipo salvati nel localStorage del browser prima del reindirizzamento
var messaggioRicevuto = localStorage.getItem('messaggio');
var tipoRicevuto = localStorage.getItem('tipoMessaggio');
if (messaggioRicevuto && tipoRicevuto) {
    NuovaRichiestaHttpXML.verificaUtenteConnesso();
    Notifica.appari({
        messaggioNotifica: messaggioRicevuto,
        tipoNotifica: tipoRicevuto,
    });
    // Rimuovo le variabili dallo storage del browser
    localStorage.removeItem('messaggio');
    localStorage.removeItem('tipoMessaggio');
}