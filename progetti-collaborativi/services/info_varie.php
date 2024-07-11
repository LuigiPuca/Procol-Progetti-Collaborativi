<?php 
session_start();
require_once('db_connection.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$risposta = [
    'messaggio' => '',
    'isUtenteConnesso' => false,
    'isSessioneScaduta' => false,
    'isSessioneRecente' => false,
    'uuid_utente' => null,
    'chi' => null,
    'ruolo' => null,
    'sezione' => null,
    'email' => null,
    'team' => null,
    'dati' => []
];

try {
    #Faccio una verifica, se esiste già una sessione utente
    if (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() < $_SESSION['timeout']) {
        $risposta['isUtenteConnesso'] = true;
        #Verifico se la sessione é recente  (cioè se sono connesso da massimo 5 secondi)
        $risposta['isSessioneRecente'] = isset($_SESSION['timein']) && (time() - $_SESSION['timein']) < 5;
        $risposta['uuid_utente'] = $_SESSION['uuid_utente'];
        $risposta['chi'] = $_SESSION['chi'];
        #salvamo la sessione utente in una variabile 
        $uuid_utente = $_SESSION['uuid_utente'];
        $suffisso = isset($_SESSION['chi']['suffisso']) ? $_SESSION['chi']['suffisso'] : "o";
        #Creiamo delle variabili che mi permettono di salvare dei dati che dipendono dalla pagina che visito
        $dati = [];
        $sezione = (isset($_POST['home'])) ? 'home' : ((isset($_POST['team'])) ? 'team' : ((isset($_POST['board'])) ? 'board' : null));
        $risposta['sezione'] = $sezione;
        # Verifichiamo se l'utente collegato sia un admin o un capo_team
        $query = "SELECT email, ruolo, team FROM utenti WHERE `uuid` = UNHEX(?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $uuid_utente);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($email, $ruolo, $team);
        $stmt->fetch();
        $stmt->close();
        # se capoteam va verificato che lo sia in modo effettivo
        if ($ruolo === 'capo_team') {
            $query_capoteam = "SELECT COUNT(*) FROM utenti u JOIN team t ON u.team = t.sigla AND t.responsabile = u.email WHERE u.email = ? AND u.ruolo = 'capo_team' AND t.sigla = ?";
            $stmt_capoteam = $mysqli->prepare($query_capoteam);
            $stmt_capoteam->bind_param("ss", $email, $team);
            $stmt_capoteam->execute();
            $stmt_capoteam->bind_result($isCapoTeam);
            $stmt_capoteam->fetch();
            $stmt_capoteam->close();
            $ruolo = $isCapoTeam ? "capo_team" : "utente";
        }

        $risposta['ruolo'] = $ruolo;
        $risposta['email'] = $email;	
        $risposta['team'] = $team;
        
        switch ($sezione) {
            case 'home':
                require_once './home.php';
                $result = elaboraHome($mysqli, $email, $_SESSION['chi'], $team, $ruolo);
                $risposta['messaggio'] = $result['msg'];
                $risposta['dati'] = $result['dati'];
                break;
            case 'team':
                require_once './team.php';
                $result = elaboraTeam($mysqli, $email, $_SESSION['chi'], $team, $ruolo);
                $risposta['messaggio'] = $result['msg'];
                $risposta['dati'] = $result['dati'];
                break;
            case 'board':
                require_once './board.php';
                if (isset($_GET['proj']) && filter_var($_GET['proj'], FILTER_VALIDATE_INT) !== false && $_GET['proj'] >= 0) {
                    $progetto = $_GET['proj'];
                    $result = elaboraBoard($mysqli, $email, $_SESSION['chi'], $progetto, $ruolo, $team);
                    $risposta['messaggio'] = $result['msg'];
                    $risposta['dati'] = $result['dati'];
                } else {
                    // Il parametro 'proj' non esiste o non è un numero intero naturale, restituisci un errore
                    $risposta['messaggio'] = "Errore: La pagina &egrave; inesistente!";
                    $risposta['dati'] = [];
                }                
                break;

        }
        
        #Chiudiamo la connessione al database
        if (isset($mysqli) && $mysqli->connect_errno == 0) $mysqli->close();
    } elseif (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
        #Altrimenti forzo la chiusura della sessione e restituisco in json che la sessione é scaduta
        session_unset();    // Rimuovo tutte le variabili di sessione
        session_destroy(); // Distruggo la sessione completamente
        $risposta['isSessioneScaduta'] = true;
    } #Altrimenti restituisco i booledani tutti falsi per stabilire che l'accesso non è esistente

} catch (Exception $e) {
    $risposta['messaggio'] = "Errore: " . $e->getMessage();
} finally {
   #Allora restituisco informazioni sull'utente codificate in json
   echo json_encode($risposta);
   exit();
}


?>