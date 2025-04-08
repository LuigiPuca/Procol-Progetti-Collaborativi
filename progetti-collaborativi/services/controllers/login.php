<?php
require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../core/AuthManager.php';
require_once __DIR__ . '/../utils/sanitizzazione.php';


# Se non arriva la richiesta di accesso si esce dallo script
if (!$_SERVER["REQUEST_METHOD"] == "POST" || !isset($_POST['accesso'])) {
    exit();
}

# se si è già autenticati 
if (SessionManager::isSessioneValida()) {
    $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
    $sm = "Sei gi&agrave; conness$suffisso";
    Risposta::set('messaggio', "");
    Risposta::jsonDaInviare();
}

$email = checkEmail($_POST['email'] ?? "");
$password = checkPassword($_POST['password'] ?? "");
$ricorda = $_POST['checkRicorda'] ?? false;


# Passare al metodo di login
AuthManager::login($email, $password, $mysqli, $ricorda);

?>