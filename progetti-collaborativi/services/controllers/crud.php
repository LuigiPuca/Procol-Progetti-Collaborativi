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
require_once __DIR__ . "/../models/Scheda.php";
require_once __DIR__ . "/../core/UserManager.php";
require_once __DIR__ . "/../core/TeamManager.php";
require_once __DIR__ . "/../core/ProjManager.php";

$user = Utente::caricaByUUID($_SESSION['uuid_utente']);

$user_data = [
    'ruolo' => $user->getRuolo(),
    'email' => $user->getEmail(),
    'team' => $user->getTeam()
];

Risposta::unisciCon($user_data);

match (true) {
    array_key_exists('board_action', $dati_ricevuti) => 
        Categoria::getIstanza($mysqli, $user, $dati_ricevuti),
    array_key_exists('activity_action', $dati_ricevuti) => 
        Scheda::getIstanza($mysqli, $user, $dati_ricevuti),
    array_key_exists('user_action', $dati_ricevuti) => 
        UserManager::checkDatiRicevuti($user, $dati_ricevuti),
    array_key_exists('team_action', $dati_ricevuti) => 
        TeamManager::checkDatiRicevuti($user, $dati_ricevuti),
    array_key_exists('proj_action', $dati_ricevuti) => 
        ProjManager::checkDatiRicevuti($user, $dati_ricevuti),
    default => throw new Exception("Errore: Nessuna azione riconosciuta")

};




