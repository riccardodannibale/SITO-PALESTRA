🇮🇹 Gym Site Manager
Un sito web per la gestione di una palestra: login e profili per clienti e dipendenti, calendari, promozioni e messaggistica. Backend PHP, database MySQL tramite XAMPP e tunnel pubblico con ngrok.
---
📁 Struttura delle directory
```text
.
├── README.md                         # Documentazione del progetto
└── site/                             # Contiene tutta la logica dell'applicazione web
    ├── calendario_cliente.php        # Calendario riservato al cliente
    ├── calendario_dipendente.php     # Calendario per il dipendente
    ├── carica_ingressi.php           # Script per caricare ingressi
    ├── carica_iscritti.php           # Script per caricare iscritti
    ├── carica_messaggi.php           # Script per caricare messaggi
    ├── change_password.php           # Cambio password
    ├── db.php                        # Connessione al database
    ├── home_cliente.php              # Homepage lato cliente
    ├── home_dipendente.php           # Homepage lato dipendente
    ├── index.php                     # Entry point del sito
    ├── inserisci_iscritto.php        # Inserimento di un nuovo iscritto
    ├── invia_messaggio.php           # Invio messaggi
    ├── location_cliente.php          # Gestione location per cliente
    ├── location_dipendente.php       # Gestione location per dipendente
    ├── login_cliente.php             # Login per i clienti
    ├── login_dipendente.php          # Login per i dipendenti
    ├── logout.php                    # Logout
    ├── profilo_cliente.php           # Profilo cliente
    ├── profilo_dipendente.php        # Profilo dipendente
    ├── promo_cliente.php             # Promo visibili al cliente
    ├── promo_dipendente.php          # Promo gestite dal dipendente
    ├── sign_up.php                   # Registrazione nuovo utente
    ├── style/                        # Fogli di stile CSS divisi per pagina/ruolo
    │   ├── styles_calendario_cliente.css
    │   ├── styles_calendario_dipendente.css
    │   ├── styles_home_cliente.css
    │   ├── styles_home_dipendente.css
    │   ├── styles_location_cliente.css
    │   ├── styles_location_dipendente.css
    │   ├── styles_profilo_cliente.css
    │   ├── styles_profilo_dipendente.css
    │   ├── styles_promo_cliente.css
    │   └── styles_promo_dipendente.css
    └── img/                          # Risorse grafiche
        ├── 1.png
        ├── 2.png
        ├── 3.jpg
        ├── 4.png
        ├── 5.jpeg
        ├── background.png
        └── logo.png
```
---
🧰 Requisiti di sistema
```text
PHP 7.4+
MySQL
Apache
XAMPP
ngrok
git
```
---
📦 Installazione su Windows
1) Installa XAMPP
Scarica e installa XAMPP, poi apri il pannello di controllo e avvia:
```text
Apache
MySQL
```
2) Verifica PHP
Apri Prompt dei comandi o PowerShell e controlla:
```bash
php -v
```
Se PHP non è riconosciuto, usa quello incluso in XAMPP oppure aggiungilo al PATH.
3) Scarica il progetto
Clona il repository oppure scarica lo ZIP ed estrailo, ad esempio in:
```text
C:\xampp\htdocs\gym-site
```
Se usi Git:
```bash
git clone https://github.com/<tuo-username>/gym-site.git
```
Poi copia la cartella `site/` dentro `htdocs\gym-site\` se necessario.
4) Importa il database
Apri phpMyAdmin da XAMPP e importa il dump del database, se presente.
5) Configura `db.php`
Aggiorna i parametri di connessione:
```php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'nome_database';
```
6) Avvia il sito
Apri il browser su:
```text
http://localhost/gym-site/
```
---
📦 Installazione su Linux
1) Installa i pacchetti necessari
Ubuntu / Debian
```bash
sudo apt-get update
sudo apt-get install apache2 mysql-server php libapache2-mod-php php-mysql unzip git
```
Fedora
```bash
sudo dnf install httpd mysql-server php php-mysqlnd unzip git
```
Arch Linux
```bash
sudo pacman -S apache mariadb php php-apache unzip git
```
2) Verifica PHP
```bash
php -v
```
3) Scarica il progetto
```bash
git clone https://github.com/<tuo-username>/gym-site.git
cd gym-site/site
```
4) Copia i file nella cartella web
Se usi Apache su Linux, copia il progetto in una cartella servita dal web server, ad esempio:
```text
/var/www/html/gym-site
```
Su XAMPP Linux, il percorso tipico è:
```text
/opt/lampp/htdocs/gym-site
```
5) Avvia Apache e MySQL
Ubuntu / Debian
```bash
sudo systemctl start apache2
sudo systemctl start mysql
```
Fedora
```bash
sudo systemctl start httpd
sudo systemctl start mariadb
```
6) Importa il database
Importa il dump tramite phpMyAdmin oppure da terminale MySQL.
7) Configura `db.php`
Aggiorna i parametri di connessione:
```php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'nome_database';
```
8) Avvia il sito
Apri il browser su:
```text
http://localhost/gym-site/
```
---
⚙️ Configurazione ambiente di sviluppo
Clona il repository.
Copia il contenuto della cartella `site/` nella directory del server web.
Avvia Apache e MySQL.
Importa il database.
Modifica `site/db.php` con i dati corretti del database.
Apri il sito nel browser.
---
🔗 Tunnel pubblico con ngrok
Per esporre l’app localmente:
```bash
ngrok http 80
```
Copia l’URL generato, ad esempio:
```text
https://abcd1234.ngrok.io
```
e aprilo nel browser per testare il sito da remoto.
Se Apache usa una porta diversa, sostituisci `80` con la porta corretta.
---
🚀 Avvio dell’applicazione
Con Apache e MySQL attivi e l’ambiente configurato:
Apri il browser su `http://localhost/gym-site/` oppure sull’URL di ngrok.
Effettua il login come cliente o dipendente.
Prova navigazione, profili, calendari, promozioni e messaggi.
---
✅ Funzionalità
```text
* Autenticazione: login e registrazione per clienti e dipendenti
* Profili: visualizzazione e modifica dei dati personali
* Calendario: prenotazioni e orari per clienti e dipendenti
* Promozioni: gestione e visualizzazione offerte
* Messaggistica: invio e ricezione messaggi interni
* Ingressi e iscritti: caricamento massivo tramite script PHP
```
---
🧹 Pulizia ambiente
Windows
Rimuovi la cartella del progetto da `htdocs` e, se necessario, elimina il database dal pannello MySQL di XAMPP.
Linux
Rimuovi la cartella del progetto dalla directory web del server e, se necessario, elimina il database locale.
Per cancellare il database da MySQL:
```sql
DROP DATABASE nome_database;
```
---
---
🇬🇧 Gym Site Manager
A website for gym management: client and staff login, profiles, calendars, promotions, and messaging. PHP backend, MySQL database via XAMPP, and public tunnel with ngrok.
---
📁 Directory Structure
```text
.
├── README.md                         # Project documentation
└── site/                             # All web application logic
    ├── calendario_cliente.php        # Client calendar
    ├── calendario_dipendente.php     # Staff calendar
    ├── carica_ingressi.php           # Upload entries script
    ├── carica_iscritti.php           # Upload members script
    ├── carica_messaggi.php           # Upload messages script
    ├── change_password.php           # Password change
    ├── db.php                        # Database connection
    ├── home_cliente.php              # Client homepage
    ├── home_dipendente.php           # Staff homepage
    ├── index.php                     # Site entry point
    ├── inserisci_iscritto.php        # New member insertion
    ├── invia_messaggio.php           # Send messages
    ├── location_cliente.php          # Client location
    ├── location_dipendente.php       # Staff location
    ├── login_cliente.php             # Client login
    ├── login_dipendente.php          # Staff login
    ├── logout.php                    # Logout
    ├── profilo_cliente.php           # Client profile
    ├── profilo_dipendente.php        # Staff profile
    ├── promo_cliente.php             # Client promotions
    ├── promo_dipendente.php          # Staff promotions
    ├── sign_up.php                   # User registration
    ├── style/                        # CSS styles by page/role
    │   ├── styles_calendario_cliente.css
    │   ├── styles_calendario_dipendente.css
    │   ├── styles_home_cliente.css
    │   ├── styles_home_dipendente.css
    │   ├── styles_location_cliente.css
    │   ├── styles_location_dipendente.css
    │   ├── styles_profilo_cliente.css
    │   ├── styles_profilo_dipendente.css
    │   ├── styles_promo_cliente.css
    │   └── styles_promo_dipendente.css
    └── img/                          # Image assets
        ├── 1.png
        ├── 2.png
        ├── 3.jpg
        ├── 4.png
        ├── 5.jpeg
        ├── background.png
        └── logo.png
```
---
🧰 System Requirements
```text
PHP 7.4+
MySQL
Apache
XAMPP
ngrok
git
```
---
📦 Install on Windows
1) Install XAMPP
Download and install XAMPP, then open the control panel and start:
```text
Apache
MySQL
```
2) Verify PHP
Open Command Prompt or PowerShell and check:
```bash
php -v
```
If PHP is not recognized, use the one bundled with XAMPP or add it to PATH.
3) Download the project
Clone the repository or download the ZIP archive and extract it, for example in:
```text
C:\xampp\htdocs\gym-site
```
If you use Git:
```bash
git clone https://github.com/<your-username>/gym-site.git
```
Then copy the `site/` folder into `htdocs\gym-site\` if needed.
4) Import the database
Open phpMyAdmin from XAMPP and import the database dump, if available.
5) Configure `db.php`
Update the connection parameters:
```php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'database_name';
```
6) Open the site
Open the browser at:
```text
http://localhost/gym-site/
```
---
📦 Install on Linux
1) Install required packages
Ubuntu / Debian
```bash
sudo apt-get update
sudo apt-get install apache2 mysql-server php libapache2-mod-php php-mysql unzip git
```
Fedora
```bash
sudo dnf install httpd mysql-server php php-mysqlnd unzip git
```
Arch Linux
```bash
sudo pacman -S apache mariadb php php-apache unzip git
```
2) Verify PHP
```bash
php -v
```
3) Download the project
```bash
git clone https://github.com/<your-username>/gym-site.git
cd gym-site/site
```
4) Copy the files into the web directory
If you are using Apache on Linux, copy the project into a web-served directory such as:
```text
/var/www/html/gym-site
```
On Linux XAMPP, the typical path is:
```text
/opt/lampp/htdocs/gym-site
```
5) Start Apache and MySQL
Ubuntu / Debian
```bash
sudo systemctl start apache2
sudo systemctl start mysql
```
Fedora
```bash
sudo systemctl start httpd
sudo systemctl start mariadb
```
6) Import the database
Import the dump through phpMyAdmin or from the MySQL command line.
7) Configure `db.php`
Update the connection parameters:
```php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'database_name';
```
8) Open the site
Open the browser at:
```text
http://localhost/gym-site/
```
---
⚙️ Development Setup
Clone the repository.
Copy the contents of `site/` into the web server directory.
Start Apache and MySQL.
Import the database.
Edit `site/db.php` with the correct database credentials.
Open the site in your browser.
---
🔗 Public Tunnel with ngrok
To expose the local application:
```bash
ngrok http 80
```
Copy the generated URL, for example:
```text
https://abcd1234.ngrok.io
```
and open it in your browser to test the site remotely.
If Apache uses a different port, replace `80` with the correct one.
---
🚀 Running the Application
With Apache and MySQL running and the environment configured:
Open `http://localhost/gym-site/` or the ngrok URL.
Log in as client or staff.
Test navigation, profiles, calendars, promotions, and messages.
---
✅ Features
```text
* Authentication: client and staff login/registration
* Profiles: view and edit personal data
* Calendar: bookings and schedules for clients and staff
* Promotions: manage and view offers
* Messaging: internal message sending and receiving
* Entries and members: bulk upload via PHP scripts
```
---
🧹 Cleanup
Windows
Remove the project folder from `htdocs` and, if needed, delete the database from the XAMPP MySQL panel.
Linux
Remove the project folder from the web server directory and, if needed, delete the local database.
To drop the database from MySQL:
```sql
DROP DATABASE database_name;
```
---
