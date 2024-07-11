document.addEventListener('DOMContentLoaded', function() {
    // aggiornoIcone();
    // aggiornoEventi();
});

function formattaDataCon(orario, timestamp) {
    // l'esistenza di un timestamp valido impedisce di avere valori non ben definiti convertiti al 01/01/1970
    if (!timestamp) return "";
    const date = new Date(timestamp);
    const now = new Date();

    const diffInSecondi = Math.floor((now - date) / 1000);
    const diffInMinuti = Math.floor(diffInSecondi / 60);
    const diffInOre = Math.floor(diffInMinuti / 60);
    const diffInGiorni = Math.floor(diffInOre / 24);
    // orario ci serve per definire se vogliamo mostrare sempre l'ora in maniera standard o usando stringhe come "pochi secondi fa"
    if (diffInGiorni < 1 && orario) {
        if (diffInOre < 1) {
            return (diffInMinuti < 1) ? "pochi secondi fa" : `${diffInMinuti} minuti fa`;
        } else {
            return `oggi alle ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
        }       
    } else if (diffInGiorni === 1 && orario) {
        return `ieri alle ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    } else {
        const tempo = ' ' + date.toLocaleTimeString('it-it', {hour: '2-digit', minute: '2-digit'});
        return date.toLocaleDateString('it-IT') + tempo;
    }
}





