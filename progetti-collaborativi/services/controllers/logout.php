<?php
require_once __DIR__ . '/../core/AuthManager.php';

if (isset($_SESSION['uuid_utente']) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['disconnessione'])) {
    AuthManager::logout();
    #Allora restituisco informazioni sullo stato della connessione in json
    Risposta::set('isUtenteConnesso', true);
} else {
    Risposta::set('isUtenteConnesso', false);
}

Risposta::jsonDaInviare();

?>