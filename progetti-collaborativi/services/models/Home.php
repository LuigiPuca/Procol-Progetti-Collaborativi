<?php

require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve a recuperare i dati utili alla pagina Home
 */

final class Home extends Sezione {

    protected function __construct(mysqli $db, Utente $user){
        parent::__construct($db, $user);

        $this->msg .= " nella home page!";

        $this->caricaProgetti(); //a cui l'utente partecipa
        $this->caricaSchede();   //assegnate all'utente
        $this->caricaResocontoAttivita();

        Risposta::set('messaggio', $this->msg);
        Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function caricaProgetti() {
        # query per controllare i progetti a cui l'utente partecipa
        $query = "
            SELECT p.id_progetto AS id, p.progetto AS nome_progetto 
            FROM progetti p 
            JOIN utenti u ON p.team_responsabile = u.team 
            WHERE u.email = ?
        ";
        $result = $this->caricaDati($query, "s", $this->email);
        $this->fetchAndSanitize($result, 'progetti');
    }

    private function caricaSchede() {
        $query = "
            SELECT s.id_progetto AS id, HEX(s.uuid_scheda) AS uuid, 
                s.titolo AS nome_scheda 
            FROM schede s JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda 
            WHERE i.incaricato = ?
        ";
        $result = $this->caricaDati($query, "s", $this->email);
        $this->fetchAndSanitize($result, 'schede_assegnate');
    }

    private function caricaResocontoAttivita() {
        /**
         * ... solo delle attività che riguardano le schede create dall'utente
         * o ad esso assegnate, nelle varie board del team.
         * Se si è capoteam carica anche quelle degli altri membri del team.
         */
        $query = $this->preparaStmtResoconto();
        [$tipi, $params] = $this->paramsPerResoconto();
        $result = $this->caricaDati($query, $tipi, ...$params);
        $this->mydb->close();
        if ($result->num_rows !== 0) {
            $this->elaboraResoconto($result);
        }
    }

    private function preparaStmtResoconto() {
        $query = "
            SELECT DISTINCT HEX(r.uuid_report), r.`timestamp`, 
                r.attore, r.descrizione, r.link, r.utente, r.team, 
                r.categoria, s.titolo, r.attore_era, r.bersaglio_era,
                c.colore_hex, i.incaricato, p.progetto 
            FROM report r 
            LEFT JOIN progetti p 
                ON p.id_progetto = r.progetto 
                AND r.team = ? 
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
        ";
        return $query;
    }

    private function paramsPerResoconto(): array {
        if ($this->user->isUtente()) {
            $params = [$this->team, $this->team, $this->email, $this->email];
            return ["ssss", $params];
        }
        $params = [$this->team, $this->team];
        return ["ss", $params];
    }
    
    private function elaboraResoconto($result) {
        while ($row = $result->fetch_assoc()) {
            $isBersaglioMe = ($row['utente'] === $this->email);
            $isAttoreMe = ($row['attore'] === $this->email);
            $isIncaricatoMe = ($row['incaricato'] === $this->email);
            $this->dati['reports'][] = [
                "uuid_report" => strtolower($row['HEX(r.uuid_report)']), 
                "timestamp" => $row['timestamp'], 
                "attore" => $row["attore"],
                "descrizione" => $row["descrizione"],
                "link" => $row["link"],
                "bersaglio" => $row["utente"],
                "team_responsabile" => $row["team"],
                "stato" => $row['categoria'],
                "titolo_scheda" => $row['titolo'],
                "attore_era" => $row['attore_era'],
                "bersaglio_era" => $row['bersaglio_era'],
                "colore_hex" => $row['colore_hex'],
                "incaricato" => $row['incaricato'],
                "progetto" => $row['progetto'],
                "isBersaglioMe" => $isBersaglioMe ? 1 : 0,
                "isAttoreMe" => $isIncaricatoMe ? 1 : 0,
                "isIncaricatoMe" => $isAttoreMe ? 1 : 0
            ];
        }
    }

}

?>