<?php

require_once __DIR__ . "/Singleton.php";

/**
 * Singleton che definisce quali proprietà e metodi devono avere ogni sezione. 
 */

 abstract class Sezione extends Singleton {
    protected mysqli $mydb;
    protected Utente $user;
    protected string $email;
    protected ?string $team;
    protected array $dati = [];
    protected string $msg = "";

    protected function __construct(mysqli $db, Utente $user) {
        $this->mydb = $db;
        $this->user = $user;
        $this->email = $user->getEmail();
        $this->team = $user->getTeam();
        $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
        $this->msg = "Benvenut$suffisso";
    }

    protected function caricaProgetti(?int $tranne = null) {
        # query per controllare i progetti a cui l'utente partecipa
        $query = "
            SELECT p.id_progetto AS id, p.progetto AS nome_progetto 
            FROM progetti p 
            JOIN utenti u ON p.team_responsabile = u.team 
            WHERE u.email = ?
        ";
        $tipi = "s";
        $params = [$this->email];
        if ($tranne) {
            $query .= " AND id_progetto != ?";
            $tipi = "si";
            $params[] = $tranne;
        }
        $result = Database::caricaDati($query, $tipi, ...$params);
        $this->fetchByResult($result, 'progetti', true);
    }

    protected function liberaRisorsa(mysqli_stmt|mysqli_result|bool|null $a) {
        if ($a instanceof mysqli_stmt) {
            $a->close(); // Qualsiasi operazione di una query preparata
        } elseif($a instanceof mysqli_result) {
            $a->free(); // SELECT di una query diretta
        }
        # bool é il risultato di una query diretta quando non ritorna risultati
    }

    /**
     * Esegue una query, preparata o diretta.
     *
     * @param string $query   La query SQL da eseguire
     * @param string|null $tipi  Stringa dei tipi per i parametri (es. "is"), 
     *                    oppure null (default) per query diretta
     * @param mixed ...$params  Parametri per la query preparata
     * @return mysqli_stmt|mysqli_result|bool
     *         Restituisce uno statement (mysqli_stmt) se preparata,
     *         un risultato (mysqli_result) o bool se query diretta
     * @throws mysqli_sql_exception In caso di errore
     */
    protected function eseguiStmt(
        string $query, ?string $tipi = null, ...$params
    ): mysqli_stmt|mysqli_result|bool {
        if ($tipi === null) {
            /** Query diretta: 
             * SELECT -> mysqli_result, Altri -> true, Errore->false
             */ 
            return $this->mydb->query($query);
        } elseif (
            is_string($tipi) && 
            count($params) !== 0 &&
            strlen($tipi) === count($params)
        ) {
            $stmt = null;
            try {
                $stmt = $this->mydb->prepare($query);
                Database::bindParams($stmt, $tipi, ...$params);
                $stmt->execute();
                return $stmt;
            } catch (Throwable $e) {
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
                throw $e; // Rilancia l’eccezione dopo aver chiuso lo statement
            }
        } else {
            throw new mysqli_sql_exception("Numero di parametri non validi!");
        }
    }
    
    protected function caricaDati(
        string $query, ?string $tipi = null, ...$params
    ) {
        if ($tipi === null) {
            $result = $this->mydb->query($query);
            if ($result === false) {
                throw new Exception("Errore nella query: " . $this->mydb->error);
            }
            return $result;
        } else {
            $stmt = Database::eseguiStmt($query, $tipi, ...$params);
            if (!$stmt instanceof mysqli_stmt) {
                throw new Exception("Errore nella preparazione o esecuzione dello statement.");
            }
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }
    }
    
    

    protected function bindParams($stmt, string $tipi, ...$params): bool {
        if (count($params) !== strlen($tipi)) {
            throw new Exception("Errore: Numero di parametri invalido");
        }

        # poichè bind_param richiede parametri passati per riferimento
        $lista_rif = [];
        foreach($params as $key => $value) {
            ${"param$key"} = $value; //per evitare problemi di riferimento
            $lista_rif[$key] = &${"param$key"};
        }

        return $stmt->bind_param($tipi, ...$lista_rif);
    }

    /**
     * Conta quanti risultati restituisce una SELECT semplice
     * 
     * @param string $nome_tabella La tabella in cui fare il conteggio
     * @param ?string $condizione Condizione di filtraggio
     * @param bool $isPrepared Definisce se la query deve essere
     * eseguita direttamente o preparata
     * @param ?string $tipi Tipi dei parametri da passare in caso
     * di query preparata
     * @param mixed ...$params Parametri da passare in
     * caso di query preparata
     * @return 0 Numero di risultati restituti
     * @throws Throwable Messaggi di errori
     */
    protected function contaDa(
        string $nome_tabella, ?string $condizione = null,
        ?string $tipi = null, ...$params
    ): int {
        try {
            $query = "SELECT COUNT(*) AS totale FROM $nome_tabella";
            if ($condizione !== null) {
                $query .= " WHERE $condizione";
            }
            $result = Database::eseguiStmt($query, $tipi, ...$params);
    
            $row = null;
            if ($result instanceof mysqli_stmt) {
                $res = $result->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $result->close();
            } elseif ($result instanceof mysqli_result) {
                $row = $result->fetch_assoc();
                $result->free();
            }
            return isset($row['totale']) ? (int)$row['totale'] : 0;
        } catch (Throwable $e) {
            Risposta::set('message', "Errore: " . $e->getMessage());
            Risposta::push($e->getMessage());
            return 0;
        }
    }

    protected function getArrayResult( 
        string $query, bool $withSanitize = false
    ): array {
        $dati = [];
        $result = $this->mydb->query($query);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if (!$withSanitize) {
                    $dati[] = $row;
                    continue;
                }
                foreach ($row as $key => $value) {
                    if (is_string($value)) {
                        $value = strip_tags($value); // rimuove i tag html 
                        # converte caratteri speciali in entità html
                        $row[$key] = 
                            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    } else {
                        $row[$key] = $value; // numeri, null, ecc. intatti
                    }
                }                
                $dati[] = $row;
            }
        }
        return $dati;
    }
    
    
    protected function fetchByResult(
        mysqli_result $result, ?string $chiave = null,
        bool $withSanitize = false
    ): void { 
        if ($chiave) { Risposta::push($chiave, 'dati'); }
        while ($row = $result->fetch_assoc()) {
            $row = $withSanitize ? Database::sanitizzaTupla($row) : $row;
            $chiave ? $this->dati[$chiave][] = $row : $this->dati[] = $row; 
        }
        unset($row);
    }

    protected function pushInDati($info) {
        foreach ($info as $key => $value) {
            Risposta::push($value, 'dati', $key);
        }
    }
}

?>