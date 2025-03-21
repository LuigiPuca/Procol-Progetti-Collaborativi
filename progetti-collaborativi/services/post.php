<?php 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Controllo se il modulo é stato inviato 
    foreach ($_POST as $chiave => $valore) {
        echo $chiave . ": " . $valore . "<br>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Controllo se il modulo é stato inviato 
    foreach ($_GET as $chiave => $valore) {
        echo $chiave . ": " . $valore . "<br>";
    }
}
?>