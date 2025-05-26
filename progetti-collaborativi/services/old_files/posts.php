<?php 
session_start();
require_once('db_connection.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$risposta = [
    'messaggio' => '',
    'operazione' => '',
    'isUtenteConnesso' => false,
    'isSessioneScaduta' => false,
    'isSessioneRecente' => false,
    'uuid_utente' => null,
    'chi' => null,
    'ruolo' => null,
    'accessoLivello' => 0,
    'sezione' => null,
    'progetto_analizzato' => null,
    'stato_analizzato' => null,
    'post_analizzato' => null,
    'reply_analizzato' => null,
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
        # la richiesta è fatta con JSON, elaboriamo i dati JSON per aggiornare $_POST 
        if (strpos($_SERVER["CONTENT_TYPE"], "application/json") === false) {
            # lancio un errore
            throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
        }
        $jsonData = file_get_contents('php://input');
        $_POST = json_decode($jsonData, true);
        if (!isset($_POST['operazione'])) {
            throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
        }
        $risposta['operazione'] = $_POST['operazione'] ?? "nessuna operazione rilevata";
        $progetto = abs((int)$_POST['id_progetto']) ?? 0; //sanitizzo il valore in modo che sia un unsigned int
        $team = ricavoTeam($progetto);
        $categoria = ucwords($_POST['categoria']) ?? ""; // mi assicuro di escapare le string
        $uuid_scheda = $_POST['uuid_scheda'] ?? "";
        $risposta['progetto_analizzato'] = $progetto;
        $risposta['stato_analizzato'] = $categoria;
        $risposta['post_analizzato'] = strtolower($_POST['uuid_scheda']);
        if (!preg_match("/^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/", $categoria) ||
            !preg_match("/^[0-9A-Fa-f]{32}$/", $uuid_scheda)) {
                throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
        }
        
        #verifichiamo se ho accesso (e a che livello) alla scheda, ai suoi dettagli e ai suoi commenti
        $accessoLivello = 0;
        if ($ruolo === "admin") {
            $accessoLivello = 5;
        }
        if ($ruolo === "capo_team") {
            $accessoLivello = 4;
        }
        if ($accessoLivello < 4) {
            // se non si é capoteam si verifica se si è colui che ha creato la scheda di progetto, nel caso quest'ultima sia anche visibile a utenti normali
            $query_accesso = "SELECT COUNT(*) FROM utenti u JOIN progetti p ON u.team = p.team_responsabile AND p.id_progetto = ? AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team') JOIN stati c ON c.id_progetto = p.id_progetto AND c.visibile = 1 AND c.stato = ? JOIN schede s ON c.stato = s.stato AND s.uuid_scheda = UNHEX(?) AND u.email = s.autore WHERE u.email = ?";  
            $stmt_accesso = $mysqli->prepare($query_accesso);
            $stmt_accesso->bind_param("isss", $progetto, $categoria, $uuid_scheda, $email);
            $stmt_accesso->execute();
            $stmt_accesso->bind_result($isAccessoCreatore);
            $stmt_accesso->fetch();
            $stmt_accesso->close();
            if ($isAccessoCreatore) $accessoLivello = 3;
        }
        if ($accessoLivello < 3) {
            // se non si é creatore del progetto si verifica se si è l'incaricato alla scheda di progetto, nel caso quest'ultima si anche visibile a utenti normali
            $query_accesso = "SELECT COUNT(*) FROM utenti u JOIN progetti p ON u.team = p.team_responsabile AND p.id_progetto = ? AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team') JOIN stati c ON c.id_progetto = p.id_progetto AND c.visibile = 1 AND c.stato = ? JOIN schede s ON c.stato = s.stato JOIN info_schede i ON i.uuid_scheda = s.uuid_scheda AND s.uuid_scheda = UNHEX(?) AND i.incaricato = u.email WHERE u.email = ?";  
            $stmt_accesso = $mysqli->prepare($query_accesso);
            $stmt_accesso->bind_param("isss", $progetto, $categoria, $uuid_scheda, $email);
            $stmt_accesso->execute();
            $stmt_accesso->bind_result($isAccessoIncaricato);
            $stmt_accesso->fetch();
            $stmt_accesso->close();  
            if ($isAccessoIncaricato) $accessoLivello = 2;                          
        }
        if ($accessoLivello < 2) {
            // in ultima analisi se non si é nessuno di questi si verifica se si puó almeno visualizzare la scheda di progetto
            $query_accesso = "SELECT COUNT(*) FROM utenti u JOIN progetti p ON u.team = p.team_responsabile AND p.id_progetto = ? AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team') JOIN stati c ON c.id_progetto = p.id_progetto AND c.visibile = 1 AND c.stato = ? JOIN schede s ON c.stato = s.stato AND s.uuid_scheda = UNHEX(?) WHERE u.email = ?";  
            $stmt_accesso = $mysqli->prepare($query_accesso);
            $stmt_accesso->bind_param("isss", $progetto, $categoria, $uuid_scheda, $email);
            $stmt_accesso->execute();
            $stmt_accesso->bind_result($isAccessoConsentito);
            $stmt_accesso->fetch();
            $stmt_accesso->close();
            if ($isAccessoConsentito) $accessoLivello = 1; 
        }
        $risposta['accessoLivello'] = $accessoLivello;
        if ($accessoLivello === 0) throw new Exception("Errore: Accesso negato. Non hai i permessi per visualizzare questa scheda, o la scheda non esiste.");
        
        switch ($_POST['operazione']) {
            case 'elimina_scheda':
                $param_scheda = strtolower($uuid_scheda);
                $link = "board.html?proj=$progetto&post=$param_scheda";
                

                #verifichiamo se dobbiamo fare eliminazione definitiva o archiviazione della scheda
                $op = (ucwords(strtolower($categoria)) === "Eliminate") ? "elimina" : "archivia";
                #verifichiamo se ho i permessi necessari per l'operazione. Per eliminare ho bisogno di almeno un livello 4, per archiviare di almeno un livello 3
                
                if (($op === 'elimina' && $accessoLivello <= 3) || ($op === 'archivia' && $accessoLivello <= 2)) {
                    $stringa = ($op === 'elimina') ? 'eliminarla' : 'archiviarla';
                    throw new Exception("Non hai accesso a questa scheda o non hai i permessi per $stringa!");
                }
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare("SELECT COUNT(*), autore FROM schede WHERE uuid_scheda = UNHEX(?) AND id_progetto = ? AND stato = ?");
                # in base a se la scheda si trovi nella categoria eliminate o meno, decidiamo se eliminare definitamente la scheda o se spostarla nella categoria schede
                $stmt_report = ($op === 'elimina') ? $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, utente, team, progetto, categoria, scheda, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Eliminazione Scheda', ?, ?, ?, ?, ?, UNHEX(?), ?, ?)") : $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, utente, team, progetto, categoria, scheda, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Archiviazione Scheda', ?, ?, ?, ?, ?, UNHEX(?), ?, ?)");
                $stmt_delete = ($op === 'elimina') ? $mysqli->prepare("DELETE FROM schede WHERE uuid_scheda = UNHEX(?)") : $mysqli->prepare("UPDATE schede SET stato = 'Eliminate', ordine_schede = ? WHERE uuid_scheda = UNHEX(?)");
                $stmt_max_ordine = ($op === 'elimina') ? null : $mysqli->prepare("SELECT COALESCE(MAX(ordine_schede), -1) AS max_ord FROM schede WHERE id_progetto = ? AND stato = 'Eliminate'");
                try {
                    #verifichiamo che la scheda che si sta cercando di eliminare esista e che sia effettivamente nello stato e progetto specificato
                    $stmt_read->bind_param("sii", $uuid_scheda, $progetto, $categoria);
                    $stmt_read->execute();
                    $stmt_read->bind_result($isSchedaEsistente, $autore);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    if ($isSchedaEsistente === 0) throw new mysqli_sql_exception("Scheda non esistente oppure presente in una categoria non accessibile!");
                    # prepariamo il resoconto in caso di successo
                    $scheda_composta = "$autore-$team-$progetto-$categoria-$uuid_scheda";
                    $stmt_report->bind_param("ssssissss", $email, $link, $autore, $team, $progetto, $categoria, $uuid_scheda, $email, $scheda_composta);
                    $stmt_report->execute();
                    $stmt_report->close();
                    if (($op === 'elimina')) {
                        #eliminiamo definitivamente la scheda se si trova già in eliminate
                        $stmt_delete->bind_param("s", $uuid_scheda);
                        $azione = 'eliminata definitivamente con successo!';
                    } else {
                        # se la scheda non si trova già nella categoria eliminate, spostiamola
                        $stmt_max_ordine->bind_param("i", $progetto);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->free_result();
                        $new_ord = $max_ord + 1;
                        $stmt_delete->bind_param("is", $new_ord, $uuid_scheda);
                        $azione = 'archiviata con successo! Sar&agrave; solo visibile a te e ai tuoi superiori effettivi.';
                    }
                    $stmt_delete->execute();
                    $stmt_delete->close();
                    $mysqli->commit();
                    $risposta['messaggio'] = "Scheda $azione";
                    $successo = true;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
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
                                $risposta['messaggio'] = "Attenzione: Riordinamento delle schede fallito! Tuttavia, " . $risposta['messaggio']; 
                            }
                        } else {
                            #impostiamo lo stesso messaggio di errore anche se fallisce la preparazione
                            $risposta['messaggio'] = "Attenzione: Riordinamento delle schede fallito! Tuttavia, " . $risposta['messaggio']; 
                        } 
                    }
                    if ($stmt_report !== null) $stmt_report->close();
                    if ($stmt_delete !== null) $stmt_delete->close();
                    if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                }
                break;
            case 'sposta_scheda_su':
            case 'sposta_scheda_giu':
                $sub_op = explode("_",$_POST['operazione']);
                $sub_op = $sub_op[2];
                $uuid_scheda_target = $_POST['uuid_scheda_target'] ?? "";
                if (!preg_match("/^[0-9A-Fa-f]{32}$/", $uuid_scheda_target)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                if ($accessoLivello <= 3) throw new Exception("Non hai i permessi necessari ad eseguire l'operazione!");
                # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare("SELECT titolo, ordine_schede FROM schede WHERE id_progetto = ? AND stato = ? AND (uuid_scheda = UNHEX(?) OR uuid_scheda = UNHEX(?)) ORDER BY CASE WHEN uuid_scheda = UNHEX(?) THEN 0 WHEN uuid_scheda = UNHEX(?) THEN 1 ELSE 2 END");
                $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_schede), -1) AS max_ord FROM schede WHERE id_progetto = ? AND stato = ?");
                $stmt_temp = $mysqli->prepare("UPDATE schede SET ordine_schede = ordine_schede + ? + 1000 WHERE id_progetto = ? AND stato = ? AND (uuid_scheda = UNHEX(?) OR uuid_scheda = UNHEX(?))");
                $stmt_update = $mysqli->prepare("UPDATE schede SET ordine_schede = ? WHERE uuid_scheda = UNHEX(?)");
                try {
                    #verifichiamo che entrambe le schede oggetto dell'operazione esistano
                    $dati_temp = [];
                    $stmt_read->bind_param('isssss', $progetto, $categoria, $uuid_scheda, $uuid_scheda_target, $uuid_scheda, $uuid_scheda_target);
                    $stmt_read->execute();
                    $stmt_read->bind_result($scheda, $ordine);
                    while ($stmt_read->fetch()) {
                        $dati_temp[] = ['scheda' => $scheda, 'ordine' => $ordine];
                    }

                    if (count($dati_temp) < 2) {
                        throw new mysqli_sql_exception("Una, o entrambe, delle schede in cui si sta cercando di operare potrebbe non esistere in questa categoria di progetto!");
                    } else if ($sub_op === 'su' && $dati_temp[0]['ordine'] <= $dati_temp[1]['ordine']) {
                        # verifichiamo se la prima categoria non sia già di ordine superiore alla categoria target
                        $scheda = $dati_temp[0]['scheda'];
                        $scheda_target = $dati_temp[1]['scheda'];
                        throw new mysqli_sql_exception("La scheda \"$scheda\" precede gi&agrave; \"$scheda_target\"!");
                    } else if ($sub_op === 'giu' && $dati_temp[0]['ordine'] >= $dati_temp[1]['ordine']) {
                        $scheda = $dati_temp[0]['scheda'];
                        $scheda_target = $dati_temp[1]['scheda'];
                        throw new mysqli_sql_exception("La scheda \"$scheda\" succede gi&agrave; \"$scheda_target\"!");
                    }
                    $scheda = $dati_temp[0]['scheda'];
                    $scheda_target = $dati_temp[1]['scheda'];
                    #verifichiamo chi é il progetto con massimo ordine
                    $stmt_max_ordine->bind_param("is", $progetto, $categoria);
                    $stmt_max_ordine->execute();
                    $stmt_max_ordine->bind_result($max_ord);
                    $stmt_max_ordine->fetch();
                    $stmt_max_ordine->close();
                    #incrementiamo l'ordine degli oggetti in analisi di un valore pari a massimo ordine + 1000 così da evitare conflitti di ordine con altre tuple non in analisi
                    $stmt_temp->bind_param("iisss", $max_ord, $progetto, $categoria, $uuid_scheda, $uuid_scheda_target);
                    $stmt_temp->execute();
                    #scambiamo l'ordine dei due oggetti in analisi
                    $stmt_update->bind_param("is", $dati_temp[1]['ordine'], $uuid_scheda);
                    $stmt_update->execute();
                    $stmt_update->bind_param("is", $dati_temp[0]['ordine'], $uuid_scheda_target);
                    $stmt_update->execute();
                    $stmt_update->close();
                    $mysqli->commit();
                    $stringa = $sub_op === 'su' ? 'prima di' : 'dopo di';
                    $risposta['messaggio'] = "Scheda \"$scheda\" spostata, $stringa \"$scheda_target\", con successo!";
                    unset($dati_temp);
                } catch (mysqli_sql_Exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                    if ($stmt_temp !== null) $stmt_temp->close();
                    if ($stmt_update !== null) $stmt_update->close();
                }
                break;
            case 'massimizza_scheda':
                $order_by = ($_POST['order_by'] === 'DESC') ? 'DESC' : 'ASC';
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare('SELECT s.id_progetto, s.stato, HEX(s.uuid_scheda), s.titolo, s.descrizione, s.autore, a.nome, a.cognome, a.genere, a.ruolo, s.creazione, s.scadenza, i.incaricato, r.nome, r.cognome, i.inizio_mandato, i.fine_mandato, i.data_inizio, i.data_fine, i.ultima_modifica, i.modificato_da, e.nome, e.cognome FROM schede s JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda AND s.uuid_scheda = UNHEX(?) LEFT JOIN utenti a ON a.email = s.autore LEFT JOIN utenti r ON r.email = i.incaricato LEFT JOIN utenti e ON e.email = i.modificato_da WHERE s.id_progetto = ? AND s.stato = ?');
                $stmt_obtain = $mysqli->prepare("SELECT HEX(r.uuid_commento), r.contenuto, r.mittente, m.nome, m.cognome, m.genere, m.ruolo, r.inviato, r.destinatario, d.nome, d.cognome, r.modificato_da, e.nome, e.cognome, r.modificato_il, r.uuid_in_risposta FROM commenti r LEFT JOIN utenti m ON m.email = r.mittente LEFT JOIN utenti d ON d.email = r.destinatario LEFT JOIN utenti e ON e.email = r.modificato_da WHERE uuid_scheda = UNHEX(?) ORDER BY r.inviato $order_by");
                try {
                    $stmt_read->bind_param("ssi", $uuid_scheda, $progetto, $categoria);
                    $stmt_read->execute();
                    $stmt_read->store_result();
                    if ($stmt_read->num_rows !== 1) {
                        throw new mysqli_sql_exception("Scheda inesistente o inaccessibile!");
                    }
                    $stmt_read->bind_result($id_progetto, $stato, $uuid_scheda, $titolo, $descrizione, $autore, $nome_autore, $cognome_autore, $sesso_autore, $ruolo_autore, $creazione, $scadenza, $incaricato, $nome_incaricato, $cognome_incaricato, $inizio_mandato, $fine_mandato, $data_inizio, $data_fine, $ultima_modifica, $modificato_da, $nome_editor, $cognome_editor);
                    $stmt_read->fetch();
                    if ($ruolo_autore === "capo_team") {
                        // verifichiamo se é effetivo
                        $query_role = "SELECT COUNT(*) FROM utenti u JOIN progetti p ON u.team = p.team_responsabile AND p.id_progetto = ? AND u.ruolo = 'capo_team' WHERE u.email = ?";
                        $stmt_role = $mysqli->prepare($query_role);
                        $stmt_role->bind_param("is", $id_progetto, $autore);
                        $stmt_role->execute();
                        $stmt_role->bind_result($isLeader);
                        $stmt_role->fetch();
                        $stmt_role->close();
                        $ruolo_autore = ($isLeader) ? "capo_team" : "utente";
                    }
                    $scheda_overlay = [
                        "id_progetto" => $id_progetto,
                        "stato" => $stato,
                        "uuid_scheda" => $uuid_scheda,
                        "titolo" => $titolo,
                        "descrizione" => $descrizione,
                        "autore" => $autore,
                        "nome_autore" => $nome_autore,
                        "cognome_autore" => $cognome_autore,
                        "sesso_autore" => $sesso_autore,
                        "ruolo_autore" => $ruolo_autore,
                        "creazione" => $creazione,
                        "scadenza" => $scadenza,
                        "incaricato" => $incaricato,
                        "nome_incaricato" => $nome_incaricato,
                        "cognome_incaricato" => $cognome_incaricato,
                        "inizio_mandato" => $inizio_mandato,
                        "fine_mandato" => $fine_mandato,
                        "data_inizio" => $data_inizio,
                        "data_fine" => $data_fine,
                        "ultima_modifica" => $ultima_modifica,
                        "modificato_da" => $modificato_da,
                        "nome_editor" => $nome_editor,
                        "cognome_editor" => $cognome_editor,
                        "commenti" => []
                    ];
                    $stmt_obtain->bind_param("s", $uuid_scheda);
                    $stmt_obtain->execute();
                    $stmt_obtain->store_result();
                    $stmt_obtain->bind_result($uuid_commento, $contenuto, $mittente, $nome_mittente, $cognome_mittente, $sesso_mittente, $ruolo_mittente, $inviato, $destinatario, $nome_destinatario, $cognome_destinatario, $modificato_da, $nome_editore, $cognome_editore, $modificato_il, $uuid_in_risposta);
                    while ($stmt_obtain->fetch()) {
                        if ($ruolo_mittente === "capo_team") {
                            // verifichiamo se é effetivo
                            $query_role = "SELECT COUNT(*) FROM utenti u JOIN progetti p ON u.team = p.team_responsabile AND p.id_progetto = ? AND u.ruolo = 'capo_team' WHERE u.email = ?";
                            $stmt_role = $mysqli->prepare($query_role);
                            $stmt_role->bind_param("is", $id_progetto, $mittente);
                            $stmt_role->execute();
                            $stmt_role->bind_result($isLeader);
                            $stmt_role->fetch();
                            $stmt_role->close();
                            $ruolo_mittente = ($isLeader) ? "capo_team" : "utente";
                        }
                        $is_modificabile = ($accessoLivello > 3 || $mittente === $email) ? 1 : 0;
                        $scheda_overlay["commenti"][] = [
                            "uuid_commento" => strtolower($uuid_commento),
                            "contenuto" => $contenuto,
                            "mittente" => $mittente,
                            "nome_mittente" => $nome_mittente,
                            "cognome_mittente" => $cognome_mittente,
                            "sesso_mittente" => $sesso_mittente,
                            "ruolo_mittente" => $ruolo_mittente,
                            "inviato" => $inviato,
                            "destinatario" => $destinatario,
                            "nome_destinatario" => $nome_destinatario,
                            "cognome_destinatario" => $cognome_destinatario,
                            "modificato_da" => $modificato_da,
                            "nome_editore" => $nome_editore,
                            "cognome_editore" => $cognome_editore,
                            "modificato_il" => $modificato_il,
                            "is_modificabile" => $is_modificabile,
                            "uuid_in_risposta" => $uuid_in_risposta
                        ];
                    }
                    $mysqli->commit();
                    $risposta['dati']['schedaOverlay'] = $scheda_overlay;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_obtain !== null) $stmt_obtain->close();
                }
                
                break;
            case 'aggiungi-descrizione':
            case 'modifica-descrizione': 
                # per aggiungere o modificare la descrizione di una scheda
                $descrizione = html_entity_decode($_POST['descrizione']) ?? "";
                if (preg_match("/^\s*$/", $descrizione)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
                if ($accessoLivello < 3) throw new Exception("Non hai accesso a questa scheda o non hai i permessi per aggiungere una descrizione!");
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare('SELECT HEX(s.uuid_scheda), s.descrizione, s.autore, i.ultima_modifica FROM schede s JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda AND s.uuid_scheda = UNHEX(?) WHERE s.id_progetto = ? AND s.stato = ?');
                $stmt_update = $mysqli->prepare('UPDATE schede SET descrizione = ? WHERE uuid_scheda = UNHEX(?)');
                $stmt_edit = $mysqli->prepare('UPDATE info_schede SET ultima_modifica = ?, modificato_da = ? WHERE uuid_scheda = UNHEX(?)');
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, ?, ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                try {
                    # inizio a verificare se la scehda esiste
                    $stmt_read->bind_param("sis", $uuid_scheda, $progetto, $categoria);
                    $stmt_read->execute();
                    $stmt_read->store_result();
                    if ($stmt_read->num_rows !== 1) {
                        throw new mysqli_sql_exception("Scheda inesistente o inaccessibile!");
                    }
                    $stmt_read->bind_result($uuid_scheda, $descrizione_scheda, $autore_scheda, $ultima_modifica);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    # superata la verifica di esistenza, andiamo a controllare se trattasi di aggiunta di una descrizione per la prima volta o di una modifica
                    $op = ($ultima_modifica === null && $descrizione_scheda === "") ? "Aggiunta Descrizione Scheda" : "Modifica Descrizione Scheda";
                    $stmt_update->bind_param("ss", $descrizione, $uuid_scheda);
                    $stmt_update->execute();
                    $stmt_update->close();
                    $azione = "Descrizione aggiunta con successo!";
                    if ($op  === 'Modifica Descrizione Scheda') {
                        $modifica_attuale = date('Y-m-d H:i:s');
                        $stmt_edit->bind_param("sss", $modifica_attuale, $email, $uuid_scheda);
                        $stmt_edit->execute();
                        $stmt_edit->close();
                        $azione = "Descrizione modificata con successo!";
                    }
                    # creiamo un report in caso di successo
                    $param_scheda = strtolower($uuid_scheda);
                    $link = "board.html?proj=$progetto&post=$param_scheda";
                    $bersaglio_composto = "$autore_scheda-$team-$progetto-$categoria-$param_scheda";
                    $stmt_report->bind_param("sssissssss", $email, $op, $link, $progetto, $categoria, $uuid_scheda, $autore_scheda, $team, $email, $bersaglio_composto);
                    $stmt_report->execute();
                    $mysqli->commit();
                    $risposta['messaggio'] = $azione;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_edit !== null) $stmt_edit->close();
                    if ($stmt_report !== null) $stmt_report->close();
                }
                break;
            case "aggiungi-commento":
                # per aggiungere o modificare la descrizione di una scheda
                $descrizione = html_entity_decode($_POST['descrizione']) ?? "";
                $contenuto_temporaneo = bin2hex(random_bytes(16));
                if (preg_match("/^\s*$/", $descrizione)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
                if ($accessoLivello = 0) throw new Exception("Non hai accesso a questa scheda o non hai i permessi per rispondere al post!");
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare('SELECT HEX(uuid_scheda), autore FROM schede WHERE uuid_scheda = UNHEX(?) AND id_progetto = ? AND stato = ?');
                $stmt_create = $mysqli->prepare('INSERT INTO commenti (uuid_scheda, contenuto, mittente) VALUES (UNHEX(?), ?, ?)');
                $stmt_find = $mysqli->prepare('SELECT HEX(uuid_commento) FROM commenti WHERE uuid_scheda = UNHEX(?) AND contenuto = ?');
                $stmt_update = $mysqli->prepare('UPDATE commenti SET contenuto = ? WHERE uuid_commento = UNHEX(?)');
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Creazione Commento', ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                try {
                    $stmt_read->bind_param("sis", $uuid_scheda, $progetto, $categoria);
                    $stmt_read->execute();
                    $stmt_read->store_result();
                    if ($stmt_read->num_rows!== 1) {
                        throw new mysqli_sql_exception("Scheda inesistente o inaccessibile!");
                    }
                    $stmt_read->bind_result($uuid_scheda, $autore_scheda);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    #creiamo il commento con contenuto temporaneamente alterato in modo da essere unico durante il processo
                    $stmt_create->bind_param("sss", $uuid_scheda, $contenuto_temporaneo, $email);
                    $stmt_create->execute();
                    #recuperiamo l'uuid del commento appena creato sfruttando il contenuto temporaneo
                    $stmt_find->bind_param("ss", $uuid_scheda, $contenuto_temporaneo);
                    $stmt_find->execute();
                    $stmt_find->bind_result($uuid_commento);
                    $stmt_find->fetch();
                    $stmt_find->close();
                    # aggiorniamo il commento con il contenuto corretto
                    $stmt_update->bind_param("ss", $descrizione, $uuid_commento);
                    $stmt_update->execute();
                    $stmt_update->close();
                    # creiamo un report in caso di successo
                    $param_scheda = strtolower($uuid_scheda);
                    $frag_commento = strtolower($uuid_commento);
                    $link = "board.html?proj=$progetto&post=$param_scheda#repl=$frag_commento";
                    $bersaglio_composto = "$autore_scheda-$team-$progetto-$categoria-$param_scheda-$frag_commento";
                    $stmt_report->bind_param("ssissssss", $email, $link, $progetto, $categoria, $uuid_scheda, $autore_scheda, $team, $email, $bersaglio_composto);
                    $stmt_report->execute();
                    $mysqli->commit();
                    $azione = "Commento aggiunto con successo!";
                    $risposta['messaggio'] = $azione;
                } catch (mysqli_sql_exception $e){
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: " . $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_create !== null) $stmt_create->close();
                    if ($stmt_find !== null) $stmt_find->close();
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_report !== null) $stmt_report->close();
                }
                break;
            case "elimina-commento":
                $uuid_commento = $_POST['uuid_commento'] ?? "";
                if (!preg_match("/^[0-9A-Fa-f]{32}$/", $uuid_commento)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                if ($accessoLivello < 4) {
                        // verifichiamo se si è almeno autori del commento                        
                        $query_role = "SELECT COUNT(*) FROM schede s JOIN commenti r ON s.id_progetto = ? AND s.stato = ? AND r.uuid_scheda = s.uuid_scheda AND s.uuid_scheda = UNHEX(?) WHERE r.uuid_commento = UNHEX(?) AND r.mittente = ?";
                        $stmt_role = $mysqli->prepare($query_role);
                        $stmt_role->bind_param("issss", $progetto, $categoria, $uuid_scheda, $uuid_commento, $email);
                        $stmt_role->execute();
                        $stmt_role->bind_result($isWriter);
                        $stmt_role->fetch();
                        $stmt_role->close();
                        if ($isWriter === 0) throw new Exception("Il commento potrebbe non esistere o non hai i permessi per eliminarlo!");
                }
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT mittente FROM commenti WHERE uuid_scheda = UNHEX(?) AND uuid_commento = UNHEX(?)");
                    $stmt_delete = $mysqli->prepare("DELETE FROM commenti WHERE uuid_commento = UNHEX(?)");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Eliminazione Commento', ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                    try {
                        # anticipiamo il report siccome la scheda sará eliminata
                        $stmt_read->bind_param("ss", $uuid_scheda, $uuid_commento);
                        $stmt_read->execute();
                        $stmt_read->store_result();
                        if ($stmt_read->num_rows !== 1) {
                            throw new mysqli_sql_exception("Commento non esistente!");
                        }
                        $stmt_read->bind_result($email_mittente);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        $param_scheda = strtolower($uuid_scheda);
                        $frag_commento = strtolower($uuid_commento);
                        $link = "board.html?proj=$progetto&post=$param_scheda";
                        $bersaglio_composto = "$email_mittente-$team-$progetto-$categoria-$param_scheda-$frag_commento";
                        $stmt_report->bind_param("ssissssss", $email, $link, $progetto, $categoria, $uuid_scheda, $email_mittente, $team, $email, $bersaglio_composto);
                        $stmt_report->execute();

                        $stmt_delete->bind_param("s", $uuid_commento);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                        $mysqli->commit();
                        $risposta['messaggio'] = "Commento eliminato con successo!";

                    } catch (mysqli_sql_exception) {
                        $mysqli->rollback();
                        $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_delete !== null) $stmt_delete->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                break;
            case "modifica-commento":
                $uuid_commento = $_POST['uuid_commento'] ?? ""; 
                $descrizione = html_entity_decode($_POST['descrizione']) ?? "";
                if (preg_match("/^\s*$/", $descrizione)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
                if ($accessoLivello < 4) {
                    // verifichiamo se si è almeno autori del commento                       
                    $query_role = "SELECT COUNT(*) FROM schede s JOIN commenti r ON s.id_progetto = ? AND s.stato = ? AND r.uuid_scheda = s.uuid_scheda AND s.uuid_scheda = UNHEX(?) WHERE r.uuid_commento = UNHEX(?) AND r.mittente = ?";
                    $stmt_role = $mysqli->prepare($query_role);
                    $stmt_role->bind_param("issss", $progetto, $categoria, $uuid_scheda, $uuid_commento, $email);
                    $stmt_role->execute();
                    $stmt_role->bind_result($isWriter);
                    $stmt_role->fetch();
                    $stmt_role->close();
                    if ($isWriter === 0) throw new Exception("Il commento potrebbe non esistere o non hai i permessi per eliminarlo!");
                } 
                
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare("SELECT mittente FROM commenti WHERE uuid_scheda = UNHEX(?) AND uuid_commento = UNHEX(?)");
                $stmt_update = $mysqli->prepare("UPDATE commenti SET contenuto = ?, modificato_da = ?, modificato_il = ? WHERE uuid_commento = UNHEX(?)");
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Modifica Commento', ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                try {
                    # controlliamo se esiste il commento che voglio modificare e verifichiamo il suo autore
                    $stmt_read->bind_param("ss", $uuid_scheda, $uuid_commento);
                    $stmt_read->execute();
                    $stmt_read->store_result();
                    if ($stmt_read->num_rows!== 1) {
                        throw new mysqli_sql_exception("Impossibile modificare. Commento non esistente!");
                    }
                    $stmt_read->bind_result($utente_bersaglio);
                    $stmt_read->fetch();

                    $modifica_attuale = date('Y-m-d H:i:s');
                    $stmt_update->bind_param("ssss", $descrizione, $email, $modifica_attuale, $uuid_commento);
                    $stmt_update->execute();

                    $param_scheda = strtolower($uuid_scheda);
                    $frag_commento = strtolower($uuid_commento);
                    $link = "board.html?proj=$progetto&post=$param_scheda#repl=$frag_commento";
                    $bersaglio_composto = "$utente_bersaglio-$team-$progetto-$categoria-$param_scheda-$frag_commento";

                    $stmt_report->bind_param("ssissssss", $email, $link, $progetto, $categoria, $uuid_scheda, $utente_bersaglio, $team, $email, $bersaglio_composto);
                    $stmt_report->execute();

                    $mysqli->commit();
                    $risposta['messaggio'] = "Commento modificato con successo!";
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_report !== null) $stmt_report->close();
                }
                break;
            case "rispondi-commento":
                $uuid_commento = $_POST['uuid_commento'] ?? ""; 
                $descrizione = html_entity_decode($_POST['descrizione']) ?? "";
                $contenuto_temporaneo = bin2hex(random_bytes(16));
                if (preg_match("/^\s*$/", $descrizione)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione");
                if ($accessoLivello = 0) throw new Exception("Non hai accesso a questa scheda o non hai i permessi per rispondere al post!");
                $mysqli->begin_transaction();
                $stmt_read = $mysqli->prepare("SELECT mittente FROM commenti WHERE uuid_scheda = UNHEX(?) AND uuid_commento = UNHEX(?)");
                $stmt_create = $mysqli->prepare("INSERT INTO commenti (uuid_scheda, contenuto, mittente, destinatario, uuid_in_risposta) VALUES (UNHEX(?), ?, ?, ?, ?)");
                $stmt_find = $mysqli->prepare('SELECT HEX(uuid_commento) FROM commenti WHERE uuid_scheda = UNHEX(?) AND contenuto = ?');
                $stmt_update = $mysqli->prepare('UPDATE commenti SET contenuto = ? WHERE uuid_commento = UNHEX(?)');
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Risposta Commento', ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                try {
                    # controlliamo se esiste il commento a cui vogliamo rispondere e verifichiamo il suo autore
                    $stmt_read->bind_param("ss", $uuid_scheda, $uuid_commento);
                    $stmt_read->execute();
                    $stmt_read->store_result();
                    if ($stmt_read->num_rows !== 1) {
                        throw new mysqli_sql_exception("Impossibile rispondere. Commento non esistente!");
                    }
                    $stmt_read->bind_result($utente_bersaglio);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    $uuid_commento = strtolower($uuid_commento);
                    # creiamo quindi una risposta al commento con contenuto temporaneo in modo da poter intercettare il commento nella tabella
                    $stmt_create->bind_param("sssss", $uuid_scheda, $contenuto_temporaneo, $email, $utente_bersaglio, $uuid_commento);
                    $stmt_create->execute();
                    # recuperiamo l'uuid del commento appena creato sfruttando il contenuto temporaneo
                    $stmt_find->bind_param("ss", $uuid_scheda, $contenuto_temporaneo);
                    $stmt_find->execute();
                    $stmt_find->store_result();
                    if ($stmt_find->num_rows!== 1) {
                        throw new mysqli_sql_exception("Impossibile rispondere. Commento non esistente!");
                    }
                    $stmt_find->bind_result($uuid_risposta);
                    $stmt_find->fetch();
                    # adesso, in caso di successo, modifichiamo il contenuto temporaneo con quello originale
                    $stmt_update->bind_param("ss", $descrizione, $uuid_risposta);
                    $stmt_update->execute();
                    # e creiamo un report
                    $param_scheda = strtolower($uuid_scheda);
                    $frag_commento = strtolower($uuid_risposta);
                    $link = "board.html?proj=$progetto&post=$param_scheda#repl=$frag_commento";
                    $bersaglio_composto = "$utente_bersaglio-$team-$progetto-$categoria-$param_scheda-$frag_commento";

                    $stmt_report->bind_param("ssissssss", $email, $link, $progetto, $categoria, $uuid_scheda, $utente_bersaglio, $team, $email, $bersaglio_composto);
                    $stmt_report->execute();

                    $mysqli->commit();
                    $risposta['messaggio'] = "Risposta con successo!";

                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_create !== null) $stmt_create->close();
                    if ($stmt_find !== null) $stmt_find->close();
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_report !== null) $stmt_report->close();
                }
                break;
            case 'ottieni-membri':
                if ($accessoLivello < 3) throw new Exception("Non hai i permessi necessari per assegnare questa scheda!");
                #ora restituiamo i membri appartenenti al team
                $query = "SELECT u.cognome AS cognome, u.nome AS nome, u.genere AS genere, u.email AS email, CASE WHEN u.email = t.responsabile THEN TRUE ELSE FALSE END AS isLeader FROM utenti u INNER JOIN team t ON u.team = t.sigla WHERE u.team = '$team' ORDER BY isLeader ASC, u.cognome, u.nome;";
                $stmt = $mysqli->query($query);
                $numero_membri = 0;
                if ($stmt->num_rows > 0) {
                    while ($row = $stmt->fetch_assoc()) {
                        # devo sanificare i dati per evitare che campi come quella della descrizione dove possono essere salvati anche caratteri html non mi crei problemi nella pagina html
                        foreach ($row as $key => $value) {
                            $value = strip_tags($value); // rimuovo i tag html 
                            $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); // converte caratteri speciali in entità html
                        }
                        
                        $risposta['dati']['membri'][] = $row;
                    }
                }
                $stmt->close();
                #ci serve leggere l'utente attualmente assegnato alla scheda
                $stmt_read = $mysqli->prepare('SELECT incaricato, inizio_mandato, fine_mandato FROM info_schede WHERE uuid_scheda = UNHEX(?)');
                $stmt_read->bind_param("s", $uuid_scheda);
                $stmt_read->execute();
                $stmt_read->bind_result($incaricato, $inizio_mandato, $fine_mandato);
                $stmt_read->fetch();
                $stmt_read->close();
                $risposta['dati']['incaricato'] = $incaricato;
                $risposta['dati']['inizio_mandato'] = $inizio_mandato;
                $risposta['dati']['fine_mandato'] = $fine_mandato;
                break;
            case 'assegna-membro':
                if ($accessoLivello < 3) throw new Exception("Non hai i permessi necessari per assegnare questa scheda!");
                $incaricato = $_POST['membro_assegnato'] ?: NULL;
                $inizio_mandato = (isset($_POST['inizio_incarico']) && $incaricato) ? str_replace("T", " ",$_POST['inizio_incarico']) : NULL;
                $fine_mandato = (isset($_POST['fine_incarico']) && $incaricato) ? str_replace("T", " ",$_POST['fine_incarico']) : NULL;
                if ($incaricato) {
                    if (!preg_match("/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/", $incaricato) 
                    || !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $inizio_mandato) 
                    || !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $fine_mandato)) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                    if ((new DateTime($fine_mandato))->getTimestamp() - (new DateTime($inizio_mandato))->getTimestamp() < 60) throw new Exception("La data di fine mandato deve essere pi&ugrave; grande di quella di inizio mandato di almeno un minuto!");
                }
                $stmt_read = $mysqli->prepare("SELECT incaricato, inizio_mandato, fine_mandato FROM info_schede WHERE uuid_scheda = UNHEX(?)");
                $stmt_update = $mysqli->prepare("UPDATE info_schede SET incaricato = ?, inizio_mandato = ?, fine_mandato = ? WHERE uuid_scheda = UNHEX(?)");
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, ?, ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");

                $mysqli->begin_transaction();

                try {
                    $stmt_read->bind_param("s", $uuid_scheda);
                    $stmt_read->execute();
                    $stmt_read->bind_result($old_incaricato, $old_inizio_mandato, $old_fine_mandato);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    
                    $stmt_update->bind_param("ssss", $incaricato, $inizio_mandato, $fine_mandato, $uuid_scheda);
                    $stmt_update->execute();
                    
                    // Genera il link per il report
                    $param_scheda = strtolower($uuid_scheda);
                    $link = "board.html?proj=$progetto&post=$param_scheda";

                    // Variabili per il report
                    $op = "";
                    $bersaglio_composto = "";

                    // Controllo delle condizioni
                    if ($old_incaricato && $old_incaricato === $incaricato && ($old_inizio_mandato !== $inizio_mandato || $old_fine_mandato !== $fine_mandato)) {
                        $op = "Riassegnazione Scheda";
                        $bersaglio_composto = "$old_incaricato-$team-$progetto-$categoria-$param_scheda";
                        $messaggio =  "Scucesso: Aggiornato l'incarico alla scheda a $incaricato (dal $inizio_mandato al $fine_mandato)";
                    } else if ($old_incaricato && !$incaricato) {
                        $op = "Revocazione Scheda";
                        $bersaglio_composto = "$old_incaricato-$team-$progetto-$categoria-$param_scheda";
                        $messaggio =  "Successo: $old_incaricato sollevato dall'incarico";
                    } else if (!$old_incaricato && $incaricato) {
                        $op = "Assegnazione Scheda";
                        $bersaglio_composto = "$incaricato-$team-$progetto-$categoria-$param_scheda";
                        $messaggio =  "Successo: Incarico alla scheda assegnato a $incaricato (dal $inizio_mandato al $fine_mandato)";
                    } else if ($old_incaricato && $old_incaricato !== $incaricato) {
                        // Deassegnazione del vecchio incaricato
                        $op = "Revocazione Scheda";
                        $bersaglio_composto = "$old_incaricato-$team-$progetto-$categoria-$param_scheda";
                        $stmt_report->bind_param("sssissssss", $email, $op, $link, $progetto, $categoria, $uuid_scheda, $old_incaricato, $team, $email, $bersaglio_composto);
                        $stmt_report->execute();

                        // Assegnazione del nuovo incaricato
                        $op = "Assegnazione Scheda";
                        $bersaglio_composto = "$incaricato-$team-$progetto-$categoria-$param_scheda";
                        $messaggio =  "Successo: Incarico alla scheda passato da $old_incaricato a $incaricato (dal $inizio_mandato al $fine_mandato)";
                    }
                    if ($op) {
                        $utente_bersaglio = $incaricato ? $incaricato : $old_incaricato;
                        $stmt_report->bind_param("sssissssss", $email, $op, $link, $progetto, $categoria, $uuid_scheda, $utente_bersaglio, $team, $email, $bersaglio_composto);
                        $stmt_report->execute();
                    } else {
                        $messaggio = "";
                    }
                    $stmt_report->close();
                    $mysqli->commit();
                    $risposta['messaggio'] = $messaggio;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();
                } finally {
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_report !== null) $stmt_report->close();
                }
                break;
            case 'ottieni-stati':
                if ($accessoLivello < 2) throw new Exception("Non hai i permessi necessari per cambiare stato a questa scheda!");
                #ora restituiamo gli stati appartenenti al progetto                
                $query_stati = "SELECT stato, colore_hex FROM stati WHERE id_progetto = ? AND stato != 'Eliminate'";
                if ($accessoLivello < 4) $query_stati .= " AND visibile = 1";
                $query_stati .= " ORDER BY ordine_stati";
                $stmt_stati = $mysqli->prepare($query_stati);
                $stmt_stati->bind_param("i", $progetto);
                $stmt_stati->execute();
                $stmt_stati->store_result();
                $stmt_stati->bind_result($stato, $colore_hex);
                $dati['stati'] = [];
                
                while ($stmt_stati->fetch()) {
                    # verifico ad ogni fetch se lo stato corrente della scheda corrisponde a uno di questi
                    $isSelezionato = ($categoria === $stato) ? "--selected" : "";
                    $stato = [
                        'stato' => $stato,
                        'colore_hex' => $colore_hex,
                        'isSelezionato' => $isSelezionato,
                    ];
                    $dati['stati'][] = $stato;
                    $dati['stato_attuale'] = $categoria;
                }
                $stmt_stati->close();
                $risposta['dati']['stati'] = $dati['stati'];
                $risposta['dati']['stato_attuale'] = $dati['stato_attuale'];
                break;
            case 'cambia-stato': 
                if ($accessoLivello < 2) throw new Exception("Non hai i permessi necessari per cambiare stato a questa scheda!");
                $new_stato = $_POST['stato_assegnato'] ?: "";
                if (!preg_match("/^[a-zA-Z0-9]{1}[a-zA-Z0-9\s]{0,19}$/", $new_stato) || $new_stato === "Eliminate") throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                $query_old = "SELECT s.stato, s.autore FROM schede s RIGHT JOIN stati c ON s.stato = c.stato AND s.id_progetto = c.id_progetto AND s.uuid_scheda = UNHEX(?) WHERE s.id_progetto = ?";
                $query_new = "SELECT stato FROM stati WHERE id_progetto = ? AND stato = ?";
                if ($accessoLivello < 4) foreach ([$query_old, $query_new] as $query_key) $query_key.= " AND visibile = 1";
                $stmt_old = $mysqli->prepare($query_old);
                $stmt_new = $mysqli->prepare($query_new);
                $stmt_max_ordine = $mysqli->prepare("SELECT COALESCE(MAX(ordine_schede), -1) AS max_ord FROM schede WHERE id_progetto = ? AND stato = ?");
                $stmt_update = $mysqli->prepare("UPDATE schede SET stato = ? , ordine_schede = ? WHERE id_progetto = ? AND uuid_scheda = UNHEX(?)");
                $stmt_complete = $mysqli->prepare("UPDATE info_schede SET data_fine = ? WHERE uuid_scheda = UNHEX(?)");
                $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, categoria, scheda, utente, team, attore_era, bersaglio_era) VALUES ('scheda', ?, 'Cambiamento Stato', ?, ?, ?, UNHEX(?), ?, ?, ?, ?)");
                $mysqli->begin_transaction();
                try {
                    # verifichiamo esistenza e visibilità della scheda di partenza
                    $stmt_old->bind_param("si", $uuid_scheda, $progetto);
                    $stmt_old->execute();
                    $stmt_old->store_result();
                    if ($stmt_old->num_rows !== 1) throw new mysqli_sql_exception("Categoria inesistente o inaccessibile! $uuid_scheda - $progetto"); 
                    $stmt_old->bind_result($old_stato, $autore);
                    $stmt_old->fetch();
                    $stmt_old->close();
                    # facciamo lo stesso per quella di arrivo
                    $stmt_new->bind_param("is", $progetto, $new_stato);
                    $stmt_new->execute();
                    $stmt_new->store_result();
                    if ($stmt_new->num_rows !== 1) throw new mysqli_sql_exception("Categoria inesistente o inaccessibile!");
                    $stmt_new->bind_result($new_stato);
                    $stmt_new->fetch();
                    $stmt_new->close();
                    $messaggio = "";
                    if ($new_stato !== $old_stato) {
                        # aggiorniamo l'ordine delle schede nella nuova categoria identificando il massimo ordine
                        $stmt_max_ordine->bind_param("is", $progetto, $new_stato);
                        $stmt_max_ordine->execute();
                        $stmt_max_ordine->bind_result($max_ord);
                        $stmt_max_ordine->fetch();
                        $stmt_max_ordine->close();
                        $new_ord = $max_ord + 1;
                        # quindi aggiorniamo ...
                        $stmt_update->bind_param("siis", $new_stato, $new_ord, $progetto, $uuid_scheda);
                        $stmt_update->execute();
                        # ... e in caso di successo reportiamo tutto
                        $param_scheda = strtolower($uuid_scheda);
                        $link = "board.html?proj=$progetto&post=$param_scheda";
                        $bersaglio_composto = "$autore-$team-$progetto-$new_stato-$param_scheda";
                        $stmt_report->bind_param("ssissssss", $email, $link, $progetto, $new_stato, $uuid_scheda, $autore, $team, $email, $bersaglio_composto);
                        # infine, verifichiamo se la nuova categoria corrisponde in completate
                        $now = ($new_stato === "Completate") ? $now = date('Y-m-d H:i:s') : NULL;
                        $stmt_complete->bind_param("ss", $now, $uuid_scheda);
                        $stmt_complete->execute();
                        $stmt_report->execute();
                        $messaggio = "Successo: Scheda passata da $old_stato a $new_stato";
                    }
                    $mysqli->commit();
                    $risposta['messaggio'] = $messaggio;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();   
                } finally {
                    if ($stmt_update !== null) $stmt_update->close();
                    if ($stmt_report !== null) $stmt_report->close();
                    if ($stmt_max_ordine !== null) $stmt_max_ordine->close();
                    if ($stmt_old !== null) $stmt_old->close();
                    if ($stmt_new !== null) $stmt_new->close();
                }
                break;  
            case 'ottieni-progresso':
                if ($accessoLivello < 3) throw new Exception("Non hai i permessi necessari per programmare questa scheda!");
                #ci serve solo sapere data inizio e scadenza che abbiamo programmato per la scheda, e la data di quando questa é stata completata
                $stmt_read = $mysqli->prepare('SELECT i.data_inizio, s.scadenza, i.data_fine, s.stato FROM info_schede i JOIN schede s ON s.uuid_scheda = i.uuid_scheda WHERE i.uuid_scheda = UNHEX(?)');
                $stmt_read->bind_param("s", $uuid_scheda);
                $stmt_read->execute();
                $stmt_read->bind_result($inizio, $scadenza, $fine, $stato);
                $stmt_read->fetch();
                $stmt_read->close();
                $risposta['dati']['inizio'] = $inizio;
                $risposta['dati']['scadenza'] = $scadenza;
                $risposta['dati']['fine'] = $fine;
                $risposta['dati']['stato'] = $stato;
                break;
            case 'imposta-durata':
                if ($accessoLivello < 3) throw new Exception("Non hai i permessi necessari per programmare questa scheda!");
                $inizio_scheda = isset($_POST['inizio_scheda']) ? str_replace("T", " ",$_POST['inizio_scheda']) : throw new Exception("Data di Avvio non rilevata correttamente!");
                $scadenza_scheda = isset($_POST['scadenza_scheda']) ? str_replace("T", " ",$_POST['scadenza_scheda']) : NULL;
                $fine_scheda = (isset($_POST['fine_scheda']) && $categoria === "Completate") ? str_replace("T", " ",$_POST['fine_scheda']) : NULL;
                if (!preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $inizio_scheda) 
                || ($scadenza_scheda && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $scadenza_scheda))
                || ($fine_scheda && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $fine_scheda))
                ) throw new Exception("Si &egrave; verificato un errore nell'invio dei dati necessari per il completamento dall'azione!");
                if ($scadenza_scheda) {
                    if ((new DateTime($scadenza_scheda))->getTimestamp() - (new DateTime($inizio_scheda))->getTimestamp() < 60) throw new Exception("La data di scadenza scheda deve essere pi&ugrave; grande di quella di avvio di almeno un minuto!");
                }
                if ($fine_scheda) {
                    if ((new DateTime($fine_scheda))->getTimestamp() - (new DateTime($inizio_scheda))->getTimestamp() < 60) throw new Exception("La data di fine scheda deve essere pi&ugrave; grande di quella di avvio di almeno un minuto!");
                }
                $stmt_read = $mysqli->prepare('SELECT i.data_inizio, s.scadenza, i.data_fine, s.stato FROM info_schede i JOIN schede s ON s.uuid_scheda = i.uuid_scheda WHERE i.uuid_scheda = UNHEX(?)');
                $stmt_update1 = $mysqli->prepare("UPDATE schede SET scadenza = ? WHERE uuid_scheda = UNHEX(?)");
                $stmt_update2 = $mysqli->prepare("UPDATE info_schede SET data_inizio = ?, data_fine = ? WHERE uuid_scheda = UNHEX(?)");
                $mysqli->begin_transaction();
                try {
                    $stmt_read->bind_param("s", $uuid_scheda);
                    $stmt_read->execute();
                    $stmt_read->bind_result($old_inizio, $old_scadenza, $old_fine, $stato);
                    $stmt_read->fetch();
                    $stmt_read->close();
                    $messaggio = "";
                    if ($old_inizio !== $inizio_scheda || $old_fine !== $fine_scheda || $old_scadenza !== $scadenza_scheda) {
                        $stmt_update1->bind_param("ss", $scadenza_scheda, $uuid_scheda);
                        $stmt_update1->execute();
                        $stmt_update2->bind_param("sss", $inizio_scheda, $fine_scheda, $uuid_scheda);
                        $stmt_update2->execute();
                        $messaggio = "Successo: Scheda riprogrammata con successo";
                    }
                    $mysqli->commit();
                    $risposta['messaggio'] = $messaggio;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $risposta['messaggio'] = "Errore Transazione: ". $e->getMessage();
                } finally {
                    if ($stmt_read !== null) $stmt_read->close();
                    if ($stmt_update1 !== null) $stmt_update1->close();
                    if ($stmt_update2 !== null) $stmt_update2->close();
                }
                break;
            case "ottieni-report":
                if ($accessoLivello < 3) throw new Exception("Non hai i permessi per visualizzare i report di questa scheda!");
                $stmt_obtain = $mysqli->prepare("SELECT HEX(r.uuid_report), r.`timestamp`, r.attore, r.descrizione, r.link, r.utente, r.team, r.categoria, r.attore_era, r.bersaglio_era, c.colore_hex FROM report r LEFT JOIN stati c ON r.progetto = c.id_progetto AND c.stato = r.categoria WHERE r.team = ? AND r.progetto = ? AND r.scheda = UNHEX(?) ORDER BY r.timestamp DESC LIMIT 50");
                $stmt_obtain->bind_param("sis", $team, $progetto, $uuid_scheda);
                $stmt_obtain->execute();
                $stmt_obtain->store_result();
                $stmt_obtain->bind_result($uuid_report, $timestamp, $attore, $descrizione, $link, $bersaglio, $team_responsabile, $stato, $attore_era, $bersaglio_era, $colore_hex);
                while ($stmt_obtain->fetch()) {
                    $dati['report'][] = [
                        "uuid_report" => strtolower($uuid_report),
                        "timestamp" => $timestamp,
                        "attore" => $attore,
                        "descrizione" => $descrizione,
                        "link" => $link,
                        "bersaglio" => $bersaglio,
                        "team_responsabile" => $team_responsabile,
                        "stato" => $stato,
                        "attore_era" => $attore_era,
                        "bersaglio_era" => $bersaglio_era,
                        "colore_hex" => $colore_hex
                    ];
                }
                $stmt_obtain->close();
                $risposta['dati']['reports'] = $dati['report'];
                $risposta['dati']['mia_mail'] = $email;
                $risposta['messaggio'] = "";
                break;
            default:
                $risposta['messaggio'] = "Errore: Operazione non riconosciuta";
                break;
        }
        
    } else if (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
        #Altrimenti forzo la chiusura della sessione e restituisco in json che la sessione é scaduta
        session_unset();    // Rimuovo tutte le variabili di sessione
        session_destroy(); // Distruggo la sessione completamento
        $risposta['isSessioneScaduta'] = true;
    } #Altrimenti restituisco i booledani tutti falsi per stabilire che l'accesso non è esistente
} catch (Exception $e) {
    $risposta['messaggio'] = "Errore: " . $e->getMessage();
} finally {
    #Chiudiamo la connessione al database
    if (isset($mysqli) && $mysqli->connect_errno == 0) $mysqli->close();
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