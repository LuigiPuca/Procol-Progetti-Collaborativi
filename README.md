# Installazione e Configurazione di XAMPP 8.2.12 (WINDOWS x64)
## Installazione di XAMPP per Windows
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
    
## Configurazione di XAMPP per Connessione a Dispositivi nella Stessa Rete LAN/WLAN
Questa è una configurazione consigliata nel caso si volesse visionare il progetto anche su dispositivi alternativi come tablet e smartphone. Senza questa configurazione, le pagine del progetto o la connessione al database non saranno raggiungibili.
1. Avviare XAMPP premendo la combinazine di tasti `WIN + S`, digitando XAMPP e cliccando sull'icona relativa. 
2. Assicurarsi che il modulo MySQL (o MariaDB) sia avviato.
3. Sul pannello di controllo di XAMPP, cliccare sul pulsante "Config" accanto al nome del modulo MySQL (o MariaDB).
4. Selezionare "my.ini" per aprire il file di configurazione di MariaDB.
5. Trovare il parametro `bind-address` e impostare il valore associato da `127.0.0.1` a `0.0.0.0` (mantenere questa impostazione solo durante i test per motivi di sicurezza).
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

## Configurazione del Sistema di Debug per PHP (Opzionale e Potrebbe Ridurre le Prestazioni delle applicazioni PHP)
1. Scaricare il seguente file dll: [Qui](https://xdebug.org/files/php_xdebug-3.1.6-7.4-vc15-x86_64.dll)
2. Muovere il file scaricato nella directory `C:\xampp\php\ext`
3. Rinomina il file in `php_xdebug.dll`
4. Aprire il file `C:\xampp\php\php.ini`, oppure andare sul pannello di controllo XAMPP, cliccare sul "Config" del modulo Apache e aprire il file `php.ini`
5. Aggiungere al file aperto la seguente riga di codice: 
    `zend_extension = xdebug`


# Installazione e Accesso al Progetto "Progetti Collaborativi"

1. **Preparazione**: Copiare la cartella `progetti-collaborativi`, scaricata da GitHub, nella directory `htdocs` di XAMPP. Per impostazione predefinita, questa directory si trova in `C:\xampp\htdocs`. Per visionare i file si suggerisce l'utilizzo di Visual Studio Code
2. **Avvio di XAMPP e attivazione dei moduli**: Premere la combinazione `Win + S`, digitare XAMPP e cliccare sull'icona relativa. All'apertura del pannello di controllo attivare i moduli Apache e MySQL (o MariaDB).
3. **Apertura della pagina phpMyAdmin**: Ritornare sul pannello di controllo XAMPP e scegliere l'azione admin corrispettiva al modulo MySQL (o MariaDB).
4. **Aprire il Prompt dei Comandi**: Sul dispositivo su cui è installato XAMPP, premere la combinazione di tasti `Win + R` e digitare `CMD`, quindi premere Invio. In alternativa aprire il browser e digitare l'url `http://localhost/phpmyadmin/`.
5. **Importare il dump del database**: All'apertura della pagina phpMyAdmin creare un database con un nome arbitrario (che è possibile cancellare in seguito al termine delle operazioni) e nella barra in alto scegliere l'opzione `Importa`. Quindi, scegliere di importare il file `progetticollaborativi.sql.gz`, selezionare come set di caratteri `utf-8` e come tipo di formatazione `SQL` e togliere la spunta su `Importazione Parziale` e attivarla invece per `Altre opzioni`, `Formatta` e `opzioni specifiche al formato`. Infine, cliccare finalmente in fondo alla pagina su `Importa`. Se ci dovesse essere un errore nell'importazione del database è possibile che qualcosa sia andato storto nei passaggi precedenti. 
6. **Ottenere l'Indirizzo IP**: Nella finestra del prompt dei comandi, digitare `ipconfig` e premere Invio.
7. **Individuare l'Indirizzo IPv4**: Cercare la dicitura `Scheda LAN wireless Wi-Fi:` e copiare il valore associato a `Indirizzo IPv4`.
8. **Accesso alla Pagina del Progetto**:
   - Per visitare la pagina del progetto su qualsiasi dispositivo collegato alla rete locale, incollare l'indirizzo IP del dispositivo principale (ottenuto nel passo 3) nella barra degli indirizzi del browser e digitare un indirizzo simile al seguente:
    ```url
    http://xxx.xxx.xxx.xxx/progetti-collaborativi
    ```
   - Per visitare il sito solo sul dispositivo principale, accedi alla pagina:
    ```url
    http://localhost/progetti-collaborativi
    ```
9. **Prima Pagina Visualizzata**: La prima pagina mostrata sarà quella di accesso/registrazione al sito. È possibile fare una delle seguenti scelte:
   - **Account Amministratore di Default**: Connettersi con l'account amministratore di default inserendo le seguenti credenziali:
     - Email: `admin@procol.com`
     - Password: `Administrator#1`
   - **Account Fittizi**: Connettersi con uno degli account fittizi le cui credenziali sono disponibili nel file `Accessi.txt`. Si consigliano gli account di Francesca Neri, Simone Lombardi o Carlo Barbieri per avere un'anteprima completa di come viene gestito un progetto da un team.
   - **Registrazione Nuovo Account**: Registrarsi con un nuovo account. Questo può essere inserito in un team o promosso a capo team o admin solo da un amministratore.
