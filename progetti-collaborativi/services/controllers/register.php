<?php
require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../core/AuthManager.php';
require_once __DIR__ . '/../utils/sanitizzazione.php';

# In caso di richiesta di accesso non arrivata -> esce da script
if (!$_SERVER["REQUEST_METHOD"] == "POST" || !isset($_POST['registrazione'])) {
    Risposta::redirectPage("signup","400: Richiesta Non Valida");
}

# in caso di sessione già attiva
if (SessionManager::isSessioneValida()) {
    $sm = "Non puoi registrarti durante una sessione gi&agrave; attiva";
    Risposta::set('messaggio', $sm);
    Risposta::jsonDaInviare();
}

# usa operatori di coealescenza per evitare valori undefined
$nome = trim($_POST['nome']) ?? "";
$cognome = trim($_POST['cognome']) ?? "";
$genere = $_POST['genere'] ?? "";
$email = trim($_POST['email']) ?? "";
$password = $_POST['password'] ?? "";
$isTCAccettate = (isset($_POST['checkTC']) && $_POST['checkTC'] == '1');

$isGenereSelezionato = ($genere === 'maschio' || $genere === 'femmina');
# verifica della validità dei dati
$err = ""; // inizialmente pongo che non ci sono errori
if (checkEmpty($nome, $cognome, $email, $password)) {
    $err = 'Compila tutti i campi!';
} elseif (!checkName($nome,$cognome)) {
    $err = 'Nome e Cognome non validi';
} elseif (!$isGenereSelezionato) {
    $err = 'Spuntare tutte le caselle';
} elseif (!checkPassword($password)) {
    $err = "La password deve essere di almeno 8 e al massimo 20 caratteri "
         . "tra cui una maiuscola, una minuscola, un numero ed un simbolo";
} elseif (!$isTCAccettate) {
    $err = "Accettare Termini e Condizioni";
}

# in caso di almeno un errore, redirect e uscita dallo script
!$err ?: Risposta::redirectPage("signup",$err);

# Altrimenti, passare al metodo di signup
AuthManager::signup($nome, $cognome, $genere, $email, $password, $mysqli);

?>