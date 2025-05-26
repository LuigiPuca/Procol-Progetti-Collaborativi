
<?php
require_once __DIR__ . "/abstracts/Sezione.php";

/**
 * Estende Sezione, e serve per definire le operazioni CRUD che riguardano 
 * le categorie visibili nella sezione Board del sito.
 */

final class Categoria extends Sezione {
    private ?string $operazione;

    private bool $opConsentita = false;

    private ?int $idProj;
    private ?string $titolo;
    private ?string $hexColor;
    private ?string $teamProj;
    private ?string $categoria;
    private ?string $categoriaTarget;
    private ?string $statoScheda;
    

    private function __construct(mysqli $db, Utente $user, ?array $datiRicevuti)
    {
        parent::__construct($db, $user);

        if (empty($datiRicevuti) || !isset($datiRicevuti['operazione'])) {
            throw new Exception("Dati non ricevuti!");
        }
        $this->msg = "";
        $this->operazione = $datiRicevuti['operazione'];

        $this->idProj = array_key_exists('board_id', $datiRicevuti) 
            ? abs((int)$datiRicevuti['board_id']) : null; 
        $this->idProj = $this->idProj 
            ?: (array_key_exists('id_progetto', $datiRicevuti)
            ? abs((int)$datiRicevuti['id_progetto']) : null);
        $this->titolo = array_key_exists('titolo', $datiRicevuti) 
            ? ucwords($datiRicevuti['titolo']) : null;
        $this->hexColor = array_key_exists('hex_color', $datiRicevuti) 
            ? strtoupper($datiRicevuti['hex_color']) : null;
        $this->categoria = array_key_exists('categoria', $datiRicevuti) 
            ? $datiRicevuti['categoria'] : null;
        $this->categoriaTarget = 
            array_key_exists('categoriaTarget', $datiRicevuti) 
            ? $datiRicevuti['categoriaTarget'] : null;
        $this->statoScheda = array_key_exists('category_name', $datiRicevuti) 
            ? ucwords($datiRicevuti['category_name']) : null;
        $this->teamProj = Permessi::seekTeamProj($this->idProj);


        $is_admin = $this->user->isAdmin();
        Risposta::set('isAdmin', (int)$is_admin);

        if (!$is_admin && $this->teamProj !== $this->user->getTeam()) {
            throw new Exception("Errore: Non puoi operare in questo progetto");
        } 

        match ($this->operazione) {
            'crea_categoria' => $this->creaCategoria(),
            'elimina_categoria' => $this->rimuoviCategoria(),
            'mostra_categoria', "nascondi_categoria" 
                => $this->toggleVisibilitaCategoria(),
            'sposta_categoria_sinistra', 'sposta_categoria_destra'
                => $this->spostaCategoria(),
            'crea_scheda' => $this->creaScheda(),
            default => throw new Exception(
                "Errore: Operazione non riconosciuta!"
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
     *              le condizioni definite (-1 se non c'è nessuna corrispondenza)
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

    /**
     * Tenta l'avvio della procedura di ordinamento delle categorie.
     */
    private function ordinaStati(string $titolo, string $azioneWas): void {
        $query = "CALL OrdinaStatiSelettivo(?)";
        $stmt = null;
        try {
            $stmt = Database::eseguiStmt($query, "i", $this->idProj);
            Database::liberaRisorsa($stmt);
        } catch (Throwable $e) {
            $this->msg = "Attenzione: Categoria \"$titolo\" $azioneWas con " 
                       . "successo, ma riordinamento delle categorie fallito!";
        }
    }

    /**
     * Tenta l'avvio della procedura di ordinamento delle categorie.
     */
    private function ordinaSchede(string $titolo, string $azioneWas): void {
        $query = "CALL OrdinaSchedeSelettivo(?, ?)";
        $stmt = null;
        
        try {
            $stmt = Database::eseguiStmt(
                $query, "is", $this->idProj, $this->statoScheda
            );
            Database::liberaRisorsa($stmt);
        } catch (Throwable $e) {
            $this->msg = "Attenzione: Scheda \"$titolo\" $azioneWas con "
                       . "successo, ma riordinamento delle schede fallito!";
        }
    }

    private function creaCategoria() {
        # Validazione dei dati
        $this->checkDatiInviati(
            checkTitles(1, 20, false, '', $this->titolo), 
            checkHexColor($this->hexColor)
        );
        Permessi::calcola($this->user, $this->idProj, $this->titolo);
        $this->opConsentita = 
            Permessi::getAttivi() & Permessi::CREA_CATEGORIA;
        $this->checkOpEseguibile($this->opConsentita);
        $link = "board.html?proj=$this->idProj";
        $composizione = "No Utente-$this->teamProj-$this->idProj-$this->titolo";
        $stmt = null;
 
        try {

            $this->mydb->begin_transaction();

            # 1. Report sulla creazione dello stato
            $this->logAzione(
                'Creazione Categoria', $link, 
                $this->titolo, $composizione, 
                'progetto'
            );

            # 2. Calcola ordine del nuovo stato
            $condizioni = ["id_progetto" => ["?", "i", $this->idProj]];
            $ordine = $this->checkMaxOrdine('stati', $condizioni) + 1;

            # 3. Creazione effettiva del nuovo stato
            $this->newStato($ordine);

            $this->mydb->commit();
            $this->msg = "Categoria \"$this->titolo\" creata con successo!";
            $this->ordinaStati($this->titolo, "creata");
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = "Errore Transazione: ";
            $sm .= !($e->getCode() === 1062) 
                ? $e->getMessage() 
                : match (true) {
                    strpos($e->getMessage(), 'PRIMARY') !== false => 
                        "In questo progetto esiste gi&agrave; una " . 
                        " categoria chiamata \"$this->titolo\"!",
                    strpos($e->getMessage(), 'id_progetto') !== false =>
                        "Errore Transazione: Si &egrave; verificato un " . 
                        "errore con la creazione della categoria " . "
                        \"$this->titolo\"! Riprova perfavore.",
                    default => 
                        "trovato un duplicato non specificato: " 
                        . $e->getMessage() . "."
                };
            throw new Exception($sm);
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    /**
     * Crea la categoria in modo effettivo, nella posizione stabilita.
     * 
     * @param int $ordine La posizione di creazione della categoria
     * @param string $colore Il colore che assumerà la categoria
     */
    private function newStato($new_ord): void {
        $query = "
            INSERT INTO stati (
                id_progetto, stato, colore_hex, ordine_stati, visibile
            ) VALUES (?, ?, ?, ?, 1)
        ";
        $params = [$this->idProj, $this->titolo, $this->hexColor, $new_ord];
        $stmt = Database::eseguiStmt($query, "issi", ...$params);
        Database::liberaRisorsa($stmt);
    }

    private function rimuoviCategoria() {
        $this->checkDatiInviati($this->categoria);
        Permessi::calcola($this->user, $this->idProj, $this->categoria);
        $this->opConsentita = 
            Permessi::getAttivi() & Permessi::GESTIONE_CATEGORIA;
        $this->checkOpEseguibile($this->opConsentita);
        $link = "board.html?proj=$this->idProj";
        $composizione = "No Utente-$this->team-$this->idProj-$this->categoria";
        $stmt = null; 

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica esistenza della categoria   
            if ($this->statoExists()) {
                throw new mysqli_sql_exception(
                    "La categoria \"$this->categoria\" non esiste in questo " . 
                    "progetto, oppure &egrave; gi&agrave; stata eliminata!"
                );
            }

            # 2. Preparazione del resoconto in caso di successo
            $this->logAzione(
                'Eliminazione Categoria', $link, 
                $this->categoria, $composizione,
                'progetto'
            );

            # 3. Eliminazione effettiva della categoria/stato
            Database::deleteTupla(
                "stati", "id_progetto = ? AND stato = ?", "is", 
                $this->idProj, $this->categoria
            );

            $this->mydb->commit();
            $this->msg = "Categoria \"$this->categoria\" eliminata con "
                       . "successo!";
            $stmt = $this->ordinaStati($this->categoria, "eliminata");
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

    private function statoExists(): int {
        return Database::contaDa(
            "stati", "id_progetto = ? AND stato = ?", true, "is",
            $this->idProj, $this->categoria
        );
    }

    private function toggleVisibilitaCategoria() {
        if (!in_array(
            $this->operazione, ['mostra_categoria', 'nascondi_categoria']
        )) {
            throw new Exception("Operazione non riconosciuta!");
        }

        if (preg_match("/^eliminate$/i", $this->categoria))  {
            throw new mysqli_sql_exception("
                La categoria \"Eliminate\" non pu&ograve; " . 
                "essere impostata come visibile!"
            );
        }
    
        $this->checkDatiInviati($this->categoria);
        Permessi::calcola($this->user, $this->idProj, $this->categoria);
        $this->opConsentita = 
            Permessi::getAttivi() & Permessi::GESTIONE_CATEGORIA;
        $this->checkOpEseguibile($this->opConsentita);

        $azione = ($this->operazione === "mostra_categoria") 
            ? 'Visualizzazione Categoria'
            : 'Oscuramento Categoria';
        $link = "board.html?proj=$this->idProj";
        $composizione = "No Utente-$this->team-$this->idProj-$this->categoria";
        
        $stmt = null; 

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica se la categoria esiste e se é già visibile/oscurata
            $will_visible = !$this->checkCategoriaVisibile($this->operazione);
            
            # 2. Preparazione del resoconto in caso di successo
            $this->logAzione(
                $azione, $link, $this->categoria, $composizione, 'progetto'
            );

            # 3. Aggiornamento effettivo della categoria/stato
            $this->updateVisibilitaStato((int)$will_visible);

            $this->mydb->commit();
            $this->msg = "Categoria \"$this->categoria\" impostata con "
                       . "successo come " 
                       . ($will_visible ? "visibile!" : "oscurata!");
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

    private function checkCategoriaVisibile(string $option) {
        $query = "
            SELECT COUNT(*) as totale, visibile AS isVisibile 
            FROM STATI 
            WHERE id_progetto = ? AND stato = ?
        ";
        $result = Database::caricaDati(
            $query, "is", $this->idProj, $this->categoria
        );
        $row = $result->fetch_assoc();
    
        # Caso: nessun risultato (query fallita o errore)
        if (!$row) {
            throw new Exception(
                "Errore nella verifica della categoria \"$this->categoria\"."
            );
        }
    
        # Caso: categoria non esistente
        if ((int)$row['totale'] !== 1) {
            throw new Exception(
                "La categoria \"$this->categoria\" non esiste in " . 
                "questo progetto!"
            );
        }
    
        # Caso: categoria già visibile
        if ($row['isVisibile'] && $option === "mostra_categoria") {
            throw new mysqli_sql_exception(
                "La categoria \"$this->categoria\" &egrave; " . 
                "gi&agrave; visibile!"
            );
        }
    
        # Caso: categoria già oscurata
        if (!$row['isVisibile'] && $option === "nascondi_categoria") {
            throw new mysqli_sql_exception(
                "La categoria \"$this->categoria\" &egrave; " . 
                "gi&agrave; oscurata!"
            );
        }

        # Caso: tutto ok -> ritorna valore di isVisible
        if((int)$row['totale'] == 1) {
            return $row['isVisibile'];
        }
    }

    /**
     * Rimuove la categoria in modo effettivo
     * 
     * @param string $categoria Il titolo della categoria da eliminare
     */
    private function updateVisibilitaStato(int $newVisibilita): void {
        $query = "
            UPDATE stati SET visibile = ?
            WHERE id_progetto = ? AND stato = ?
        ";
        $stmt = Database::eseguiStmt(
            $query, "iis", $newVisibilita, $this->idProj, $this->categoria
        );
        Database::liberaRisorsa($stmt);
    }

    private function spostaCategoria() {
        if (!in_array($this->operazione, [
            'sposta_categoria_sinistra', 'sposta_categoria_destra'
        ])) {
            throw new Exception("Operazione non riconosciuta!");
        }
        $this->checkDatiInviati($this->categoria, $this->categoriaTarget);
        Permessi::calcola($this->user, $this->idProj, $this->categoria);
        $this->opConsentita = 
            Permessi::getAttivi() & Permessi::GESTIONE_CATEGORIA;
        $this->checkOpEseguibile($this->opConsentita);
        $azione = ($this->operazione === "sposta_categoria_sinistra") 
            ? 'prima'
            : 'dopo';

        $stmt = null; 

        $this->checkOpEseguibile(true);

        try {

            $this->mydb->begin_transaction();

            # 1. Verifica se le categorie esistono e le compare di posizione 
            $dati_temp = $this->compareCategorie($azione);
            
            # 2. Trova la posizione dello stato di ordine più alto
            $condizioni = ["id_progetto" => ["?", "i", $this->idProj]];
            $max_ord = $this->checkMaxOrdine('stati', $condizioni);
        
            # 3. Aggiornamento temporaneo delle 2 categorie del progetto
            $this->updateTemporaneoCategorie($max_ord);

            # 4. Aggiornamento (scambio) effettivo della 2 categorie/stati 
            $this->updatePosizioneCategoria(
                $dati_temp[0]['stato'], $dati_temp[1]['ordine']
            );
            $this->updatePosizioneCategoria(
                $dati_temp[1]['stato'], $dati_temp[0]['ordine']
            );

            $this->mydb->commit();
            $this->msg = "Categoria \"$this->categoria\" spostata $azione di "
                       . "$this->categoriaTarget con successo!";
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

    private function compareCategorie(string $option) {
        $dati_temp = [];
        $query = "
            SELECT stato, ordine_stati 
            FROM stati 
            WHERE id_progetto = ? AND (stato = ? OR stato = ?) 
            ORDER BY CASE 
                WHEN stato = ? THEN 0 
                WHEN stato = ? THEN 1 
                ELSE 2 
            END
        ";
        $result = Database::caricaDati(
            $query, 'issss', $this->idProj, $this->categoria, 
            $this->categoriaTarget, $this->categoria, $this->categoriaTarget
        );
        while ($row = $result->fetch_assoc()) {
            $dati_temp[] = [
                'stato' => $row['stato'], 'ordine' => (int)$row['ordine_stati']
            ];
        }

        if (count($dati_temp) < 2) {
            throw new mysqli_sql_exception(
                "Una, o entrambe, delle categorie in cui si sta cercando " . 
                "di operare potrebbe non esistere in questo progetto!"
            );
        } elseif (
            $option === "prima" && 
            $dati_temp[0]['ordine'] < $dati_temp[1]['ordine']
        ) {
            # sx: verifica se categoria non è di ordine inferiore al target
            $stato = $dati_temp[0]['stato'];
            $stato_target = $dati_temp[1]['stato'];
            throw new mysqli_sql_exception(
                "La categoria \"$stato\" precede gi&agrave; \"$stato_target\"!"
            );
        } elseif (
            $option === "dopo" && 
            $dati_temp[0]['ordine'] > $dati_temp[1]['ordine']
        ) {
            # dx: verifica se categoria non è di ordine superiore al target
            $stato = $dati_temp[0]['stato'];
            $stato_target = $dati_temp[1]['stato'];
            throw new mysqli_sql_exception(
                "La categoria \"$stato\" succede gi&agrave; \"$stato_target\"!"
            );
        }

        return $dati_temp;
    }

    private function updateTemporaneoCategorie($max_ord) {
        $query = "
            UPDATE stati 
            SET ordine_stati = ordine_stati + ? + 1000 
            WHERE id_progetto = ? AND (stato = ? OR stato = ?)
        ";
        $stmt = Database::eseguiStmt(
            $query, "iiss", $max_ord, $this->idProj, 
            $this->categoria, $this->categoriaTarget);
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
    private function updatePosizioneCategoria(
        string $categoria1, int $ordine2
    ) {
        $query = "
            UPDATE stati 
            SET ordine_stati = ? 
            WHERE id_progetto = ? AND stato = ?
        ";
        $stmt = Database::eseguiStmt(
            $query, "iis", $ordine2, $this->idProj, $categoria1
        );
        Database::liberaRisorsa($stmt);
    }

    private function getUtentiIscritti() {
        $this->mydb->begin_transaction();
        try {
            $query = "
                SELECT ultimo_accesso, CONCAT(cognome, ' ', nome) AS anagrafica, 
                    email, ruolo, team, data_creazione 
                FROM utenti 
                LIMIT ? 
                OFFSET ?
            ";
            $params = [$this->recordPerPagina, $this->offset];
            $result = Database::caricaDati($query, "ii", ...$params);
            $this->fetchByResult($result);

            # numero totale di pagine
            $numero_tuple = Database::contaDa(
                "utenti", "ultimo_accesso > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $this->calcolaNumPagine($numero_tuple);
            $this->mydb->commit();
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: ". $e->getMessage());
        }
    }

    private function creaScheda() {
        # Validazione dei dati
        $this->checkDatiInviati(
            checkTitles(1, 20, false, "\s\\\\",$this->statoScheda), 
            checkTitles(1, 20, true, ' ',$this->titolo), 
        );
        Permessi::calcola(
            $this->user, $this->idProj, $this->statoScheda, $this->titolo
        );
        $this->opConsentita = 
            Permessi::getAttivi() & Permessi::VISIONE_CATEGORIA;
        $this->checkOpEseguibile($this->opConsentita);
        $this->checkOpEseguibile(true);
        $stmt = null;
 
        try {
            
            $this->mydb->begin_transaction();

            # 1. Calcola ordine del nuovo stato
            $condizioni = [
                "id_progetto" => ["?", "i", $this->idProj],
                "stato" => ["?", "s", $this->statoScheda]
            ];
            $ordine = $this->checkMaxOrdine('schede', $condizioni) + 1;

            # 2. Creazione effettiva della nuova scheda
            $this->newScheda($ordine);

            # 3. Verifica dati del nuovo stato
            $uuid_scheda = $this->retrieveNewScheda($ordine);

            # 4. Report sulla creazione dello stato
            $param_scheda = strtolower($uuid_scheda);
            $link = "board.html?proj=$this->idProj&post=$param_scheda";
            $composizione = 
                "No Utente-$this->teamProj-$this->idProj-$this->statoScheda" . 
                "-$param_scheda";


            $this->logAzione(
                'Creazione Scheda', $link, 
                $this->titolo, $composizione, 
                'scheda', ['scheda' => ["UNHEX(?)" ,"s", $uuid_scheda]]
            );

            $this->mydb->commit();
            $this->msg = "Scheda \"$this->titolo\" creata con successo!";
            $this->ordinaSchede($this->titolo, "creata");
        } catch (mysqli_sql_exception $e) {
            $this->mydb->rollback();
            $sm = "Errore Transazione: ";
            $sm .= !($e->getCode() === 1062) 
                ? $e->getMessage() 
                : match (true) {
                    strpos($e->getMessage(), 'PRIMARY') !== false => 
                        "Scheda non creata per chiave primaria duplicata",
                    strpos($e->getMessage(), 'id_progetto') !== false =>
                        $e->getMessage(),
                    default => 
                        "Trovato un duplicato non specificato: " 
                        . $e->getMessage() . "."
                };
            throw new Exception($sm);
        } catch (Throwable $e) {
            $this->mydb->rollback();
            throw new Exception("Errore: " . $e->getMessage());
        }
    }

    private function newScheda($new_ord): void {
        $query = "
            INSERT INTO schede (
                id_progetto, stato, titolo, autore, ordine_schede
            ) VALUES (?, ?, ?, ?, ?)
        ";
        $params = [
            $this->idProj, $this->statoScheda, $this->titolo, 
            $this->user->getEmail(), $new_ord
        ];
        $stmt = Database::eseguiStmt($query, "isssi", ...$params);
        Database::liberaRisorsa($stmt);
    }

    private function retrieveNewScheda($new_ord): string {
        $query = "
            SELECT HEX(uuid_scheda) AS uuid_scheda 
            FROM schede 
            WHERE id_progetto = ? AND stato = ? AND titolo = ? 
                AND autore = ? AND ordine_schede = ?
        ";
        $params = [
            $this->idProj, $this->statoScheda, $this->titolo, 
            $this->user->getEmail(), $new_ord
        ];
        $result = Database::caricaDati($query, "isssi", ...$params);
        $row = $result->fetch_assoc();
        Database::liberaRisorsa($result);
        if (!$row || empty($row['uuid_scheda'])) {
            throw new mysqli_sql_exception(
                "Recupero della scheda fallita, creazione annullata."
            );
        }
        return $row['uuid_scheda'];
    }

}

?>