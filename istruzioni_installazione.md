# Installazione e Configurazione di XAMPP 
## Installazione di XAMPP 8.2.12 su Windows (x64)
### Alternative di Installazione:

#### Installazione Manuale:
1. Visitare il link: [Scarica XAMPP 8.2.12](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/8.2.12/xampp-windows-x64-8.2.12-0-VS16-installer.exe)
2. Avviare l'eseguibile, possibilmente con privilegi da amministratore per garantire che tutti i componenti vengano installati correttamente e possano operare senza problemi.

#### Installazione con Winget:
1. Premere la combinazione di tasti `Win + X`, e nel menu a comparsa selezionare `Windows Powershell (amministratore)`.
2. Se winget è già installato sul proprio sistema, saltare al punto successivo. Altrimenti, digitare il seguente comando per scaricare e installare il pacchetto App Installer da GitHub, che include winget:
   ```powershell
   Invoke-WebRequest -Uri https://github.com/microsoft/winget-cli/releases/download/v1.4.11071/Microsoft.DesktopAppInstaller_8wekyb3d8bbwe.msixbundle -OutFile Microsoft.DesktopAppInstaller.msixbundle
   Add-AppxPackage -Path Microsoft.DesktopAppInstaller.msixbundle
2. Sempre su Windows Powershell, eseguire il seguente comando per installare XAMPP utilizzando winget:
    ```powershell
    winget install ApacheFriends.Xampp.8.2

## Installazione di XAMPP 8.2.4 su macOS (Apple Silicon)
1. Scaricare l’ultima versione compatibile con macOS ARM dal sito ufficiale: [XAMPP 8.2.4](https://sourceforge.net/projects/xampp/files/XAMPP%20Mac%20OS%20X/8.2.4/xampp-osx-8.2.4-0-installer.dmg/download)
2. Aprire il file ‎`.dmg` e avviare il file di installazione di XAMPP, scegliendo come percorso di installazione la ‎`Applicazioni`.
3. Avviare XAMPP e, se richiesto, concedere i permessi di esecuzione.

## Configurazione di XAMPP (WIN) per Connessione a Dispositivi nella Stessa Rete LAN/WLAN
Questa è una configurazione consigliata nel caso si volesse visionare il progetto anche su dispositivi alternativi come tablet e smartphone. Senza questa configurazione, le pagine del progetto o la connessione al database non saranno raggiungibili.
1. Avviare XAMPP premendo la combinazine di tasti `WIN + S`, digitando XAMPP e cliccando sull'icona relativa. 
2. Assicurarsi che il modulo MySQL (o MariaDB) sia avviato.
3. Sul pannello di controllo di XAMPP, cliccare sul pulsante "Config" accanto al nome del modulo MySQL (o MariaDB).
4. Selezionare "my.ini" per aprire il file di configurazione di MariaDB.
5. Trovare il parametro `bind-address` e impostare il valore associato da `127.0.0.1` a `0.0.0.0` (⚠ Nota: questa modifica consente connessioni da qualsiasi IP. Usare solo in ambienti di test per questioni di sicurezza.
6. Salvare il file di configurazione e riavviare il modulo MySQL (o MariaDB).
7. Se si sta utilizzando un firewall sul computer, assicurarsi che questo sia configurato per consentire le connessioni in ingresso sulla porta 3306 (porta predefinita del solito modulo) da indirizzi IP remoti.
8. Ora, cliccare sul pulsante "Config" del modulo Apache, e sul file httpd.conf aggiungere le seguenti righe a fondo pagina: 
    ```apache
    # connessione per dispositivi remoti
    <Directory "C:/xampp/phpmyadmin">
        AllowOverride none
        Require all granted
    </Directory>
    ```
9. Ora è possibile connettersi al server utilizzando un client MySQL remoto, specificando l'indirizzo IP pubblico o il nome del dominio del proprio dispositivo e la porta configurata (di default è la porta 3306).

## Configurazione di XAMPP (macOS) per Connessione su Dispositivo Principale e Altri Dispositivi nella Stessa Rete LAN/WLAN
1. Aprire il file di configurazione Apache da terminale (ad esempio con nano):
   ```zsh
   sudo nano /Applications/XAMPP/xamppfiles/etc/httpd.conf
   ```
3. Cercare e decommentare (rimuovere il `#`) la seguente riga:
   ```apache
   Include etc/extra/httpd-vhosts.conf
   ```
4. Salvare e chiudere (se si usa nano `^ + X` e poi `Y`)
5. (Opzionale/Consigliato) Se si vuole usare una cartella diversa da htdocs dove poter contenere il nostro sito si consiglia, per motivi di permessi e organizzazione, di creare una cartella `Sites` nella propria home directory digitando da terminale:
   ```zsh
   sudo mkdir ~/Sites
   sudo mkdir ~/Sites/procol
   sudo chown -R system:daemon ~/Sites
   sudo chmod -R 775 ~/Sites
   cat <<EOF | sudo tee ~/Sites/procol/.htaccess > /dev/null
   RewriteEngine On
   RewriteCond %{REQUEST_URI} ^/\$
   RewriteRule ^\$ /login.html [L,R=302]
   EOF
   ```
6. Aprire il file dei virtual host (ad esempio con nano):
   ```zsh
   sudo nano /Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf
   ```
7. Aggiungere l'host virtuale per htdocs dopo i due dummy
   ```apache
   <VirtualHost *:80>
      ServerAdmin webmaster@localhost
      DocumentRoot "/Applications/XAMPP/xamppfiles/htdocs"
      ServerName localhost
      ErrorLog "logs/localhost-error_log"
      CustomLog "logs/localhost-access_log" common
   </VirtualHost>
   ```
8. Se abbiamo scelto di passare anche per il punto 5, aggiungere dopo i due host dummy ma prima di quello per htdocs, sostituendo `mio_utente` con il nome effettivo del proprio utente, anche:
   ```apache
   <VirtualHost *:80>
      ServerAdmin webmaster@procol.local
      DocumentRoot "/Users/mio_utente/Sites/procol"
      ServerName procol.local
      ServerAlias www.procol.local
      ErrorLog "logs/procol-error_log"
      CustomLog "logs/procol-access_log" common
      <Directory "/Users/mio_utente/Sites/procol">
         AllowOverride All
         Require all granted
      </Directory>
   </VirtualHost>
   ```
9. Salvare, chiudere e aprire il file `hosts`:
   ```zsh
   sudo nano /etc/hosts
   ```
10. Aggiungere i seguenti domini (aggiungere solo quelli non già presenti):
    ```
    127.0.0.1       localhost
    127.0.0.1       procol.local
    255.255.255.255 broadcasthost
    ::1             localhost
    ```
11. Salvare e da ora in poi, sul dispositivo principale, é possibile accedere al sito da [http://procol.local/progetti-collaborativi/login.html](http://procol.local/progetti-collaborativi/login.html)
12. Per accedere effettivamente al progetto anche da dispositivi esterni (ma sulla stessa rete locale) si può utilizzare `Bonjour`, che consente di accedere al dispositivo tramite un alias `.local`. Pertanto:
    - aprire il terminale e digitare:  `scutil --get LocalHostName`
    - Se il risultato è ad esempio `MacBook-Air-di-Tizio`
    - allora l'alias di Bonjour sarà `macbook-air-di-tizio.local`, quindi d'ora in poi il sito sarà accessibile digitando sulla barra degli indirizzi il sito:
         ```url
         http://macbook-air-di-tizio.local/progetti-collaborativi/login.html
         ```

## Configurazione del Sistema di Debug per PHP su Windows (⚠ Opzionale e Potrebbe Ridurre le Prestazioni delle applicazioni PHP)
1. Scaricare il seguente file dll: [Qui](https://xdebug.org/files/php_xdebug-3.1.6-7.4-vc15-x86_64.dll)
2. Muovere il file scaricato nella directory `C:\xampp\php\ext`
3. Rinomina il file in `php_xdebug.dll`
4. Aprire il file `C:\xampp\php\php.ini`, oppure andare sul pannello di controllo XAMPP, cliccare sul "Config" del modulo Apache e aprire il file `php.ini`
5. Aggiungere al file aperto la seguente riga di codice: 
    `zend_extension = xdebug`


# Installazione e Accesso al Progetto "Progetti Collaborativi"

1. **Preparazione**: Copiare la cartella `progetti-collaborativi`, scaricata da GitHub, in una cartella specificata tra gli host virtuali (`Sites/procol` nel nostro esempio), o più semplicemente nella directory `htdocs` di XAMPP. Per impostazione predefinita, questa directory si trova in `C:\xampp\htdocs` su Windows e `/Applications/XAMPP/xamppfiles/htdocs"` su MacOS. Per visionare i file si suggerisce l'utilizzo di Visual Studio Code
2. **Avvio di XAMPP e attivazione dei moduli**:
   - Per Windows premere la combinazione `Win + S`, digitare XAMPP e cliccare sull'icona relativa. All'apertura del pannello di controllo attivare i moduli `Apache` e `MySQL` (o `MariaDB`). In seguito, per l'apertura della pagina phpMyAdmin**: ritornare sul pannello di controllo XAMPP e scegliere l'azione `admin` corrispettiva al modulo `MySQL` (o `MariaDB`).
   - Per MacOS premere la combinazione di tasti `cmd + space`, digitare `manager-osx` e premere il tasto `invio`. All'apertura del pannello di controllo selezionare la sottocategoria `Manage Servers` e premere start sui moduli Apache e MySQL (o MariaDB).
3. **Aprire phpmyadmin sul dispositivo in cui è installato XAMPP**:
   - Per Windows: ritornare sul pannello di controllo XAMPP e scegliere l'azione `admin` corrispettiva al modulo `MySQL` (o `MariaDB`). Alternativamente, aprire il browser e digitare l'url `http://localhost/phpmyadmin/`.
   - Per MacOS: aprire il browser e digitare l'url `http://localhost/phpmyadmin/`.
4. **Importare il dump del database**: All'apertura della pagina phpMyAdmin creare un database con un nome arbitrario (che è possibile cancellare in seguito al termine delle operazioni) e nella barra in alto scegliere l'opzione `Importa`. Quindi, scegliere di importare il file `progetticollaborativi.sql.gz`, selezionare come set di caratteri `utf-8` e come tipo di formatazione `SQL` e togliere la spunta su `Importazione Parziale` e attivarla invece per `Altre opzioni`, `Formatta` e `opzioni specifiche al formato`. Infine, cliccare finalmente in fondo alla pagina su `Importa`. Se ci dovesse essere un errore nell'importazione del database è possibile che qualcosa sia andato storto nei passaggi precedenti. 
5. **Ottenere l'Indirizzo IP**: Nella finestra del prompt dei comandi, digitare `ipconfig` e premere Invio.
6. **Individuare l'Indirizzo IPv4**: Cercare la dicitura `Scheda LAN wireless Wi-Fi:` e copiare il valore associato a `Indirizzo IPv4`.
7. **Accesso alla Pagina del Progetto**:
   - Per visitare la pagina del progetto su qualsiasi dispositivo collegato alla rete locale, incollare l'indirizzo IP del dispositivo principale (ottenuto nel passo 3) nella barra degli indirizzi del browser e digitare un indirizzo simile al seguente:
    ```url
    http://xxx.xxx.xxx.xxx/progetti-collaborativi
    ```
   - Per visitare il sito solo sul dispositivo principale, accedi alla pagina:
    ```url
    http://localhost/progetti-collaborativi
    ```
   - Se il dispositivo principale ha MacOS, sia per l'accesso al sito sul dispositivo principale stesso o su quelli secondari, si consiglia di seguire le istruzioni di configurazione relative a XAMPP per MacOS
10. **Prima Pagina Visualizzata**: La prima pagina mostrata sarà quella di accesso/registrazione al sito. È possibile fare una delle seguenti scelte:
   - **Account Amministratore di Default**: Connettersi con l'account amministratore di default inserendo le seguenti credenziali:
     - Email: `admin@procol.com`
     - Password: `Administrator#1`
   - **Account Fittizi**: Connettersi con uno degli account fittizi le cui credenziali sono disponibili nel file `Accessi.txt`. Si consigliano gli account di Francesca Neri, Simone Lombardi o Carlo Barbieri per avere un'anteprima completa di come viene gestito un progetto da un team.
   - **Registrazione Nuovo Account**: Registrarsi con un nuovo account. Questo può essere inserito in un team o promosso a capo team o admin solo da un amministratore.


