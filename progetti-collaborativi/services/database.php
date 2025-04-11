<?php
$config = [
    'db_host' => 'localhost',
    'db_name' => 'progetticollaborativi',
    'db_user' => 'root',
    'db_password' => '',
];

try {
    // Creazione della connessione MySQLi
    $mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);

    // Impostazione del set di caratteri UTF-8
    if (!$mysqli->set_charset("utf8")) {
        throw new Exception("Errore durante l'impostazione del set di caratteri UTF-8: " . $mysqli->error);
    }

    // Uso Report error e strict per convertire errori in avvisi PHP ed eccezioni MySQLi, per evitare l'interruzione dello script PHP
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Disabilitazione della modalità di preparazione emulata
    $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

    // Stampa un messaggio di conferma
    echo "Connessione al database avvenuta con successo! <br>";
    // $a = base62_encode("Connessione al database avvenuta con successo!");
    // header("Location: ../redirect.html?msg=" . urlencode($a));
} catch (Exception $e) {
    // Gestione dell'eccezione
    echo 'Si &egrave verificato un errore: ' . $e->getMessage() . '<a href="portal.html">Torna indietro</a>';
    echo "Si &egrave verificato un errore: " . $e->getMessage();
    $a = base64_encode('Si &egrave verificato un errore: ' . $e->getMessage() . '<br><br><a href="./portal.html">Torna indietro</a>');
    //inviamo l'errore a una pagina dedicata che ha giá uno stile pronto
    header("Location: ../redirect.html?msg=" . urlencode($a));
    
    
    exit(); // Esci dallo script in caso di errore
}
?>

