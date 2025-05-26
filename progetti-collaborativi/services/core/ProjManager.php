<?php

require_once __DIR__ . '/../avvio.php';
require_once __DIR__ . '/../models/Utente.php';
require_once __DIR__ . '/../models/Squadra.php';
require_once __DIR__ . '/../models/Progetto.php';
require_once __DIR__ . "/../models/abstracts/Sezione.php";
require_once __DIR__ . '/DBLogger.php';
require_once __DIR__ . '/UserManager.php';

/**
 * La classe ProjManager serve per gestire tutte le operazioni CRUD
 * eseguibili da un amministratore
 */

 final class ProjManager 
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
    private ?Progetto $proj;
    private ?Squadra $newTeamResp;
    private int $isAdmin = 0;
    private string $msg = "";
    private string $action;
    
    /**
     * Per poter gestire un progetto, è necessario riconoscere che
     * l'utente che compie l'azione sia admin, che l'azione sia riconosciuta
     * come valida e che i dati siano nel formato valido
     */
    public function __construct(
        Utente $io, string $action, ?string $teamResp, ?int $idProj
    ) {
        $this->mydb = (Database::getIstanza())->getDataBaseConnesso();
        $this->io = $io;
        $this->isAdmin = (int)$io->isAdmin();
        Risposta::set('isAdmin', $this->isAdmin);
        $suffisso = $_SESSION['chi']['suffisso'] ?? "o";
        $this->action = $action;
        $this->proj = ($idProj) ? $this->createOggProj($idProj) : null;
        $this->newTeamResp = ($teamResp) 
                           ? Squadra::caricaBySigla($teamResp) 
                           : null;

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

    private function createOggProj($id): Progetto {
        if ($id < 0) { 
            throw new Exception(
                "Errore: Qualcosa &egrave; andato storto nell'invio dei dati!"
            );
        }
        return Progetto::caricaById($id);
    }

    private function inviaRisposta() 
    {
        Risposta::set('messaggio', $this->msg);
        // Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    public function crea($nome, $descrizione, $scadenza) {
        # 0. assicurarsi di avere la data in formato datetime per mySQL
        $scadenza = date("Y-m-d H:i:s", strtotime($scadenza)); 
        $teamResp = ($this->newTeamResp) 
                  ? ($this->newTeamResp) 
                  : null;
        $siglaResp = ($teamResp) ? $teamResp->getSigla() : null;
        $nomeTeamResp = ($teamResp) ? $teamResp->getNome() : null;

        checkDatiInviati(
            checkElNames(1, 50, $nome),
            checkCharRange(0, 255, $descrizione),
            checkDateTime($scadenza, true)
            ($nomeTeamResp ? checkSiglaTeam($nomeTeamResp) : true)
        );
        
        try {
            $this->msg = "";
            $this->mydb->begin_transaction();

            # 1. Verifica esistenza del team resp e inscerisce il progetto
            $newProj = Progetto::crea(
                $nome, $descrizione, $scadenza, $teamResp
            );
            $id = $newProj->getId();
            $nome = $newProj->getNome();

            # 2. Crea il report in caso di successo
            DBLogger::progetto(
                $this->io->getEmail(), 'crea', $id, $teamResp
            );

            $this->mydb->commit();
            $this->msg = "Successo: Il Progetto '$nome' &egrave; "
                       . "stato creato!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = match ($e->getCode()) {
                1062 => "Esiste gi&agrave un progetto chiamato $nome!",
                1452 => "Team '$nomeTeamResp' non trovato",
                default => $e->getMessage()
            };
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $sm
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        $this->inviaRisposta();
    }

    
    public function elimina() 
    {
        try {
            $oldId = $this->proj->getId();
            $oldName = $this->proj->getNome();
            $this->mydb->begin_transaction();
            # 2. Prepara il report per l'eliminazione team
            DBLogger::progetto($this->io->getEmail(), "elimina", $oldId);

            # 3. Elimina il team selezionato
            $this->proj->elimina();
            
            $this->mydb->commit();
            $this->msg = "Successo: Il progetto '$oldName' &egrave; "
                       . "stato eliminato!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = ($e->getCode() == 1452)
                ? "Progetto da eliminare non trovato!" 
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

    public function aggiorna($newName, $newIntro, $newDeadline) {
        $newName = ucfirst($newName);
        $newDeadline = date("Y-m-d H:i:s", strtotime($newDeadline));
        $newTeam = ($this->newTeamResp) 
                 ? strtoupper($this->newTeamResp->getSigla())
                 : null;
        $oldName = $this->proj->getNome();
        $oldIntro = $this->proj->getDescrizione();
        $oldDeadline = $this->proj->getScadenza();
        $oldTeam = $this->proj->getTeamResp();
        checkDatiInviati(
            checkElNames(1, 50, $newName),
            checkCharRange(0, 255, $newIntro),
            checkDateTime($newDeadline, true),
            ($newTeam ? checkSiglaTeam($newTeam) : true)
        );
        
        try {
            
            $this->mydb->begin_transaction();

            # 1. verifica se ci sono cambiamenti
            if (
                $oldName === $newName && 
                $oldTeam === $newTeam &&
                $oldIntro === $newIntro && 
                $oldDeadline === $newDeadline
            ) {
                $this->mydb->rollback();
                $this->msg = "";
                $this->inviaRisposta();
                return;
            }

            # 2. Se ci sono, si verifica se cambiamento riguarda il responsabile
            $cambiamento = 0;
            if ($oldTeam !== $newTeam) {
                DBLogger::progetto(
                    $this->io->getEmail(), 'assegna',
                    $this->proj->getId(), $newTeam 
                );
                $cambiamento |= (1 << 0);
            }

            # 3. ma anche se riguarda il nome, la descrizione o la scadenza
            if (
                $oldName !== $newName || 
                $oldIntro !== $newIntro ||
                $oldDeadline !== $newDeadline
            ) {
                DBLogger::progetto(
                    $this->io->getEmail(), 'modifica', 
                    $this->proj->getId(), $newTeam 
                );
                $cambiamento |= (1 << 1);
            }

            $this->proj->aggiorna([
                'progetto' => $newName,
                'descrizione' => $newIntro,
                'team_responsabile' => $newTeam,
                'scadenza' => $newDeadline
            ]);

            $this->mydb->commit();
            $this->msg = match (true) {
                $cambiamento === (1 << 0) => 
                    "Successo: '$newTeam' &egrave; il nuovo " . 
                    "Team responsabile di '$newName'",
                $cambiamento === (1 << 1) => 
                    "Successo: Il progetto '$newName' &egrave; stato " .
                    "aggiornato correttamente",
                $cambiamento === 3 => 
                    "Successo: Il progetto '$newName' &egrave; stato " .
                    "aggiornato correttamente, e il nuovo Team Responsabile " . 
                    "&egrave; '$newTeam'"
            };

        } catch (mysqli_sql_exception $e) {
            $sm = match ($e->getCode()) {
                1062 => "Esiste gi&agrave un progetto chiamato $newNome!",
                1452 => "Team $oldName non trovato",
                default => $e->getMessage()
            };
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
        $teamResp = isset($dati['selezione_team']) 
                  ? $dati['selezione_team']
                  : null;
        $idProj = isset($dati['id_progetto']) 
                ? (int)(urlencode($dati['id_progetto'])) 
                : null;

        $pm = new ProjManager($user, $op, $teamResp, $idProj);
        match ($op) {
            'crea' => $pm->crea(
                $dati['nome_progetto'], 
                $dati['descrizione_progetto'], 
                $dati['scadenza_progetto'],
            ),
            'elimina' => $pm->elimina(),
            'aggiorna' => $pm->aggiorna(
                $dati['nome_progetto'], 
                $dati['descrizione_progetto'], 
                $dati['scadenza_progetto'],
            )
        };
    }
    
 }
