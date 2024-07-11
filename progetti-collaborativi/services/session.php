<?php 
session_start();

#Faccio una verifica, se esiste già una sessione utente
if (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() < $_SESSION['timeout']) {
    #Verifico se la sessione é recente  (cioè se sono connesso da massimo 5 secondi)
    $sessione_recente = false;
    if (isset($_SESSION['timein']) && time() - $_SESSION['timein'] < 5) {
        $sessione_recente = true;
    }
    #Allora restituisco informazioni sull'utente codificate in json
    echo json_encode(array(
        'isUtenteConnesso' => true, 
        'uuid_utente' => $_SESSION['uuid_utente'], 
        'chi' => $_SESSION['chi'], 
        'isSessioneRecente' => $sessione_recente, 
        'isSessioneScaduta' => false));
} elseif (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
    #Altrimenti forzo la chiusura della sessione e restituisco in json che la sessione é scaduta
    session_unset();    // Rimuovo tutte le variabili di sessione
    session_destroy(); // Distruggo la sessione completamente
    echo json_encode(array('isUtenteConnesso' => false, 'isSessioneScaduta' => true));
    exit();
} else {
    #Altrimenti restituisco informazioni in json solo che l'accesso non è esistente
    echo json_encode(array('isUtenteConnesso' => false, 'isSessioneScaduta' => false));
}
?>