<?php
/**
 * La classe Utente contiene tutto ciò che ci interessa riguardo l'utente
 * connesso, quali uuid, email, ruolo, team e mantenere la connessione al db
 * incapsulata, oltre ai metodi definiti ed eseguibili solo internamente alla
 * classe (metodi privati).
 */


class Utente {
    private const FOUNDER = "admin@procol.com";
    public const EMAIL = '`uuid`';
    public const RUOLO = 'ruolo';
    public const TEAM = 'team';

    public static $campiValidi = [
        self::EMAIL,
        self::RUOLO,
        self::TEAM,
    ];

    private string $uuid; 
    private string $email;
    private string $ruolo;
    private string $ruoloDB;
    private ?string $team;

    # Costruttore di Utente. Prende in input l'UUID e la connessione al DB
    public function __construct($uuid, $email, $ruoloDB, $team = null) {
        $this->uuid = $uuid;
        $this->email = $email;
        $this->ruoloDB = $ruoloDB;
        $this->team = $team;
        $this->ruolo = $this->isGestoreDelTeam();
    }
    
    public static function caricaByUUID($uuid) 
    {
        # Usando placeholder e prepared statemend si evitano SQL-injection
        $query = "
            SELECT email, ruolo, team
            FROM utenti 
            WHERE `uuid` = UNHEX(?)
        ";
        # Ottiene il risultato dalla query preparata 
        $result = Database::caricaDati($query, "s", $uuid);
        
        # Se esiste una riga nel risultato, allora...
        if ($row = $result->fetch_assoc()) { 
            return new Utente($uuid, $row['email'], $row['ruolo'], $row['team']);
        } else {
            # ... Altrimenti messaggio di errore
            Risposta::set('messaggio', "Errore: Utente non trovato.");
            Risposta::jsonDaInviare();
        }
        Database::liberaRisorsa($result);
    }

    public static function caricaByEmail($email): Utente
    {
        $query = "
            SELECT ruolo, HEX(`uuid`) AS `uuid`, team
            FROM utenti 
            WHERE email = '$email'
        ";
        $result = Database::caricaDati($query);
        $row = $result->fetch_assoc();
        $num_risultati = $result->num_rows;
        if (!$row || $num_risultati !== 1) {
            throw new mysqli_sql_exception(
                "L'email $email non &egrave; stata trovata!"
            );
        }
        return new Utente(
            $row['uuid'], $email, $row['ruolo'], $row['team']
        );
    }

    /**
     * Un capo team a cui non è assegnata la gestione del team a cui appartiene
     * va in realtà considerato, ai fini dei permessi, un utente. Va verificato
     */ 
    private function isGestoreDelTeam(): string 
    {
        try {
            if (in_array($this->ruoloDB,['utente', 'admin'])) {
                return $this->ruoloDB;
            }
            $query = "
                SELECT COUNT(*) AS isGestore
                FROM utenti u
                JOIN team t
                ON u.team = t.sigla AND t.responsabile = u.email
                WHERE u.`uuid` = UNHEX(?) AND u.ruolo = 'capo_team'
            ";
            $result = Database::caricaDati($query, "s", $this->uuid);
            $row = $result->fetch_assoc();
            Database::liberaRisorsa($result);
            if (!$row) {
                throw new mysqli_sql_exception("Errore: Utente non esistente");
            }
            return $row['isGestore'] === 1 ? 'capo_team' : 'utente';
        } catch (mysqli_sql_exception $e) {
            Risposta::set('messaggio', "Errore: " . $e->getMessage());
            Risposta::jsonDaInviare();
        } 
    }

    // public function ancoraEsiste() {
    //     $query = "
    //         SELECT COUNT(*) AS still_exists 
    //         FROM utenti u 
    //         WHERE u.`uuid` = UNHEX(?)
    //     ";
    //     $result = Database::caricaDati($query, "s", $this->uuid);
    //     $row = $result->fetch_assoc();
    //     if (!$row || $row['still_exists'] !== 1) {
    //         return false;
    //     }
    //     return true;
    // }

    public function aggiorna($campi) 
    {
        $set = [];
        $params = [];
        $types = '';
        foreach ($campi as $campo=>$valore) {
            if (!in_array($campo, self::$campiValidi, true)) {
                throw new InvalidArgumentException(
                    "Campo '$campo' non consentito "
                );
            }
            $set[] = "$campo = ?";
            $params[] = $valore;
            $types .= 's';
        }
        $setter = implode(', ', $set);
        $where = "`uuid` = UNHEX('$this->uuid')";
        $n = Database::updateTupla(
            'utenti', $setter, $where, $types, ...$params
        );
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: Impossibile procedere, l’utente {$this->email} " . 
                "non &egrave; pi&ugrave; presente nel sistema."
            );
        }
    }

    public function elimina() 
    {
        $where = "`uuid` = UNHEX(?)";
        $n = Database::deleteTupla("utenti", $where, "s", $this->uuid);
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: L’utente {$this->email} risulta gi&agrave; eliminato."
            );
        }
    }

    # Per accedere agli attributi abbiamo i metodi get
    public static function founder() { return self::FOUNDER; }
    public function getUUID() { return $this->uuid; }
    public function getEmail() { return $this->email;}
    public function getRuoloDB() { return $this->ruoloDB; }
    public function getRuolo() { return $this->ruolo; }
    public function getTeam() { return $this->team; }

    # Per verificare se l'utente è di uno specifico ruolo
    public function isAdmin() { return $this->ruolo === 'admin'; }
    public function isCapoTeamDB() { return $this->ruoloDB === 'capo_team'; }
    public function isCapoDelTeam() { return $this->ruolo === 'capo_team'; }
    public function isUtente() { return $this->ruolo === 'utente'; }
    public function isUtenteDB() { return $this->ruoloDB === 'utente'; }
    public function isFounder() { 
        return $this->isAdmin() && $this->getEmail() === self::FOUNDER;
    }
    public function isRespTemporaneo() { return $this->isAdmin && $this->team; }
    public function isResponsabile() {
        return $this->isCapoDelTeam() || $this->isRespTemporaneo();
    }
}
?>