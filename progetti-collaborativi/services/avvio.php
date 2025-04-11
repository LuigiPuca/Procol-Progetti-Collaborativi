<?php

/**
 * Questa sezione si occupa di tutte le operazioni preliminari da usare
 * in vari script dell'applicazione. Tra cui creazione di una sessione, 
 * gestione degli errori, e connessione al db
 */

# Avvia la sessione se non è già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



# Attiva eccezioni per mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

# Script che serve per contenere le risposte alle richieste
require_once __DIR__ . '/core/Risposta.php';

# Script che serve per gestire gli errori
require_once __DIR__ . '/core/ErrorHandler.php';
$handler = new ErrorHandler();
$handler->register();

# Richiedere sempre, una sola volta, di importare il SessionManager
require_once __DIR__ . '/core/SessionManager.php';


# Caricare il db solo se NON è stato richiesto esplicitamente di evitarlo
$no_DB = (
    isset($_POST['no_DB']) 
    ?? isset($_GET['no_DB']) 
    ?? 'false'
) === 'true';

if(!$no_DB) {
    require_once __DIR__ . '/core/Database.php';
    $mysqli = (Database::getIstanza())->getDataBaseConnesso();
}


# Verificare se esiste ed è valida una sessione utente
$altri_dati = SessionManager::verificaSessione($no_DB);

?>