<?php
/**
 * La creazione e la connessione al database può essere fatta attraverso il
 * seguente Singleton. In questo modo si garantisce al più una sola 
 * connessione.
 */ 

class Database {
    private static $istanza = null;
    private $mysqli;
    
    private function __construct() {
        $host = 'localhost';
        $dbName = 'progetticollaborativi';
        $dbUser = 'root';
        $dbPassword = '';

        $this->mysqli = new mysqli($host, $dbUser, $dbPassword, $dbName);
        
        # se la connessione al db fallisce 
        if ($err = $this->mysqli->connect_error) {
            throw new Exception('Errore: Connessione fallita: ' . $err);
        }

        # se si verifica un errore nell'impostare UTF-8 come set di caratteri
        if (!$this->mysqli->set_charset("utf8")) {
            throw new Exception(
                "Errore: Imposisbile impostazione del set " 
                . "di caratteri UTF-8: " . $this->$mysqli->error
            );
        }

        # forza a restituire interi e float nativi quando possibile
        $this->mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
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
        return $this->mysqli;
    }

    public function chiudiConnessione() {
        if ($this->mysqli !== null) {
            $this->mysqli->close();
            $this->mysqli = null;
            self::$istanza = null;
        }
    }
    

}

?>