<?php
/**
 * Seleziona quale file usare per recuperare informazioni necessarie alla 
 * costruzione di elementi lato client.
 */
require_once __DIR__ . "/../avvio.php";
require_once __DIR__ . "/../models/Utente.php";

$suffisso = Risposta::get('chi')['suffisso'] ?? "o";

if (!$altri_dati) {
    Risposta::set('messaggio', "Attenzione: Non sei pi&ugrave; online");
    Risposta::jsonDaInviare();
}
require_once __DIR__ . "/../utils/sanitizzazione.php";
require_once __DIR__ . "/../utils/ambiente.php";

require_once __DIR__ . "/../models/Categoria.php";

$user = new Utente($_SESSION['uuid_utente'], $mysqli);

$user_data = [
    'ruolo' => $user->getRuolo(),
    'email' => $user->getEmail(),
    'team' => $user->getTeam()
];

Risposta::unisciCon($user_data);

match (true) {
    isset($dati_ricevuti['board_action']) => 
        Categoria::getIstanza($mysqli, $user, $dati_ricevuti),
    default => throw new Exception("Errore: Nessuna azione riconosciuta")
};




