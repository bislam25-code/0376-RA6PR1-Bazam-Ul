# WorkTracker

Aplicació web per al registre i seguiment d'hores de treball en projectes, desenvolupada amb **PHP** i **MySQL**.

## 📁 Estructura del projecte

```
WorkTracker/
├── assets/            # Recursos estàtics (CSS, JS, imatges)
│   └── style.css      # Estils globals
├── auth/              # Mòdul d'autenticació i dashboards
│   ├── login.php      # Inici de sessió
│   ├── register.php   # Registre de nous usuaris
│   ├── logout.php     # Tancament de sessió
│   ├── dashboard_admin.php   # Panell d'administrador
│   └── dashboard_empleat.php # Panell d'empleat
├── config/            # Configuració de la base de dades
│   ├── db.php         # Connexió PDO
│   └── schema.sql     # Script SQL de creació de la BD
├── index.php          # Punt d'entrada (redirecció per rol)
├── .gitignore
└── README.md
```

## 🛠 Requisits

- PHP 8.0 o superior
- MySQL 5.7 / MariaDB 10.3+
- Servidor web (Apache / Nginx)

## 🚀 Instal·lació

1. **Clona el repositori:**
   ```bash
   git clone https://github.com/bislam25-code/0376-RA6PR1-Bazam-Ul.git
   cd 0376-RA6PR1-Bazam-Ul
   ```

2. **Configura la base de dades:**
   ```bash
   mysql -u root -p < config/schema.sql
   ```

3. **Configura les credencials** a `config/db.php`:
   ```php
   $db_host = 'localhost';
   $db_name = 'worktracker';
   $db_user = 'root';
   $db_pass = '';
   ```

4. **Desplega el projecte** al directori arrel del teu servidor web (ex: `/var/www/html/WorkTracker`).

5. **Accedeix** a `http://localhost/WorkTracker` i registra't com a nou usuari.

## 👥 Rols

| Rol      | Accés                                       |
|----------|---------------------------------------------|
| **admin**  | Panell d'administració (gestió d'usuaris, projectes, informes) |
| **empleat** | Panell personal (registre d'hores, consulta de registres) |

## 🧰 Tecnologies

- PHP (PDO, sessions, password_hash / password_verify)
- MySQL / MariaDB
- HTML5 + CSS3