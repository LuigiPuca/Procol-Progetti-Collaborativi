<?php 

#Richiedo (e quindi includo) il file database.php per la connessione al database
require_once('database.php');

#Verifico se il form è stato inviato e se un campo di nome register é stato inviato
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrazione'])) {
    # Ottieni i valori inviati dal form HTML
    $nome = $_POST['nome'] ?? ""; // usiamo l'operatore di coealescenza per evitare valori non definiti
    $cognome = $_POST['cognome'] ?? "";
    $genere = $_POST['genere'] ?? "";
    $email = $_POST['email'] ?? "";
    $password = $_POST['password'] ??"";
    // $password_confirm = $_POST['password_confirm'] ?? "";
    $checkTC = $_POST['checkTC'] ?? "";
    #verifichiamo la validitá di ogni campo della variabile globale
    $isNomeValido = preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ'\s]{0,48}[a-zA-ZÀ-ÿ])?$/u", $nome);
    $isCognomeValido = preg_match("/^[a-zA-ZÀ-ÿ](?:[a-zA-ZÀ-ÿ'\s]{0,48}[a-zA-ZÀ-ÿ])?$/u", $cognome);
    $isGenereSelezionato = ($genere === 'maschio' || $genere === 'femmina');
    $isMailValida = preg_match("/^[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*@[a-zA-Z0-9]+([._-][a-zA-Z0-9]+)*\.[a-zA-Z]{2,4}$/", $email);
    $isPassValida = preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,20}$/", $password);
    $isTCAccettate = (isset($_POST['checkTC']) && $_POST['checkTC'] == '1');
    if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
        $msg = 'Compila tutti i campi!';
    } elseif (!$isNomeValido || !$isCognomeValido) {
        $msg = 'Nome e Cognome non validi.';
    } elseif (!$isGenereSelezionato) {
        $msg = 'Devi spuntare tutte le caselle';
    } elseif (!$isMailValida) { 
        $msg = 'Indirizzo email non valido';
    } elseif (!$isPassValida) {
        $msg = 'La password deve essere di almeno 8 e al massimo 20 caratteri tra cui una maiuscola, una minuscola, un numero e un simbolo';
    } elseif (!$isTCAccettate) {
        $msg = 'Devi accettare Termini e Condizioni';
    } else {
        $nome = ucwords(mb_strtolower($nome));
        $cognome = ucwords(mb_strtolower($cognome));
        $email = mb_strtolower($email);
        # il seguente rigo ci serve per il messaggio di benvenuto
        $suffisso = ($genere === 'maschio') ? 'o' : 'a';
        #la password deve essere hashata prima di essere inviata al database
        $password = password_hash($password, PASSWORD_BCRYPT);

        #preimpostiamo uno statement e verifichiamo se l'email esiste già nel database
        $stmt = $mysqli->prepare("SELECT uuid FROM utenti WHERE email = ?");
        #associo il valore corretto dell'email al parametro preparato (o placeholder) della query "?"
        $stmt->bind_param("s", $email);
        #eseguo quindi effettivamente la query
        $stmt->execute();
        #memorrizo il risultato della query
        $stmt->store_result();

        #se abbiamo come risultato almeno una riga 
        if ($stmt->num_rows > 0) {
            # allora l'email esiste già nel database
            $msg = 'L\'email &egrave; gi&agrave; stata utilizzata';
        } else {
            # altrimenti posso procedere con la registrazione, inserendo in tabella la seguente tupla
            $stmt = $mysqli->prepare("INSERT INTO utenti (nome, cognome, genere, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nome, $cognome, $genere, $email, $password);
            if ($stmt->execute()) {
                $msg = 'Registrazione effettuata con successo! Benvenut'. ($genere === 'maschio' ? 'o' : 'a'). ' '. ucwords($nome). ' '.  ucwords($cognome). '<br>('. $email. ')';
            } else {
                // Se c'è stato un errore nell'esecuzione della query
                $msg = 'Si &egrave; verificato un errore durante la registrazione. Si prega di riprovare pi&ugrave; tardi.';
            }
        }


        
        
    }
    $a = base64_encode( '' . $msg . '<br><br><a href="./login.html">Torna indietro</a>');
    header("Location: ../errore.html?msg=" . urlencode($a)); 
    exit(); 
}
?>
