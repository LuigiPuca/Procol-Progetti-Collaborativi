<?php # Gestione del logout
session_start();
if (isset($_SESSION['uuid_utente']) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['disconnessione'])) {
    # Eseguo il logout facendo prima un unset della variabili di sessione...
    session_unset();
    # e poi distruggendo la sessione stessa
    session_destroy();
    #Allora restituisco informazioni sullo stato della connessione in json
    echo json_encode(array('isUtenteConnesso' => true));
} else {
    echo json_encode(array('isUtenteConnesso' => false));
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['disconnessione'])) {
    
    
}

?>