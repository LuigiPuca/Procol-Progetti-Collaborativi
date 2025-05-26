<?php
/**
 * La classe Squadra contiene tutto ciò che ci interessa riguardo i team, quali
 * sigla, nome, utente responsabile e il numero di progetti, oltre che metodi
 * utili alla creazione, aggiornamento e eliminazione di tuple nel DB inerenti.
 */


class Squadra {
    public const NOME = 'nome';
    public const RESPONSABILE = 'responsabile';

    public static $campiValidi = [
        self::NOME,
        self::RESPONSABILE
    ];

    private string $sigla;
    private string $nome;
    private string $responsabile;
    private int $numProgetti = 0;

    # Costruttore di Utente. Prende in input l'UUID e la connessione al DB
    public function __construct($sigla, $nome, $responsabile, $numProgetti) {
        $this->sigla = $sigla;
        $this->nome = $nome;
        $this->responsabile = $responsabile;
        $this->numProgetti = $numProgetti;
    }

    public static function caricaBySigla($sigla): Squadra {
        if (!checkSiglaTeam($sigla)) {
            throw new Exception(
                "Errore: Qualcosa &egrave; andato storto nell'invio dei dati!"
            );
        }
        # Usando placeholder e prepared statemend si evitano SQL-injection
        $query = "
            SELECT nome, responsabile AS resp, numero_progetti AS num_p
            FROM team
            WHERE sigla = ?
        ";
        # Ottiene il risultato dalla query preparata 
        $result = Database::caricaDati($query, "s", $sigla);
        $row = $result->fetch_assoc();
        $num_risultati = $result->num_rows;
        if (!$row || $num_risultati !== 1) {
            throw new mysqli_sql_exception(
                "Il team di sigla $sigla non &egrave; stata trovato!"
            );
        }
        return new Squadra($sigla, $row['nome'], $row['resp'], $row['num_p']);
    }

    public function crea($sigla, $nome, $responsabile) {
        $n = Database::createTupla(
                'team', 
                'sigla, nome, responsabile, numero_progetti',
                "?, ?, ?, 0", "sss", $sigla, $nome, $responsabile
        );
        # per numero tuple inserite diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: Impossibile procedere, il team '{$this->nome}' " . 
                "non &egrave; stato creato."
            );
        }

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
        $where = "sigla = '$this->sigla'";
        $n = Database::updateTupla(
            'team', $setter, $where, $types, ...$params
        );
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: Impossibile procedere, il team '{$this->nome}' " . 
                "non &egrave; pi&ugrave; presente nel sistema."
            );
        }
    }

    public function elimina() 
    {
        $where = "sigla = ?";
        $n = Database::deleteTupla("team", $where, "s", $this->sigla);
        # per numero tuple eliminate diverso da 1 restituisce errore da gestire
        if ($n !== 1) {
            throw new mysqli_sql_exception(
                "Errore: " . 
                "Il team '{$this->nome}' risulta gi&agrave; eliminato."
            );
        }
    }

    # Per accedere agli attributi abbiamo i metodi get
    public function getSigla() { return $this->sigla; }
    public function getNome() { return $this->nome; }
    public function getResponsabile() { return $this->responsabile; }
    public function getNumProgetti() { return $this->numProgetti; }
}
?>