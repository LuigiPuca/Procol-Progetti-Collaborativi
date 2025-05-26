
<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve per definire le operazioni CRUD che riguardano 
 * le categorie visibili nella sezione Board del sito.
 */

final class Scheda extends Sezione {
    private ?string $operazione;

    private bool $opConsentita = false;
    private int $lvl = 0; //livello di accesso

    private ?int $idProj;
    private ?string $titolo;
    private ?string $hexColor;
    private ?string $teamProj;
    private ?string $categoria;
    private ?string $statoScheda;
    private ?string $uuidScheda;
    private ?string $uuidTarget;
    private ?string $orderBy;
    private ?string $descrizione;
    private ?string $uuidCommento;
    private ?string $incaricato;
    private ?string $inizioMandato;
    private ?string $fineMandato;
    private ?string $nuovoStato;
    private ?string $inizioScheda;
    private ?string $scadenzaScheda;
    private ?string $fineScheda;

    private function __construct(mysqli $db, Utente $user, ?array $datiRicevuti) 
    {
        parent::__construct($db, $user);

        if (empty($datiRicevuti) || !isset($datiRicevuti['operazione'])) {
            throw new Exception("Dati non ricevuti!");
        }
        $this->msg = "";
        $this->operazione = $datiRicevuti['operazione'];

        $this->idProj = array_key_exists('id_progetto', $datiRicevuti) 
            ? abs((int)$datiRicevuti['id_progetto']) : null;
        $this->teamProj = Permessi::seekTeamProj($this->idProj);
        $this->categoria = array_key_exists('categoria', $datiRicevuti) 
            ? $datiRicevuti['categoria'] : null;
        $this->uuidScheda = array_key_exists('uuid_scheda', $datiRicevuti) 
            ? strtolower($datiRicevuti['uuid_scheda']) : null;
        $this->uuidTarget= array_key_exists('uuid_scheda_target', $datiRicevuti)
            ? strtolower($datiRicevuti['uuid_scheda_target']) : null;
        $this->orderBy = (
            array_key_exists('order_by', $datiRicevuti) &&
            $datiRicevuti['order_by'] === 'DESC'
        ) ? 'DESC' : 'ASC';
        $this->descrizione = array_key_exists('descrizione', $datiRicevuti)
            ? trim(html_entity_decode($datiRicevuti('descrizione'))) : "";
        $this->uuidCommento = array_key_exists('uuid_commento', $datiRicevuti) 
            ? strtolower($datiRicevuti['uuid_commento']) : "";
        $this->incaricato = (
            array_key_exists('membro_assegnato', $datiRicevuti) && 
            $datiRicevuti['membro_assegnato']
        ) ? $datiRicevuti['membro_assegnato'] : null;
        $this->inizioMandato = (
            array_key_exists('inizio_incarico', $datiRicevuti) &&
            $this->incaricato
        ) ? str_replace("T", " ", $datiRicevuti['inizio_incarico']) : null;
        $this->fineMandato = (
            array_key_exists('fine_incarico', $datiRicevuti) &&
            $this->incaricato
        ) ? str_replace("T", " ", $datiRicevuti['fine_incarico']) : null;
        $this->nuovoStato = array_key_exists('stato_assegnato', $datiRicevuti)
            ? trim($datiRicevuti['stato_assegnato']) : "";
        $this->inizioScheda = (
            array_key_exists('inizio_scheda', $datiRicevuti)
        ) ? str_replace("T", " ", $datiRicevuti['inizio_scheda']) : null;
        $this->scadenzaScheda = (
            array_key_exists('scadenza_scheda', $datiRicevuti)
        ) ? str_replace("T", " ", $datiRicevuti['scadenza_scheda']) : null;
        $this->fineScheda = (
            array_key_exists('fine_scheda', $datiRicevuti) &&
            $this->categoria === "Completate"
        ) ? str_replace("T", " ", $datiRicevuti['fine_scheda']) : null;

        Risposta::set('operazione', $this->operazione);
        Risposta::set('accessoLivello', $this->lvl);
        Risposta::set('progetto_analizzato', $this->idProj);
        Risposta::set('stato_analizzato', $this->categoria);
        Risposta::set('post_analizzato', strtolower($this->uuidScheda));

        $this->checkDatiInviati(
            checkTitles(1, 20, '', $this->categoria), 
            checkUUIDs($this->uuidScheda)
        );
        
        Permessi::calcola(
            $this->user, $this->idProj, 
            $this->categoria, $this->uuidScheda
        );

        $permessi = Permessi::getAttivi();

        $is_admin = $this->user->isAdmin();
        $this->lvl = match (true) {
            (bool)($permessi & Permessi::DA_ADMIN) => 5,
            (bool)($permessi & Permessi::GESTIONE_CATEGORIA) => 4,
            (bool)($permessi & Permessi::CTRL_ATTIVITA) => 3,
            (bool)($permessi & Permessi::GESTIONE_ATTIVITA) => 2,
            (bool)($permessi & Permessi::COMMENTA_ATTIVITA) => 1,
            default => 0
        };

        Risposta::set('accessoLivello', $this->lvl);

        if ($this->lvl < 1) {
            throw new mysqli_sql_exception(
                "Errore: Accesso negato o operando su risorsa inesistente"
            );
        } 

        match ($this->operazione) {
            'elimina_scheda' => $this->rimuoviScheda(),
            'sposta_scheda_su', 'sposta_scheda_giu' => $this->spostaScheda(),
            'massimizza_scheda' => $this->massimizzaScheda(),
            'aggiungi-descrizione', 'modifica-descrizione' 
                => $this->setDescrizioneScheda(),
            'aggiungi-commento' => $this->commentaPost(),
            'elimina-commento' => $this->rimuoviCommento(),
            'modifica-commento' => $this->aggiornaCommento(),
            'rispondi-commento' => $this->replicaCommento(),
            'ottieni-membri' => $this->ottieniMembri(),
            'assegna-membro' => $this->assegnaMembro(),
            'ottieni-stati' => $this->ottieniStati(),
            'cambia-stato' => $this->cambiaStato(),
            'ottieni-progresso' => $this->ottieniProgresso(),
            'imposta-durata' => $this->impostaDurata(),
            'ottieni-report' => $this->ottieniReport(),
            default => throw new Exception(
                "Errore: Operazione $this->operazione non riconosciuta!"
            )
        };

        Risposta::set('messaggio', $this->msg);
        Risposta::set('dati', $this->dati);
        Risposta::jsonDaInviare();
    }

    private function checkDatiInviati(...$args) {
        $error = 
            "Errore: Si &egrave; verificato un problema nell'invio " . 
            "dei dati necessari per il completamento dall'azione!";
        if (!$this->idProj || !$this->teamProj) {
            throw new Exception($error);
        } 
        foreach ($args as $arg) {
            if (!$arg) {
                throw new Exception($error);
            }
        };
    }

    private function checkOpEseguibile($valida = false) {
        $error = "Non hai i permessi necessari per eseguire l'operazione!";
        if (!$valida) {
            throw new Exception($error);
        }
    }

    /**
     * Crea un resoconto dell'azione svolta sul DB.
     * 
     * @param string $operazione L'operazione che è stata svolta
     * @param string $categoria Su quale categoria è stata eseguita l'azione
     * @param string $composizione Tiene memoria di più info in caso di 
     *               eliminazione o aggiornamento futuro degli oggetti
     * @param string $suClasse Definisce su quale classe di oggetto (del DB) è 
     *               svolta l'operazione
     * @param array ...$args Altri campi in array da aggiungere come elementi di
     *              array associativo
     */
    private function logAzione(
        string $operazione, string $link, 
        string $categoria, string $composizione, 
        string $suClasse, array $args = []
    ): void {
        if (!in_array($suClasse, ['progetto', 'scheda'])) {
            throw new Exception(
                "Questa azione non pu&ograve; essere salvata su log"
            );
        }

        foreach ($args as $arg) {
            if (!is_array($arg)) {
                throw new InvalidArgumentException(
                    "Ogni argomento extra deve essere un array associativo."
                );
            }
        }

        $assoc = [
            "tipo_azione"   => ["?", "s", $suClasse],
            "attore"        => ["?", "s", $this->user->getEmail()], 
            "descrizione"   => ["?", "s", $operazione], 
            "link"          => ["?", "s", $link],
            "team"          => ["?", "s", $this->teamProj], 
            "progetto"      => ["?", "i", $this->idProj],
            "categoria"     => ["?", "s", $categoria], 
            "attore_era"    => ["?", "s", $this->user->getEmail()],
            "bersaglio_era" => ["?", "s", $composizione]
        ];

        $assoc = array_merge($assoc, $args);

        # 1. Campi (chiavi)
        $campi = implode(', ', array_keys($assoc));

        # 2. Placeholders (valori prima colonna)
        $holders = implode(', ', array_column($assoc, 0));

        # 3. Tipi di binding (valori seconda colonna)
        $types = implode('', array_column($assoc, 1));

        # 4. Parametri (valori terza colonna)
        $params = array_column($assoc, 2);

        $query = "INSERT INTO report ($campi) VALUES ($holders)";

        $stmt = Database::eseguiStmt($query, $types, ...$params);
        Database::liberaRisorsa($stmt);
    }

    /**
     * Calcola la posizione dell'ultima tupla di una tabella (l'ordine è 
     * stabilito da un campo 'ordine_$tabella'. Se nessuna tupla viene 
     * restituita il valore risulta essere -1.
     * 
     * @param string $tabella Il nome della tabella che contiene la tupla
     *               d'interesse
     * @param array $condizioni Le condizioni passate attraverso un array 
     *              associativo, le cui righe sono nella forma
     *              $campo => [$placeholder, $tipo_bind, $parametro_bind] 
     * @return int  L'ordine dello stato di indice più elevato che rispetta
     *              le condizioni definite (-1 se non c'è nessuna 
     *              corrispondenza)
     */
    private function checkMaxOrdine(
        string $tabella = "stati", 
        array $condizioni = []
    ): int {
        if (!count($condizioni)) {
            throw new Exception("Condizioni mancanti!");
        }
        
        $condizione = []; $types = []; $params = [];
        foreach ($condizioni as $key => $values) {
            $condizione[] = $key . " = " . $values[0];
            $types[] = $values[1];
            $params[] = $values[2];
        }

        $condizione = implode(' AND ', $condizione);
        $types = implode('', $types);
        
        $query = "
            SELECT COALESCE(MAX(ordine_$tabella), -1) AS max_ord 
            FROM $tabella 
            WHERE $condizione
        ";

        $result = Database::caricaDati($query, $types, ...$params);
        if ($row = $result->fetch_assoc()) {
            $result->free();
            return (int)($row['max_ord'] ?? -1);
        }
        $result->free();
        return -1;
    }

    private function getAutoreScheda(): ?string {
        $query = "
            SELECT COUNT(*) as totale, autore 
            FROM schede 
            WHERE uuid_scheda = UNHEX(?) AND id_progetto = ? AND stato = ?
        ";
        $result = Database::caricaDati(
            $query, "sii", 
            $this->uuidScheda, $this->idProj, $this->categoria
        );
        $row = $result->fetch_assoc();
        if (!$row || empty($row['totale']) || $row['totale'] == 0 ) {
            throw new mysqli_sql_exception(
                "Scheda non esistente oppure presente in una " . 
                "categoria non accessibile!"
            );
        } 
        return $row['autore'];
    }

    /**
     * Tenta l'avvio della procedura di ordinamento delle schede.
     */
    private function ordinaSchede(string $azioneWas): void {
        $query = "CALL OrdinaSchedeSelettivo(?, ?)";
        $stmt = null;
        try {
            $stmt = Database::eseguiStmt(
                $query, "is", $this->idProj, $this->categoria
            );
            Database::liberaRisorsa($stmt);
        } catch (Throwable $e) {
            $this->msg = "Attenzione: Riordinamento delle schede fallito! "
                       . "Tuttavia, $this->msg"; 
        }
    }

    private function rimuoviScheda() {
        $op = (ucwords(strtolower($this->categoria)) === "Eliminate") 
            ? "elimina" : "archivia";
        if (
            ($op === 'elimina' && $this->lvl < 4) ||
            ($op === 'archivia' && $this->lvl < 3)
        ) {
            $stringa = ($op === 'elimina') ? 'eliminarla' : "archiviarla";
            throw new mysqli_sql_exception(
                "Non hai accesso a questa scheda o " . 
                "non hai i permessi per $stringa!"
            );
        }

        try {
            $this->mydb->begin_transaction();

            # 1. Verifica l'autore della scheda in caso di esistenza di questa
            $autore = $this->getAutoreScheda();
            $param_scheda = strtolower($this->uuidScheda);
            $link = "board.html?proj=$this->idProj&post=$param_scheda";
            $composizione = "$autore-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda";
            
            # 2. Preparazione del resoconto in caso di successo
            $log_action = ($op === 'elimina') 
                ? 'Eliminazione Scheda' : 'Archiviazione Scheda';
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $autore]
            ];
            $this->logAzione(
                $log_action, $link, 
                $this->categoria, $composizione,
                'scheda', $campi
            );

            $this->msg = "Successo:";
            # 3. Eliminazione effettiva o Archiviazione della scheda
            if ($op === 'elimina') {
                Database::deleteTupla(
                    "schede", "uuid_scheda = UNHEX(?)", "s", $this->uuidScheda
                );
                $msg = 'Scheda eliminata definitivamente con successo!';
            } else {
                /** 
                 * Per archiviarla bisogna spostarla nell'ultima posizione 
                 * della categoria Eliminate
                 */
                $condizioni = [
                    "id_progetto" => ["?", "i", $this->idProj],
                    "stato" => ["?", "s", "Eliminate"]
                ];
                $new_ord = $this->checkMaxOrdine("schede", $condizioni) + 1;
                Database::updateTupla(
                    "schede", 
                    "stato = 'Eliminate', ordine_schede = ?",
                    "uuid_scheda = UNHEX(?)",
                    "is", $new_ord, $this->uuidScheda
                );
                $msg = 'Scheda archiviata con successo! Sar&agrave; solo '
                        . 'visibile a te e ai tuoi superiori effettivi.';
            }
            
            $this->mydb->commit();
            $this->msg = $msg;
            $stmt = $this->ordinaSchede($msg);
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }

    }

     private function compareAttivita(string $option) {
        $dati_temp = [];
        $query = "
            SELECT titolo, ordine_schede
            FROM schede
            WHERE id_progetto = ? AND stato = ? 
                AND (uuid_scheda = UNHEX(?) OR uuid_scheda = UNHEX(?)) 
            ORDER BY CASE 
                WHEN uuid_scheda = UNHEX(?) THEN 0 
                WHEN uuid_scheda = UNHEX(?) THEN 1 
                ELSE 2 
            END
        ";
        $result = Database::caricaDati(
            $query, 'isssss', $this->idProj, $this->categoria, 
            $this->uuidScheda, $this->uuidTarget, 
            $this->uuidScheda, $this->uuidTarget
        );
        while ($row = $result->fetch_assoc()) {
            $dati_temp[] = [
                'scheda' => $row['titolo'], 
                'ordine' => (int)$row['ordine_schede']
            ];
        }

        if (count($dati_temp) < 2) {
            throw new mysqli_sql_exception(
                "Una, o entrambe, delle schede in cui si sta cercando di " . 
                "operare potrebbe non esistere in questa categoria di progetto!"
            );
        } elseif (
            $option === "su" && 
            $dati_temp[0]['ordine'] <= $dati_temp[1]['ordine']
        ) {
            # sx: verifica se categoria non è di ordine inferiore al target
            $scheda = $dati_temp[0]['scheda'];
            $target = $dati_temp[1]['scheda'];
            throw new mysqli_sql_exception(
                "La scheda \"$scheda\" precede gi&agrave; \"$target\"!"
            );
        } elseif (
            $option === "giu" && 
            $dati_temp[0]['ordine'] >= $dati_temp[1]['ordine']
        ) {
            # dx: verifica se categoria non è di ordine superiore al target
            $scheda = $dati_temp[0]['scheda'];
            $target = $dati_temp[1]['scheda'];
            throw new mysqli_sql_exception(
                "La categoria \"$scheda\" succede gi&agrave; \"$target\"!"
            );
        }

        return $dati_temp;
    }

    private function updateTemporaneoAttivita($max_ord) {
        $query = "
            UPDATE schede 
            SET ordine_schede = ordine_schede + ? + 1000 
            WHERE id_progetto = ? AND stato = ? 
                AND (uuid_scheda = UNHEX(?) OR uuid_scheda = UNHEX(?))
        ";
        $stmt = Database::eseguiStmt(
            $query, "iisss", $max_ord, $this->idProj, 
            $this->categoria, $this->uuidScheda, $this->uuidTarget);
        Database::liberaRisorsa($stmt);
    }

    /**
     * Aggiorna la posizione della categoria passata con l'ordine della
     * categoria con cui sta per essere scambiata.
     * 
     * @param string $categoria1 La categoria a cui si vuole cambiare posizione
     * @param int $ordine2 La posizione in cui si vuole che sia spostata la
     *            categoria
     */
    private function updatePosizioneAttivita(
        string $attivita1, int $ordine2
    ) {
        $query = "
            UPDATE schede
            SET ordine_schede = ? 
            WHERE uuid_scheda = UNHEX(?)
        ";
        $stmt = Database::eseguiStmt(
            $query, "is", $ordine2, $attivita1
        );
        Database::liberaRisorsa($stmt);
    }


    private function spostaScheda() {
        $sub_op = explode("_", $this->operazione);
        $sub_op = $sub_op[2];
        $this->checkDatiInviati(checkUUIDs($this->uuidTarget));
        $this->opConsentita = !($this->lvl < 4);
        $this->checkOpEseguibile($this->opConsentita);

        $stmt = null;

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica se le scheda esistono e le compara di posizione 
            $dati_temp = $this->compareAttivita($sub_op);
            
            # 2. Trova la posizione della scheda di ordine più alto
            $condizioni = [
                "id_progetto" => ["?", "i", $this->idProj],
                "stato" => ["?", "s", $this->categoria]
            ];
            $max_ord = $this->checkMaxOrdine("schede", $condizioni);
        
            # 3. Aggiornamento temporaneo delle 2 schede della categoria
            $this->updateTemporaneoAttivita($max_ord);

            # 4. Aggiornamento (scambio) effettivo della 2 categorie/stati
            $this->updatePosizioneAttivita(
                $this->uuidScheda, $dati_temp[1]['ordine']
            ); 
            $this->updatePosizioneAttivita(
                $this->uuidTarget, $dati_temp[0]['ordine']
            );
            

            $this->mydb->commit();
            $stringa = $sub_op === 'su' ? 'prima di' : 'dopo di';
            $this->msg = "Scheda \"{$dati_temp[0]['scheda']}\" spostata "
                       . "$stringa {$dati_temp[1]['scheda']} con successo!";
            unset($dati_temp);
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
        
    }
    
    private function getInfoScheda(): array {
        $query = '
            SELECT s.id_progetto, s.stato, HEX(s.uuid_scheda) as uuid_scheda, 
                s.titolo, s.descrizione, s.creazione, s.scadenza, 
                s.autore, 
                a.nome AS nome_autore, a.cognome AS cognome_autore, 
                a.genere AS sesso_autore, a.ruolo AS ruolo_autore,
                i.incaricato, 
                r.nome AS nome_incaricato, r.cognome AS cognome_incaricato, 
                i.inizio_mandato, i.fine_mandato, 
                i.data_inizio, i.data_fine, i.ultima_modifica, 
                i.modificato_da, 
                e.nome AS nome_editor, e.cognome AS cognome_editor
            FROM schede s 
            JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda
            LEFT JOIN utenti a ON a.email = s.autore 
            LEFT JOIN utenti r ON r.email = i.incaricato 
            LEFT JOIN utenti e ON e.email = i.modificato_da 
            WHERE s.uuid_scheda = UNHEX(?) 
                AND s.id_progetto = ? 
                AND s.stato = ?
        ';
        $result = Database::caricaDati(
            $query, "ssi", $this->uuidScheda, 
            $this->idProj, $this->categoria
        );
        if ($result->num_rows !== 1) {
            throw new mysqli_sql_exception(
                "Scheda inesistente o inaccessibile!"
            );
        }
        $row = $result->fetch_assoc();

        # verifica che se l'autore é capo_team, lo sia effettivamente
        if ($row['ruolo_autore'] === 'capo_team') {
            $row['ruolo_autore'] = 
                $this->isSelectedUserLeader($row['ruolo_autore'])
                ? 'capo_team' : 'utente';
        }
        $row['commenti'] = [];
        return $row;

    }

    private function isSelectedUserLeader($autore) {
        $query = "
            SELECT COUNT(*) AS isLeader
            FROM utenti u 
            JOIN progetti p 
                ON u.team = p.team_responsabile 
                AND p.id_progetto = ? AND u.ruolo = 'capo_team' 
            WHERE u.email = ?
        ";
        $result = Database::caricaDati($query, "is", $this->idProj, $autore);
        $row = $result->fetch_assoc();
        if (!$row || !isset($row['isLeader'])) {
            return false;
        }

        return ((int)$row['isLeader'] > 0);
    }

    private function getInfoCommenti($scheda_overlay) {
        $query = "
            SELECT HEX(r.uuid_commento) AS uuid_commento, r.contenuto, 
                r.mittente, 
                m.nome AS nome_mittente, m.cognome AS cognome_mittente, 
                m.genere AS sesso_mittente, m.ruolo AS ruolo_mittente, 
                r.inviato, r.destinatario, 
                d.nome AS nome_destinatario, d.cognome AS cognome_destinatario, 
                r.modificato_da, r.modificato_il, r.uuid_in_risposta,
                e.nome AS nome_editore, e.cognome AS cognome_editore
            FROM commenti r 
            LEFT JOIN utenti m ON m.email = r.mittente 
            LEFT JOIN utenti d ON d.email = r.destinatario 
            LEFT JOIN utenti e ON e.email = r.modificato_da 
            WHERE uuid_scheda = UNHEX(?) 
            ORDER BY r.inviato $this->orderBy
        ";
        $result = Database::caricaDati($query, "s", $this->uuidScheda);
        while ($row = $result->fetch_assoc()) {
            $row['uuid_commento'] = strtolower($row['uuid_commento']);
            # verifica che se l'autore é capo_team, lo sia effettivamente
            if ($row['ruolo_mittente'] === 'capo_team') {
                $row['ruolo_mittente'] = 
                    $this->isSelectedUserLeader($row['ruolo_mittente'])
                    ? 'capo_team' : 'utente';
            }
            $row['is_modificabile'] = (
                !($this->lvl < 4) ||
                $row['mittente'] === $this->user->getEmail()
            ) ? 1 : 0;
            $scheda_overlay['commenti'][] = $row;
        }
        return $scheda_overlay;

    }

    private function massimizzaScheda() {
        try {

            $this->mydb->begin_transaction();

            # 1. Ottiene dati della scheda se esistente e accessibile
            $scheda_overlay = $this->getInfoScheda();
            
            # 2. Aggiunge dati dei commenti alla scheda
            $scheda_overlay = $this->getInfoCommenti($scheda_overlay);
            

            $this->mydb->commit();
            $this->msg = "";
            $this->dati['schedaOverlay'] = $scheda_overlay;
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function getInfoPost(): array {
        $query = "
            SELECT HEX(s.uuid_scheda) as uuid_scheda, 
                s.descrizione as descrizione_scheda, 
                s.autore as autore_scheda, i.ultima_modifica 
            FROM schede s 
            JOIN info_schede i ON s.uuid_scheda = i.uuid_scheda  
            WHERE s.uuid_scheda = UNHEX(?) 
                AND s.id_progetto = ? 
                AND s.stato = ?
        ";

        $result = Database::caricaDati(
            $query, "sis", $this->uuidScheda, $this->idProj, $this->categoria
        );
        if ($result->num_rows !== 1) {
            throw new mysqli_sql_exception(
                "Scheda inesistente o inaccessibile!"
            );
        }
        return $row = $result->fetch_assoc();


    }

    private function setDescrizioneScheda() {
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai accesso a questa scheda o non hai i permessi per " .
                "aggiungere una descrizione!"
            );
        }

        if ($descrizione === '') { $this->checkDatiInviati(false); }
        
        try {

            $this->mydb->begin_transaction();

            # 1. Ottiene dati del post se esistente e accessibile
            $post = $this->getInfoPost();
            $op = (
                $post['ultima_modifica'] === null && 
                $post['descrizione_scheda'] === ""
            ) ? "Aggiunta Descrizione Scheda" : "Modifica Descrizione Scheda";
            
            # 2. Aggiorna il post con i dati ricevuti dal client
            Database::updateTupla(
                "schede", "descrizione = ?", "uuid_scheda = UNHEX(?)",
                "ss", $this->descrizione, $this->uuidScheda
            );
            $azione = "Descrizione aggiunta con successo!";
            if ($op  === 'Modifica Descrizione Scheda') {
                $modifica_attuale = date('Y-m-d H:i:s');
                Database::updateTupla(
                    "info_schede", "ultima_modifica = ?, modificato_da = ?",
                    "uuid_scheda = UNHEX(?)", "sss", $modifica_attuale, 
                    $this->user->getEmail(), $this->uuidScheda
                );
                $azione = "Descrizione modificata con successo!";
            }

            # 3. Crea un report in caso di successo
            $param_scheda = strtolower($this->uuidScheda);
            $link = "board.html?proj=$this->idProj&post=$param_scheda";
            $composizione = "{$post['autore_scheda']}-$this->idProj-"
                          . "$this->categoria-$param_scheda";
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $post['autore_scheda']]
            ];

            $this->logAzione(
                $op, $link, $this->categoria, 
                $composizione, 'scheda', $campi
            );

            $this->mydb->commit();
            $this->msg = $azione;
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function getUUIDCommento($msg = "") {
        if (!$msg) {
            $msg = "Impossibile creare il commento. Riprovare, " 
                 . "e se il problema persiste contattarci.";
        }
        $query = "
            SELECT HEX(uuid_commento) as uuid_commento
            FROM commenti 
            WHERE uuid_scheda = UNHEX(?) AND contenuto = ?
        ";
        $result = Database::caricaDati(
            $query, "ss", $this->uuidScheda, $this->descrizione
        );
        if ($result->num_rows !== 1) {
            throw new mysqli_sql_exception($msg);
        }
        $row = $result->fetch_assoc();
        if (!$row || !isset($row['uuid_commento'])) {
            throw new mysqli_sql_exception("UUID commento non trovato.");
        }
        Database::liberaRisorsa($result);
        return $row['uuid_commento'];
    }

    private function commentaPost() {
        $contenuto_temporaneo = bin2hex(random_bytes(16));
        if ($this->lvl < 1) {
            throw new mysqli_sql_exception(
                "Non hai accesso a questa scheda o non hai i permessi " . 
                "per rispondere al post!"
            );
        }

        if ($descrizione === '') { $this->checkDatiInviati(false); }

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica l'autore del post a in caso di esistenza della scheda
            $autore_scheda = $this->getAutoreScheda();

            # 2. Creare il commento con il contenuto temporaneo unico
            Database::createTupla(
                "commenti", "uuid_scheda, contenuto, mittente", "UNHEX(?), ?, ?",
                "sis", $this->uuidScheda, $contenuto_temporaneo, 
                $this->user->getEmail()
            );

            # 3. Ricerca del commento creato
            $uuid_commento = $this->getUUIDCommento();

            # 4. Aggiornare il commento con il contenuto corretto
            Database::updateTupla(
                "commenti", "contenuto = ?", "uuid_commento = UNHEX(?)",
                "ss", $this->descrizione, $uuid_commento
            );

            $param_scheda = strtolower($this->uuidScheda);
            $frag_commento = strtolower($uuid_commento);
            $link = "board.html?proj=$this->idProj&post=$param_scheda"
                  . "#repl=$frag_commento";
            $composizione = "$autore_scheda-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda-$frag_commento";

            # 5. Crea un report in caso di successo
            
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $autore_scheda]
            ];

            $this->logAzione(
                "Creazione Commento", $link, $this->categoria, 
                $composizione, 'scheda', $campi
            );

            $this->mydb->commit();
            $this->msg = "Commento aggiunto con successo!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function isAutoreCommento(): bool {
        $query = "
            SELECT COUNT(*) as isAutore
            FROM schede s 
            JOIN commenti r ON r.uuid_scheda = s.uuid_scheda 
            WHERE s.id_progetto = ? AND s.stato = ? AND s.uuid_scheda = UNHEX(?)
                AND r.uuid_commento = UNHEX(?) AND r.mittente = ?
        ";
        $result = Database::caricaDati(
            $query, "issss", $this->idProj, $this->categoria, 
            $this->uuidScheda, $this->uuidCommento, $this->user->getEmail()
        );
        $row = $result->fetch_assoc();
        if (!$row || !isset($row['isAutore'])) {
            return false;
        }

        return ((int)$row['isAutore'] > 0);
    }

    private function getAutoreCommento(): ?string {
        $query = "
            SELECT mittente
            FROM commenti 
            WHERE uuid_scheda = UNHEX(?) AND uuid_progetto = UNHEX(?)
        ";
        $result = Database::caricaDati(
            $query, "ss", $this->uuidScheda, $this->uuidCommento
        );
        if ($result->num_rows !== 1) {
            throw new mysqli_sql_exception("Commento non esistente!");
        }
        $row = $result->fetch_assoc();
        if (!$row || !isset($row['mittente'])) {
            return null;
        }
        return $row['mittente'];
    }

    private function rimuoviCommento() {
        $this->checkDatiInviati(checkUUIDs($this->schedaCommento));
        # Se non si è almeno capo team, verificare di essere autori commento
        if ($this->lvl < 4 && !$this->isAutoreCommento()) {
            throw new mysqli_sql_exception(
                "Il commento potrebbe non esistere o non hai i permessi " . 
                "necessari per eliminarlo"
            );
        }

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica l'autore del commento in caso esista
            $mittente = $this->getAutoreCommento();


            $param_scheda = strtolower($this->uuidScheda);
            $frag_commento = strtolower($this->uuidCommento);
            $link = "board.html?proj=$this->idProj&post=$param_scheda"
                  . "#repl=$frag_commento";
            $composizione = "$mittente-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda-$frag_commento";

            # 2. Creazione del report 
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $mittente]
            ];

            $this->logAzione(
                "Eliminazione Commento", $link, $this->categoria, 
                $composizione, 'scheda', $campi
            );

            # 3. Elimina il commento in modo effettivo
            Database::deleteTupla(
                "commenti", "uuid_commento = UNHEX(?)", 
                "s", $this->uuidCommento
            );

            $this->mydb->commit();
            $this->msg = "Commento eliminato con successo!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function aggiornaCommento() {
        $this->checkDatiInviati(checkUUIDs($this->schedaCommento));
        # Se non si è almeno capo team, verificare di essere autori commento
        if ($this->lvl < 4 && !$this->isAutoreCommento()) {
            throw new mysqli_sql_exception(
                "Il commento potrebbe non esistere o non hai i permessi " . 
                "necessari per modificarlo"
            );
        }

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica l'autore del commento in caso esista
            $mittente = $this->getAutoreCommento();


            $param_scheda = strtolower($this->uuidScheda);
            $frag_commento = strtolower($this->uuidCommento);
            $link = "board.html?proj=$this->idProj&post=$param_scheda"
                  . "#repl=$frag_commento";
            $composizione = "$mittente-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda-$frag_commento";


            # 3. Modifica il commento in modo effettivo
            $modifica_attuale = date('Y-m-d H:i:s');
            Database::updateTuplaTupla(
                "commenti", "contenuto = ?, modificato_da = ?, 
                modificato_il = ?", "uuid_commento = UNHEX(?)", 
                "ss", $this->descrizione, $this->user->getEmail(), 
                $modifica_attuale, $this->uuidCommento
            );

            # 3. Creazione del report in caso di successo
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $mittente]
            ];

            $this->logAzione(
                "Modifica Commento", $link, $this->categoria, 
                $composizione, 'scheda', $campi
            );

            $this->mydb->commit();
            $this->msg = "Commento modificato con successo!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function replicaCommento() {
        $contenuto_temporaneo = bin2hex(random_bytes(16));
        if ($this->lvl < 1) {
            throw new mysqli_sql_exception(
                "Non hai accesso a questa scheda o non hai i permessi " . 
                "per rispondere al post!"
            );
        }

        if ($descrizione === '') { $this->checkDatiInviati(false); }

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica l'autore del post a in caso di esistenza della scheda
            $mittente = $this->getAutoreCommento();

            if (!$mittente) {
                throw new mysqli_sql_exception(
                    "Impossibile rispondere a questo commento!"
                );
            }

            # 2. Creare il commento con il contenuto temporaneo unico
            Database::createTupla(
                "commenti", "uuid_scheda, contenuto, mittente " . 
                "destinatario, uuid_in_risposta", "UNHEX(?), ?, ?, ?, ?",
                "sssss", $this->uuidScheda, $contenuto_temporaneo, 
                $this->user->getEmail(), $mittente, $this->uuidCommento
            );

            # 3. Ricerca del commento creato
            $err = "Impossibile rispondere. Commento non esistente!";
            $uuid_risposta = $this->getUUIDCommento($err);

            # 4. Aggiornare il commento con il contenuto corretto
            Database::updateTupla(
                "commenti", "contenuto = ?", "uuid_commento = UNHEX(?)",
                "ss", $this->descrizione, $uuid_risposta
            );

            $param_scheda = strtolower($this->uuidScheda);
            $frag_commento = strtolower($uuid_risposta);
            $link = "board.html?proj=$this->idProj&post=$param_scheda"
                  . "#repl=$frag_commento";
            $composizione = "$mittente-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda-$frag_commento";

            # 5. Crea un report in caso di successo
            
            $campi = [
                "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                "utente" => ["?", "s", $mittente]
            ];

            $this->logAzione(
                "Risposta Commento", $link, $this->categoria, 
                $composizione, 'scheda', $campi
            );

            $this->mydb->commit();
            $this->msg = "Commento aggiunto con successo!";
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }


    private function pushMembriDelTeam():void {
        $query = "
            SELECT u.cognome AS cognome, u.nome AS nome, u.genere AS genere,
                u.email AS email, 
                CASE 
                    WHEN u.email = t.responsabile THEN TRUE 
                    ELSE FALSE 
                END AS isLeader 
            FROM utenti u 
            INNER JOIN team t ON u.team = t.sigla 
            WHERE u.team = ? 
            ORDER BY isLeader ASC, u.cognome, u.nome;";
        $result = Database::caricaDati($query, "s", $this->teamProj);
        if ($result->num_rows > 0) {
            $this->fetchByResult($result, 'membri', true);
        }
        Database::liberaRisorsa($result);
    }

    private function pushDettagliResponsabile(): void {
        $query = "
            SELECT incaricato, inizio_mandato, fine_mandato 
            FROM info_schede 
            WHERE uuid_scheda = UNHEX(?)
        ";
        $result = Database::caricaDati($query, "s", $this->uuidScheda);
        $row = $result->fetch_assoc();
        Database::liberaRisorsa($result);
        if (!$row) { return; }
        $this->dati = array_merge($this->dati, $row);
    }

    private function ottieniMembri() {
        $this->msg = "";
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per assegnare questa scheda!"
            );
        }

        # Ottiene i membri del team e li pusha nei dati da mandata
        $this->pushMembriDelTeam();

        # Ottiene i dettagli dell'utente attualmente assegnato alla scheda
        $this->pushDettagliResponsabile();
    }

    private function getInfoScheda2():array {
        $query = "
            SELECT incaricato AS inc, 
                inizio_mandato AS inizio,
                fine_mandato AS fine
            FROM info_schede 
            WHERE uuid_scheda = UNHEX(?)
        ";
        $result = Database::caricaDati($query, "s", $this->uuidScheda);
        if ($result->num_rows !== 1) {
            return [
                'inc' => null, 
                'inizio' => null,
                'fine' => null,
            ];
        }
        $row = $result->fetch_assoc();
        Database::liberaRisorsa($result);
        return $row;        
    }

    private function assegnaMembro() {
        $this->msg = "";
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per assegnare questa scheda!"
            );
        }
        if ($this->incaricato) {
            $this->checkDatiInviati(
                checkEmail($this->incaricato),
                checkDateTime($this->inizioMandato, $this->fineMandato, true)
            );
            $fineM = (new DateTime($this->fineMandato))->getTimestamp();
            $inizioM = (new DateTime($this->inizioMandato))->getTimestamp();
            if ($fineM - $inizioM < 60) {
                throw new Exception(
                    "La data di fine mandato deve essere pi&ugrave; grande " . 
                    "di quella di inizio mandato di almeno un minuto!"
                );
            }
        }

        try {

            $this->mydb->begin_transaction();

            # 1. Ottiene dati sull'incaricato della scheda
            [
                'inc' => $old_inc, 'inizio' => $old_inizio, 'fine' => $old_fine
            ] = $this->getInfoScheda2();

            # 2. Aggiorna l'incaricato della scheda
            Database::updateTupla(
                "info_schede", 
                "incaricato = ?, inizio_mandato = ?, fine_mandato = ?",
                "uuid_scheda = UNHEX(?)", "ssss", 
                $this->incaricato, $this->inizioMandato, 
                $this->fineMandato, $this->uuidScheda
            );

            $param_scheda = strtolower($this->uuidScheda);
            $link = "board.html?proj=$this->idProj&post=$param_scheda";
            
            $op = ""; $composizione = ""; // variabili per il report
            $composizione = "$this->incaricato-$this->teamProj-$this->idProj"
                          . "-$this->categoria-$param_scheda";

            # 5. Crea un report in caso di successo (verificando condizioni)
            if (
                $old_inc && $old_inc === $this->incaricato &&
                ($old_inizio !== $this->inizioMandato || 
                 $old_fine !== $this->fineMandato)
            ) {
                $op = "Riassegnazione Scheda";
                $composizione = "$old_inc-$this->teamProj-$this->idProj"
                              . "-$this->categoria-$param_scheda";
                $msg = "Successo: Aggiornato l'incarico alla scheda a "
                     . $this->incaricato 
                     . " (dal $this->inizioMandato al $this->fineMandato)";
            } else if ($old_inc && !$this->incaricato) {
                $op = "Revocazione Scheda";
                $composizione = "$old_inc-$this->teamProj-$this->idProj"
                              . "-$this->categoria-$param_scheda";
                $msg =  "Successo: $old_inc sollevato dall'incarico";
            } else if (!$old_inc && $this->incaricato) {
                $op = "Assegnazione Scheda";
                $composizione = "$this->incaricato-$this->teamProj"
                              . "-$this->idProj-$this->categoria-$param_scheda";
                $msg = "Successo: Incarico alla scheda assegnato a "
                     . $this->incaricato 
                     . " (dal $this->inizioMandato al $this->fineMandato)";
            } else if ($old_inc && $old_inc !== $this->incaricato) {
                # Deassegnazione del vecchio incaricato
                $op = "Revocazione Scheda";
                $composizione = "$old_inc-$this->teamProj-$this->idProj"
                              . "-$this->categoria-$param_scheda";

                $campi = [
                    "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                    "utente" => ["?", "s", $old_inc]
                ];

                $this->logAzione(
                    $op, $link, $this->categoria, 
                    $composizione, 'scheda', $campi
                );

                $op = "Assegnazione Scheda";
                $composizione = "$this->incaricato-$this->teamProj"
                              . "-$this->idProj-$this->categoria-$param_scheda";
                $msg = "Successo: Incarico alla scheda passato da "
                     . "$old_inc a $this->incaricato"
                     . "(dal $this->inizioMandato al $this->fineMandato)";

            }
            if ($op) {
                $bersaglio = $this->incaricato ? $this->incaricato : $old_inc;

                $campi = [
                    "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                    "utente" => ["?", "s", $bersaglio]
                ];

                $this->logAzione(
                    $op, $link, $this->categoria, 
                    $composizione, 'scheda', $campi
                );
            } else {
                $msg = "";
            }
            
            $this->mydb->commit();
            $this->msg = $msg;
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function getListaStati(): array {
        $query = "
            SELECT stato, colore_hex 
            FROM stati 
            WHERE id_progetto = ? AND stato != 'Eliminate'
        ";
        if ($this->lvl < 4) {
            $query .= " AND visibile = 1";
        }
        $query .= " ORDER BY ordine_stati";
        $result = Database::caricaDati($query, "i", $this->idProj);
        $result_rows = []; $result_rows['stati'] = []; 
        $result_rows['stato_attuale'] = $this->categoria;
        if ($result->num_rows === 0) {
            return $result_rows;
        }
        while ($row = $result->fetch_assoc()) {
            /**
             * Verifico ad ogni fetch se lo stato corrente della scheda 
             * corrisponde a uno di questi
             */
            $bool = ($this->categoria === $row['stato']) ? "--selected" : "";
            $row['isSelezionato'] = $bool; // lo aggiungo alla riga
            $result_rows['stati'][] = $row;
        }
        Database::liberaRisorsa($result);
        return $result_rows;
    }

    private function ottieniStati() {
        $this->msg = "";
        if ($this->lvl < 2) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per cambiare stato alla scheda!"
            );
        }
        # Restituisce gli stati assegnabili, appartenenti al progetto
        $list = $this->getListaStati();
        $this->dati = array_merge($this->dati, $list);
    }

    private function getOldStato($hasCheckVisibilita = false): array {
        $query = "
            SELECT s.stato, s.autore 
            FROM schede s 
            RIGHT JOIN stati c 
                ON s.stato = c.stato 
                AND s.id_progetto = c.id_progetto   
                WHERE s.uuid_scheda = UNHEX(?) AND s.id_progetto = ?
        ";
        if ($hasCheckVisibilita === true) {
            $query .= " AND visibile = 1";
        }
        $result = Database::caricaDati(
            $query, "si", $this->uuidScheda, $this->idProj
        );
        if ($result->num_rows !== 1) {
            throw new mysqli_sql_exception(
                "Categoria attuale inesistente o inaccessibile"
            );
        }
        $row = $result->fetch_assoc();
        Database::liberaRisorsa($result);
        return $row;
        
    }

     private function checkNewStato($hasCheckVisibilita = false): void {
        $condizione = "id_progetto = ? AND stato = ?";
        if ($hasCheckVisibilita === true) {
            $condizione .= " AND visibile = 1";
        }
        $count = Database::contaDa(
            'stati', $condizione, "is", $this->idProj, $this->nuovoStato 
        );
        if ($count !== 1) {
            throw new mysqli_sql_exception(
                "Categoria bersaglio inesistente o inaccessibile"
            );
        }
    }
    private function cambiaStato() {
        $this->msg = "";
        if ($this->lvl < 2) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per cambiare stato alla scheda!"
            );
        }
        $this->checkDatiInviati(
            checkTitles(1, 20, false, '', $this->nuovoStato)
        );

        $conCheck = $this->lvl < 4;

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica esistenza e visibilità dello stato di partenza
            ['stato' => $old_stato, 'autore' => $autore] = 
                $this->getOldStato($conCheck);

            # 2. Idem per lo stato di arrivo
                $this->checkNewStato($conCheck);

            $msg = "";
            # 3. Verifica se coincidono vecchio e nuovo stato
            if ($this->nuovoStato !== $old_stato) {
                # 4. Aggiorna l'ordine delle schede nella nuova categoria
                $condizioni = [
                    "id_progetto" => ["?", "i", $this->idProj],
                    "stato" => ["?", "s", $this->categoria]
                ];
                $new_ord = $this->checkMaxOrdine("schede", $condizioni);

                Database::updateTupla(
                    "schede", "stato = ?, ordine_schede = ?",
                    "id_progetto = ? AND uuid_scheda = UNHEX(?)",
                    "siis", $this->nuovoStato, $new_ord, 
                    $this->idProj, $this->uuidScheda
                );

                
                # 5. se nuovoStato è "Completate" aggiunge data completamento
                $now = $this->nuovoStato === "Completate" 
                    ? $now = date('Y-m-d H:i:s') : NULL;

                Database::updateTupla(
                    "info_schede", "data_fine = ?", "uuid_scheda = UNHEX(?)",
                    "ss", $now, $this->uuidScheda
                );
                
                # 6. Report, in caso di successo
                $param_scheda = strtolower($this->uuidScheda);
                $link = "board.html?proj=$this->idProj&post=$param_scheda";
                $composizione = "$autore-$this->teamProj-$this->idProj"
                              . "-$this->categoria-$param_scheda";
                $campi = [
                    "scheda" => ["UNHEX(?)", "s", $this->uuidScheda],
                    "utente" => ["?", "s", $autore]
                ];

                $this->logAzione(
                    'Cambiamento Stato', $link, $this->categoria, 
                    $composizione, 'scheda', $campi
                );

                $msg = "Successo: Scheda passata da $old_stato a "
                     . $this->nuovoStato;

            }
            $this->mydb->commit();
            $this->msg = $msg;
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }

    }

    private function ottieniProgresso() {
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per programmare questa scheda!"
            );
        }

        $query = "
            SELECT i.data_inizio as inizio, s.scadenza, 
                i.data_fine as fine, s.stato 
            FROM info_schede i 
            JOIN schede s ON s.uuid_scheda = i.uuid_scheda 
            WHERE i.uuid_scheda = UNHEX(?)
        ";

        $result = Database::caricaDati($query, "s", $this->uuidScheda);
        $row = $result->fetch_assoc();
        if (!$row) {
            throw new mysqli_sql_exception(
                "Impossibile recuperare i dati di progresso della scheda"
            );
        }
        $this->dati = array_merge($this->dati, $row);
    }

     private function getInfoScheda3(): array {
        $query = "
            SELECT i.data_inizio, s.scadenza, i.data_fine
            FROM info_schede i 
            JOIN schede s ON s.uuid_scheda = i.uuid_scheda 
            WHERE i.uuid_scheda = UNHEX(?) and s.stato = ?
        ";
        $result = Database::caricaDati(
            $query, "ss", $this->uuidScheda, $this->categoria
        );
        $row = $result->fetch_assoc();
        if (!$row) {
            throw new mysqli_sql_exception(
                "Verifica scheda fallita. Riprovare."
            );
        }
        Database::liberaRisorsa($result);
        return $row;        
    }

    private function setDurata() {
        Database::updateTupla(
            "schede", "scadenza = ?", "uuid_scheda = UNHEX(?)",
            "ss", $this->scadenzaScheda, $this->uuidScheda
        );
        Database::updateTupla(
            "info_schede", "data_inizio = ?, data_fine = ?", 
            "uuid_scheda = UNHEX(?)", "sss", $this->inizioScheda, 
            $this->fineScheda, $this->uuidScheda
        );                 
    }

    private function impostaDurata() {
        $this->msg = "";
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai i permessi necessari per programmare questa scheda!"
            );
        }
        if (!$this->inizioScheda) {
            throw new Exception("Data di Avvio non rilevata correttamente!");
        }
        $this->checkDatiInviati(
            checkDateTime($this->inizioScheda, true) ||
            ($this->scadenzaScheda && 
            checkDateTime($this->scadenzaScheda, true)) ||
            ($this->fineScheda && checkDateTime($this->fineScheda, true))
        );
        if ($this->scadenzaScheda) {
            if (
                (new DateTime($this->scadenzaScheda))->getTimestamp() - 
                (new DateTime($this->inizioScheda))->getTimestamp() < 60
            ) { 
                throw new mysqli_sql_exception(
                    "La data di scadenza scheda deve essere pi&ugrave; " . 
                    "grande di quella di avvio di almeno un minuto!"
                );
            }
        }
        if ($this->fineScheda) {
            if (
                (new DateTime($this->fineScheda))->getTimestamp() - 
                (new DateTime($this->inizioScheda))->getTimestamp() < 60
            ) {
                throw new mysqli_sql_exception(
                    "La data di fine scheda deve essere pi&ugrave; " . 
                    "grande di quella di avvio di almeno un minuto!"
                );
            }
        }

        try {

            $this->mydb->begin_transaction();

            # 1. Ottiene info delle scheda
            $old = $this->getInfoScheda3();
            if (
                $old['data_inizio'] !== $this->inizioScheda ||
                $old['data_fine'] !== $this->fineScheda ||
                $old['scadenza'] !== $this->scadenzaScheda
            ) {
                # 2. Imposta durata in modo effettivo
                Database::updateTupla(
                    "schede", "scadenza = ?", "uuid_scheda = UNHEX(?)", "ss", 
                    $this->scadenzaScheda, $this->uuidScheda
                );
                Database::updateTupla(
                    "info_schede", "data_inizio = ?, data_fine = ?", 
                    "uuid_scheda = UNHEX(?)", "sss", $this->inizioScheda,
                    $this->fineScheda, $this->uuidScheda
                );
                $msg = "Successo: Scheda riprogrammata con successo";
            }
    
            $this->mydb->commit();
            $this->msg = $msg;
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            throw new mysqli_sql_exception(
                "Errore Transazione: " . $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }

    }

    private function getReports(): array {
        $query = "
            SELECT HEX(r.uuid_report) as uuid_report, r.`timestamp`, 
                r.attore, r.descrizione, r.link, r.utente as bersaglio, 
                r.team as team_responsabile, r.categoria as stato, 
                r.attore_era, r.bersaglio_era, c.colore_hex 
            FROM report r 
            LEFT JOIN stati c 
                ON r.progetto = c.id_progetto 
                AND c.stato = r.categoria 
            WHERE r.team = ? AND r.progetto = ? AND r.scheda = UNHEX(?) 
            ORDER BY r.timestamp DESC LIMIT 50
        ";
        $result = Database::caricaDati(
            $query, "sis", $this->teamProj, $this->idProj, $this->uuidScheda
        );
        $array_results = [];
        while ($row = $result->fetch_assoc()) {
            $row['uuid_report'] = strtolower($row['uuid_report']);
            $array_results[] = $row;
        }
        return $array_results;
    }

    private function ottieniReport() {
        $this->msg = "";
        if ($this->lvl < 3) {
            throw new mysqli_sql_exception(
                "Non hai i permessi per visualizzare i report di questa scheda!"
            );
        }

        $this->dati['reports'] = $this->getReports();
        $this->dati['mia_email'] = $this->user->getEmail();
        $this->msg = "";

        
    }

}

?>