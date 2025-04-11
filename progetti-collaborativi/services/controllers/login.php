<?php
require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../core/AuthManager.php';
require_once __DIR__ . '/../utils/sanitizzazione.php';


# Se non arriva la richiesta di accesso si esce dallo script
if (!$_SERVER["REQUEST_METHOD"] == "POST" || !isset($_POST['accesso'])) {
    Risposta::redirectPage("signup","400: Richiesta Non Valida");
}

# se si è già autenticati 
if (SessionManager::isSessioneValida()) {
    $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
    $sm = "Sei gi&agrave; conness$suffisso";
    Risposta::set('messaggio', $sm);
    Risposta::jsonDaInviare();
}

$email = $_POST['email'] ?? "";
$password = $_POST['password'] ?? "";
$ricorda = $_POST['checkRicorda'] ?? false;


# Passare al metodo di login
AuthManager::login($email, $password, $mysqli, $ricorda);

?>