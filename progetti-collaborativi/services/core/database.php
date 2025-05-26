<?php
/**
 * La creazione e la connessione al database può essere fatta attraverso il
 * seguente Singleton. In questo modo si garantisce al più una sola 
 * connessione.
 * Nelle ultime versioni di PHP, essendo mysqlnd di default attivo, invece di
 * di store_result(), num_rows() nell'if, fetch() nel while, e il
 * bind_result($email, $ruolo, $team), è consigliabile usare get_result(), 
 * fetch_assoc() nel while, num_rows() nell if, e avere i campi corrispondente 
 * alle chiavi dell'array associativo. Ciò permette di rispare righe di codice.
 * Nel caso in cui bisogna verificare un solo risultato con una colonna, ad
 * esempio nei count, va bene usare il bind_result().
 * Se num_rows() ha la stessa capacità in entrambe le versioni, si evince come
 * invece fetch_assoc() non restituisce vero o falso come farebbe fetch() ma 
 * restituisce un array associativo con i campi del database come chiavi.
 * Ciò ci risparmia di passare per l'operazione di binding.
 * In realtà, bind_result() funziona con get_result(), ma è a questo punto 
 * inutile non necessitando di chiamare store_result().
 */ 

final class Database {
    private static $istanza = null;
    private static $mysqli;
    
    private function __construct() {
        $host = 'localhost';
        $dbName = 'progetticollaborativi';
        $dbUser = 'root';
        $dbPassword = '';

        self::$mysqli = new mysqli($host, $dbUser, $dbPassword, $dbName);
        
        # se la connessione al db fallisce 
        if ($err = self::$mysqli->connect_error) {
            throw new Exception('Errore: Connessione fallita: ' . $err);
        }

        # se si verifica un errore nell'impostare UTF-8 come set di caratteri
        if (!self::$mysqli->set_charset("utf8")) {
            throw new Exception(
                "Errore: Imposisbile impostazione del set " 
                . "di caratteri UTF-8: " . self::$mysqli->error
            );
        }

        # forza a restituire interi e float nativi quando possibile
        self::$mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
    }

    # impedisce clonazione e deserializzazione
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception("Impossibile deserializzare il Singleton");
    }

    public static function getIstanza() {
        if (self::$istanza === null) {
            self::$istanza = new self();
        }
        return self::$istanza;
    }

    public function getDataBaseConnesso() {
        return self::$mysqli;
    }

    public function chiudiConnessione() {
        if (self::$mysqli !== null) {
            self::$mysqli->close();
            self::$mysqli = null;
            self::$istanza = null;
        }
    }

    /**
     * Rileva se il parametro passato é uno stmt o un result, e in caso
     * affermativo libera la risorsa
     * 
     * @param mysqli_stmt|mysqli_result|bool|null $a La risorsa da liberare
     */
    public static function liberaRisorsa(mysqli_stmt|mysqli_result|bool|null $a) {
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
    public static function eseguiStmt(
        string $query, ?string $tipi = null, ...$params
    ): mysqli_stmt|mysqli_result|bool {
        if ($tipi === null) {
            /** Query diretta: 
             * SELECT -> mysqli_result, Altri -> true, Errore->false
             */ 
            return self::$mysqli->query($query);
        } elseif (
            is_string($tipi) && 
            count($params) !== 0 &&
            strlen($tipi) === count($params)
        ) {
            $stmt = null;
            try {
                $stmt = self::$mysqli->prepare($query);
                self::bindParams($stmt, $tipi, ...$params);
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

    public static function caricaDati(
        string $query, ?string $tipi = null, ...$params
    ) {
        if ($tipi === null) {
            $result = self::$mysqli->query($query);
            if ($result === false) {
                throw new Exception(
                    "Errore nella query: " . self::$mysqli->error
                );
            }
            return $result;
        } else {
            $stmt = self::eseguiStmt($query, $tipi, ...$params);
            if (!$stmt instanceof mysqli_stmt) {
                throw new Exception(
                    "Errore nella preparazione o esecuzione dello statement."
                );
            }
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }
    }

    private static function bindParams(mysqli_stmt $stmt, string $tipi, ...$params): bool {
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
    public static function contaDa(
        string $nome_tabella, ?string $condizione = null,
        ?string $tipi = null, ...$params
    ): int {
        try {
            $query = "SELECT COUNT(*) AS totale FROM $nome_tabella";
            if ($condizione !== null) {
                $query .= " WHERE $condizione";
            }
            $result = self::eseguiStmt($query, $tipi, ...$params);
    
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
    
    /**
     * Elimina le tuple specificati da tabella e condizioni
     * 
     * @param string nome_tabella Il nome della tabella in cui andiamo a 
     *               filtrare le tuple da eliminare
     * @param ?string condizione Il set di condizioni per cio le tuple della 
     *                tabella vengono filtrate
     * 
     * @param ?string $tipi I tipi di binding
     * @param mixed ...$params I parametri da passare alla query preparata
     */
    public static function deleteTupla(
        string $nome_tabella, ?string $condizione = null,
        ?string $tipi = null, ...$params
    ): int {
        if ($condizione === null) {
            throw new Exception("Scegliere una condizione per eliminare!");
        }
        $query = "DELETE FROM $nome_tabella WHERE $condizione";
        $stmt = self::eseguiStmt($query, $tipi, ...$params);
        if ($stmt instanceof mysqli_stmt) {
            $num = $stmt->affected_rows;
            self::liberaRisorsa($stmt);
            return $num;
        } elseif ($stmt === false) {
            throw new mysqli_sql_exception(
                "Errore nella query DELETE: " . self::$mysqli->error
            );
        }
    }

    public static function updateTupla(
        string $nome_tabella, string $imposta, ?string $condizione = null,
        ?string $tipi = null, ...$params
    ): int {
        if ($condizione === null) {
            throw new Exception("Scegliere una condizione per aggiornare!");
        }
        $query = "UPDATE $nome_tabella SET $imposta WHERE $condizione";
        $stmt = self::eseguiStmt($query, $tipi, ...$params);
        if ($stmt instanceof mysqli_stmt) {
            $num = $stmt->affected_rows;
            self::liberaRisorsa($stmt);
            return $num;
        } elseif ($stmt === false) {
            throw new mysqli_sql_exception(
                "Errore nella query UPDATE: " . self::$mysqli->error
            );
        }
    }

    public static function createTupla(
        string $nome_tabella, ?string $campi = null, 
        ?string $valori = null, ?string $tipi = null, ...$params
    ): int {
        if ($campi === null || $valori === null) {
            throw new Exception(
                "Scegliere campi e valori per creare una nuova riga!"
            );
        }
        $query = "INSERT INTO $nome_tabella ($campi) VALUES ($valori);";
        $stmt = self::eseguiStmt($query, $tipi, ...$params);
        if ($stmt instanceof mysqli_stmt) {
            $num = $stmt->affected_rows;
            self::liberaRisorsa($stmt);
            return $num;
        } elseif ($stmt === false) {
            throw new mysqli_sql_exception(
                "Errore nella query INSERT INTO: " . self::$mysqli->error
            );
        }
    }

    public static function sanitizzaTupla($row): array {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $value = strip_tags($value); // rimuove i tag html 
                # converte caratteri speciali in entità html
                $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            } else {
                $row[$key] = $value; // lascia intatti numeri, null, ecc.
            }
        }
        return $row;
    }

    /**
     * Da usare solo dopo query di inserimento con chiave primaria 
     * autoincrementante
     * 
     * @return int La chiave primaria della nuova tupla inserita
     */
    public static function lastInsertID(): int {
        return (int)(self::$mysqli->insert_id);
    }

}

?>