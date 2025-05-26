<?php
/**
 * La classe Progetto contiene tutto ciò che ci interessa riguardo il progetto
 * analizzato, quali progetto, descrizione, scadenza e team responsabile.
 */


class Progetto {
    public const PROGETTO = 'progetto';
    public const DESCRIZIONE = 'descrizione';
    public const SCADENZA = 'scadenza';
    public const TEAM_RESPONSABILE = 'team_responsabile';

    public static $campiValidi = [
        self::PROGETTO,
        self::DESCRIZIONE,
        self::SCADENZA,
        self::TEAM_RESPONSABILE
    ];

    private int $idProj; // id del progetto
    private string $nome;                          
    private string $descrizione;                   
    private ?string $scadenza; 
    private ?string $teamResp;

    public function __construct(
        $idProj, $nome, $descrizione, $scadenza = null, $teamResp = null
    ) 
    {
        $this->idProj = $idProj;
        $this->nome = $nome;
        $this->descrizione = $descrizione;
        $this->scadenza = $scadenza;
        $this->teamResp = $teamResp;
    }

    public static function caricaById($idProj): Progetto 
    {
        if (!is_int($idProj) || $idProj < 0) {
            throw new Exception(
                "Errore: Qualcosa &egrave; andato storto nell'invio dei dati!"
            );
        }
        # Usando placeholder e prepared statemend si evitano SQL-injection
        $query = "
            SELECT progetto AS nome, descrizione as descr, 
                team_responsabile AS resp, scadenza 
            FROM progetti
            WHERE id_progetto = ?
        ";
        # Ottiene il risultato dalla query preparata 
        $result = Database::caricaDati($query, "i", $idProj);
        $row = $result->fetch_assoc();
        $num_risultati = $result->num_rows;
        if (!$row || $num_risultati !== 1) {
            throw new mysqli_sql_exception(
                "Il progetto selezionato non &egrave; stato trovato!"
            );
        }
        return new Progetto(
            $idProj, $row['nome'], $row['descr'], 
            $row['scadenza'], $row['resp']);
    }

    public static function crea($nome, $descrizione, $scadenza, $team = null) {
        $n = Database::createTupla(
                'progetti', 
                'progetto, descrizione, scadenza, team_responsabile',
                "?, ?, ?, IF(? = '', NULL, ?)", "sssss", 
                $nome, $descrizione, $scadenza, $team, $team
        );
        # per numero tuple inserite diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: Impossibile procedere, il progetto '{$nome}' " . 
                "non &egrave; stato creato."
            );
        }
        $id = Database::lastInsertID();
        return new Progetto($id, $nome, $descrizione, $scadenza, $team);
    }

    public function aggiorna(array $campi) 
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
        $where = "id_progetto = $this->idProj";
        $n = Database::updateTupla(
            'progetti', $setter, $where, $types, ...$params
        );
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: Impossibile procedere, il progetto '{$this->nome}' " . 
                "non &egrave; pi&ugrave; presente nel sistema."
            );
        }
    }

    public function elimina() 
    {
        $where = "id_progetto = ?";
        $n = Database::deleteTupla("progetti", $where, "i", $this->idProj);
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: " . 
                "Il progetto '{$this->nome}' risulta gi&agrave; eliminato."
            );
        }
    }

    # Per accedere agli attributi abbiamo i metodi get
    public function getId() { return (int)($this->idProj); }
    public function getNome() { return $this->nome; }
    public function getDescrizione() { return $this->descrizione; }
    public function getTeamResp() { return $this->teamResp; }
    public function getScadenza() { return $this->scadenza; }
}
?>