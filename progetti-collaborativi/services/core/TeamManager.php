<?php

require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../models/Utente.php';
require_once __DIR__ . '/../models/Squadra.php';
require_once __DIR__ . "/../models/abstracts/Sezione.php";
require_once __DIR__ . '/DBLogger.php';
require_once __DIR__ . '/UserManager.php';

/**
 * La classe AuthManager serve per gestire tutti i processi di 
 * autenticazione, come accesso, disconnessione e registrazione.
 */

 final class TeamManager 
 {
    private const OP_VALIDE = [
        'crea',                // creare team, inserendoli nel DB
        'elimina',             // eliminare dal DB il team
        'aggiorna',            // aggiorna nel DB il team
        'modifica',            // modifica nel DB il team
        'assegna',             // assegna nel DB l'utente al team
    ];

    private mysqli $mydb;
    private Utente $io;
    private ?Squadra $team;
    private ?string $newResponsabile;
    private int $isAdmin = 0;
    private string $msg = "";
    private string $action;
    
    /**
     * Per poter gestire una lista di utenti, è necessario riconoscere che
     * l'utente che compie l'azione sia admin, che l'azione sia riconosciuta
     * come valida
     */
    public function __construct(Utente $io, string $action) 
    {
        $this->mydb = (Database::getIstanza())->getDataBaseConnesso();
        $this->io = $io;
        $this->isAdmin = (int)$io->isAdmin();
        Risposta::set('isAdmin', $this->isAdmin);
        $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
        $this->action = $action;

        if (!$this->isAdmin) {
            throw new Exception(
                "Permesso negato: Sei stat$suffisso reindirizzat$suffisso"
            );
        }

        if (!in_array($action, self::OP_VALIDE)) {
            throw new Exception("Errore: Operazione $action non riconosciuta");
        }

        $this->msg = "Permesso consentito";
        Risposta::set('messaggio',$this->msg);
    }

    private function createOggTeam($sigla): Squadra 
    {
        if (!checkSiglaTeam($sigla)) {
            throw new Exception(
                "Errore: Qualcosa &egrave; andato storto nell'invio dei dati!"
            );
        }
        return Squadra::caricaBySigla($sigla);
    }

    private function inviaRisposta() 
    {
        Risposta::set('messaggio', $this->msg);
        // Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    public function checkResponsabile(
        string $responsabile, ?string $siglaTeam = null
    ) {
        $user = Utente::caricaByEmail($responsabile);
        $args = func_num_args();
        $condizione = ($args === 2) 
                    ? $user->getTeam() && $user->getTeam() !== $siglaTeam
                    : $user->getTeam();
        if ($condizione) {
            throw new mysqli_sql_exception(
                "'$responsabile' &egrave; gi&agrave; in un altro team!"
            );
        }
        $this->msg = "";

        # Se l'utente non è capo team (o admin) bisogna promuoverlo
        if ($user->isUtenteDB()) {
            $userCtrl = new UserManager(
                $this->io, "promuovi", [['email' => $user->getEmail()]]
            );
            $userCtrl->ridefinisciRuolo(false);
        }
    }

    public function crea($sigla, $nome, $responsabile) {
        checkDatiInviati(
            checkSiglaTeam($sigla),
            checkElNames(1, 20, $nome),
            checkEmail($responsabile) 
        );
        
        try {
            $this->mydb->begin_transaction();

            # 1. Verifica esistenza dell'utente e che non sia in un team
            $this->checkResponsabile($responsabile);

            # 2. Inserisce effettivamente il team
            $newTeam = new Squadra($sigla, $nome, $responsabile, 0);
            $newTeam->crea($sigla, $nome, $responsabile);

            # 3. Crea il report in caso di successo
            DBLogger::team($this->io->getEmail(), 'crea', $sigla, $responsabile);

            $this->mydb->commit();
            $this->msg = "Successo: Il team {$newTeam->getNome()} &egrave; "
                       . "stato creato!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = ($e->getCode() == 1062) 
                ? "Esiste gi&agrave; un team con questi dati. " . 
                  "Prova un'altra sigla o un altro nome!" 
                : $e->getMessage();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $sm
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    
    public function elimina($sigla) 
    {
        try {
            $this->mydb->begin_transaction();

            # 1. Verifica esistenza del team
            $team = $this->createOggTeam($sigla);
            # 2. Prepara il report per l'eliminazione team
            DBLogger::team(
                $this->io->getEmail(), "elimina", $team->getSigla()
            );

            # 3. Elimina il team selezionato
            $team->elimina();
            
            $this->mydb->commit();
            $this->msg = "Successo: Il team '{$team->getNome()}'"
                       . "&egrave; stato eliminato!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = ($e->getCode() == 1452)
                ? "Team da eliminare non trovato!" 
                : $e->getMessage();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $sm
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    public function aggiorna($sigla, $newName, $newResponsabile) {
        checkDatiInviati(
            checkSiglaTeam($sigla),
            checkElNames(1, 20, $newName), 
            checkEmail($newResponsabile)
        );
        $oldTeam = $this->createOggTeam($sigla);
        $oldName = $oldTeam->getNome();
        $oldResponsabile = $oldTeam->getResponsabile();

        try {
            $this->mydb->begin_transaction();

            # 1. verifica se ci sono cambiamenti
            if ($oldName === $newName && $oldResponsabile === $newResponsabile) {
                $this->mydb->rollback();
                $this->msg = "";
                $this->inviaRisposta();
                return;
            }

            # 2. Se ci sono, si verifica se cambiamento riguarda il responsabile
            $cambiamento = 0;
            if ($oldResponsabile !== $newResponsabile) {
                $this->checkResponsabile($newResponsabile, $sigla);
                DBLogger::team(
                    $this->io->getEmail(), 'assegna', 
                    $oldTeam->getSigla(), $newResponsabile
                );
                $cambiamento |= (1 << 0);
            }
            

            # 3. ma anche se riguarda il nome
            if ($oldName !== $newName) {
                DBLogger::team(
                    $this->io->getEmail(), 'modifica', 
                    $oldTeam->getSigla(), $newResponsabile
                );
                $cambiamento |= (1 << 1);
            }

            
            $oldTeam->aggiorna([
                'nome' => $newName,
                'responsabile' => $newResponsabile
            ]);

            $this->mydb->commit();
            $this->msg = match (true) {
                $cambiamento === (1 << 0) => 
                    "Successo: $newResponsabile &egrave; il nuovo " . 
                    "Capo Team di '$oldName'",
                $cambiamento === (1 << 1) => 
                    "Successo: Il team '$oldName' &egrave; stato " .
                    "rinominato in $newName",
                $cambiamento === 3 => 
                    "Successo: Il team '$oldName' &egrave; stato " .
                    "rinominato in $newName e il nuovo Capo Team " .
                    "&egrave $newResponsabile"
            };

        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = ($e->getCode() == 1062) 
                ? "Esiste gi&agrave; un team con questi dati. " . 
                  "Prova un'altra sigla o un altro nome!" 
                : $e->getMessage();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $sm
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
        if (!isset($dati['sigla_team']) || empty($dati['sigla_team'])) {
            throw new Exception(
                "Attenzione: Team non riconosciuto!"
            );
        }
        $op = $dati['operazione'];
        $userResp = isset($dati['selezione_responsabile']) 
                  ? strtolower($dati['selezione_responsabile'])
                  : null;
        $siglaTeam = strtoupper($dati['sigla_team']);

        $tm = new TeamManager($user, $op, $siglaTeam);
        match ($op) {
            'crea' => $tm->crea($siglaTeam, $dati['nome_team'], $userResp),
            'elimina' => $tm->elimina($siglaTeam),
            'aggiorna' => $tm->aggiorna($siglaTeam, $dati['nome_team'], $userResp)
        };
    }

    
 }
