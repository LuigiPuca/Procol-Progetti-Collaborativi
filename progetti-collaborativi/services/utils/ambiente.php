<?php

$dati_passati = null;
$params_passati = null;
$metodo = $_SERVER["REQUEST_METHOD"] ?? '';
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';

if (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    # Se la richiesta è fatta con JSON, elaboriamo i dati JSON per aggiornare $_POST 
    # altrimenti procediamo come di norma
    $jsonData = file_get_contents('php://input');
    $dati_passati = json_decode($jsonData, true);
} else if (
    $metodo === 'POST' && 
    strpos($contentType, 'application/x-www-form-urlencoded') !== false
) {
    $dati_passati = $_POST;
}
if (!empty($_GET)) {
    $params_passati = $_GET;
}

$dati_ricevuti = array_merge($params_passati ?? [], $dati_passati ?? []);

?>
