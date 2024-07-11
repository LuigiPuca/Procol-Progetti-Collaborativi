<?php 
include('session.php');
require_once('database.php');


// Gestione del login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accesso'])) {
    // Effettua l'autenticazione dell'utente
    // Verifica l'utente nel database e se le credenziali sono corrette, imposta $_SESSION['uuid_utente'] con l'UUID dell'utente
    $email = $_POST['email'] ?? "";
    $password = $_POST['password'] ?? "";
    $ricorda = $_POST['checkRicorda'] ?? false;
    // echo "1 " . $email . "<br>";
    // echo "2 " . $password . "<br><br>";
    // echo "3 " . $ricorda . "<br><br>";

    #eseguo una query per verificare se l'email è già presente nel DB e vedo qual'è sia l'uuid, la password memorizzata e il ruolo
    $stmt = $mysqli->prepare("SELECT HEX(`uuid`), nome, cognome, genere, `password`, ruolo FROM utenti WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 1) {
        #associo i risultati della query a questi 3 parametri
        $stmt->bind_result($uuid_utente, $nome_utente, $cognome_utente, $genere_utente, $password_db, $ruolo);
        #estraggo una tupla di questa associazione, cioé la prima e l'unica che ci interessa
        $stmt->fetch();
        // echo "UUID Utente: " . $uuid_utente . "<br>";
        // echo "Password nel Database: " . $password_db . "<br>";
        // echo "Ruolo: " . $ruolo . "<br>";
        #siccome nel db per questioni di sicurezza la password é salvata hashata, uso password_verify per fare il confronto
        if (password_verify($password, $password_db)) {
            #se la password è corretta, imposto $_SESSION['user_id'] con l'UUID dell'utente
            $_SESSION['uuid_utente'] = $uuid_utente;
            #restituisco informazioni sull'utente codificate in json
            // echo json_encode(array('isPassCorrispondente' => true, 'uuid_utente' => $_SESSION['uuid_utente']));
            $genere_rilevato = ($genere_utente === 'maschio') ? 'o' : 'a';
            $msg = "Bentornat" . "$genere_rilevato". " " . $nome_utente . " " . $cognome_utente . "!<br><br>Stai per essere indirizzato alla pagina ";
            $_SESSION['chi'] = array('nome' => $nome_utente, 'cognome' => $cognome_utente, 'suffisso' => $genere_rilevato);
            if ($ruolo == 'admin') {
                #per gli amministratori imposto la durata della sessione a solo 30 min, per ragioni di sicurezza. 
                #nel caso si è scelto di mantenere l'accesso ($ricorda = true) allora portiamo la durata a quella base di un utente normale, cioè due ore
                $durata_sessione = (!$ricorda) ? 1800 : 7200;
                #e il cookie di sessione, in entrambi i casi, a 0 secondi, perdendosi quindi a chiusura o riavvio del browser
                $durata_cookie = 0;
                $msg .= "Admin";
                header('Location: ../dashboard.html');
            } elseif ($ruolo == 'capo_team') {
                #per un capo di un team imposto la durata base della sessione a 1h (sicurezza meno severa), quella allungata pari a 6 ore
                $durata_sessione = (!$ricorda) ? 3600 : 21600;
                #e lo stesso anche per il cookie di sessione
                $durata_cookie = (!$ricorda) ? 3600 : 21600;
                $msg .= "Capo Team";
                header("Location: ../"); //provvisorio
                // header('Location: capo_team_dashboard.php');
            } else {
                #per un utente normale di base le imposto entrambe a 2h. Se allungo la sessione invece dureranno 12 ore
                $durata_sessione = (!$ricorda) ? 7200 : 43200;
                $durata_cookie = (!$ricorda) ? 7200 : 43200;
                $msg .= "Utente";
                header("Location: ../"); //provvisorio
                // header('Location: utente_dashboard.php');
            }
            # memorizzo quando ho effettuato l'accesso nella colonna 'ultimo_accesso' di utenti, e un resoconto nella tabella report
            $mysqli->begin_transaction();
            $stmt_update = $mysqli->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE email = ?");
            $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, attore_era) VALUES ('sessione', ?, 'Accesso', ?)");
            try {
                $stmt_update->bind_param("s", $email);
                $stmt_update->execute();
                $stmt_report->bind_param("ss", $email, $email);
                $stmt_report->execute();
                $mysqli->commit();
            } catch (mysqli_sql_exception $e) {
                $mysqli->rollback();
                $msg = $e->getMessage();
            } finally {
                if ($stmt_update !== null) $stmt_update->close();
                if ($stmt_report !== null) $stmt_report->close();
            }
            # salvo la durata della sessione e il cookie di sessione, e faccio un backup della variabile di sessione
            $_SESSION['durata_sessione'] = $durata_sessione ?? 300;
            $_SESSION['durata_cookie'] = $durata_cookie ?? 0;
            $backup_sessione = $_SESSION;
            # una volta fatto il backup distruggo la sessione per impostare correttamente la durata e la riavvio
            session_destroy();  //visualizzazione condizionale
            ini_set('session.gc_maxlifetime', $durata_sessione); // imposto la durata della sessione
            session_set_cookie_params($durata_cookie, '/'); // imposto la durata del cookie di sessione
            session_start(); //riavvio la sessione 
            $_SESSION = $backup_sessione; //la sessione riprende il suo stato precedente
            $_SESSION['timeout'] = time() + $durata_sessione; //metto "+ 20" se voglio fare dei test veloci, altrimenti $durata_sessione
            $_SESSION['timein'] = time(); //ci servirá per verificare che la sessione sia recente

        } else {
            #altrimenti restituisco che la password non è corretta
            // echo json_encode(array('isPassCorrispondente' => false));
            $msg = "La combinazione email e password &egrave; errata.";
        }
    } else {
        $msg = "L'email non &egrave; associata a nessun account.";
    }
    if (isset($_SESSION['uuid_utente'])) {
        #Allora restituisco informazioni sull'utente codificate in json
        // echo json_encode(array('isUtenteConnesso' => true, 'uuid_utente' => $_SESSION['uuid_utente'], 'durata_sessione' => $_SESSION['durata_sessione']));
        // echo '<br><br>';
    } else {
        #Altrimenti restituisco informazioni in json solo che l'accesso non è esistente
        // echo json_encode(array('isUtenteConnesso' => false));
        // echo '<br><br>';
        $a = base64_encode( '' . $msg . '<br><br><a href="./login.html">Torna indietro</a>');
        header("Location: ../errore.html?msg=" . urlencode($a)); 
        exit(); 
    }
    // echo $msg;
    $stmt->close();

    
}
?>