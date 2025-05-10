<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve a recuperare i dati extra inerenti alle sottosezioni
 * della pagina Dashboard, facendo un focus su di essi e all'occorrenza 
 * filtrando.
 */


final class Dashfocus extends Sezione {
    private int $recordPerPagina = 25;
    private int $paginaCorrente = 1;
    private int $offset = 0;
    private int $numeroPagine = 1;
    private array $azioni;
    private bool $isAccessoConsentito = false;

    private function __construct(mysqli $db, Utente $user) {
        parent::__construct($db, $user);
        $pagina_corrente = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        if ($pagina_corrente !== 0) {
            $this->paginaCorrente = $pagina_corrente;
        }
        $this->offset = ($this->paginaCorrente - 1) * $this->recordPerPagina;

        $is_admin = $this->user->isAdmin() ? 1 : 0;
        Risposta::set('isAdmin', $is_admin);
        $this->msg = ($is_admin) ? "Accesso Consentito" : " Accesso Negato";
        Risposta::set('messaggio', $this->msg);

        $this->azioni = [
            'utenti-iscritti' => [$this, 'getUtentiIscritti'],
            'ultimi-accessi' => [$this, 'getUltimiAccessi'],
            'resoconto' => [$this, 'getResoconto']
        ];

        foreach ($this->azioni as $param => $callback) {
            if (isset($_GET[$param])) {
                call_user_func($callback);
            }
        }
        
        
        Risposta::set('numeroPagine', $this->numeroPagine);
        Risposta::set('paginaCorrente', $this->paginaCorrente);
        Risposta::set('messaggio', $this->msg);
        Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function getUtentiIscritti() {
        $query = "
                SELECT ultimo_accesso, CONCAT(cognome, ' ', nome) AS anagrafica, 
                    email, ruolo, team, data_creazione 
                FROM utenti 
                LIMIT ? 
                OFFSET ?
            ";
        $params = [$this->recordPerPagina, $this->offset];

        try {
            $this->mydb->begin_transaction();
            $result = Database::caricaDati($query, "ii", ...$params);
            $this->fetchByResult($result);

            # numero totale di pagine
            $numero_tuple = Database::contaDa(
                "utenti", "ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $this->calcolaNumPagine($numero_tuple);
            $this->mydb->commit();
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: ". $e->getMessage());
        }
    }

    private function getUltimiAccessi() {
        $query = "
                SELECT ultimo_accesso, CONCAT(cognome, ' ', nome) AS anagrafica, 
                    email, ruolo, team, data_creazione 
                FROM utenti 
                WHERE ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT ? 
                OFFSET ?
            ";
        $params = [$this->recordPerPagina, $this->offset];

        try {
            $this->mydb->begin_transaction();
            $result = Database::caricaDati($query, "ii", ...$params);
            $this->fetchByResult($result);

            # numero totale di pagine
            $numero_tuple = Database::contaDa(
                "utenti", "ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $this->calcolaNumPagine($numero_tuple);
            $this->mydb->commit();
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: ". $e->getMessage());
        }
    }

    private function getResoconto() {
        $ordine = strtoupper($_GET['ordina'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
        $filtri = $_GET['filtri'] ?? [];

        [$clausole, $params] = $this->filtraggio($filtri);
        $condizione = $clausole ? implode(" OR ", $clausole) : null;
        $query = $this->buildQueryResoconto($ordine, $condizione);
        $tipi = (!empty($params)) ? str_repeat("s", count($params)) : null;
        
        try {
            $this->mydb->begin_transaction();
            
            $result = Database::caricaDati($query, $tipi, ...$params);
            $this->fetchByResult($result);
            $result->free();

            # numero totale di pagine
            $numero_tuple = 
                Database::contaDa('report', $condizione, true, $tipi, ...$params);
            $this->calcolaNumPagine($numero_tuple);
            $this->mydb->commit();
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function filtraggio(array $filtri): array {
        $clausole_where = [];
        $parametri = [];

        $filtri_validi = ['sessione', 'utente', 'team', 'progetto', 'scheda'];

        foreach ($filtri_validi as $tipo) {
            if (isset($filtri[$tipo])) {
                $clausole_where[] = "tipo_azione = ?";
                $parametri[] = $tipo;
            }
        }

        if (empty($clausole_where)) { $clausole_where = null; }
        if (empty($parametri)) { $parametri = []; }

        return [$clausole_where, $parametri];
    }

    private function calcolaNumPagine($numero_tuple) {
        $numero_pagine = (int)ceil($numero_tuple / $this->recordPerPagina);
        if ($numero_pagine !== 0) {
            $this->numeroPagine = $numero_pagine;
        }
    }

    private function buildQueryResoconto($ordine, $condizione) {
        $query = "
            SELECT HEX(r.uuid_report) AS uuid_report, r.tipo_azione AS tipo, 
                r.`timestamp` AS `timestamp`, r.attore AS attore, 
                r.descrizione AS descrizione, r.link AS link, 
                r.utente AS utente, r.team AS team, 
                r.progetto AS progetto, r.categoria AS categoria, 
                HEX(r.scheda) AS scheda, r.attore_era AS attore_era, 
                r.bersaglio_era AS bersaglio_era, p.progetto 
                AS nome_progetto, s.titolo AS titolo 
            FROM report r 
                LEFT JOIN progetti p ON r.progetto = p.id_progetto 
                LEFT JOIN schede s ON s.uuid_scheda = r.scheda
        ";

        # clausola where dinamica attravero array
        if (is_string($condizione)) {
            $query .= " WHERE " . $condizione;
        }

        Risposta::set(
            "messaggio", $ordine . $this->recordPerPagina . $this->offset
        );
        $query .= " 
            ORDER BY `timestamp` $ordine 
            LIMIT $this->recordPerPagina
            OFFSET $this->offset
        ";

        return $query;
    }

}

?>