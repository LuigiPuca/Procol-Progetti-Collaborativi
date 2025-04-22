<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve a recuperare i dati utili alla pagina Home
 */


final class Board extends Sezione {
    private int $progetto;
    private bool $isAccessoConsentito = false;

    private function __construct(mysqli $db, Utente $user, int $progetto){
        parent::__construct($db, $user);
        $this->progetto = $progetto;
        
        $this->msg .= " nella bacheca!";

        

        $this->caricaInfoProgetto();
        $this->isAccessoConsentito = ($this->user->isAdmin()) 
            ? true 
            : $this->verificaAccesso();
        
        if ($this->isAccessoConsentito === false) {
            throw new Exception("Errore: Progetto inaccessibile o inesistente");
        }

        $this->dati['id_progetto'] = $this->progetto;
        
        $this->caricaBacheca(); //formata da categorie e schede attività
        $this->caricaResocontoAttivita();
        Risposta::set('messaggio', $this->msg);
        Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function caricaInfoProgetto() {
        $query = $this->preparaStmtInfoProgetto();
        [$tipi, $params] = $this->paramsPerInfoProgetto();
        $result = $this->caricaDati($query, $tipi, ...$params);
        if ($result->num_rows !== 1) {
            $result->free();
            throw new Exception("Errore: Progetto inesistente o inaccessibile");
        }
        $row = $result->fetch_assoc();
        // $stmt = $this->mydb->prepare($query);
        // $stmt->bind_param("si", $this->email, $this->progetto);
        // $stmt->execute();
        // $result = $stmt->get_result();
        // if ($result->num_rows !== 1) {
        //     $result->free();
        //     throw new Exception("Errore: Progetto inesistente o inaccessibile");
        // }
        $this->dati['progetto']['nome_progetto'] = $row['nome_progetto'];
        $this->dati['progetto']['descrizione_progetto'] = $row['descrizione'];
        $scadenza_progetto = new DateTime($row['scadenza_progetto']);
        $scadenza_progetto = $scadenza_progetto->format('d/m/Y H:i:s');
        $this->dati['progetto']['scadenza_progetto'] = $scadenza_progetto;
        $result->free();
    }

    private function preparaStmtInfoProgetto() {
        $query = ($this->user->isAdmin()) 
            ? "
                SELECT DISTINCT progetto AS nome_progetto, 
                    descrizione AS descrizione, scadenza AS scadenza_progetto
                FROM progetti 
                WHERE id_progetto = ?
            " 
            : "
                SELECT DISTINCT p.progetto AS nome_progetto, 
                    p.descrizione AS descrizione, scadenza AS scadenza_progetto 
                FROM progetti p JOIN utenti u ON p.team_responsabile = u.team 
                WHERE u.email = ? AND p.id_progetto = ?
            ";
        return $query;
    }

    private function paramsPerInfoProgetto() : array {
        if ($this->user->isAdmin()) {
            $params = [$this->progetto];
            return ["i", $params];
        }
        $params = [$this->email, $this->progetto];
        return  ["si", $params];
    }

    private function verificaAccesso(): bool {
        $query = "
            SELECT COUNT(*) AS isAccessoConsentito 
            FROM utenti u 
            JOIN progetti p ON u.team = p.team_responsabile 
            WHERE u.email = ? AND id_progetto = ?
        ";
        $stmt = $this->mydb->prepare($query);
        $stmt->bind_param("si", $this->email, $this->progetto);
        $stmt->execute();
        $stmt->bind_result($isAccessoConsentito);
        $stmt->fetch();
        $stmt->close();
        return (bool) $isAccessoConsentito;
    }

    private function caricaBacheca() {
        # recupera categorie (stati)
        $query = "
            SELECT id_progetto, stato, colore_hex, ordine_stati, visibile 
            FROM stati 
            WHERE id_progetto = ?
        ";
        if ($this->user->isUtente()) $query .= " AND visibile = 1";
        $query .= " ORDER BY ordine_stati DESC";
        $result = $this->caricaDati($query, "i", $this->progetto);
        $this->dati['stati'] = [];
        # elabora le categorie e recupera schede per ogni categoria (stato)
        $this->elaboraBacheca($result);
    }

    private function elaboraBacheca($result) {
        while ($row = $result->fetch_assoc()) {
            $stato_corrente = [
                'id_progetto' => $row['id_progetto'],
                'stato' => $row['stato'],
                'colore_hex' => $row['colore_hex'],
                'ordine_stati' => $row['ordine_stati'],
                'isVisibile' => $row['visibile'],
                'schede' => [] //array in cui saranno allegate le schede
            ];

            # recupera scheda associate allo stato corrente
            $this->recuperaSchede($stato_corrente);
        }
        $result->free();
    }

    private function recuperaSchede($stato_corrente) {
        $query = "
            SELECT HEX(uuid_scheda) as hex, titolo, descrizione, ordine_schede 
            FROM schede 
            WHERE id_progetto = ? AND stato = ? 
            ORDER BY ordine_schede DESC
        ";
        $result = $this->caricaDati(
            $query, 'is', 
            $this->progetto, $stato_corrente['stato']
        );
        while ($row = $result->fetch_assoc()) {
            $stato_corrente['schede'][] = [
                'uuid_scheda' => $row['hex'],
                'titolo' => $row['titolo'],
                'descrizione' => $row['descrizione'],
                'ordine_schede' => $row['ordine_schede']
            ];
        }
        $this->dati['stati'][] = $stato_corrente;
        $result->free();

    }


    private function caricaResocontoAttivita() {
        /**
         * ... solo delle attività che riguardano le schede create dall'utente
         * o ad esso assegnate, nelle board corrente.
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
                c.colore_hex, i.incaricato
            FROM report r 
            LEFT JOIN stati c ON r.progetto = c.id_progetto 
                AND c.stato = r.categoria 
            LEFT JOIN schede s ON s.uuid_scheda = r.scheda 
            LEFT JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda 
            WHERE r.team = ? AND r.progetto = ?
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
            $params = 
                [$this->team, $this->progetto, $this->email, $this->email];
            return ["siss", $params];
        }
        $params = [$this->team, $this->progetto];
        return ["si", $params];
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
                "isBersaglioMe" => $isBersaglioMe ? 1 : 0,
                "isAttoreMe" => $isIncaricatoMe ? 1 : 0,
                "isIncaricatoMe" => $isAttoreMe ? 1 : 0
            ];
        }
    }

}

?>