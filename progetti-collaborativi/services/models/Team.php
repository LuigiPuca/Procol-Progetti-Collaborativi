<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve a recuperare i dati utili alla pagina Team
 */

final class Team extends Sezione {

    private function __construct(mysqli $db, Utente $user){
        parent::__construct($db, $user);
        $this->msg .= " nella pagina del team!";

        $this->caricaProgetti(); //a cui il team partecipa
        $this->caricaInfoTeam(); //a cui appartiente l'utente
        $this->caricaMembriTeam();  //appartenti allo stesso team dell'utente
        $this->caricaResocontoAttivita();

        Risposta::set('messaggio', $this->msg);
        Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function caricaInfoTeam() {
        $query = "
            SELECT t.sigla AS sigla, t.nome AS team 
            FROM team t 
            JOIN utenti u ON t.sigla = u.team 
            WHERE u.email = ?
        ";
        $result = Database::caricaDati($query, "s", $this->email);
        if ($row = $result->fetch_assoc()) {
            $this->dati['sigla'] = $row['sigla'];
            $this->dati['team'] = $row['team'];
        }
    }

    private function caricaMembriTeam() {
        $query = "
            SELECT u.cognome AS cognome, u.nome AS nome, u.genere 
                AS genere, u.email AS email, 
            CASE WHEN u.email = t.responsabile 
                THEN TRUE 
                ELSE FALSE 
            END AS isLeader 
            FROM utenti u 
            INNER JOIN team t ON u.team = t.sigla 
            WHERE u.team = ?
            ORDER BY isLeader ASC, u.cognome, u.nome
        ";
        $result = Database::caricaDati($query, "s", $this->team);
        $this->fetchByResult($result, 'membri', true);
    }

    private function caricaResocontoAttivita() {
        /**
         * ... solo delle attività che riguardano le schede create dall'utente
         * o ad esso assegnate, nelle varie board del team.
         * Se si è capoteam carica anche quelle degli altri membri del team.
         */
        $query = $this->buildQueryResoconto();
        [$tipi, $params] = $this->paramsPerResoconto();
        $result = Database::caricaDati($query, $tipi, ...$params);
        $this->mydb->close();
        if ($result->num_rows !== 0) {
            $this->elaboraResoconto($result);
        }  
    }

    private function buildQueryResoconto() {
        $query = "
            SELECT DISTINCT HEX(r.uuid_report) AS uuid_report, r.`timestamp` AS
                `timestamp`, r.attore, r.descrizione, r.link, 
                r.utente AS bersaglio, r.team AS team_responsabile, 
                r.categoria as stato, s.titolo as titolo_scheda, r.attore_era, 
                r.bersaglio_era, c.colore_hex, i.incaricato, p.progetto 
            FROM report r 
            LEFT JOIN progetti p 
                ON p.id_progetto = r.progetto 
            LEFT JOIN stati c ON r.progetto = c.id_progetto 
                AND c.stato = r.categoria 
            LEFT JOIN schede s ON s.uuid_scheda = r.scheda 
            LEFT JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda 
            WHERE r.team = ?
        ";
        if ($this->user->isUtente()) {
            $query .= " AND (r.utente = ? OR i.incaricato = ?) ";
        }
        $query .= "
            AND r.descrizione IN (
                'Creazione Scheda', 'Archiviazione Scheda', 
                'Eliminazione Scheda', 'Cambiamento Stato', 
                'Aggiunta Descrizione Scheda', 'Modifica Descrizione Scheda', 
                'Creazione Commento', 'Modifica Commento', 'Risposta Commento', 
                'Eliminazione Commento', 'Revocazione Scheda', 
                'Assegnazione Scheda', 'Riassegnazione Scheda', 
                'Creazione Categoria', 'Eliminazione Categoria', 
                'Oscuramento Categoria', 'Visualizzazione Categoria'
            )
            ORDER BY r.timestamp DESC LIMIT 50;
        ";
        return $query;
    }

    private function paramsPerResoconto(): array {
        if ($this->user->isUtente()) {
            $params = [$this->team, $this->email, $this->email];
            return ["sss", $params];
        }
        $params = [$this->team];
        return ["s", $params];
    }
    
    private function elaboraResoconto($result) {
        $this->fetchByResult($result, 'reports');
        foreach (array_keys($this->dati['reports']) as $i) {
            $this->dati['reports'][$i]['isBersaglioMe'] =
                ($this->dati['reports'][$i]['bersaglio'] === $this->email)
                ? 1 : 0;
            $this->dati['reports'][$i]['isAttoreMe'] =
                ($this->dati['reports'][$i]['attore'] === $this->email)
                ? 1 : 0;
            $this->dati['reports'][$i]['isIncaricatoMe'] =
                ($this->dati['reports'][$i]['incaricato'] === $this->email)
                ? 1 : 0;
        };
    }

}

?>