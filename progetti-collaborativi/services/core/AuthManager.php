<?php 
require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../models/Utente.php';

/**
 * La classe AuthManager serve per gestire tutti i processi di 
 * autenticazione, come accesso, disconnessione e registrazione.
 */

class AuthManager {
    public static function signup(
        $nome, $cognome, $genere, 
        $email, $psw, $mysqli
    ) {
        $sm = Risposta::get('messaggio');

        #formatta i dati, per inserirli nel modo corretto nel DB
        $nome = ucwords(mb_strtolower($nome));
        $cognome = ucwords(mb_strtolower($cognome));
        $email = mb_strtolower($email);

        # hasha la password, per essere inviata in seguito al db
        $h_psw = password_hash($psw, PASSWORD_BCRYPT);
        
        # preimposta uno statement che verifichi l'esistenza nel db dell'email
        $stmt = $mysqli->prepare("SELECT uuid FROM utenti WHERE email = ?");
        # associa email al placeholder della query "?"
        $stmt->bind_param("s", $email);
        # esegue quindi effettivamente la query
        $stmt->execute();
        # memorriza il risultato della query
        $stmt->store_result();

        

        # se il risultato restituisce almeno una riga
        if ($stmt->num_rows > 0) {
            # Account già esisente
            Risposta::redirectPage("signup", "L'email esiste gi&agrave.");
        }

        # altrimenti, si procede con la registrazione
        $query_registrazione = "
            INSERT INTO utenti (nome, cognome, genere, email, password) 
                VALUES (?, ?, ?, ?, ?);
        ";
        $stmt = $mysqli->prepare($query_registrazione);
        $stmt->bind_param("sssss", $nome, $cognome, $genere, $email, $h_psw);
        if (!$stmt->execute()) {
            Risposta::redirectPage("signup", "Impossibile registrarsi.");
        }

        # genera un suffisso utile al msg di benvenuto
        $suffisso = ($genere === 'maschio') ? 'o' : 'a';
        $sm = 'Registrazione effettuata con successo! Benvenut' . $suffisso 
            . ' ' . ucwords($nome) . " ". ucwords($cognome) 
            . '<br>(' . $email . ')';
        Risposta::redirectPage("signup", $sm);
    }

    public static function login($email, $psw, $mysqli, $ricorda = false) {
        $sm = Risposta::get('messaggio');
        if (!$email || !$psw) {
            $sm = "Errore Server: Dati ricevuti in maniera non conforme.";
            Risposta::set('messaggio', $sm);
            Risposta::jsonDaInviare(400);
        }
        $query = "
            SELECT HEX(`uuid`) as `uuid`, nome, cognome, genere, 
            `password`, ruolo FROM utenti WHERE email = ?
        ";
        
        $stmt = $mysqli->prepare($query); //prepara la query
        $stmt->bind_param("s", $email); //associa email al primo placeholder
        $stmt->execute(); //segue la query
        $result = $stmt->get_result(); //salva i risultati
        # Se non vi è nessun risultato
        if (!($row = $result->fetch_assoc())) {
            $sm = "Errore: L'email non &egrave; associata a nessun account.";
            Risposta::set('messaggio', $sm);
            Risposta::redirectPage("errLogin", Risposta::get('messaggio'));
        }
        
        $stmt->close();
        /** 
         * Nel DB, per questioni di sicurezza, la password risulta hashata.
         * Di conseguenza, questa viene confrontata con la $psw inserita 
         * attraverso la funzione password_verify
         */
        # se la password non è corretta
        if (!password_verify($psw, $row['password'])) {
            $sm = "Errore: La combinazione email e password &egrave; errata.";
            Risposta::set('messaggio', $sm);
            Risposta::redirectPage("errLogin", Risposta::get('messaggio'));
        }

        # se la password è corretta, creare un oggetto utente
        $utente = Utente::caricaByUUID($row['uuid']);

        # verificare quanto debba durare la sessione lato server.
        $durata_sessione = match($utente->getRuolo()) {
            'admin' => $ricorda ? 7200 : 1800,
            'capo_team' => $ricorda ? 21600 : 3600,
            'utente' => $ricorda ? 43200 : 7200,
            default => $ricorda ? 20 : 10
        };

        /**
         * Per motivi di sicurezza, si vuole che la sessione, lato server, duri
         * sempre meno al crescere dell'importanza del tipo di utenza. Se al 
         * momento della connessione si è scelto di ricordare l'accesso, allora
         * la durata della sessione , specificata in secondi, sarà tot volte
         * maggiore di quella standard.
         * Per i cookie di sessione si verifica che, nel caso l'utente sia un
         * admin che ha scelto di non ricordare l'accesso, l'utente viene
         * disconnesso in modo forzato, seppur la sessione server potrebbe non
         * essere scaduta.
         * Per tutti gli altri casi i cookie di sessione avranno stessa durata
         * della sessione lato server.
         */
        $daSalvare = !($utente->isAdmin()) && !$ricorda;
        # verificare quanto debba durare la sessione lato client
        $durata_cookie = $daSalvare ? $durata_sessione : 0;
    
        # memorizzare tempo di accesso nel log del db
        $isInLog = self::logDiAccesso($email, $mysqli);
        
        # altrimenti fare l'accesso
        SessionManager::impostaSessione([
            'uuid' => $row['uuid'],
            'nome' => $row['nome'],
            'cognome' => $row['cognome'],
            'genere' => $row['genere']
        ], $durata_sessione, $durata_cookie);
        
        if ($isInLog) {
            Risposta::redirectPage("okLogin", $row['ruolo']);
        } else {
            Risposta::redirectPage("errLogin", Risposta::get('messaggio'));
        }
    }

    # funziona statica che si occupa di disconnettere l'utente
    public static function logout() {
        SessionManager::distruggiSessione();
    }

    /**
     * Memorizza tempo 'ultimo_accesso' nel campo omonimo dell'utente e crea
     * un resoconto nella tabella report
     */
    private static function logDiAccesso($email, $mysqli) {
        $mysqli->begin_transaction();

        $query_update = "
            UPDATE utenti SET ultimo_accesso = NOW() WHERE email = ?
        ";
        $query_report = "
            INSERT INTO report (tipo_azione, attore, descrizione, attore_era) 
            VALUES ('sessione', ?, 'Accesso', ?)
        ";
        $stmt_update = $mysqli->prepare($query_update);
        $stmt_report = $mysqli->prepare($query_report);
        try {
            $stmt_update->bind_param("s", $email);
            $stmt_update->execute();
            $stmt_report->bind_param("ss", $email, $email);
            $stmt_report->execute();
            $mysqli->commit();
            return true;
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            $msg = "Errore: " . e->getMessage();
            Risposta::set('messaggio', $msg);
            return false;
        } finally {
            if ($stmt_update !== null) $stmt_update->close();
            if ($stmt_report !== null) $stmt_report->close();
        }
    }
}
?>