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

$suffisso = Risposta::get('chi')['suffisso'] ?: "o";

$sezione = null;
foreach (['home','team','board','inesistente'] as $sezione){
    if (isset($_POST[$sezione])) {
        Risposta::set('sezione', $sezione);
        $sezione = ucfirst($sezione);
        break;
    }
}

if (!$sezione) {
    throw new Exception("Errore: Impossibile richiedere il servizio");
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
            !isset($_GET['proj']) ||
            filter_var($_GET['proj'], FILTER_VALIDATE_INT) === false ||
            $_GET['proj'] < 0
        ) {
            throw new Exception("Errore: La pagina &egrave; inesistente!");
        }
        Board::getIstanza($mysqli, $user, $_GET['proj']);      
        break;
    default:
        throw new Exception("Il servizio richiesto è $session");
}




?>