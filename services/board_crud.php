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
    'accessoLivello' => 0,
    'sezione' => null,
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
            $query_capoteam = "SELECT COUNT(*) FROM utenti u JOIN team t ON u.team = t.sigla AND t.responsabile = u.email WHERE u.email = ? AND u.ruolo = 'capo_team'";
            $stmt_capoteam = $mysqli->prepare($query_capoteam);
            $stmt_capoteam->bind_param("s", $email);
            $stmt_capoteam->execute();
            $stmt_capoteam->bind_result($isCapoTeam);
            $stmt_capoteam->fetch();
            $stmt_capoteam->close();
            $ruolo = $isCapoTeam ? "capo_team" : "utente";
        }

        $risposta['ruolo'] = $ruolo;
        
        if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
            # Se la richiesta è fatta con JSON, elaboriamo i dati JSON per aggiornare $_POST 
            # altrimenti procediamo come di norma
            $jsonData = file_get_contents('php://input');
            $_POST = json_decode($jsonData, true);
        }
        if (isset($_POST['operazione'])) {
            switch ($_POST['operazione']) {
                case 'crea_categoria':
                    $progetto = abs((int)$_POST['board_id']); //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $titolo = ucwords($_POST['titolo']); // mi assicuro di escapare le string
                    $hex_color = strtoupper($_POST['hex_color']);
                    $link = "board.html?proj=$progetto";
                    $stato_composto = "No Utente-$team-$progetto-$titolo";
                    if (
                        !preg_match("/^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/", $titolo) ||
                        !preg_match("/^#[0-9a-fA-F]{8}$/", $hex_color)
                        ) {
                            throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    } 
                    #verifichiamo se ho accesso al progetto
                    $isAccessoConsentito = false;
                    if ($ruolo === "admin") {
                        $isAccessoConsentito = true;
                    } else if ($ruolo !== "admin") {
                        $query_accesso = "SELECT COUNT(*) AS isAccessoConsentito FROM utenti u JOIN progetti p ON u.team = p.team_responsabile WHERE u.email = ? AND id_progetto = ? AND u.ruolo != 'admin'";
                        $stmt_accesso = $mysqli->prepare($query_accesso);
                        $stmt_accesso->bind_param("si", $email, $progetto);
                        $stmt_accesso->execute();
                        $stmt_accesso->bind_result($isAccessoConsentito);
                        $stmt_accesso->fetch();
                        $stmt_accesso->close();
                    }
                    if (!$isAccessoConsentito) {
                        throw new Exception("Non hai accesso al progetto!");
                    }

                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, team, progetto, categoria, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Creazione Categoria', ?, ?, ?, ?, ?, ?)");
                    $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_stati), -1) AS max_ord FROM stati WHERE id_progetto = ?");
                    $stmt_create = $mysqli->prepare("INSERT INTO stati (id_progetto, stato, colore_hex, ordine_stati, visibile) VALUES (?, ?, ?, ?, 1) ");                       
                    try {
                        #prepariamo ad eseguire il resoconto dell'azione in caso di successo
                        $stmt_report->bind_param("sssisss", $email, $link, $team, $progetto, $titolo, $email, $stato_composto);
                        $stmt_report->execute();
                        #verifichiamo chi é la categoria che ha ordine maggiore
                        $stmt_max_ordine->bind_param("i", $progetto);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->free_result();
                        #la nuova categoria avrà come ordine l'ordine maggiore incrementato di uno. 
                        $new_ord = $max_ord + 1;
                        #creiamo quindi la categoria in modo effettivo
                        $stmt_create->bind_param("issi", $progetto, $titolo, $hex_color, $new_ord);
                        $stmt_create->execute();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Categoria \"$titolo\" creata con successo!";
                        $successo = true;
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        if ($e->getCode() === 1062) {
                            if (strpos($e->getMessage(), 'PRIMARY') !== false) {
                                $risposta['messaggio'] = "Errore Transazione: In questo progetto esiste gi&agrave; una categoria chiamata \"$titolo\"!";
                            } elseif (strpos($e->getMessage(), 'id_progetto') !== false) {
                                $risposta['messaggio'] = "Errore Transazione: Si &egrave; verificato un errore con la creazione della categoria \"$titolo\"! Riprova perfavore.";
                            } else {
                                $risposta['messaggio'] = "Errore Transazione: trovato un duplicato non specificato: " . $e->getMessage() . ".";
                            }
                        } else {
                            $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                        }
                    } finally {
                        $successo = $successo ?? false;
                        if ($successo) {
                            #finita la transazione con successo decidiamo di tenere ordinati in modo sequenziale e successivo gli ordini degli stati
                            #tentiamo quindi di avviare la seguente procedura
                            $query = "CALL OrdinaStatiSelettivo(?)";
                            if ($stmt = $mysqli->prepare($query)) {
                                #Associamo i parametri e eseguiamo la query
                                $stmt->bind_param("i", $progetto);
                                if ($stmt->execute()) {
                                    #Se esguita corretamente la query, chiudiamo
                                    $stmt->close();
                                } else {
                                    #Se l'esecuzione fallisce, impostiamo un messaggio di errore
                                    $risposta['messaggio'] = "Attenzione: Categoria \"$titolo\" creata con successo, ma riordinamento delle categorie fallito!";
                                }
                            } else {
                                #impostiamo lo stesso messaggio di errore anche se fallisce la preparazione
                                $risposta['messaggio'] = "Attenzione: Categoria \"$titolo\" creata con successo, ma riordinamento delle categorie fallito! ";
                            } 
                        }
                        if ($stmt_create !== null) $stmt_create->close();
                        if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                    break;
                case 'elimina_categoria':
                    $progetto = abs((int)$_POST['id_progetto']) ?? false; //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = $_POST['categoria'] ?? false; // mi assicuro di escapare le string
                    if ($progetto === false || $categoria === false) {
                        throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    }
                    $link = "board.html?proj=$progetto";
                    $stato_composto = "No Utente-$team-$progetto-$categoria";
                    #verifichiamo se posso operare al progetto (devo essere o capo_team EFFETTIVO del team (non solo di ruolo) o admin (appartenente o meno al team))
                    $isOperazioneConsentita = false;
                    if ($ruolo === 'admin') {
                        $isOperazioneConsentita = true;
                    } else {
                        // vedere nel file istruzioni usate come é stata ottenuta la vista tremite join
                        $query_permesso = "SELECT COUNT(*) FROM utenti u LEFT JOIN vista_progetti_team_utenti v ON u.team = v.sigla_team WHERE u.ruolo != 'admin' AND u.team = ? AND id_progetto = ? AND u.email = ? AND v.email_responsabile = ?";
                        $stmt_permesso = $mysqli->prepare($query_permesso);
                        $stmt_permesso->bind_param("siss", $team, $progetto, $email, $email);
                        $stmt_permesso->execute();
                        $stmt_permesso->bind_result($isOperazioneConsentita);
                        $stmt_permesso->fetch();
                        $stmt_permesso->close();
                    }
                    if (!$isOperazioneConsentita) {
                        throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                    }
                    if (in_array(ucwords(strtolower($categoria)), ["In Corso", "In Attesa", "Completate", "In Ritardo", "Eliminate"])) {
                        $categoria = ucwords(strtolower($categoria));
                        throw new Exception("Non puoi eliminare la categoria \"$categoria\" perch&egrave; &egrave; una categoria predefinita!");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT COUNT(*) FROM stati WHERE id_progetto = ? AND stato = ?");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, team, progetto, categoria, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Eliminazione Categoria', ?, ?, ?, ?, ?, ?)");
                    $stmt_delete = $mysqli->prepare("DELETE FROM stati WHERE id_progetto = ? AND stato = ?");
                    try {               
                        # verifichiamo se la categoria che si sta cercando di eliminare esista
                        $stmt_read->bind_param('is', $progetto, $categoria);
                        $stmt_read->execute();
                        $stmt_read->bind_result($isRisultatoEsistente);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        if ($isRisultatoEsistente === 0) {
                            throw new mysqli_sql_exception("La categoria \"$categoria\" non esiste in questo progetto, oppure &egrave; gi&agrave; stata eliminata!");
                        }
                        # prepariamo il resoconto in caso di successo 
                        $stmt_report->bind_param("sssisss", $email, $link, $team, $progetto, $categoria, $email, $stato_composto);
                        $stmt_report->execute();
                        
                        # eliminiamo effettivamente il progetto selezionato
                        $stmt_delete->bind_param("is", $progetto, $categoria);
                        $stmt_delete->execute();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Categoria \"$categoria\" eliminata con successo!";
                        $successo = true;
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                    } finally {
                        $successo = $successo ?? false;
                        if ($successo) {
                            #finita la transazione con successo decidiamo di tenere ordinati in modo sequenziale e successivo gli ordini degli stati
                            #tentiamo quindi di avviare la seguente procedura
                            $query = "CALL OrdinaStatiSelettivo(?)";
                            if ($stmt = $mysqli->prepare($query)) {
                                #Associamo i parametri e eseguiamo la query
                                $stmt->bind_param("i", $progetto);
                                if ($stmt->execute()) {
                                    #Se esguita corretamente la query, chiudiamo
                                    $stmt->close();
                                } else {
                                    #Se l'esecuzione fallisce, impostiamo un messaggio di errore
                                    $risposta['messaggio'] = "Attenzione: Categoria \"$categoria\" eliminata con successo, ma riordinamento delle categorie fallito!";
                                }
                            } else {
                                #impostiamo lo stesso messaggio di errore anche se fallisce la preparazione
                                $risposta['messaggio'] = "Attenzione: Categoria \"$categoria\" eliminata con successo, ma riordinamento delle categorie fallito! ";
                            } 
                        }
                        if ($stmt_read !== null) $stmt_report->close();
                        if ($stmt_report !== null) $stmt_report->close();
                        if ($stmt_delete !== null) $stmt_delete->close();
                    }
                    break;
                case 'mostra_categoria':
                    $progetto = abs((int)$_POST['id_progetto']) ?? false; //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = $_POST['categoria'] ?? false; // mi assicuro di escapare le string
                    if (preg_match("/^eliminate$/i", $categoria))  throw new Exception("La categoria \"Eliminate\" non pu&ograve; essere impostata come visibile!");
                    if ($progetto === false || $categoria === false) {
                        throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    }
                    $link = "board.html?proj=$progetto";
                    $stato_composto = "No Utente-$team-$progetto-$categoria";                
                    #verifichiamo se posso operare al progetto (devo essere o capo_team EFFETTIVO del team (non solo di ruolo) o admin (appartenente o meno al team))
                    $isOperazioneConsentita = false;
                    if ($ruolo === 'admin') {
                        $isOperazioneConsentita = true;
                    } else {
                        // vedere nel file istruzioni usate come é stata ottenuta la vista tremite join
                        $query_permesso = "SELECT COUNT(*) FROM utenti u LEFT JOIN vista_progetti_team_utenti v ON u.team = v.sigla_team WHERE u.ruolo != 'admin' AND u.team = ? AND id_progetto = ? AND u.email = ? AND v.email_responsabile = ?";
                        $stmt_permesso = $mysqli->prepare($query_permesso);
                        $stmt_permesso->bind_param("siss", $team, $progetto, $email, $email);
                        $stmt_permesso->execute();
                        $stmt_permesso->bind_result($isOperazioneConsentita);
                        $stmt_permesso->fetch();
                        $stmt_permesso->close();
                    }
                    if (!$isOperazioneConsentita) {
                        throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT COUNT(*), visibile AS isVisibile FROM STATI WHERE id_progetto = ? AND stato = ?");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, team, progetto, categoria, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Visualizzazione Categoria', ?, ?, ?, ?, ?, ?)");
                    $stmt_update = $mysqli->prepare("UPDATE stati SET visibile = 1 WHERE id_progetto = ? AND stato = ?");
                    try {
                        # verifichiamo se la categoria che si sta cercando di mostrare esista e che non sia già visibile
                        $stmt_read->bind_param('is', $progetto, $categoria);
                        $stmt_read->execute();
                        $stmt_read->bind_result($isRisultatoEsistente, $isVisibile);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        if ($isRisultatoEsistente === 0) {
                            throw new mysqli_sql_exception("La categoria \"$categoria\" non esiste in questo progetto!");
                        } else if ($isVisibile === 1) {
                            throw new mysqli_sql_exception("La categoria \"$categoria\" &egrave; gi&agrave; visibile!");
                        } 
                        # prepariamo il resoconto in caso di successo
                        $stmt_report->bind_param("sssisss", $email, $link, $team, $progetto, $categoria, $email, $stato_composto);
                        $stmt_report->execute();
                        # mostriamo effettivamente il progetto selezionato
                        $stmt_update->bind_param("is", $progetto, $categoria);
                        $stmt_update->execute();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Categoria \"$categoria\" impostata come visibile, a tutti gli utenti, con successo!";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt_report !== null) $stmt_report->close();
                        if ($stmt_update !== null) $stmt_update->close();
                    }
                    break;  
                case 'nascondi_categoria':
                    $progetto = abs((int)$_POST['id_progetto']) ?? false; //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = $_POST['categoria'] ?? false; // mi assicuro di escapare le string
                    if ($progetto === false || $categoria === false) {
                        throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    }
                    $link = "board.html?proj=$progetto";
                    $stato_composto = "No Utente-$team-$progetto-$categoria";
                    #verifichiamo se posso operare al progetto (devo essere o capo_team EFFETTIVO del team (non solo di ruolo) o admin (appartenente o meno al team))
                    $isOperazioneConsentita = false;
                    if ($ruolo === 'admin') {
                        $isOperazioneConsentita = true;
                    } else {
                        // vedere nel file istruzioni usate come é stata ottenuta la vista tremite join
                        $query_permesso = "SELECT COUNT(*) FROM utenti u LEFT JOIN vista_progetti_team_utenti v ON u.team = v.sigla_team WHERE u.ruolo != 'admin' AND u.team = ? AND id_progetto = ? AND u.email = ? AND v.email_responsabile = ?";
                        $stmt_permesso = $mysqli->prepare($query_permesso);
                        $stmt_permesso->bind_param("siss", $team, $progetto, $email, $email);
                        $stmt_permesso->execute();
                        $stmt_permesso->bind_result($isOperazioneConsentita);
                        $stmt_permesso->fetch();
                        $stmt_permesso->close();
                    }
                    if (!$isOperazioneConsentita) {
                        throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT COUNT(*), visibile AS isVisibile FROM STATI WHERE id_progetto = ? AND stato = ?");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, team, progetto, categoria, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Oscuramento Categoria', ?, ?, ?, ?, ?, ?)");
                    $stmt_update = $mysqli->prepare("UPDATE stati SET visibile = 0 WHERE id_progetto = ? AND stato = ?");
                    try {
                        # verifichiamo se la categoria che si sta cercando di nascondere esista e che non sia già visibile
                        $stmt_read->bind_param('is', $progetto, $categoria);
                        $stmt_read->execute();
                        $stmt_read->bind_result($isRisultatoEsistente, $isVisibile);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        if ($isRisultatoEsistente === 0) {
                            throw new mysqli_sql_exception("La categoria \"$categoria\" non esiste in questo progetto!");
                        } else if ($isVisibile === 0) {
                            throw new mysqli_sql_exception("La categoria \"$categoria\" &egrave; gi&agrave; nascosta!");
                        } 
                        # prepariamo il resoconto in caso di successo
                        $stmt_report->bind_param("sssisss", $email, $link, $team, $progetto, $categoria, $email, $stato_composto);
                        $stmt_report->execute();
                        # nascondiamo effettivamente il progetto selezionato
                        $stmt_update->bind_param("is", $progetto, $categoria);
                        $stmt_update->execute();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Categoria \"$categoria\" impostata come nascosta, a tutti gli utenti, con successo!";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_report !== null) $stmt_report->close();
                        if ($stmt_update !== null) $stmt_update->close();
                    }
                    break;
                case 'sposta_categoria_sinistra':
                    $progetto = abs((int)$_POST['id_progetto']) ?? false; //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = $_POST['categoria'] ?? false; // mi assicuro di escapare le string
                    $categoria_target = $_POST['categoriaTarget'] ?? false;
                    if ($progetto === false || $categoria === false || $categoria_target === false) {
                        throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    }                    
                    #verifichiamo se posso operare al progetto (devo essere o capo_team EFFETTIVO del team (non solo di ruolo) o admin (appartenente o meno al team))
                    $isOperazioneConsentita = false;
                    if ($ruolo === 'admin') {
                        $isOperazioneConsentita = true;
                    } else {
                        // vedere nel file istruzioni usate come é stata ottenuta la vista tremite join
                        $query_permesso = "SELECT COUNT(*) FROM utenti u LEFT JOIN vista_progetti_team_utenti v ON u.team = v.sigla_team WHERE u.ruolo != 'admin' AND u.team = ? AND id_progetto = ? AND u.email = ? AND v.email_responsabile = ?";
                        $stmt_permesso = $mysqli->prepare($query_permesso);
                        $stmt_permesso->bind_param("siss", $team, $progetto, $email, $email);
                        $stmt_permesso->execute();
                        $stmt_permesso->bind_result($isOperazioneConsentita);
                        $stmt_permesso->fetch();
                        $stmt_permesso->close();
                    }
                    if (!$isOperazioneConsentita) {
                        throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT stato, ordine_stati FROM stati WHERE id_progetto = ? AND (stato = ? OR stato = ?) ORDER BY CASE WHEN stato = ? THEN 0 WHEN stato = ? THEN 1 ELSE 2 END");
                    $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_stati), -1) AS max_ord FROM stati WHERE id_progetto = ?");
                    $stmt_temp = $mysqli->prepare("UPDATE stati SET ordine_stati = ordine_stati + ? + 1000 WHERE id_progetto = ? AND (stato = ? OR stato = ?)");
                    $stmt_update = $mysqli->prepare("UPDATE stati SET ordine_stati = ? WHERE id_progetto = ? AND stato = ?");
                    try {
                        #verifichiamo che entrambe le categorie oggetto dell'operazione esistano
                        $dati_temp = [];
                        $stmt_read->bind_param('issss', $progetto, $categoria, $categoria_target, $categoria, $categoria_target);
                        $stmt_read->execute();
                        $stmt_read->bind_result($stato, $ordine);
                        while ($stmt_read->fetch()) {
                            $dati_temp[] = ['stato' => $stato, 'ordine' => $ordine];
                        }
                        $stmt_read->close();
                        if (count($dati_temp) < 2) {
                            throw new mysqli_sql_exception("Una, o entrambe, delle categorie in cui si sta cercando di operare potrebbe non esistere in questo progetto!");
                        } else if ($dati_temp[0]['ordine'] < $dati_temp[1]['ordine']) {
                            # verifichiamo se la prima categoria non sia già di ordine superiore alla categoria target
                            $stato = $dati_temp[0]['stato'];
                            $stato_target = $dati_temp[1]['stato'];
                            throw new mysqli_sql_exception("La categoria \"$stato\" precede gi&agrave; \"$stato_target\"!");
                        }
                        #verifichiamo chi é il progetto con massimo ordine
                        $stmt_max_ordine->bind_param('i', $progetto);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->close();
                        #incrementiamo l'ordine degli oggetti in analisi di un valore pari a massimo ordine + 1000 così da evitare conflitti di ordine con altre tuple non in analisi
                        $stmt_temp->bind_param("iiss", $max_ord, $progetto, $categoria, $categoria_target);
                        $stmt_temp->execute();
                        #decrementiamo l'ordine della prima categoria ed incrementiamo quella della seconda
                        $stmt_update->bind_param("iis", $dati_temp[1]['ordine'], $progetto, $dati_temp[0]['stato']);
                        $stmt_update->execute();
                        $stmt_update->bind_param("iis", $dati_temp[0]['ordine'], $progetto, $dati_temp[1]['stato']);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $mysqli->commit();
                        $risposta['messaggio'] = "\"$categoria\" spostata, prima di \"$categoria_target\", con successo!";
                        unset($dati_temp);                     
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                        if ($stmt_temp !== null) $stmt_temp->close();
                        if ($stmt_update !== null) $stmt_update->close();
                    }
                    break;
                case 'sposta_categoria_destra':
                    $progetto = abs((int)$_POST['id_progetto']) ?? false; //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = $_POST['categoria'] ?? false; // mi assicuro di escapare le string
                    $categoria_target = $_POST['categoriaTarget'] ?? false;
                    if ($progetto === false || $categoria === false || $categoria_target === false) {
                        throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    }
                    #verifichiamo se posso operare al progetto (devo essere o capo_team EFFETTIVO del team (non solo di ruolo) o admin (appartenente o meno al team))
                    $isOperazioneConsentita = false;
                    if ($ruolo === 'admin') {
                        $isOperazioneConsentita = true;
                    } else {
                        // vedere nel file istruzioni usate come é stata ottenuta la vista tremite join
                        $query_permesso = "SELECT COUNT(*) FROM utenti u LEFT JOIN vista_progetti_team_utenti v ON u.team = v.sigla_team WHERE u.ruolo != 'admin' AND u.team = ? AND id_progetto = ? AND u.email = ? AND v.email_responsabile = ?";
                        $stmt_permesso = $mysqli->prepare($query_permesso);
                        $stmt_permesso->bind_param("siss", $team, $progetto, $email, $email);
                        $stmt_permesso->execute();
                        $stmt_permesso->bind_result($isOperazioneConsentita);
                        $stmt_permesso->fetch();
                        $stmt_permesso->close();
                    }
                    if (!$isOperazioneConsentita) {
                        throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT stato, ordine_stati FROM stati WHERE id_progetto = ? AND (stato = ? OR stato = ?) ORDER BY CASE WHEN stato = ? THEN 0 WHEN stato = ? THEN 1 ELSE 2 END");
                    $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_stati), -1) AS max_ord FROM stati WHERE id_progetto = ?");
                    $stmt_temp = $mysqli->prepare("UPDATE stati SET ordine_stati = ordine_stati + ? + 1000 WHERE id_progetto = ? AND (stato = ? OR stato = ?)");
                    $stmt_update = $mysqli->prepare("UPDATE stati SET ordine_stati = ? WHERE id_progetto = ? AND stato = ?");
                    try{
                        #verifichiamo che entrambe le categorie oggetto dell'operazione esistano
                        $dati_temp = [];
                        $stmt_read->bind_param('issss', $progetto, $categoria, $categoria_target, $categoria, $categoria_target);
                        $stmt_read->execute();
                        $stmt_read->bind_result($stato, $ordine);
                        while ($row = $stmt_read->fetch()) {
                            $dati_temp[] = ['stato' => $stato, 'ordine' => $ordine];
                        }
                        $stmt_read->close();
                        if (count($dati_temp) < 2) {
                            throw new mysqli_sql_exception("Una, o entrambe, delle categorie in cui si sta cercando di operare potrebbe non esistere in questo progetto!");
                        } else if ($dati_temp[0]['ordine'] > $dati_temp[1]['ordine']) {
                            # verifichiamo se la prima categoria non sia già di ordine inferiore alla categoria target
                            $stato = $dati_temp[0]['stato'];
                            $stato_target = $dati_temp[1]['stato'];
                            throw new mysqli_sql_exception("La categoria \"$stato\" succede gi&agrave; \"$stato_target\"!");
                        }
                        #verifichiamo chi é il progetto con massimo ordine
                        $stmt_max_ordine->bind_param('i', $progetto);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->close();
                        #incrementiamo l'ordine degli oggetti in analisi di un valore pari a massimo ordine + 1000 così da evitare conflitti di ordine con altre tuple non in analisi
                        $stmt_temp->bind_param("iiss", $max_ord, $progetto, $categoria, $categoria_target);
                        $stmt_temp->execute();
                        #incrementiamo l'ordine della prima categoria e decrementiamo quella della seconda
                        $stmt_update->bind_param("iis", $dati_temp[1]['ordine'], $progetto, $dati_temp[0]['stato']);
                        $stmt_update->execute();
                        $stmt_update->bind_param("iis", $dati_temp[0]['ordine'], $progetto, $dati_temp[1]['stato']);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $mysqli->commit();
                        $risposta['messaggio'] = "\"$categoria\" spostata, dopo \"$categoria_target\", con successo!";

                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                        if ($stmt_temp !== null) $stmt_temp->close();
                        if ($stmt_update !== null) $stmt_update->close();
                    }
                    break;
                case 'crea_scheda':
                    $progetto = abs((int)$_POST['board_id']); //sanitizzo il valore in modo che sia un unsigned int
                    $team = ricavoTeam($progetto);
                    $categoria = ucwords($_POST['category_name']); // mi assicuro di escapare le string
                    $titolo_scheda = $_POST['titolo']; 
                    if (
                        !preg_match("/^[a-zA-Z0-9]{1}[a-zA-Z0-9\s\\\\]{0,19}$/", $categoria) ||
                        !preg_match("/^[a-zA-Z0-9]{1}[\$£€¥&@#ÀàÁáÂâÃãÄäÅåÆæÇçÈèÉéÊêËëÌìÍíÎîÏïÐðÑñÒòÓóÔôÕõÖöØøÙùÚúÛûÜüÝýÞþßÿa-zA-Z0-9 \s \' \\\\ \. \, \: \; \! \? \% \-]{0,49}$/", $titolo_scheda)
                        ) {
                            throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione $titolo_scheda!");
                    } 
                    #verifichiamo se ho accesso al progetto e alla categoria
                    $isAccessoConsentito = false;
                    if ($ruolo === "admin") {
                        $isAccessoConsentito = true;
                    } else if ($ruolo !== "admin") {
                        if ($ruolo === "capo_team") {
                            $query_accesso = "SELECT COUNT(*) AS isAccessoConsentito FROM utenti u JOIN progetti p ON u.team = p.team_responsabile JOIN stati s ON s.id_progetto = p.id_progetto AND s.stato = ? WHERE u.email = ? AND p.id_progetto = ? AND u.ruolo = 'capo_team'";  
                        } else {
                            $query_accesso = "SELECT COUNT(*) AS isAccessoConsentito FROM utenti u JOIN progetti p ON u.team = p.team_responsabile JOIN stati s ON s.id_progetto = p.id_progetto AND s.visibile = 1 AND s.stato = ? WHERE u.email = ? AND p.id_progetto = ? AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team')";  
                        }
                        $stmt_accesso = $mysqli->prepare($query_accesso);
                        $stmt_accesso->bind_param("ssi", $categoria, $email, $progetto);
                        $stmt_accesso->execute();
                        $stmt_accesso->bind_result($isAccessoConsentito);
                        $stmt_accesso->fetch();
                        $stmt_accesso->close();
                    }
                    if (!$isAccessoConsentito) {
                        throw new Exception("Non hai accesso al progetto o alla categoria!");
                    }
                     # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, team, categoria, scheda, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Creazione Scheda', ?, ?, ?, ?, UNHEX(?), ?, ?)");
                    $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_schede), -1) AS max_ord FROM schede WHERE id_progetto = ? AND stato = ?");
                    $stmt_create = $mysqli->prepare("INSERT INTO schede (id_progetto, stato, titolo, autore, ordine_schede) VALUES (?, ?, ?, ?, ?)");
                    $stmt_recupero = $mysqli->prepare("SELECT HEX(uuid_scheda) AS uuid_scheda FROM schede WHERE id_progetto = ? AND stato = ? AND titolo = ? AND autore = ? AND ordine_schede = ?"); 
                    try {
                        #verifichiamo chi é la scheda che ha ordine maggiore nella sua categoria di progetto
                        $stmt_max_ordine->bind_param("is", $progetto, $categoria);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->free_result();
                        #la nuova scheda avrà, nella sua categoria, come ordine l'ordine maggiore incrementato di uno. 
                        $new_ord = $max_ord + 1;
                        #creiamo quindi la scheda in modo effettivo
                        $stmt_create->bind_param("isssi", $progetto, $categoria, $titolo_scheda, $email, $new_ord);
                        if (!$stmt_create->execute()) {
                            throw new mysqli_sql_exception("Creazione della scheda fallita.");
                        }
                        # Recuperiamo l'UUID della riga appena inserita e convertiamolo in esadecimale
                        $stmt_recupero->bind_param("isssi", $progetto, $categoria, $titolo_scheda, $email, $new_ord);
                        $stmt_recupero->execute();
                        $result = $stmt_recupero->get_result();
                        $row = $result->fetch_assoc(); // Ottieni la riga risultante come array associativo
                        $uuid_scheda = $row['uuid_scheda'];
                        if (!$uuid_scheda) {
                            throw new mysqli_sql_exception("Recupero della scheda fallita, creazione annullata.");
                        }
                        $stmt_recupero->close();
                        $param_scheda = strtolower($uuid_scheda);
                        $link = "board.html?proj=$progetto&post=$param_scheda";
                        $scheda_composta = "No Utente-$team-$progetto-$categoria-$param_scheda";
                        #solo ora possiamo fare il resoconto dell'azione in quanto ci serviva l'uuid della scheda
                        $stmt_report->bind_param("ssisssss", $email, $link, $progetto, $team, $categoria, $uuid_scheda, $email, $scheda_composta);
                        $stmt_report->execute();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Scheda \"$titolo_scheda\" creata con successo!";
                        $successo = true;
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        if ($e->getCode() === 1062) {
                            if (strpos($e->getMessage(), 'PRIMARY') !== false) {
                                $risposta['messaggio'] = "Errore Transazione: Scheda non creata per chiave primaria duplicata";
                            } elseif (strpos($e->getMessage(), 'id_progetto') !== false) {
                                $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                            } else {
                                $risposta['messaggio'] = "Errore Transazione: trovato un duplicato non specificato: " . $e->getMessage() . ".";
                            }
                        } else {
                            $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                        }
                    } finally {
                        $successo = $successo ?? false;
                        if ($successo) {
                            #finita la transazione con successo decidiamo di tenere ordinati in modo sequenziale e successivo gli ordini delle schede
                            #tentiamo quindi di avviare la seguente procedura
                            $query = "CALL OrdinaSchedeSelettivo(?, ?)";
                            if ($stmt = $mysqli->prepare($query)) {
                                #Associamo i parametri e eseguiamo la query
                                $stmt->bind_param("is", $progetto, $categoria);
                                if ($stmt->execute()) {
                                    #Se esguita corretamente la query, chiudiamo
                                    $stmt->close();
                                } else {
                                    #Se l'esecuzione fallisce, impostiamo un messaggio di errore
                                    $risposta['messaggio'] = "Attenzione: Scheda \"$scheda\" creata con successo, ma riordinamento delle schede fallito!";
                                }
                            } else {
                                #impostiamo lo stesso messaggio di errore anche se fallisce la preparazione
                                $risposta['messaggio'] = "Attenzione: Schede \"$schede\" creata con successo, ma riordinamento delle schede fallito! ";
                            } 
                        }
                        if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                        if ($stmt_create !== null) $stmt_create->close();
                        if ($stmt_recupero !== null) $stmt_recupero->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                    break;
                default:
                    $risposta['messaggio'] = "Errore: Operazione non riconosciuta";
                    break;
            }
        }
        
        #Chiudiamo la connessione al database
        if (isset($mysqli) && $mysqli->connect_errno == 0) $mysqli->close();
    } elseif (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
        #Altrimenti forzo la chiusura della sessione e restituisco in json che la sessione é scaduta
        session_unset();    // Rimuovo tutte le variabili di sessione
        session_destroy(); // Distruggo la sessione completamento
        $risposta['isSessioneScaduta'] = true;
    } #Altrimenti restituisco i booledani tutti falsi per stabilire che l'accesso non è esistente
} catch (Exception $e) {
    $risposta['messaggio'] = "Errore: " . $e->getMessage();
} finally {
    #Allora restituisco informazioni sull'utente codificate in json
    echo json_encode($risposta);
    exit();
}

function ricavoTeam($progetto) {
    global $mysqli;
    $query = "SELECT team_responsabile FROM progetti WHERE id_progetto =?";
    if ($stmt = $mysqli->prepare($query)) {
        $stmt->bind_param("i", $progetto);
        if ($stmt->execute()) {
            $stmt->bind_result($team);
            $stmt->fetch();
            $stmt->close();
            return $team;
        } else {
            return null;
        }
    } else {
        return null;
    }
}

?>