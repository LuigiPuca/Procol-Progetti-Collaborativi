<?php 

class DBLogger {
    const USER_ACTIONS = [
        'elimina' => 'Eliminazione Utente',
        'promuovi' => 'Promozione Utente',
        'declassa' => 'Declassamento Utente'
    ];
    const TEAM_ACTIONS = [
        'coinvolgi' => 'Aggiunta Utente Nel Team',
        'caccia' => 'Rimozione Utente Da Team',
        'crea' => 'Creazione Team',
        'elimina' => 'Eliminazione Team',
        'modifica' => 'Aggiornamento Team',
        'assegna' => 'Assegnazione Team'
    ];

    const PROJ_ACTIONS = [
        'crea' => 'Creazione Progetto',
        'elimina' => 'Cancellazione Progetto',
        'modifica' => 'Aggiornamento Progetto',
        'assegna' => 'Assegnazione Progetto'
    ];

    const BOARD_ACTIONS = [
        'crea' => 'Creazione Categoria',
        'elimina' => 'Eliminazione Categoria',
        'nascondi' => 'Oscuramento Categoria',
        'rivela' => 'Visualizzazione Categoria',
    ];

    const POST_ACTIONS = [
        'crea' => 'Creazione Scheda',
        'descrivi' => 'Aggiunta Descrizione Scheda',
        'modifica' => 'Modifica Descrizione Scheda',
        'elimina' => 'Eliminazione Scheda',
        'archivia' => 'Archiviazione Scheda',
        'incarica' => 'Assegnazione Scheda',
        'rinnova' => 'Riassegnazione Scheda',
        'revoca' => 'Deassegnazione Scheda',
        'sposta' => 'Cambiamento Stato',
    ];

    const REPLY_ACTIONS = [
        'crea' => 'Creazione Commento',
        'modifica' => 'Modifica Commento',
        'elimina' => 'Eliminazione Commento',
        'risposta' => 'Risposta Commento',
    ];

    private static function logAction(
        array $actions,
        string $action,
        array $fields,
        array $holders,
        array $params,
        string $types
    ) {
        if (!array_key_exists($action, $actions)) {
            throw new InvalidArgumentException("Azione non riconosciuta: $action");
        }
        # Sostituisce il segnaposto __ACTION__ con la descrizione dell'azione
        foreach ($params as &$param) {
            if ($param === '__ACTION__') {
                $param = $actions[$action];
            }
        }
        unset($param);

        $fieldsStr  = implode(', ', $fields);
        $holdersStr = implode(', ', $holders);
        Database::createTupla('report', $fieldsStr, $holdersStr, $types, ...$params);
    }

    public static function sessione($email)
    {
        self::logAction(
            ['accesso' => 'Accesso'],
            'accesso',
            ['tipo_azione', 'attore', 'descrizione', 'attore_era'],
            ['Sessione', '?', 'Accesso', $email],
            [$email, $email],
            'ss'
        );
    }

    public static function utente($actor, $action, $target) 
    {
        $fields  = ['tipo_azione', 'attore', 'descrizione', 'attore_era', 'bersaglio_era'];
        $holders = ["'utente'", '?', '?', '?', '?'];
        $params = [$actor, '__ACTION__', $actor, $target];
        $types = 'ssss';
        if ($action !== 'elimina') {
            $fields[] = "utente";
            $holders[] .= "?";
            $params[] = $target;
            $types .= "s";
        }
        self::logAction(
            self::USER_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }

    public static function team($actor, $action, $team, $member = null) 
    {
        $targetWas = ($member) ? "$member-$team" : "No Utente-$team";
        $fields  = [
            'tipo_azione', 'attore', 'descrizione', 
            'attore_era', 'bersaglio_era'
        ];
        $holders = ["'team'", '?', '?', '?', '?'];
        $params  = [$actor, '__ACTION__', $actor, $member];
        $types   = 'ssss';
        if ($action !== 'elimina') {
            $fields[] = 'team';
            $holders[] = '?';
            $params[] = $team;
            $types .= 's';
        }
        if (!in_array($action,["crea", "elimina"]) && $member) {
            $fields[] = "utente";
            $holders[] = "?";
            $params[] = $member;
            $types .= 's';
        }
        self::logAction(
            self::TEAM_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }

    public static function progetto($actor, $action, $proj = null, $team = null)
    {
        $fields  = [
            'tipo_azione', 'link', 'attore', 
            'descrizione', 'attore_era', 'bersaglio_era'
        ];
        $holders = ["'progetto'", '?', '?', '?', '?', '?'];
        $link = $proj ? "board.html?proj=$proj" : null;
        $targetWas = "No Utente-" . ($team ?? "No Team") . "-$proj";
        
        $params = [$link, $actor, '__ACTION__', $actor, $targetWas];
        $types = "sssss";

        if ($action !== "elimina" && $proj) {
            $fields[] = "progetto";
            $holders[] = "?";
            $params[] = $proj;
            $types .= "i";
        }
        if ($team === null) {
            $fields[] = "team";
            $holders[] = "?";
            $params[] = $team;
            $types .= "s";
        }
        self::logAction(
            self::PROJ_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }

    public static function board($actor, $action, $proj, $team, $state = null) 
    {
        $fields  = [
            'tipo_azione', 'progetto', 'team', 'link', 
            'attore', 'descrizione', 'attore_era', 'bersaglio_era'
        ];
        $holders = ["'progetto'", "?", "?", "?", "?", "?", "?", "?"];
        $link = "board.html?proj=$proj";
        $targetWas = "No Utente-$team-$proj-$state";
        $params = [
            $proj, $team, $link, $actor, '__ACTION__', $actor, $targetWas
        ];
        $types = "isssssss";

        if ($action !== "elimina" && $state) {
            $fields[] = "categoria";
            $holders[] = "?";
            $params[] = $state;
            $types .= "s";
        }
        self::logAction(
            self::BOARD_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }

    public static function scheda(
        $actor, $action, $proj, $state, $team, $post = null, $member = null
    ): void
    {
        $fields  = [
            'tipo_azione', 'progetto', 'team', 'categoria', 'link', 
            'attore', 'descrizione', 'attore_era', 'bersaglio_era'
        ];
        $holders = ["'scheda'", "?", "?", "?", "?", "?", "?", "?", "?"];
        $link = "board.html?proj=$proj";
        if ($action !== "elimina" && $post) {
            $link .= "&post=" . strtolower($post);
        }  
        $targetWas = ($member ?? "No Utente") . "-$team-$proj-$state";
        $targetWas .= ($post ? "-" . strtolower($post) : "");
        $params = [
            $proj, $team, $state, $link, 
            $actor, '__ACTION__', $actor, $targetWas
        ];
        if ($action !== 'elimina' && $post) {
            $fields[] = "scheda";
            $holders[] = "UNHEX(?)";
            $params[] = $post;
        }
        if ($member) {
            $fields[] = "utente";
            $holders[] = "?";
            $params[] = $member;
        }
        $types = "i" . (str_repeat("s", substr_count($holders, "?")) - 1);
        self::logAction(
            self::POST_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }

    public static function commento(
        $actor, $action, $proj, $state, $team, $post, $re = null, $member = null
    ) 
    {
        $fields  = [
            'tipo_azione', 'progetto', 'team', 'categoria', 'scheda',
            'link', 'attore', 'descrizione', 'attore_era', 'bersaglio_era'
        ];
        $holders = [
            "'scheda'", "?", "?", "?", "UNHEX(?)", "?", "?", "?", "?", "?"
        ];
        $thread = strtolower($post);
        $frag = strtolower($re);
        $link = "board.html?proj=$proj&post=$thread";
        if ($action !== "elimina" && $re) {
            $link .= "#repl=$frag";
        }
        $targetWas = ($member ?? "No Utente") . "-$team-$proj-$state"
                   . ($frag ? "-$frag" : "");
        $params = [
            $proj, $team, $state, $link, 
            $actor, '__ACTION__', $actor, $targetWas
        ];
        if ($action !== 'elimina' && $re) {
            $fields[] = "commento";
            $holders[] = "UNHEX(?)";
            $params[] = $re;
        }
        if ($member) {
            $fields[] = "utente";
            $holders[] = "?";
            $params[] = $member;
        }
        $types = "i" . (str_repeat("s", substr_count($holders, "?")) - 1);
        self::logAction(
            self::POST_ACTIONS, $action, $fields, $holders, $params, $types
        );
    }
   
}