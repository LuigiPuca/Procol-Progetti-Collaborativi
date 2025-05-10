<?php
/**
 * Seleziona quale file usare per recuperare informazioni necessarie alla 
 * costruzione di elementi lato client.
 */
require_once __DIR__ . "/../avvio.php";
require_once __DIR__ . "/../models/Utente.php";

# crea un array che contiene tutte le informazioni specifiche per la richiesta
$dati = [];

Risposta::unisciCon($altri_dati);

$suffisso = Risposta::get('chi')['suffisso'] ?? "o";

if (!$altri_dati) {
    Risposta::set('messaggio', "Accesso negato");
    Risposta::jsonDaInviare();
}

require_once __DIR__ . "/../utils/ambiente.php";

$sezione = null;
foreach (['home', 'team', 'board', 'dashboard', 'dashfocus,'] as $valore) {
    if (isset($dati_ricevuti[$valore])) {
        Risposta::set('sezione', $valore);
            $sezione = ucfirst($valore);
            break;
    }
}

if (!$sezione) {
    throw new Exception("Errore: Impossibile richiedere il servizio $contentType e $metodo?");
}

$user = new Utente($_SESSION['uuid_utente'], $mysqli);

$user_data = [
    'ruolo' => $user->getRuolo(),
    'email' => $user->getEmail(),
    'team' => $user->getTeam()
];

Risposta::unisciCon($user_data);
require_once __DIR__ . "/../models/$sezione.php";

switch ($sezione) {
    case 'Home':
        Home::getIstanza($mysqli, $user);
        break;
    case 'Team':
        Team::getIstanza($mysqli, $user);
        break;
    case 'Board':
        if (
            !isset($dati_ricevuti['proj']) ||
            filter_var($dati_ricevuti['proj'], FILTER_VALIDATE_INT) === false ||
            $dati_ricevuti['proj'] < 0
        ) {
            throw new Exception("Errore: La pagina &egrave; inesistente!");
        }
        Board::getIstanza($mysqli, $user, $_GET['proj']);      
        break;
    case 'Dashboard': 
        if (!$user->isAdmin()) {
            throw new Exception(
                "Accesso Negato: " . 
                "stai per essere reindirizzat$suffisso."
            );
        }
        Risposta::set('messaggio', 'Accesso Consentito.');
        $dati_post = null;
        if ($metodo !== 'GET') {
            $dati_post = $dati_ricevuti;
        }
        Dashboard::getIstanza($mysqli, $user, $dati_post);      
        break;
    case 'Dashfocus':
        if (!$user->isAdmin()) {
            throw new Exception(
                "Accesso Negato: " . 
                "stai per essere reindirizzat$suffisso."
            );
        }
        Risposta::set('messaggio', 'Richiesta Consentita.');
        Dashfocus::getIstanza($mysqli, $user);
        break;


    default:
        throw new Exception("Errore: Servizio richiesto $s non valido!");
}




?>