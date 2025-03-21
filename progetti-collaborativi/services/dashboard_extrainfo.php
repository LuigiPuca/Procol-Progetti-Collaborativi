<?php 
require_once('db_connection.php');
session_start();

# Configuriamo MySQLi per segnalare automaticamente gli errori come eccezioni PHP
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


try {
    $suffisso = isset($_SESSION['chi']['suffisso']) ? $_SESSION['chi']['suffisso'] : "o";
    if (isset($_SESSION['uuid_utente']) && isset($_SESSION['timeout']) && time() < $_SESSION['timeout']) {
        #salvo in una variabile l'uuid_utente 
        $uuid_utente = $_SESSION['uuid_utente'];
        # Verifico che l'utente collegato sia un admin
        $query = "SELECT COUNT(*) as is_admin FROM utenti WHERE `uuid` = UNHEX(?) AND ruolo = 'admin'";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $uuid_utente);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($is_admin);
        $stmt->fetch();
        $stmt->close();
        if (!$is_admin) {
            //header('Location: ../login.html');
            $sm = "Accesso negato: Stai per essere reindirizzat$suffisso";
        } else {
            $sm = "Accesso consentito";
            // var_dump($_GET);
            # vogliamo vedere chi sono gli utenti
            $record_per_pagina = 25;
            $pagina_corrente = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
            $pagina_corrente === 0 ? $pagina_corrente = 1 : null;
            $offset = ($pagina_corrente - 1) * $record_per_pagina;
            if (isset($_GET['utenti-iscritti'])) {
                $dati = [];
                
                $query = "SELECT ultimo_accesso, CONCAT(cognome, ' ', nome) AS anagrafica, email, ruolo, team, data_creazione FROM utenti LIMIT $record_per_pagina OFFSET $offset";
                $stmt = $mysqli->query($query);
                if ($stmt->num_rows > 0) {
                    while ($row = $stmt->fetch_assoc()) {
                        $dati[] = $row;
                    }
                }
                $data = $dati; //salvo nell'array

                # bisogna calcolare anche il numero totale di pagine 
                $totale = "SELECT COUNT(*) FROM utenti";
                $stmt_totale = $mysqli->query($totale);
                $numero_tuple = $stmt_totale->fetch_row()[0];
                $numero_pagine = (int) ceil($numero_tuple / $record_per_pagina);
                if ($numero_pagine === 0) $numero_pagine = 1;
            }
            # e quali sono quelli attualmente connessi
            if (isset($_GET['ultimi-accessi'])) {
                $dati = [];
                $query = "SELECT ultimo_accesso, CONCAT(cognome, ' ', nome) AS anagrafica, email, ruolo, team, data_creazione FROM utenti WHERE ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT $record_per_pagina OFFSET $offset";
                $stmt = $mysqli->query($query);
                if ($stmt->num_rows > 0) {
                    while ($row = $stmt->fetch_assoc()) {
                        $dati[] = $row;
                    }
                }
                $data = $dati; //salvo nell'array

                # bisogna calcolare anche il numero totale di pagine 
                $totale = "SELECT COUNT(*) FROM utenti WHERE ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                $stmt_totale = $mysqli->query($totale);
                $numero_tuple = $stmt_totale->fetch_row()[0];
                $numero_pagine = (int) ceil($numero_tuple / $record_per_pagina);
                if ($numero_pagine === 0) $numero_pagine = 1;
            }

            if (isset($_GET['resoconto'])) {
                # ci serve inoltre ottenere i report statistici su ogni azioni rilevante nel sito
                $dati = [];
                $ordine = isset($_GET['ordina']) && $_GET['ordina'] === 'ASC' ? 'ASC' : 'DESC'; 
                $filtri = isset($_GET['filtri']) ? $_GET['filtri'] : [];

                $mysqli->begin_transaction();
                try {
                    $query = "SELECT HEX(r.uuid_report) AS uuid_report, r.tipo_azione AS tipo, r.`timestamp` AS `timestamp`, r.attore AS attore, r.descrizione AS descrizione, r.link AS link, r.utente AS utente, r.team AS team, r.progetto AS progetto, r.categoria AS categoria, HEX(r.scheda) AS scheda, r.attore_era AS attore_era, r.bersaglio_era AS bersaglio_era, p.progetto AS nome_progetto, s.titolo AS titolo FROM report r LEFT JOIN progetti p ON r.progetto = p.id_progetto LEFT JOIN schede s ON s.uuid_scheda = r.scheda";

                    #creiamo una clausola where dinamica attraverso un array
                    $clausole_where = [];
                    $parametri = [];
                    if (isset($filtri['sessione'])) {
                        $clausole_where[] = "tipo_azione = ?";
                        $parametri[] = 'sessione';
                    }
                    if (isset($filtri['utente'])) {
                        $clausole_where[] = "tipo_azione = ?";
                        $parametri[] = 'utente';
                    }
                    if (isset($filtri['team'])) {
                        $clausole_where[] = "tipo_azione = ?";
                        $parametri[] = 'team';
                    }
                    if (isset($filtri['progetto'])) {
                        $clausole_where[] = "tipo_azione = ?";
                        $parametri[] = 'progetto';
                    }
                    if (isset($filtri['scheda'])) {
                        $clausole_where[] = "tipo_azione = ?";
                        $parametri[] = 'scheda';
                    }

                    #aggiungiamo la clausola WHERE se nella query c'è almeno un filtro \
                    if (!empty($clausole_where)) {
                        # implode funziona come il join di js, AND é la scelta del nostro delimitatore
                        $query.= " WHERE ". implode(" OR ", $clausole_where);
                    }
                    $sm = $ordine . $record_per_pagina . $offset;
                    $query .= " ORDER BY `timestamp` $ordine LIMIT $record_per_pagina OFFSET $offset";
                    $stmt = $mysqli->prepare($query);
                    if (!empty($parametri)) {
                        #scegliamo di inserire tante s quante sono i parametri che abbiamo e quali sono i parametri a cui si bindano
                        $stmt->bind_param(str_repeat("s", count($parametri)), ...$parametri);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $dati = [];
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $dati[] = $row;
                        }
                    }
                    $data = $dati;
                    # vogliamo calcolare il numero totale di pagine
                    $totale = "SELECT COUNT(*) FROM report";
                    if (!empty($clausole_where)) {
                        $totale .= " WHERE " . implode(' OR ', $clausole_where);
                    }
                    $stmt_totale = $mysqli->prepare($totale);
                    if (!empty($parametri)) {
                        $stmt_totale->bind_param(str_repeat("s", count($parametri)), ...$parametri);
                    }
                    $stmt_totale->execute();
                    $stmt_totale->store_result(); 
                    $stmt_totale->bind_result($numero_tuple);
                    $stmt_totale->fetch();
                    $numero_pagine = (int) ceil($numero_tuple / $record_per_pagina);
                if ($numero_pagine === 0) $numero_pagine = 1;
                } catch (mysqli_sql_exception $e) {
                    $mysqli->rollback();
                    $sm = "Errore: " . $e->getMessage();
                }
            }
            
        }

        
    } else {
        $sm = "Accesso negato: Stai per essere reindirizzat$suffisso";
    }

} catch (Exception $e) {
    // Gestisco l'eccezione, registrando l'errore in una variabile per messaggi di sistema.
    $sm = "Errore: " . $e->getMessage();
} finally {
    $is_admin = isset($is_admin) ? $is_admin : 0;
    $pagina_corrente = isset($pagina_corrente) ? $pagina_corrente : 1;
    $numero_pagine = isset($numero_pagine) ? $numero_pagine : 1;
    $data = isset($data)? $data : ['non', 'esiste'];
    echo json_encode(['messaggio' => $sm, 'isAdmin' => $is_admin, 'numeroPagine' => $numero_pagine, 'paginaCorrente' => $pagina_corrente, 'data' => $data]);
    $mysqli->close();
    exit();
}

?>