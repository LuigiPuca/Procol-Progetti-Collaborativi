<?php

require_once __DIR__ . "/Singleton.php";

/**
 * Singleton che definisce quali proprietà e metodi devono avere ogni sezione. 
 */

 abstract class Sezione extends Singleton {
    protected mysqli $mydb;
    protected Utente $user;
    protected string $email;
    protected string $team;
    protected array $dati = [];
    protected string $msg = "";

    protected function __construct(mysqli $db, Utente $user){
        $this->mydb = $db;
        $this->user = $user;
        $this->email = $user->getEmail();
        $this->team = $user->getTeam();
        $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
        $this->msg = "Benvenut$suffisso";
    }

    protected function caricaDati(string $query, string $tipi, ...$params) {
        $stmt = $this->mydb->prepare($query);
        $this->bindParams($stmt, $tipi, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
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

    protected function fetchAndSanitize(
        mysqli_result $result, string $chiave_associata
    ): void {
        Risposta::set($chiave_associata, []);
        while ($row = $result->fetch_assoc()) {
            foreach ($row as $key=>$value) {
                $value = strip_tags($value); // rimuove tag html
                # converte caratteri speciali in entità html
                $row[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); 
            }
            $this->dati[$chiave_associata][] = $row;
        }
        unset($row);
    }
}

?>