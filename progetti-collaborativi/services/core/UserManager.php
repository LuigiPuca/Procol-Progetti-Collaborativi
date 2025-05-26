<?php

require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../models/Utente.php';
require_once __DIR__ . '/../models/Squadra.php';
require_once __DIR__ . "/../models/abstracts/Sezione.php";
require_once __DIR__ . '/DBLogger.php';

/**
 * La classe AuthManager serve per gestire tutti i processi di 
 * autenticazione, come accesso, disconnessione e registrazione.
 */

 final class UserManager 
 {
    private const OP_VALIDE = [
        'elimina',             // eliminare dal DB gli utenti
        'promuovi',            // promuovere di ruolo gli utenti
        'declassa',            // declassare di ruolo gli utenti
        'caccia',              // rimuovere gli utenti dai loro team
        'coinvolgi'            // aggiunge gli utenti allo stesso team
    ];

    private mysqli $mydb;
    private Utente $io;
    private array $utenti;
    private int $isAdmin = 0;
    private string $msg = "";
    private string $action;
    
    /**
     * Per poter gestire una lista di utenti, è necessario riconoscere che
     * l'utente che compie l'azione sia admin, che l'azione sia riconosciuta
     * come valida
     */
    public function __construct(Utente $io, string $action, array $emails)
    {
        $this->mydb = (Database::getIstanza())->getDataBaseConnesso();
        $this->io = $io;
        $this->isAdmin = (int)$io->isAdmin();
        Risposta::set('isAdmin', $this->isAdmin);
        $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
        $this->action = $action;
        $this->utenti = $this->createOggUtenti(...$emails);

        if (!$this->isAdmin) {
            throw new Exception(
                "Permesso negato: Sei stat$suffisso reindirizzat$suffisso"
            );
        }

        if (!in_array($action, self::OP_VALIDE)) {
            throw new Exception("Errore: Operazione non riconosciuta");
        }

        $this->msg = "Permesso consentito";
        Risposta::set('messaggio',$this->msg);
    }

    private function createOggUtenti(...$emails): array 
    {
        $emails = array_map(fn($obj) => $obj['email'], $emails);
        if (!checkEmail(...$emails)) {
            throw new Exception(
                "Errore: Qualcosa &egrave; andato storto nell'invio dei dati!"
            );
        }
        $array = [];
        foreach ($emails as $email) {
            $array[] = Utente::caricaByEmail($email);
        }
        if (empty($array)) {
            throw new mysqli_sql_exception(
                "L'azione '$this->azione' non pu&ograve; essere eseguita " . 
                "senza una selezione"
            );
        }
        return $array;
    }

    private function inviaRisposta() 
    {
        Risposta::set('messaggio', $this->msg);
        // Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function noActionOnFounder(Utente $user, string $err) 
    {
        if ($user->isFounder()) {
            $err = "Il founder " . Utente::founder() . " non pu&ograve; essere " 
                 . $err;
            throw new mysqli_sql_exception($err);
        }
    }

    private function noActionOnAdmin(Utente $user, string $err) {
        if ($user->isAdmin()) {
            $err = "L'admin " . $user->getEmail() . " non pu&ograve; essere " 
                 . $err;
            throw new mysqli_sql_exception($err);
        }
    }

    public function elimina() 
    {
        try {
            $this->mydb->begin_transaction();
            # 0. Seleziona gli utenti
            foreach($this->utenti as $user) {

                # 1. Errore se sto agendo sul founder o admin
                $this->noActionOnFounder($user, "eliminato");
                $this->noActionOnAdmin($user, "eliminato");
       
                # 2. Preparare il report per l'eliminazione utente
                DBLogger::utente(
                    $this->io->getEmail(), "elimina", $user->getEmail()
                );

                # 3. Elimina l'utente selezionato
                $user->elimina();
            }
            $this->mydb->commit();
            $this->msg = "Successo: Tutti gli account selezionati sono "
                       . "stati eliminati!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    public function ridefinisciRuolo($invia = true) {
        $operazione = $this->action;
        try {
            $this->mydb->begin_transaction();
            # 0. Seleziona gli utenti
            foreach($this->utenti as $user) {

                # 1. Errore se sto agendo su founder 
                $this->noActionOnFounder($user, "ridefinito");

                # 2. Verifica se l'operazione è possibile in base al grado
                $chiave = ($this->io->isFounder() ? "founder_" : "admin_") 
                        . $operazione . "_" . ($user->isUtenteDB() ? 'utente' 
                            : ($user->isCapoTeamDB() ? (
                                $user->isCapoDelTeam() ? 'leader' : 'capo'
                            ) : 'admin')
                        );
                $newRuolo = match($chiave) {
                    'founder_promuovi_utente', 'admin_promuovi_utente' => 
                        'capo_team',
                    "founder_promuovi_capo", "founder_promuovi_leader" =>
                        'admin',
                    "admin_promuovi_capo", "admin_promuovi_leader" =>
                        throw new mysqli_sql_exception(
                            "Non hai i permessi necessari per promuovere " .
                            $user->getEmail()
                        ),
                    "founder_promuovi_admin", "admin_promuovi_founder" =>
                        throw new mysqli_sql_exception(
                            $user->getEmail() . 
                            " non pu&ograve; essere promosso ulteriormente"
                        ),
                    "founder_declassa_admin" => 'capo_team',
                    "admin_declassa_admin" => throw new mysqli_sql_exception(
                        "Non hai i permessi necessari per declassare " .
                        $user->getEmail()
                    ),
                    "founder_declassa_leader", "admin_declassa_leader" =>
                        throw new mysqli_sql_exception(
                            $user->getEmail() . " non pu&ograve; essere  " . 
                            "declassato finch&eacute; a capo di un team"
                        ),
                    "founder_declassa_capo", "admin_declassa_capo" => 'utente',
                    default => throw new mysqli_sql_exception(
                        $user->getEmail() . 
                        " non pu&ograve; essere declassato ulteriormente"
                    )
                };
       
                # 3. Preparare il report per la ridefinizione utente
                DBLogger::utente(
                    $this->io->getEmail(), $operazione, $user->getEmail()
                );

                # 4. Aggiorna l'utente selezionato
                $user->aggiorna(["ruolo" => $newRuolo]);
            }
            if ($invia) { $this->mydb->commit(); }
            $this->msg = "Successo: I ruoli degli account selezionati sono "
                       . "stati ridefiniti!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        if ($invia) { $this->inviaRisposta(); }
    }

    public function unisciATeam($newTeam) {
        $newTeam = strtoupper($newTeam);
        $invariati = 0;
        $daCambiare = count($this->utenti);
        try {
            $this->mydb->begin_transaction();
            # 0. Recupero dati sul futuro team
            $newTeam = Squadra::caricaBySigla($newTeam);
            foreach($this->utenti as $user) {
                # 1. Errore se sto agendo su founder o admin, come admin
                if (!$this->io->isFounder()) {
                    $this->noActionOnFounder(
                        $user, "inserito in un team da un admin!"
                    );
                    $this->noActionOnAdmin(
                        $user, "inserito in un team da un admin!"
                    );
                }

                # 2. Recupero la sigla del team attuale dell'utente
                $oldTeam = $user->getTeam() 
                         ? Squadra::caricaBySigla($user->getTeam())
                         : null;
                
                # Verifica se l'utente ha team e diverso da quello nuovo
                if (
                    $oldTeam !== null &&
                    $newTeam->getSigla() === $oldTeam->getSigla()
                ) {
                    $invariati++;
                    continue; // perchè l'utente è gia nel team
                }

                # 3. Verifica che l'utente non è responsabile altrove
                if ($user->isResponsabile()) {
                    throw new mysqli_sql_exception(
                        "{$user->getEmail()} non pu&ograve; entrare nei " . 
                        "'{$newTeam->getNome()}' perch&eacute; responsabile " . 
                        "dai '{$oldTeam->getNome()}'!"
                    );
                }

                # 4. Preparare il/i report ...
                if ($oldTeam) {
                    # dell'utente cacciato dal suo vecchio team (se non nullo)
                    DBLogger::team(
                        $this->io->getEmail(), 'caccia', 
                        $oldTeam, $user->getEmail()
                    );
                }
                # dell'utente che entra nel nuovo team
                DBLogger::team(
                    $this->io->getEmail(), 'coinvolgi', 
                    $newTeam, $user->getEmail()
                );

                # 5. Aggiorna l'utente selezionato
                $user->aggiorna(["team" => $newTeam]);
            }
            $this->mydb->commit();
            $this->msg = ($invariati !== $daCambiare) 
                       ? "Successo: Gli account selezionati ora fanno parte " .
                         "dei ". $newTeam->getNome() . "!"
                       : "";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    public function cacciaDaTeam() {
        $invariati = 0;
        $daCambiare = count($this->utenti);
        try {
            $this->mydb->begin_transaction();
            # 0. Agisce su ogni utente selezionato
            foreach($this->utenti as $user) {
                # 1. Errore se si agisce su founder o admin, come admin
                if (!$this->io->isFounder()) {
                    $this->noActionOnFounder(
                        $user, "cacciato dal team da un admin!"
                    );
                    $this->noActionOnAdmin(
                        $user, "cacciato dal team da un admin!"
                    );
                }

                # 2. Recupera sigla del team attuale dell'utente
                $oldTeam = $user->getTeam() 
                         ? Squadra::caricaBySigla($user->getTeam())
                         : null;
                
                # 3. Verifica se l'utente non ha team e diverso da quello nuovo
                if ($oldTeam === null) {
                    $invariati++;
                    continue; // perchè l'utente è gia senza team
                }

                # 4. Verifica che l'utente non è responsabile altrove
                if ($user->isResponsabile()) {
                    throw new mysqli_sql_exception(
                        "{$user->getEmail()} non pu&ograve; essere cacciato " . 
                        "da '{$oldTeam->getNome()}' finch&eacute; responsabile!"
                    );
                }

                # 5. Prepara il report dell'utente cacciato dal suo team
                DBLogger::team(
                    $this->io->getEmail(), 'caccia', $oldTeam, $user->getEmail()
                );
                

                # 6. Aggiorna l'utente selezionato
                $user->aggiorna(["team" => null]);
            }
            $this->mydb->commit();
            $this->msg = ($invariati !== $daCambiare) 
                       ? "Successo: Gli account selezionati sono stati " .
                         "rimossi dai loro team!"
                       : "";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    public static function checkDatiRicevuti(Utente $user, $dati) {
        if (
            !isset($dati['operazione']) || 
            !in_array($dati['operazione'], self::OP_VALIDE)
        ) {
            $op = isset($dati['operazione']) ? "'{$dati['operazione']}'" : null;
            throw new Exception("Operazione $op non riconosciuta"); 
        }
        $op = $dati['operazione'];
        if (!isset($dati['emails']) || empty($dati['emails'])) {
            throw new Exception(
                "Attenzione: Selezionare prima una o pi&ugrave; email!"
            );
        }

        $um = new UserManager($user, $op, $dati['emails']);
        match ($op) {
            'elimina' => $um->elimina(),
            'promuovi', 'declassa' => $um->ridefinisciRuolo(),
            'coinvogli' => isset($dati['team']) 
                ? $um->unisciATeam($dati['team']) 
                :throw new Exception("Errore: Nessun team selezionato"),
            'caccia' => $um->cacciaDaTeam(),
        };
    }
    

    
 }
