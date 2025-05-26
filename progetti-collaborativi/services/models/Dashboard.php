<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve a recuperare i dati utili alla pagina Dashboard
 */


final class Dashboard extends Sezione {
    private ?array $datiPost;
    private int $oggi;
    private array $giorniSettimana = [];
    private bool $isAccessoConsentito = false;

    private function __construct(mysqli $db, Utente $user, ?array $datiPost) {
        parent::__construct($db, $user);
        $this->datiPost = $datiPost;
        $this->msg .= " nel pannello di monitoraggio!";
        Risposta::set('messaggio', $this->msg);
        
        $this->giorniDellaSettimana();

        $this->isAccessoConsentito = $this->user->isAdmin() ? true : false;
        Risposta::set('isAdmin', (int)$this->isAccessoConsentito);
        $this->msg = ($this->isAccessoConsentito) 
            ? "Accesso Consentito" 
            : "Accesso Negato";
        Risposta::set('messaggio', $this->msg);
        
        # carica info necessarie a non rompere la pagina
        $this->caricaInfoDiBase();

        # altri info necessarie, ma che non rompono la pagina in caso di assenza
        $this->caricaAltreInfo();

        # invia manualmente tutte le informazioni accumulate 
        Risposta::jsonDaInviare();
    }

    private function giorniDellaSettimana(): void {
        # array che memorizza i giorni della settimana
        $giorni_settimana = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
        $this->oggi = (int)date('w'); // (0 -> domenica ... 6 -> sabato )
        $indice_giorno_piu_lontano = ($this->oggi + 1) % 7; // 6 giorni fa
        # riordina i giorni della settimana partendo da 6 giorni prima
        for ($i = 0; $i < 7; $i++) {
            $giorno = $giorni_settimana[
                ($indice_giorno_piu_lontano + $i) % 7
            ];
            $this->giorniSettimana[] = $giorno; 
        }
    }

    private function caricaInfoDiBase(): void {
        /**
         * recupera info di base, array di giorni della settimana ordinata,
         * numero di commenti, schede completate e in ritardo nella settimana, 
         * giorno per giorno, numero di progetti e di quelli a cui è assegnato
         * un team
         */ 
        $this->dati = [
            'numero_utenti_iscritti' => Database::contaDa("utenti"),
            'numero_connessi_last24h' => Database::contaDa(
                "utenti", 
                "ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            ),
            'numero_team_creati' => Database::contaDa("team"),
            'numero_commenti' => Database::contaDa("commenti"),
            'giorni_ordinati' => $this->giorniSettimana,
            'commenti_weekly' => $this->conteggioSettimanale(
                "inviato", "commenti"
            ),
            'schede_completate_weekly' => $this->conteggioSettimanale(
                "spostamento", "vista_schede_completate"
            ),
            'schede_in_ritardo_weekly' => $this->conteggioSettimanale(
                "spostamento", "vista_schede_in_ritardo"
            ),
            'schede_in_ritardo_weekly' => $this->conteggioSettimanale(
                "spostamento", "vista_schede_in_ritardo"
            ),
            'numero_progetti' => Database::contaDa('progetti'),
            'numero_progetti_con_team' =>
                Database::contaDa("progetti", "team_responsabile IS NOT NULL")
        ];
        # vanno passati quantomeno i dati obbligatori
        Risposta::set('dati', $this->dati);
    }

    private function caricaAltreInfo(): void {
        $info = [];
        $info['lista_progetti'] = $this->caricaProgettiCreati();

        # ... team assegnabili al progetto, sort per num di prog già assegnati
        $info['team_assegnabili'] = $this->caricaTeamAssegnabili();

        $info['lista_team'] = $this->caricaTeamCreati();

        # ... tutti gli utenti assegnabili ad un team (se richiesto)
        if ($this->datiPost) {
            $info['utenti_assegnabili'] = 
                $this->caricaUtentiAssegnabili($this->datiPost);
        }

        # ... categoria di attività coompletate
        $info['schede_completate'] = $this->caricaSchede("completate");

        # ... categoria di attività in ritardo
        $info['schede_in_ritardo'] = $this->caricaSchede("in_ritardo");
        $this->pushInDati($info);
    }

    private function conteggioSettimanale(string $campo, string $da_tabella) {
        $valori_per_giorno = array_fill(0,7,0);
        try {
            $query = "
                SELECT DAYOFWEEK($campo) AS giorno, COUNT(*) AS conteggio
                FROM $da_tabella
                WHERE $campo >= CURDATE() - INTERVAL 7 DAY
                GROUP BY DAYOFWEEK($campo) 
                ORDER BY giorno
            ";
            $result = $this->mydb->query($query);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $giorno = (int)$row['giorno']; // (1 -> dom ... 7 -> sab)
                    $indice = ($giorno + 5 - $this->oggi) % 7; // allinea giorni
                    $valori_per_giorno[$indice] = $row['conteggio'];
                }
            }
        } catch (Throwable){
            Risposta::set('message', "Errore: " . $e->getMessage());
            Risposta::push($e->getMessage());
        }
        return $valori_per_giorno;  
    }

    private function caricaProgettiCreati(): array {
        $query = "SELECT * FROM `vista_progetti_team_utenti` ORDER BY id_progetto ASC";
        return $this->getArrayResult($query, true);
    }

    private function caricaTeamAssegnabili(): array {
        $query = "
            SELECT CONCAT(sigla, ' | ', nome, ' [', numero_progetti, ']') 
            AS team_da_assegnare 
            FROM team 
            ORDER BY numero_progetti; 
        ";
        return $this->getArrayResult($query);
    }

    private function caricaTeamCreati(): array {
        $query = "SELECT * FROM `vista_team_utenti` ORDER BY sigla_team ASC";
        return $this->getArrayResult($query);
    }

    private function caricaUtentiAssegnabili($datiPost): array {
        $already_in = ($datiPost['recupera_team'] === "N/A") 
            ? "" 
            : $datiPost['recupera_team'];
        $query = "
            SELECT CONCAT(cognome, ' ', nome) AS anagrafica, email, ruolo 
            FROM utenti 
            WHERE (team IS NULL OR team = ?) 
            ORDER BY cognome ASC, nome ASC, email ASC;
        ";
        $result = Database::caricaDati($query, "s", $already_in);
        return $this->elaboraUtentiAssegnabili($result);
    }

    private function elaboraUtentiAssegnabili($result): array {
        $dati = [];
        while ($row = $result->fetch_assoc()) {
            $ruolo = match($row['ruolo']) {
                'admin' => "{A}", 'capo_team' => "{C}", default => "{U}"
            };
            $anagrafica = "$ruolo " . $row['anagrafica'];
            $new_row = ['anagrafica' => $anagrafica, 'email' => $row['email']];
            $dati[] = $new_row;
        }
        return $dati;
    }
    
    private function caricaSchede(string $categoria): array {
        $query = "
            SELECT HEX(uuid_scheda) 
                AS uuid_scheda, id_progetto, titolo, stato, spostamento 
            FROM `vista_schede_$categoria` 
            ORDER BY spostamento ASC";
        return $this->getArrayResult($query);
    }

}

?>