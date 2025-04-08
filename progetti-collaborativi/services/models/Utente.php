<?php
/**
 * La classe Utente contiene tutto ciò che ci interessa riguardo l'utente
 * connesso, quali uuid, email, ruolo, team e mantenere la connessione al db
 * incapsulata, oltre ai metodi definiti ed eseguibili solo internamente alla
 * classe (metodi privati).
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


class Utente {
    private $uuid; 
    private $email;
    private $ruolo;
    private $team;
    private $mysqli;

    # Costruttore di Utente. Prende in input l'UUID e la connessione al DB
    public function __construct($uuid, $mysqli) {
        $this->uuid = $uuid; //Assegna l'UUID all'attributo omonimo di this
        $this->mysqli = $mysqli; //Assegna la connDB all'attributo della stessa
        $this->caricaDati();
    }
    private function caricaDati() {
        # Usando placeholder e prepared statemend si evitano SQL-injection
        $query = <<<SQL
            SELECT email, ruolo, team
            FROM utenti 
            WHERE `uuid` = UNHEX(?)
        SQL;
        $stmt = $this->mysqli->prepare($query); // Prepara la query
        $stmt->bind_param('s', $this->uuid); // Associa l'UUID al placeholder
        $stmt->execute(); // Esegue la query
        $result = $stmt->get_result(); // Ottiene il risultato dalla query
        
        # Se esiste una riga nel risultato, allora...
        if ($row = $result->fetch_assoc()) {  
            # ... Assegna i valori dei campi agli attributi di this
            $this->email =  $row['email'];
            $this->ruolo = $this->isGestoreDelTeam($row['email'], $row['ruolo'], $row['team']);
            $this->team  = $row['team'];
        } else {
            # ... Altrimenti messaggio di errore
            Risposta::set('messaggio', "Errore: Utente non trovato.");
            Risposta::jsonDaInviare();
        }
        $stmt->close();
    }

    /**
     * Un capo team a cui non è assegnata la gestione del team a cui appartiene
     * va in realtà considerato, ai fini dei permessi, un utente. Va verificato*/ 
    private function isGestoreDelTeam($email, $ruolo, $team) {
        $stmt = null;
        try {
            if ($ruolo === 'utente' || $ruolo === 'admin' || !$ruolo) {
                return $ruolo ? $ruolo : "utente";
            }
            $query = <<<SQL
                SELECT COUNT(*)
                FROM utenti u
                JOIN team t
                ON u.team = t.sigla AND t.responsabile = u.email
                WHERE u.email = ? AND u.ruolo = 'capo_team'
            SQL;
            $stmt = $this->mysqli->prepare($query); //Prepara la query
            $stmt->bind_param("s", $email); //Binda l'email al placeholder
            $stmt->execute(); //Esegue la query
            $stmt->store_result();
            $stmt->bind_result($isGestoreTeam); //dichiara dove salvare
            $stmt->fetch();
            return ($isGestoreTeam) ? $ruolo : "utente";
        } catch (mysqli_sql_exception $e) {
            Risposta::set('messaggio', "Errore: " . $e->getMessage());
            Risposta::jsonDaInviare();
        } finally {
            if ($stmt) $stmt->close();
        }
    }

    # Per accedere agli attributi abbiamo i metodi get
    public function getUUID() { return $this->uuid; }
    public function getEmail() { return $this->email;}
    public function getRuolo() { return $this->ruolo; }
    public function getTeam() { return $this->team; }

    # Per verificare se l'utente è di uno specifico ruolo
    public function isAdmin() { return $this->team === 'admin'; }
    public function isCapoDelTeam() { return $this->team === 'capo_team'; }
    public function isUtente() { return $this->team === 'utente'; }
}
?>