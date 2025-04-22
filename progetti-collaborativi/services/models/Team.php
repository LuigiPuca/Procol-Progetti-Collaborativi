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

    private function caricaProgetti() {
        # query per controllare i progetti a cui il team partecipa
        $query = "
            SELECT p.id_progetto AS id, p.progetto AS nome_progetto 
            FROM progetti p 
            WHERE p.team_responsabile = ?
        ";
        $result = $this->caricaDati($query, "s", $this->team);
        $this->fetchAndSanitize($result, 'progetti');
    }

    private function caricaInfoTeam() {
        $query = "
            SELECT t.sigla AS sigla, t.nome AS team 
            FROM team t 
            JOIN utenti u ON t.sigla = u.team 
            WHERE u.email = ?
        ";
        $stmt = $this->mydb->prepare($query);
        $stmt->bind_param("s", $this->email);
        $stmt->execute();
        $result = $stmt->get_result();
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
        $result = $this->caricaDati($query, "s", $this->dati['sigla']);
        $this->fetchAndSanitize($result, 'membri');
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