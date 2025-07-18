<?php

final class Permessi {
    const VISIONE_HOME = 1 << 0; // Basta che viene passato l'utente
    const VISIONE_TEAM = 1 << 1; // L'utente ha team o è admin
    const VISIONE_BOARD = 1 << 2; // L'utente ha stesso team del proj (o admin)
    const COMMENTA_ATTIVITA = 1 << 3; // attività è visibile
    const GESTIONE_ATTIVITA = 1 << 4; // Se si è almeno responsabile_attivita+
    const CTRL_ATTIVITA = 1 << 5; // Se si é capo del team, admin, o creatore
    const CREA_CATEGORIA = 1 << 6; // Se si è capo del team o admin
    const VISIONE_CATEGORIA = 1 << 7; // Se si è almeno capo_team+ o visibile
    const GESTIONE_CATEGORIA = 1 << 8; // Se si è almeno capo_team+
    const DA_ADMIN = 1 << 9; // Se si è admin

    private static int $attivi = 0;
    private static ?string $teamProj;
    
    public static function getAttivi(): int {
        return self::$attivi;
    }

    public static function getTeamProj(): int {
        return self::$teamProj;
    }

    public static function calcola(
        Utente $user, ?int $idProj,
        ?string $categoria = null, ?string $attivita = null
    ): void {

        $num_args = func_num_args();
        # verifica che l'utente abbia un ruolo definito
        if ($num_args === 0 || !$user->getRuolo()) {
            self::$attivi = 0;
            return;
        }

        # se l'utente è admin vengono dati tutti i permessi
        if ($user->isAdmin()) { 
            self::$attivi = (1 << 10) - 1;
            return;
        } 

        # Altrimenti...
        $mydb = (Database::getIstanza())->getDataBaseConnesso();

        # Se solo l'utente è passato si fornisce permesso di visione della home
        if ($num_args > 0) {
            $add = ($user->getTeam()) 
                 ? self::VISIONE_HOME | self::VISIONE_TEAM 
                 : self::VISIONE_HOME;
            self::$attivi |= $add;
        }

        if ($num_args > 1 && (self::$attivi & self::VISIONE_TEAM)) {
            $isProjMatch = $user->getTeam() === self::$teamProj;
            if (!$isProjMatch) {
                return;
            }
            self::$attivi |= self::VISIONE_BOARD;
            if ($user->isCapoDelTeam()) {
                self::$attivi |= self::CREA_CATEGORIA;
            }
        }

        $isStatoVisibile = false;

        if ($num_args > 2 && (self::$attivi & self::VISIONE_BOARD)) {
            $isStatoVisibile = self::isCategoriaVisibile(
                $user, $idProj,$categoria, self::$teamProj
            );
            if (!$isStatoVisibile) {
                return;
            }
            self::$attivi |= self::VISIONE_CATEGORIA;
            if ($user->isCapoDelTeam()) {
                self::$attivi |= self::GESTIONE_CATEGORIA;
            }
        }

        if ($num_args === 4 && (self::$attivi & self::VISIONE_CATEGORIA)) {
            $args = [$user->getEmail(), $idProj, $categoria, $attivita];
            if (
                self::isScheda($user->isCapoDelTeam(), $args, "creata") || 
                self::isScheda(false, $args, "creata")
            ) {
                self::$attivi |= self::CTRL_ATTIVITA;
                self::$attivi |= self::GESTIONE_ATTIVITA;
                self::$attivi |= self::COMMENTA_ATTIVITA;
            } elseif (self::isScheda(false, $args, "assegnata")) {
                self::$attivi |= self::GESTIONE_ATTIVITA;
                self::$attivi |= self::COMMENTA_ATTIVITA;
            } elseif (self::isScheda(false, $args, "accessibile")) {
                self::$attivi |= self::COMMENTA_ATTIVITA;
            }
        }
    }

    private static function isCategoriaVisibile($user, $idProj, $categoria, $projTeam): bool {
        $query = "
            SELECT COUNT(*) AS isAccessoConsentito 
            FROM utenti u
            JOIN progetti p ON u.team = p.team_responsabile 
        ";
        if ($user->isCapoDelTeam()) {
            $query .= "
                JOIN stati s ON s.id_progetto = p.id_progetto 
                    AND s.stato = ?
                WHERE u.email = ? AND p.id_progetto = ? 
                    AND u.ruolo = 'capo_team'
            ";
        } elseif ($user->isUtente()) {
            $query .= "
                JOIN stati s ON s.id_progetto = p.id_progetto 
                    AND s.stato = ? AND s.visibile = 1
                WHERE u.email = ? AND p.id_progetto = ? 
                    AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team')
            ";
        }
        $types = "ssi";
        $params = [$categoria, $user->getEmail(), $idProj];
        $result = Database::caricaDati($query, $types, ...$params);
        $row = $result->fetch_assoc(); 
        if ((int)$row['isAccessoConsentito'] !== 1) {
            return false;
        }
        return (bool)$row['isAccessoConsentito'];
    }

    public static function seekTeamProj(?int $idProj): string {
        $query = "SELECT team_responsabile FROM progetti WHERE id_progetto = ?";
        try {
            $result = Database::caricaDati($query, "i", $idProj);
            $row = $result->fetch_assoc();
            self::$teamProj = $row['team_responsabile'] ?? null;
        } catch (mysqli_exception $e) {
            self::$teamProj = null;
        } finally {
            return self::$teamProj;
        }
    }

    private static function isScheda(bool $isCapoTeam,
        array $params, string $opz = "accessibile"
    ): int {
        if (!in_array($opz, ["creata", "assegnata", "accessibile"])) {
            return false;
        }
        $condizione = "";
        $join = "";
        $visibile = ($isCapoTeam) ? "" : " AND c.visibile = 1  ";
        if ($opz === "creata") {
            $condizione = " AND u.email = s.autore ";
        } elseif ($opz === "assegnata") {
            $join = "
                JOIN info_schede i 
                    ON i.uuid_scheda = s.uuid_scheda
                    AND i.incaricato = u.email 
            ";
        }
        $query = "
            SELECT COUNT(*) as isTrue
            FROM utenti u 
            JOIN progetti p ON u.team = p.team_responsabile 
            JOIN stati c ON c.id_progetto = p.id_progetto 
            JOIN schede s 
                ON c.stato = s.stato 
                $condizione
            $join
            WHERE u.email = ?
                AND p.id_progetto = ? 
                $visibile
                AND c.stato = ?
                AND s.uuid_scheda = UNHEX(?)
                AND (u.ruolo = 'utente' OR u.ruolo = 'capo_team')
        ";
        $types = "siss";
        $result = Database::caricaDati($query, $types, ...$params); 
        $row = $result->fetch_assoc();
        if ((int)$row['isTrue'] !== 1) {
            return false;
        }
        return (bool)$row['isTrue'];      
    }
}
