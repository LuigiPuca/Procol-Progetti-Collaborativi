<?php 
require_once('db_connection.php');
session_start();

# Configuriamo MySQLi per segnalare automaticamente gli errori come eccezioni PHP
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $uuid_utente = $_SESSION['uuid_utente'];
    $suffisso = isset($_SESSION['chi']['suffisso']) ? $_SESSION['chi']['suffisso'] : "o";
    if (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() < $_SESSION['timeout']) {
        if (isset($_SESSION['uuid_utente']) && $_SERVER["REQUEST_METHOD"] == "POST") {
            #salvo in una variabile l'uuid_utente 
            $uuid_utente = $_SESSION['uuid_utente'];
            # Verifico che l'utente collegato sia un admin
            $query = "SELECT COUNT(*) as is_admin, email FROM utenti WHERE `uuid` = UNHEX(?) and ruolo = 'admin'";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("s", $uuid_utente);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($is_admin, $attore);
            $stmt->fetch();
            $stmt->close();
            if (!$is_admin) {
                //header('Location: ../portal.html');
                $sm = "Permesso negato: Sei stat$suffisso reindirizzat$suffisso";
            } else {
                $sm = "Permesso consentito";
                if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
                    # Se la richiesta è fatta con JSON, elaboriamo i dati JSON per aggiornare $_POST 
                    # altrimenti procediamo come di norma
                    $jsonData = file_get_contents('php://input');
                    $_POST = json_decode($jsonData, true);
                }

                #Per eliminare un utente bisogna verificare prima se l'utente esiste, poi se si hanno i permessi per eliminarlo
                if (isset($_POST['operazione']) && $_POST['operazione'] === 'elimina') {
                    try {
                        $mysqli->begin_transaction();
                        $stmt_delete = $mysqli->prepare("DELETE FROM utenti WHERE email = ?");
                        $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, attore_era, bersaglio_era) VALUES ('utente', ? , 'Eliminazione Utente', ?, ?, ?)");
                        if (isset($_POST['emails']) && !empty($_POST['emails'])) {
                            foreach($_POST['emails'] as $record) {
                                if ($record['email'] === 'admin@procol.com') {
                                    throw new mysqli_sql_exception("L'email admin@procol.com non può essere eliminata!");
                                }
                                $email = $record['email'];
                                
                                # Verifichiamo se l'utente, su cui vogliamo eseguire l'operazione, esiste e restituiamo il ruolo
                                $stmt_read = $mysqli->prepare("SELECT COUNT(*), ruolo FROM utenti WHERE email = ?");
                                $stmt_read->bind_param("s", $email);
                                $stmt_read->execute();
                                $stmt_read->bind_result($count, $ruolo);
                                $stmt_read->fetch();
                                $stmt_read->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                $stmt_read->close();

                                # Verifichiamo se l'utente che esegue l'operazione è il founder
                                $stmt_role = $mysqli->prepare("SELECT email as attore, (email = 'admin@procol.com') as im_founder FROM utenti WHERE `uuid` = UNHEX(?)");
                                $stmt_role->bind_param("s", $uuid_utente);
                                $stmt_role->execute();
                                $stmt_role->bind_result($attore, $im_founder);
                                $stmt_role->fetch();
                                $stmt_role->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                $stmt_role->close();

                                # Query di eliminazione
                                if (($count === 1 && $ruolo !== 'admin') || ($count === 1 && $im_founder)) { 
                                    #salviamo prima nel report perché qualcosa viene eliminato
                                    $stmt_report->bind_param("ssss", $attore, $email, $attore, $email);
                                    $stmt_report->execute();

                                    #poi eseguiamo l'azione di eliminazione vera e propria
                                    $stmt_delete->bind_param("s", $email);
                                    $stmt_delete->execute();

                                    $sm = "Successo: Tutti gli account selezionati sono stati eliminati!";
                                } else if ($count === 1 && $ruolo == 'admin' && !$im_founder) {
                                    throw new mysqli_sql_exception("Non hai i permessi necessari ad eliminare $email!");
                                } else {
                                    throw new mysqli_sql_exception("L'email $email non è stata trovata!");
                                }
                            }
                            $mysqli->commit();
                        } else {
                            throw new mysqli_sql_exception("Nessuna email selezionata o esistente che può essere eliminata!");
                        }
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = "Errore Transazione: ". $e->getMessage();
                    } finally {
                        if ($stmt_delete !== null) $stmt_delete->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                } 

                 # Facendo le stesse verifiche possiamo fare altre operazioni come... promozione/declassamento dell'utente
                 if ((isset($_POST['operazione']) && $_POST['operazione'] === 'promuovi') || (isset($_POST['operazione']) && $_POST['operazione'] === 'declassa')) {
                    $operazione = $_POST['operazione'];
                    $descrizione = $operazione === 'promuovi' ? 'Promozione Utente' : 'Declassamento Utente';
                    try {
                        $mysqli->begin_transaction();
                        $stmt_role = $mysqli->prepare("SELECT email as attore, (email = 'admin@procol.com') as im_founder FROM utenti WHERE `uuid` = UNHEX(?)");
                        $stmt_read = $mysqli->prepare("SELECT COUNT(*), ruolo FROM utenti WHERE email = ?");
                        $stmt_update = $mysqli->prepare("UPDATE utenti SET ruolo = ? WHERE email = ?");
                        $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, attore_era, bersaglio_era) VALUES ('utente', ?, ?, ?, ?, ?)");
                        if (isset($_POST['emails']) && !empty($_POST['emails'])) {
                            foreach($_POST['emails'] as $record) {
                                if ($record['email'] === 'admin@procol.com') {
                                    throw new mysqli_sql_exception("L'utente con email admin@procol.com non pu&ograve; essere n&eacute; promosso o declassato!");
                                }
                                $email = $record['email'];
                                
                                # Verifichiamo se l'utente, su cui vogliamo eseguire l'operazione, esiste e restituiamo il ruolo
                                $stmt_read->bind_param("s", $email);
                                $stmt_read->execute();
                                $stmt_read->bind_result($count, $ruolo);
                                $stmt_read->fetch();
                                $stmt_read->free_result(); // Liberiamo i risultati prima di eseguire un'altra query

                                # Verifichiamo se l'utente che esegue l'operazione è il founder
                                $stmt_role->bind_param("s", $uuid_utente);
                                $stmt_role->execute();
                                $stmt_role->bind_result($attore, $im_founder);
                                $stmt_role->fetch();
                                $stmt_role->free_result(); // Liberiamo i risultati prima di eseguire un'altra query

                                # Query di aggiornamento
                                if ($count === 1) { 
                                    #eseguiamo prima l'operazione di aggiornamento. Verifichiamo innazitutto quale sarà il nuovo ruolo
                                    if ($operazione === 'promuovi') {
                                        $new_ruolo = $ruolo === 'utente' ? 'capo_team' : ($ruolo === 'capo_team' ? 'admin' : 'stop');
                                    } else {
                                        # se vogliamo degradare al di sotto di capoteam dobbiamo assicurarci che l'utente non sia a capo di un team
                                        if ($ruolo === 'capo_team') {
                                            $stmt_capoteam = $mysqli->prepare("SELECT COUNT(*) FROM team WHERE responsabile = ?");
                                            $stmt_capoteam->bind_param("s", $email);
                                            $stmt_capoteam->execute();
                                            $stmt_capoteam->bind_result($isCapoTeam);
                                            $stmt_capoteam->fetch();
                                            $stmt_capoteam->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                            if ($isCapoTeam > 0) {
                                                throw new mysqli_sql_exception("$email non pu&ograve; essere declassato finch&eacute; a capo di un team");
                                            }
                                        }
                                        $new_ruolo = $ruolo === 'admin' ? 'capo_team' : ($ruolo === 'capo_team' ? 'utente' :'stop');
                                    }
                                    #e se effettivamente l'utente puó essere promosso/declassato a quel ruolo
                                    if ($new_ruolo !== 'stop' && (($operazione === 'promuovi' && ($new_ruolo !== 'admin' || ($new_ruolo === 'admin' && $im_founder))) || ($operazione === 'declassa' && ($new_ruolo !== 'capo_team' || ($new_ruolo === 'capo_team' && $im_founder))))) {
                                        $stmt_update->bind_param("ss", $new_ruolo, $email);
                                        $stmt_update->execute();
                                    } else if (($operazione === 'promuovi' && ($new_ruolo === 'admin' && !$im_founder)) || ($operazione === 'declassa' && ($new_ruolo === 'capo_team' && !$im_founder))) {
                                        throw new mysqli_sql_exception("Non hai i permessi necessari per promuovere o declassare $email!");
                                    } else {
                                        throw new mysqli_sql_exception("$email non può essere promosso/declassato ulteriomente!");
                                    }
                                    #se tutto va bene salviamo il progresso in un report
                                    $stmt_report->bind_param("sssss", $attore, $descrizione, $email, $attore, $email);
                                    $stmt_report->execute();
                                    $sm = "Successo: Tutti i ruoli degli account selezionati sono stati aggiornati!";
                                } else {
                                    throw new mysqli_sql_exception("L'email $email non &egrave; stata trovata!");
                                }
                            }
                            $mysqli->commit();
                        } else {
                            throw new mysqli_sql_exception("Nessuna email selezionata o esistente che pu&ograve; essere aggiornata in rango!");
                        }
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = "Errore Transazione: ". $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_role !== null) $stmt_role->close();
                        if ($stmt_update !== null) $stmt_update->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                }

                # ... rimozione di un utente dal team
                if (isset($_POST['operazione']) && $_POST['operazione'] === 'rimuovi dal team') {
                    try {
                        $mysqli->begin_transaction();
                        $stmt_leader = $mysqli->prepare("SELECT COUNT(*) FROM team WHERE sigla = ? AND responsabile = ?");
                        $stmt_update = $mysqli->prepare("UPDATE utenti SET team = NULL WHERE email = ?");
                        $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, team, attore_era, bersaglio_era) VALUES ('team', ? , 'Rimozione Utente Da Team', ?, ?, ?, ?)");
                        if (isset($_POST['emails']) && !empty($_POST['emails'])) {
                            foreach($_POST['emails'] as $record) {
                                $email = $record['email'];
                                
                                # Verifichiamo se l'utente, su cui vogliamo eseguire l'operazione, esiste e restituiamo il ruolo
                                $stmt_read = $mysqli->prepare("SELECT COUNT(*), ruolo, team FROM utenti WHERE email = ?");
                                $stmt_read->bind_param("s", $email);
                                $stmt_read->execute();
                                $stmt_read->bind_result($count, $ruolo, $old_team);
                                $stmt_read->fetch();
                                $stmt_read->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                $stmt_read->close();

                                # Verifichiamo se l'utente che esegue l'operazione è il founder
                                $stmt_role = $mysqli->prepare("SELECT email as attore, (email = 'admin@procol.com') as im_founder FROM utenti WHERE `uuid` = UNHEX(?)");
                                $stmt_role->bind_param("s", $uuid_utente);
                                $stmt_role->execute();
                                $stmt_role->bind_result($attore, $im_founder);
                                $stmt_role->fetch();
                                $stmt_role->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                $stmt_role->close();

                                # Query di aggiornamento
                                if (($count === 1 && $old_team && ($ruolo !== 'admin' || $im_founder))) {
                                    #l'utente non deve essere capo_team EFFETTIVO in carica di un team, in quanto un team non può privarsi del suo leader senza aver trovato prima un sostituto
                                        $isLeader = 0;
                                        if ($ruolo === 'admin' || $ruolo === 'capo_team') {
                                            $stmt_leader = $mysqli->prepare("SELECT COUNT(*) FROM team WHERE sigla = ? AND responsabile = ?");
                                            $stmt_leader->bind_param("ss", $old_team, $email);
                                            $stmt_leader->execute();
                                            $stmt_leader->bind_result($isLeader);
                                            $stmt_leader->fetch();
                                            $stmt_leader->free_result();
                                        }
                                        if (!$isLeader) {
                                            $stmt_update->bind_param("s", $email);
                                            $stmt_update->execute();
                                            #se tutto va bene salviamo il progresso in un report
                                            $bersaglio_composto = "$email-$old_team";
                                            $stmt_report->bind_param("sssss", $attore, $email, $old_team, $attore, $bersaglio_composto);
                                            $stmt_report->execute();
                                            $sm = "Successo: Tutti gli account selezionati sono stati rimossi dal loro team!";
                                        } else {
                                            throw new mysqli_sql_exception("L'utente con email $email non pu&ograve; essere rimosso dal Team perch&eacute; suo Leader!");
                                        }
                                    } else if ($count === 1 && $ruolo == 'admin' && !$im_founder) {
                                        throw new mysqli_sql_exception("Non hai i permessi necessari per rimuovere $email dal team!");
                                    } else  {
                                        throw new mysqli_sql_exception("L'email $email non &egrave; stata trovata o non appartiene a nessun team!");
                                    }
                            } 
                            $mysqli->commit();
                        } else {
                            throw new mysqli_sql_exception("Nessuna email selezionata o esistente che pu&ograve; essere rimossa dal team");
                        }
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = "Errore Transazione: ". $e->getMessage();
                    } finally {
                        if ($stmt_update !== null) $stmt_update->close();
                        if ($stmt_leader !== null) $stmt_leader->close();
                        if (isset($stmt_report) && $stmt_report !== null) $stmt_report->close();

                    }
                }

                # ... aggiunta di un utente al team
                if (isset($_POST['operazione']) && $_POST['operazione'] === 'aggiungi al team') {
                    try {
                        $mysqli->begin_transaction();
                        $stmt_role = $mysqli->prepare("SELECT email as attore, (email = 'admin@procol.com') as im_founder FROM utenti WHERE `uuid` = UNHEX(?)");
                        $stmt_read = $mysqli->prepare("SELECT COUNT(*), ruolo, team FROM utenti WHERE email = ?");
                        $stmt_team = $mysqli->prepare("SELECT COUNT(*), nome FROM team WHERE sigla = ?");
                        $stmt_leader = $mysqli->prepare("SELECT COUNT(*) FROM team WHERE responsabile = ?");
                        $stmt_update = $mysqli->prepare("UPDATE utenti SET team = ? WHERE email = ?");
                        $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, team, attore_era, bersaglio_era) VALUES ('team', ?, ?, ?, ?, ?, ?)");
                        if (isset($_POST['emails']) && !empty($_POST['emails'])) {
                            foreach($_POST['emails'] as $record) {
                                $email = $record['email'];
                                #recuperiamo quale deve essere il nuovo team e verifichiamo che il suo valore sia pulito e valido
                                $new_team = strtoupper($_POST['team']); //Rendiamo tutto in caps
                                if (!preg_match("/^[a-zA-Z0-9]{1,3}$/", $new_team)) {
                                    throw new mysqli_sql_exception("I dati non sono stati inviati correttamente! Perfavore, riprovare.");
                                }
                                $descrizione1 = 'Rimozione Utente Da Team';
                                $descrizione2 = 'Aggiunta Utente Nel Team';

                                # Verifichiamo se l'utente che esegue l'operazione è il founder
                                $stmt_role->bind_param("s", $uuid_utente);
                                $stmt_role->execute();
                                $stmt_role->bind_result($attore, $im_founder);
                                $stmt_role->fetch();
                                $stmt_role->free_result(); // Liberiamo i risultati prima di eseguire un'altra query

                                # Verifichiamo se l'utente, su cui vogliamo eseguire l'operazione, esiste e restituiamo il ruolo
                                $stmt_read->bind_param("s", $email);
                                $stmt_read->execute();
                                $stmt_read->bind_result($count, $ruolo, $old_team);
                                $stmt_read->fetch();
                                $stmt_read->free_result(); // Liberiamo i risultati prima di eseguire un'altra query

                                # Verifichiamo se il team, a cui vogliamo aggiungere gli utenti, esiste:
                                $stmt_team->bind_param("s", $new_team);
                                $stmt_team->execute();
                                $stmt_team->bind_result($count2, $nome_team);
                                $stmt_team->fetch();
                                $stmt_team->free_result(); // Liberiamo i risultati prima di eseguire un'altra query
                                # Query di aggiornamento
                                if (($count === 1 && $count2 === 1 && ($ruolo !== 'admin' || $im_founder))) {
                                    #l'utente non deve essere capo_team EFFETTIVO in carica di un team, in quanto un team non può privarsi del suo leader senza aver trovato prima un sostituto
                                    $isLeader = 0;
                                    if ($ruolo === 'admin' || $ruolo === 'capo_team') {
                                        $stmt_leader->bind_param("s", $email);
                                        $stmt_leader->execute();
                                        $stmt_leader->bind_result($isLeader);
                                        $stmt_leader->fetch();
                                        $stmt_leader->free_result();
                                    }
                                    if (!$isLeader) {
                                        $stmt_update->bind_param("ss", $new_team, $email);
                                        $stmt_update->execute();
                                        #se tutto va bene salviamo il progresso in un report
                                        $bersaglio_composto = "$email-$old_team";
                                        if ($old_team) {
                                            $stmt_report->bind_param("ssssss", $attore, $descrizione1, $email, $old_team, $attore, $bersaglio_composto);
                                            $stmt_report->execute();
                                        }
                                        $bersaglio_composto = "$email-$new_team";
                                        $stmt_report->bind_param("ssssss", $attore, $descrizione2, $email, $new_team, $attore, $bersaglio_composto);
                                        $stmt_report->execute();
                                        $sm = "Successo: Tutti gli account selezionati sono stati aggiunti al team selezionato!";
                                    } else {
                                        throw new mysqli_sql_exception("L'utente con email $email non pu&ograve; essere inserito nel Team perch&eacute; Leader di un Team !");
                                    }
                                } else if ($count === 1 && $ruolo == 'admin' && !$im_founder) {
                                    throw new mysqli_sql_exception("Non hai i permessi necessari per cambiare team a $email!");
                                } else if ($count === 1 && $count2 === 0) {
                                    throw new mysqli_sql_exception("Il team $nome_team non &egrave; stata trovato!");
                                } else {
                                    throw new mysqli_sql_exception("L'email $email non &egrave; stata trovata!");
                                }
                            } 
                            $mysqli->commit();
                        } else {
                            throw new mysqli_sql_exception("Nessuna email selezionata o esistente che pu&ograve; essere aggiunta al team");
                        }
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = "Errore Transazione: ". $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_role !== null) $stmt_role->close();
                        if ($stmt_team !== null) $stmt_team->close();
                        if ($stmt_leader !== null) $stmt_leader->close();
                        if ($stmt_update !== null) $stmt_update->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                }

                # se vogliamo creare (CREATE) un progetto
                if (isset($_POST['create_progetto'])) {
                    #useró query preparate per evitare iniezioni SQL 
                    $nome_progetto = $_POST['nome_progetto']; 
                    $descrizione_progetto = $_POST['descrizione_progetto']; 
                    $scadenza_progetto = date("Y-m-d H:i:s", strtotime($_POST['scadenza_progetto'])); // mi assicuro di avere la data in formato datetime per mySQL
                    $selezione_team = strtoupper($_POST['selezione_team']); //mi assicuro di avere tutti i caratteri in caps
                    if (
                        !preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,48}[a-zA-ZÀ-ÿ])?$/", $nome_progetto) ||
                        !preg_match("/^.{0,255}$/", $descrizione_progetto) || 
                        !preg_match("/^\d{4}-\d{2}-\d{2}?(?:\s\d{2})?(?::\d{2})?(?::\d{2})?/", $scadenza_progetto) ||
                        !preg_match("/^[a-zA-Z0-9]{0,3}$/", $selezione_team)
                        ) {
                            throw new Exception("I dati non sono stati inviati correttamente. Perfavore, ricontrollare la formattazione");
                    } 
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt = $mysqli->prepare("INSERT INTO progetti (progetto, descrizione, scadenza, team_responsabile) VALUES (?, ?, ?, IF(? = '', NULL, ?))");
                    $stmt_read = $mysqli->prepare("SELECT id_progetto FROM progetti WHERE progetto = ?");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, progetto, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Creazione Progetto', ?, ?, ?, ?)");
                    try {
                        #prepariamo l'istruzione di inserimento
                        $stmt->bind_param("sssss", $nome_progetto, $descrizione_progetto, $scadenza_progetto, $selezione_team, $selezione_team);
                        $stmt->execute();
                        #se tutto é andato a buon fine aggiungiamo un report, ci serve peró identificare per la creazione del link prima l'id del progetto creato
                        $stmt_read->bind_param("s", $nome_progetto);
                        $stmt_read->execute();
                        $stmt_read->bind_result($id_progetto);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        #quindi costruiamo il link e facciamo il report
                        $link = "board.html?proj=$id_progetto";
                        $bersaglio_composto = "No Utente-No Team-$id_progetto";
                        $stmt_report->bind_param("ssiss", $attore, $link, $id_progetto, $attore, $bersaglio_composto);
                        $stmt_report->execute();
                        # faccio il tentativo di commit
                        $mysqli->commit();
                        $sm = "Successo: Il progetto \"$nome_progetto\" &egrave; stato creato correttamente!";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1062) ? "Errore Transazione: Nome gi&agrave in uso. Sceglierne un altro!" : "Errore Transazione: " . $e->getMessage();
                        $sm = ($e->getCode() == 1452) ? "Errore Transazione: Team non trovato!"  : $sm;
                    } finally {
                        if ($stmt !== null) $stmt->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                } 

                # se vogliamo eliminare (DELETE) un progetto, dopo aver verificato che esiste (READ)
                if (isset($_POST['delete_progetto'])) {
                    $id_progetto = (int) urlencode($_POST['id_progetto']); // mi assicuro di avere un valore intero
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, progetto, attore_era, bersaglio_era) VALUES ('progetto', ?, 'Cancellazione Progetto', ?, ?, ?)");
                    $stmt = $mysqli->prepare("DELETE FROM progetti WHERE id_progetto = ?");
                    try {
                        # siccome stiamo cancellando una tupla, prepariamo prima un report
                        $bersaglio_composto = "No Utente-No Team-$id_progetto";
                        $stmt_report->bind_param("siss", $attore, $id_progetto, $attore, $bersaglio_composto);
                        $stmt_report->execute();
                        #prepariamo l'istruzione di inserimento
                        $query = "SELECT COUNT(*) AS conteggio, progetto FROM progetti WHERE id_progetto = $id_progetto";
                        $stmt_read = $mysqli->query($query);
                        if ($stmt_read->num_rows > 0) {
                            $risultato = $stmt_read->fetch_assoc();
                            $conteggio = $risultato['conteggio'];
                            $nome_progetto = $risultato['progetto'];
                            if ($conteggio === 1) {
                                $stmt->bind_param("i", $id_progetto);
                                $stmt->execute();
                                # faccio il tentativo di commit
                                $mysqli->commit();
                                $sm = "Successo: Il progetto \"$nome_progetto\" &egrave; stato eliminato correttamente!";
                            } else {
                                throw new mysqli_sql_exception("Progetto non trovato!");
                            }    
                        } 
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1452) ? "Errore Transazione: Progetto da eliminare non trovato!"  : "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt !== null) $stmt->close();
                        if ($stmt_report!== null) $stmt_report->close();
                    }
                }

                # se vogliamo aggiornare (UPDATE) un progetto 
                if (isset($_POST['edit_progetto'])) {
                    // foreach ($_POST as $chiave => $valore) {
                    //     echo $chiave . ": " . $valore . "<br>";
                    // }
                    # useró query preparate per evitare iniezioni SQL 
                    $id_progetto = (int) urlencode($_POST['id_progetto']); // mi assicuro di avere un valore intero
                    $nome_progetto = $_POST['nome_progetto'];
                    $descrizione_progetto = $_POST['descrizione_progetto']; 
                    $scadenza_progetto = date("Y-m-d H:i:s", strtotime($_POST['scadenza_progetto'])); // mi assicuro di avere la data in formato datetime per mySQL
                    $selezione_team = $_POST['selezione_team'] ? strtoupper($_POST['selezione_team']) : ""; //mi assicuro di avere tutti i caratteri in caps
                    // echo  "$id_progetto<br>";
                    // echo  "$nome_progetto<br>";
                    // echo  "$descrizione_progetto<br>";
                    // echo  "$scadenza_progetto<br>";
                    // echo  "$selezione_team<br>";
                    # verifichiamo che siano rispettati alcuni pattern 
                    if (
                        !preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,48}[a-zA-ZÀ-ÿ])?$/", $nome_progetto) ||
                        !preg_match("/^.{0,255}$/", $descrizione_progetto) || 
                        !preg_match("/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/", $scadenza_progetto) ||
                        !preg_match("/^[a-zA-Z0-9]{0,3}$/", $selezione_team)
                        ) {
                            throw new Exception("I dati non sono stati inviati correttamente. Perfavore, ricontrollare la formattazione");
                    } 
            
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT descrizione, scadenza, team_responsabile FROM progetti WHERE id_progetto = ?");
                    $stmt_update = $mysqli->prepare("UPDATE progetti SET progetto = ?, descrizione = ?, scadenza = ?, team_responsabile = IF(? = '', NULL, ?) WHERE id_progetto = ?");
                    try {
                        # siccome stiamo modificando una tupla, verifichiamo prima che questa esista, ricavandone i dati e poi confrontandoli
                        $stmt_read->bind_param("i", $id_progetto);
                        $stmt_read->execute();
                        $stmt_read->store_result();
                        if ($stmt_read->num_rows === 0) {
                            throw new mysqli_sql_exception("Progetto non trovato!");
                        }
                        $stmt_read->bind_result($old_descrizione, $old_scadenza, $old_team_responsabile);
                        $stmt_read->fetch();
                        $stmt_read->close();
                        $aggiornamento = 0;
                        $messaggio = "";
                        $descrizione_report = "";
                        if ($old_team_responsabile !== $selezione_team) {
                            $messaggio = "Successo: Il progetto \"$nome_progetto\" &egrave; stato assegnato correttamente ad un nuovo team!";
                            $descrizione_report = "Assegnazione Progetto";
                            $aggiornamento = 1;
                            // throw new mysqli_sql_exception($selezione_team);
                        } else if ($old_scadenza !== $scadenza_progetto || $old_descrizione !== $descrizione_progetto){
                            $messaggio = "Successo: Il progetto \"$nome_progetto\" &egrave; stato aggiornato correttamente!";
                            $descrizione_report = "Aggiornamento Progetto";
                            $aggiornamento = 1;
                        }
                        if ($aggiornamento = 1) {
                            # prepariamo il report
                            $stmt_update->bind_param("sssssi", $nome_progetto, $descrizione_progetto, $scadenza_progetto, $selezione_team, $selezione_team, $id_progetto);
                            $stmt_update->execute();
                            $link = "board.html?proj=$id_progetto";
                            $team_bersaglio = (!$selezione_team) ? "No Team" : $selezione_team;
                            $bersaglio_composto = "No Utente-$team_bersaglio-$id_progetto";
                            if(!$selezione_team) $selezione_team = NULL;
                            $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, link, team, progetto, attore_era, bersaglio_era) VALUES ('progetto', ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_report->bind_param("ssssiss", $attore, $descrizione_report, $link, $selezione_team, $id_progetto, $attore, $bersaglio_composto);
                            $stmt_report->execute();
                            
                        } else {
                            $mysqli->rollback();
                        }
                        # faccio il tentativo di commit
                        $mysqli->commit();
                        $sm = isset($messaggio) ? $messaggio : "";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1062) ? "Errore Transazione: Nome gi&agrave usato!" : "Errore Transazione: " . $e->getMessage();
                        $sm = ($e->getCode() == 1452) ? "Errore Transazione: Team non trovato!"  : $sm;
                    } finally {
                        if ($stmt_report !== null) $stmt_report->close();
                        if ($stmt_update !== null) $stmt_update->close();
                    }
                } 

                # se vogliamo creare (CREATE) un team
                if (isset($_POST['create_team'])) {
                    # useró query preparate per evitare iniezioni SQL 
                    $sigla_team = strtoupper($_POST['sigla_team']); // Rendiamo tutto in caps
                    $nome_team = ucfirst($_POST['nome_team']); // mi assicuro di rendere la prima lettera maiuscola
                    $selezione_responsabile = strtolower($_POST['selezione_responsabile']); //mi assicuro di avere tutti i caratteri non in caps
                    if (
                        !preg_match("/^[a-zA-Z0-9]{1,3}$/", $sigla_team) ||
                        !preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,18}[a-zA-ZÀ-ÿ])?$/", $nome_team) ||
                        !preg_match("/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/", $selezione_responsabile)
                        ) {
                            throw new Exception("I dati non sono stati inviati correttamente. Perfavore, ricontrollare la formattazione");
                    } 
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_esistenza = $mysqli->prepare("SELECT team, ruolo FROM utenti WHERE email = ?");
                    $stmt_promozione = $mysqli->prepare("UPDATE utenti SET ruolo = 'capo_team' WHERE email = ?");
                    $stmt_report_promozione = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, attore_era, bersaglio_era) VALUES ('utente', ?, 'Promozione Utente', ?, ?, ?)");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, team, attore_era, bersaglio_era) VALUES ('team', ?, 'Creazione Team', ? ,?, ?, ?)");
                    $stmt = $mysqli->prepare("INSERT INTO team (sigla, nome, responsabile, numero_progetti) VALUES (?, ?, ?, 0)");
                    try {
                        #verifichiamo innanzitutto l'esistenza del membro che vogliamo come responsabile del team
                        $stmt_esistenza->bind_param("s", $selezione_responsabile);
                        $stmt_esistenza->execute();
                        $stmt_esistenza->store_result();
                        if ($stmt_esistenza->num_rows === 0) {
                            throw new mysqli_sql_exception("Utente selezionato non trovato!");
                        }
                        $stmt_esistenza->bind_result($team_responsabile, $ruolo_responsabile);
                        $stmt_esistenza->fetch();
                        $stmt_esistenza->free_result();
                        # per proseguire vogliamo che l'utente non abbia già un team
                        if ($team_responsabile !== null) {
                            throw new mysqli_sql_exception("Utente selezionato \"$selezione_responsabile\" &egrave; gi&agrave; in un altro team!");
                        }
                        $messaggio = "";
                        # se l'utente é già admin o capo_team non abbiamo problemi, e possiamo proseguire direttamente con lo stmt di inserimento. Altrimenti se é utente semplice bisogna prima promuoverlo a capo_team
                        if ($ruolo_responsabile === "utente") {
                            $stmt_promozione->bind_param("s", $selezione_responsabile);
                            $stmt_promozione->execute();
                            $messaggio = "Successo: $selezione_responsabile &egrave; stato promosso a Capo Team ed il team \"$nome_team\" &egrave; stato creato correttamente!";
                            $stmt_report_promozione->bind_param("ssss", $attore, $selezione_responsabile, $attore, $selezione_responsabile);
                            $stmt_report_promozione->execute();
                        }
                        #prepariamo l'istruzione di inserimento
                        $stmt->bind_param("sss", $sigla_team, $nome_team, $selezione_responsabile);
                        $stmt->execute();
                        # in caso di successo facciamo il report
                        $bersaglio_composto = "$selezione_responsabile-$sigla_team";
                        $stmt_report->bind_param("sssss", $attore, $selezione_responsabile, $sigla_team, $attore, $bersaglio_composto);
                        $stmt_report->execute();
                        # faccio il tentativo di commit
                        $mysqli->commit();
                        $sm = $messaggio ? $messaggio : "Successo: Il team \"$nome_team\" &egrave; stato creato correttamente con $selezione_responsabile come Capo Team!";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1062) ? "Errore Transazione: Esiste gi&agrave; un team con questi dati. Prova un'altra sigla o un altro nome!" : "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt !== null) $stmt->close();
                        if ($stmt_esistenza !== null) $stmt_esistenza->close();
                        if ($stmt_promozione !== null) $stmt_promozione->close();
                        if ($stmt_report !== null) $stmt_report->close();
                        if ($stmt_report_promozione !== null) $stmt_report_promozione->close();
                    }
                }

                # se vogliamo eliminare (DELETE) un team, dopo aver verificato che esiste (READ)
                if (isset($_POST['delete_team'])) {
                    # useró query preparate per evitare iniezioni SQL 
                    $sigla_team = strtoupper($_POST['id_team']); // rendiamo tutto in caps
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT COUNT(*) AS conteggio, nome FROM team WHERE sigla = ?");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, team, attore_era, bersaglio_era) VALUES ('team', ?, 'Eliminazione Team', ?, ?, ?)");
                    $stmt_delete = $mysqli->prepare("DELETE FROM team WHERE sigla = ?");
                    try {
                        #vediamo prima se il team che vogliamo eliminare esiste
                        $stmt_read->bind_param("s", $sigla_team);
                        $stmt_read->execute();
                        $stmt_read->store_result();
                        if ($stmt_read->num_rows === 0) {
                            throw new mysqli_sql_exception("Team non trovato!");
                        }
                        $stmt_read->bind_result($conteggio, $nome_team);
                        $stmt_read->fetch();
                        $stmt_read->free_result();
                        # se il team esiste, prepariamo un report per la eliminazione
                        $bersaglio_composto = "No Utente-$sigla_team";
                        $stmt_report->bind_param("ssss", $attore, $sigla_team, $attore, $bersaglio_composto);
                        $stmt_report->execute();
                        # facciamo l'istruzione di eliminazione
                        $stmt_delete->bind_param("s", $sigla_team);
                        $stmt_delete->execute();
                        #faccio il tentativo di commit
                        $mysqli->commit();
                        $sm = "Successo: Il team \"$nome_team\" &egrave; stato eliminato correttamente!";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1452) ? "Errore Transazione: Team da eliminare non trovato!"  : "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_delete !== null) $stmt_delete->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                }

                # se vogliamo aggiornare (UPDATE) un Team 
                if (isset($_POST['edit_team'])) {
                    # useró query preparate per evitare iniezioni SQL 
                    $sigla_team = strtoupper($_POST['id_team']);  // rendere tutto caps
                    $nome_team = ucfirst($_POST['nome_team']); // mi assicuro di rendere la prima lettera maiuscola
                    $selezione_responsabile = strtolower($_POST['selezione_responsabile']); //mi assicuro di avere tutti i caratteri non in caps
                    # verifichiamo che siano rispettati alcuni pattern 
                    if (
                        !preg_match("/^[a-zA-Z0-9]{1,3}$/", $sigla_team) ||
                        !preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ\'\s]{0,18}[a-zA-ZÀ-ÿ])?$/", $nome_team) ||
                        !preg_match("/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/", $selezione_responsabile)
                        ) {
                            throw new Exception("I dati non sono stati inviati correttamente. Perfavore, ricontrollare la formattazione");
                    }
                    # Iniziamo una transazione per garantire integrità dei dati e evitare conflitti di concorrenza
                    $mysqli->begin_transaction();
                    $stmt_read = $mysqli->prepare("SELECT nome, responsabile FROM team WHERE sigla = ?");
                    $stmt_esistenza = $mysqli->prepare("SELECT team, ruolo FROM utenti WHERE email = ?");
                    $stmt_promozione = $mysqli->prepare("UPDATE utenti SET ruolo = 'capo_team' WHERE email = ?");
                    $stmt_report_promozione = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, attore_era, bersaglio_era) VALUES ('utente', ?, 'Promozione Utente', ?, ?, ?)");
                    $stmt_report = $mysqli->prepare("INSERT INTO report (tipo_azione, attore, descrizione, utente, team, attore_era, bersaglio_era) VALUES ('team', ?, ?, ?, ?, ?, ?)");
                    $stmt = $mysqli->prepare("UPDATE team SET nome = ?, responsabile = ? WHERE sigla = ?");
                    try {
                        #verifichiamo se stiamo modificando davvero la tupla, trovando prima i dati attuali del team
                        $stmt_read->bind_param("s", $sigla_team);
                        $stmt_read->execute();
                        $stmt_read->store_result();
                        if ($stmt_read->num_rows === 0) {
                            throw new mysqli_sql_exception("Team non trovato!");
                        }
                        $stmt_read->bind_result($old_nome, $old_responsabile);
                        $stmt_read->fetch();
                        $stmt_read->free_result();
                        $aggiornamento = 0;
                        $messaggio = "";
                        $descrizione_report = "";
                        #verifichiamo se stiamo modificando il responsabile del team
                        if ($old_responsabile != $selezione_responsabile) {
                            #verifichiamo innanzitutto l'esistenza del membro che vogliamo come responsabile del team
                            $stmt_esistenza->bind_param("s", $selezione_responsabile);
                            $stmt_esistenza->execute();
                            $stmt_esistenza->store_result();
                            if ($stmt_esistenza->num_rows === 0) {
                                throw new mysqli_sql_exception("Utente selezionato non trovato!");
                            }
                            $stmt_esistenza->bind_result($team_attuale, $ruolo_attuale);
                            $stmt_esistenza->fetch();
                            $stmt_esistenza->free_result();
                            # per proseguire verifichiamo che l'utente non sia già in un team che non sia quello in cui stiamo effettuando la promozione
                            if ($team_attuale !== null && $team_attuale !== $sigla_team) {
                                throw new mysqli_sql_exception("Utente selezionato &egrave; gi&agrave; in un altro team!");
                            }
                            $messaggio = "Successo: $selezione_responsabile è il nuovo Capo Team di \"$nome_team\"";
                            # se l'utente é già admin o capo_team non abbiamo problemi, e possiamo proseguire direttamente con lo stmt di inserimento. Altrimenti se é utente semplice bisogna prima promuoverlo a capo_team
                            if ($ruolo_attuale === "utente") {
                                $stmt_promozione->bind_param("s", $selezione_responsabile);
                                $stmt_promozione->execute();
                                $messaggio = "Successo: $selezione_responsabile &egrave; stato promosso a Capo Team ed il team \"$nome_team\" &egrave; stato aggiornato correttamente!";
                                $stmt_report_promozione->bind_param("ssss", $attore, $selezione_responsabile, $attore, $selezione_responsabile);
                                $stmt_report_promozione->execute();
                            }
                            $aggiornamento = 1;
                            $descrizione_report = "Assegnazione Team";
                        } else if ($old_nome !== $nome_team) {
                            # o verifichiamo se stiamo solo modificando il nome del team
                            $messaggio = "Successo: Il Team \"$nome_team\" &egrave; stato aggiornato correttamente!";
                            $aggiornamento = 1;
                            $descrizione_report = "Aggiornamento Team";
                        }
                        if ($aggiornamento = 1) {
                            #prepariamo il report
                            $bersaglio_composto = "$selezione_responsabile-$sigla_team";
                            $stmt_report->bind_param("ssssss", $attore, $descrizione_report, $selezione_responsabile, $sigla_team, $attore, $bersaglio_composto);
                            $stmt_report->execute();
                            #prepariamo l'istruzione di aggiornamento
                            $stmt->bind_param("sss", $nome_team, $selezione_responsabile, $sigla_team);
                            $stmt->execute();
                        } else {
                            $mysqli->rollback();
                        }
                        # facciamo il tentativo di commit
                        $mysqli->commit();
                        $sm = $messaggio ? $messaggio : "";
                    } catch (mysqli_sql_exception $e) {
                        $mysqli->rollback();
                        $sm = ($e->getCode() == 1062) ? "Errore Transazione: Esiste gi&agrave; un team con questi dati. Prova un'altra sigla o un altro nome!" : "Errore Transazione: " . $e->getMessage();
                    } finally {
                        if ($stmt !== null) $stmt->close();
                        if ($stmt_report_promozione !== null) $stmt_report_promozione->close();
                        if ($stmt_read !== null) $stmt_read->close();
                        if ($stmt_promozione !== null) $stmt_promozione->close();
                        if ($stmt_esistenza !== null) $stmt_esistenza->close();
                        if ($stmt_report !== null) $stmt_report->close();
                    }
                }
            }      
        } else {
            $sm = "Accesso negato: Sei stat$suffisso reindirizzato";
        }
    } elseif ((isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() > $_SESSION['timeout'])) {
        $suffisso = isset($info) ? $suffisso : "o";
        $sm = "Accesso negato: La tua sessione &egrave; scaduta. Sei stat$suffisso reindirizzat$suffisso e disconness$suffisso!";
        session_unset();    // Rimuovo tutte le variabili di sessione
        session_destroy();
        
    } else {
        $suffisso = isset($info) ? $suffisso : "o";
        $sm = "Accesso negato: Non sei pi&ugrave; conness$suffisso. Sei stato reindirizzat$suffisso";
    }
} catch (Exception $e) {
    # Gestisco l'eccezione, registrando l'errore in una variabile per messaggi di sistema.
    $sm = "Errore: " . $e->getMessage();
}
$info = isset($info) ? $info : [];
$is_admin = isset($is_admin) ? $is_admin : 0;

echo json_encode(['messaggio' => $sm, 'isAdmin' => $is_admin, 'info' => $info]);
exit();
?>